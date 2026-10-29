<?php
/**
 * Fakturoid API v3 — multi-company proforma (bank) + invoice after card paid + webhook.
 */

if (!defined('ABSPATH')) {
    exit;
}

class LKBA_Payments_Fakturoid {

    const SUBDIR        = 'lkba-payments/invoices';
    const WEBHOOK_QUERY = 'lkba_fakturoid_webhook';

    /** @var array<string, mixed>|null */
    private $company;

    /** @var string */
    private $company_id = '';

    public static function boot() {
        static $booted = false;
        if ($booted) {
            return;
        }
        $booted = true;
        $instance = new self('');
        add_action('init', array($instance, 'maybe_handle_webhook'), 4);
    }

    /**
     * @param string $company_id
     */
    public function __construct($company_id = '') {
        $this->company_id = (string) $company_id;
        $this->company    = $company_id !== ''
            ? LKBA_Payments_Settings::get_company($company_id)
            : null;
    }

    /**
     * @param string $company_id
     * @return self
     */
    public static function for_company($company_id) {
        return new self($company_id);
    }

    /**
     * @return bool
     */
    public function is_configured() {
        if (!$this->company) {
            return false;
        }
        return !empty($this->company['fakturoid_enabled'])
            && !empty($this->company['fakturoid_slug'])
            && !empty($this->company['fakturoid_client_id'])
            && !empty($this->company['fakturoid_client_secret']);
    }

    /**
     * @param string $company_id
     * @return string
     */
    public static function get_webhook_url($company_id) {
        return add_query_arg(
            array(
                self::WEBHOOK_QUERY => '1',
                'company'           => $company_id,
            ),
            home_url('/')
        );
    }

    /**
     * Bank transfer: create proforma (idempotent if context already has invoice id).
     *
     * @param array $context
     * @return array|WP_Error keys: invoice_id, number, variable_symbol, pdf
     */
    public function create_proforma(array $context) {
        $context = LKBA_Payments_Context::normalize($context);

        if (!$this->is_configured()) {
            return new WP_Error('fakturoid_disabled', __('Fakturoid is not configured for this company.', 'lkba-payments'));
        }

        if ($context['fakturoid_invoice_id'] !== '') {
            return array(
                'invoice_id'      => $context['fakturoid_invoice_id'],
                'number'          => $context['fakturoid_invoice_number'],
                'variable_symbol' => $context['fakturoid_variable_symbol'],
                'pdf'             => $context['fakturoid_pdf'],
            );
        }

        $subject_id = $this->ensure_subject($context);
        if (is_wp_error($subject_id)) {
            LKBA_Payments_Logger::log('fakturoid_subject_failed', $subject_id->get_error_message(), array(
                'ref_id' => $context['ref_id'],
            ), $this->company_id);
            return $subject_id;
        }

        $vat_rate = $this->get_document_vat_rate();
        $lines    = $this->build_lines($context, $vat_rate);

        $payload = array(
            'subject_id'                 => $subject_id,
            'lines'                      => $lines,
            'document_type'              => 'proforma',
            'proforma_followup_document' => 'final_invoice_paid',
            'custom_id'                  => $context['ref_id'],
            'order_number'               => $context['ref_id'],
            'note'                       => sprintf(
                /* translators: %s: order ref */
                __('Objednávka %s', 'lkba-payments'),
                $context['ref_id']
            ),
        );

        if ($vat_rate > 0) {
            // Decision §5: site prices are VAT-inclusive.
            $payload['vat_price_mode'] = 'from_total_with_vat';
        }

        $created = $this->api_request('POST', '/invoices.json', $payload);
        if (is_wp_error($created)) {
            LKBA_Payments_Logger::log('fakturoid_proforma_failed', $created->get_error_message(), array(
                'ref_id' => $context['ref_id'],
            ), $this->company_id);
            return $created;
        }

        $invoice_id = isset($created['id']) ? (int) $created['id'] : 0;
        if (!$invoice_id) {
            return new WP_Error('fakturoid_create', __('Fakturoid did not return a proforma ID.', 'lkba-payments'));
        }

        $vs     = isset($created['variable_symbol']) ? preg_replace('/\D/', '', (string) $created['variable_symbol']) : '';
        $number = isset($created['number']) ? (string) $created['number'] : '';
        $pdf    = $this->download_invoice_pdf($invoice_id, $context['ref_id']);

        $result = array(
            'invoice_id'      => (string) $invoice_id,
            'number'          => $number,
            'variable_symbol' => $vs,
            'pdf'             => $pdf,
        );

        LKBA_Payments_Logger::log('fakturoid_proforma_created', 'Proforma created', array(
            'ref_id'         => $context['ref_id'],
            'invoice_id'     => $invoice_id,
            'variable_symbol'=> $vs,
        ), $this->company_id);

        do_action('lkba_payments_fakturoid_proforma_created', $context, $result);

        return $result;
    }

    /**
     * Card path (decision §4): create final invoice only after Comgate paid.
     *
     * @param array $context
     * @return array|WP_Error
     */
    public function create_invoice_after_card_paid(array $context) {
        $context = LKBA_Payments_Context::normalize($context);

        if (!$this->is_configured()) {
            return new WP_Error('fakturoid_disabled', __('Fakturoid is not configured for this company.', 'lkba-payments'));
        }

        // Already has a document (e.g. rare race) — refresh PDF only.
        if ($context['fakturoid_invoice_id'] !== '') {
            return $this->sync_document($context);
        }

        $subject_id = $this->ensure_subject($context);
        if (is_wp_error($subject_id)) {
            return $subject_id;
        }

        $vat_rate = $this->get_document_vat_rate();
        $payload  = array(
            'subject_id'   => $subject_id,
            'lines'        => $this->build_lines($context, $vat_rate),
            'document_type'=> 'invoice',
            'custom_id'    => $context['ref_id'],
            'order_number' => $context['ref_id'],
            'note'         => sprintf(
                /* translators: %s: order ref */
                __('Platba kartou — %s', 'lkba-payments'),
                $context['ref_id']
            ),
        );

        if ($vat_rate > 0) {
            $payload['vat_price_mode'] = 'from_total_with_vat';
        }

        $created = $this->api_request('POST', '/invoices.json', $payload);
        if (is_wp_error($created)) {
            return $created;
        }

        $invoice_id = isset($created['id']) ? (int) $created['id'] : 0;
        if (!$invoice_id) {
            return new WP_Error('fakturoid_create', __('Fakturoid did not return an invoice ID.', 'lkba-payments'));
        }

        // Mark paid in Fakturoid (card already collected).
        $pay = $this->api_request('POST', '/invoices/' . $invoice_id . '/payments.json', array(
            'paid_on' => gmdate('Y-m-d'),
            'amount'  => (float) $context['amount'],
        ));
        if (is_wp_error($pay)) {
            LKBA_Payments_Logger::log('fakturoid_mark_paid_failed', $pay->get_error_message(), array(
                'ref_id'     => $context['ref_id'],
                'invoice_id' => $invoice_id,
            ), $this->company_id);
        }

        $vs     = isset($created['variable_symbol']) ? preg_replace('/\D/', '', (string) $created['variable_symbol']) : '';
        $number = isset($created['number']) ? (string) $created['number'] : '';
        $pdf    = $this->download_invoice_pdf($invoice_id, $context['ref_id']);

        $result = array(
            'invoice_id'      => (string) $invoice_id,
            'number'          => $number,
            'variable_symbol' => $vs,
            'pdf'             => $pdf,
        );

        LKBA_Payments_Logger::log('fakturoid_invoice_after_card', 'Invoice created after Comgate paid', array(
            'ref_id'     => $context['ref_id'],
            'invoice_id' => $invoice_id,
        ), $this->company_id);

        return $result;
    }

    /**
     * @param array $context
     * @return array|WP_Error|true
     */
    public function sync_document(array $context) {
        $context = LKBA_Payments_Context::normalize($context);
        if ($context['fakturoid_invoice_id'] === '') {
            return true;
        }

        $invoice_id = (int) $context['fakturoid_invoice_id'];
        $remote     = $this->api_request('GET', '/invoices/' . $invoice_id . '.json');
        if (is_wp_error($remote)) {
            return $remote;
        }

        $result = array(
            'invoice_id'      => (string) $invoice_id,
            'number'          => isset($remote['number']) ? (string) $remote['number'] : $context['fakturoid_invoice_number'],
            'variable_symbol' => !empty($remote['variable_symbol'])
                ? preg_replace('/\D/', '', (string) $remote['variable_symbol'])
                : $context['fakturoid_variable_symbol'],
            'pdf'             => $context['fakturoid_pdf'],
        );

        $pdf = $this->download_invoice_pdf($invoice_id, $context['ref_id']);
        if ($pdf !== '') {
            $result['pdf'] = $pdf;
        }

        return $result;
    }

    public function maybe_handle_webhook() {
        if (empty($_GET[self::WEBHOOK_QUERY])) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            return;
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            status_header(405);
            header('Allow: POST');
            echo 'Method Not Allowed';
            exit;
        }

        $company_id = isset($_GET['company']) ? sanitize_text_field(wp_unslash($_GET['company'])) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $company    = $company_id !== '' ? LKBA_Payments_Settings::get_company($company_id) : null;

        if (!$company) {
            LKBA_Payments_Logger::log('fakturoid_webhook', 'Rejected: unknown company', array('company' => $company_id));
            status_header(400);
            echo 'Unknown company';
            exit;
        }

        $expected = (string) ($company['fakturoid_webhook_auth'] ?? '');
        if ($expected === '') {
            LKBA_Payments_Logger::log('fakturoid_webhook', 'Rejected: webhook auth not configured', array(), $company_id);
            status_header(503);
            echo 'Webhook auth not configured';
            exit;
        }

        $auth = self::get_request_authorization();
        if (!hash_equals($expected, $auth)) {
            LKBA_Payments_Logger::log('fakturoid_webhook', 'Rejected: bad Authorization header', array(), $company_id);
            status_header(401);
            echo 'Unauthorized';
            exit;
        }

        $idempotency = isset($_SERVER['HTTP_IDEMPOTENCY_KEY'])
            ? sanitize_text_field(wp_unslash($_SERVER['HTTP_IDEMPOTENCY_KEY']))
            : '';
        if ($idempotency !== '') {
            $cache_key = 'lkba_fr_wh_' . md5($company_id . '|' . $idempotency);
            if (get_transient($cache_key)) {
                status_header(200);
                echo 'OK';
                exit;
            }
        }

        $raw     = file_get_contents('php://input');
        $payload = json_decode($raw, true);
        if (!is_array($payload)) {
            status_header(400);
            echo 'Invalid JSON';
            exit;
        }

        $event = isset($payload['event_name']) ? (string) $payload['event_name'] : '';
        if ($event !== 'invoice_paid') {
            status_header(200);
            echo 'Ignored';
            exit;
        }

        $invoice = isset($payload['body']['invoice']) && is_array($payload['body']['invoice'])
            ? $payload['body']['invoice']
            : array();
        $payment = isset($payload['body']['payment']) && is_array($payload['body']['payment'])
            ? $payload['body']['payment']
            : array();

        $context = LKBA_Payments_Registry::resolve_fakturoid($invoice, $payment);
        if (!$context) {
            LKBA_Payments_Logger::log('fakturoid_webhook', 'No matching order', array(
                'invoice_id' => $invoice['id'] ?? null,
                'custom_id'  => $invoice['custom_id'] ?? null,
                'vs'         => $payment['variable_symbol'] ?? ($invoice['variable_symbol'] ?? null),
            ), $company_id);
            status_header(200);
            echo 'No order';
            exit;
        }

        if (empty($context['company_id'])) {
            $context['company_id'] = $company_id;
        }

        $transaction_id = !empty($payment['id'])
            ? 'fakturoid-payment-' . $payment['id']
            : 'fakturoid-' . ($invoice['id'] ?? $context['ref_id']);

        $ok = LKBA_Payments_Registry::mark_paid($context, $transaction_id, 'fakturoid', array(
            'invoice' => $invoice,
            'payment' => $payment,
        ));

        // Refresh PDF for the paid document when possible.
        if (!empty($invoice['id'])) {
            $client = self::for_company($company_id);
            $ctx    = $context;
            $ctx['fakturoid_invoice_id'] = (string) $invoice['id'];
            $synced = $client->sync_document($ctx);
            if (!is_wp_error($synced) && is_array($synced)) {
                do_action('lkba_payments_fakturoid_document_synced', $context, $synced);
            }
        }

        LKBA_Payments_Logger::log('fakturoid_webhook', $ok ? 'Payment completed' : 'mark_paid returned false', array(
            'ref_id'         => $context['ref_id'],
            'transaction_id' => $transaction_id,
        ), $company_id);

        if ($idempotency !== '') {
            set_transient('lkba_fr_wh_' . md5($company_id . '|' . $idempotency), 1, WEEK_IN_SECONDS);
        }

        status_header(200);
        echo 'OK';
        exit;
    }

    /**
     * @return array{ok:bool,message:string}
     */
    public function run_connectivity_test() {
        if (!$this->is_configured()) {
            return array(
                'ok'      => false,
                'message' => __('Fill slug, Client ID and Client Secret and enable Fakturoid.', 'lkba-payments'),
            );
        }

        $token = $this->get_access_token();
        if (is_wp_error($token)) {
            return array(
                'ok'      => false,
                'message' => $token->get_error_message(),
            );
        }

        $account = $this->api_request('GET', '/account.json');
        if (is_wp_error($account)) {
            return array(
                'ok'      => false,
                'message' => $account->get_error_message(),
            );
        }

        $name = isset($account['name']) ? (string) $account['name'] : $this->get_account_slug();
        return array(
            'ok'      => true,
            'message' => sprintf(
                /* translators: %s: Fakturoid account name */
                __('Connection OK — account “%s”.', 'lkba-payments'),
                $name
            ),
        );
    }

    /**
     * @return int
     */
    public function get_document_vat_rate() {
        $account = $this->get_account_details();
        if (is_array($account) && isset($account['vat_mode']) && $account['vat_mode'] === 'non_vat_payer') {
            return 0;
        }
        return max(0, (int) ($this->company['fakturoid_vat_rate'] ?? 0));
    }

    /**
     * @return array|WP_Error|null
     */
    public function get_account_details() {
        if (!$this->is_configured()) {
            return null;
        }
        $slug      = $this->get_account_slug();
        $cache_key = 'lkba_fakturoid_account_' . md5($this->company_id . '|' . $slug);
        $cached    = get_transient($cache_key);
        if (is_array($cached)) {
            return $cached;
        }
        $account = $this->api_request('GET', '/account.json');
        if (is_wp_error($account) || !is_array($account)) {
            return $account;
        }
        set_transient($cache_key, $account, HOUR_IN_SECONDS);
        return $account;
    }

    /**
     * @return string
     */
    public function get_account_slug() {
        $slug = (string) ($this->company['fakturoid_slug'] ?? '');
        $slug = trim($slug, "/ \t\n\r\0\x0B");
        return preg_replace('/[^a-zA-Z0-9\-_]/', '', $slug);
    }

    /**
     * @return string
     */
    public function get_user_agent() {
        $ua = (string) ($this->company['fakturoid_user_agent'] ?? '');
        return $ua !== '' ? $ua : 'LKBA Payments (kubasanitrak)';
    }

    /**
     * @return string
     */
    private function api_base() {
        return 'https://app.fakturoid.cz/api/v3/accounts/' . $this->get_account_slug();
    }

    /**
     * @param array $context
     * @param int   $vat_rate
     * @return array
     */
    private function build_lines(array $context, $vat_rate) {
        $lines = array();
        foreach ($context['line_items'] as $item) {
            $lines[] = array(
                'name'       => isset($item['name']) ? (string) $item['name'] : $context['label'],
                'quantity'   => max(1, (int) ($item['quantity'] ?? 1)),
                'unit_price' => (float) ($item['unit_price'] ?? $context['amount']),
                'vat_rate'   => (int) $vat_rate,
            );
        }
        return $lines;
    }

    /**
     * @param array $context
     * @return int|WP_Error
     */
    private function ensure_subject(array $context) {
        $invoice   = is_array($context['invoice_data']) ? $context['invoice_data'] : array();
        $email     = $context['customer_email'] !== '' ? $context['customer_email'] : 'noreply@example.com';
        $custom_id = !empty($invoice['ic'])
            ? 'ic_' . preg_replace('/\D/', '', (string) $invoice['ic'])
            : 'ref_' . preg_replace('/[^A-Za-z0-9_\-]/', '_', $context['ref_id']);

        $existing = $this->api_request('GET', '/subjects.json?custom_id=' . rawurlencode($custom_id));
        if (!is_wp_error($existing) && !empty($existing[0]['id'])) {
            return (int) $existing[0]['id'];
        }

        if (!empty($invoice['company_name']) || !empty($invoice['name'])) {
            $payload = array(
                'custom_id'       => $custom_id,
                'name'            => (string) ($invoice['company_name'] ?? $invoice['name']),
                'street'          => trim(($invoice['street'] ?? '') . ' ' . ($invoice['street_number'] ?? '')),
                'city'            => $invoice['city'] ?? '',
                'zip'             => $invoice['zip'] ?? '',
                'country'         => $invoice['country'] ?? 'CZ',
                'registration_no' => preg_replace('/\D/', '', $invoice['ic'] ?? ''),
                'vat_no'          => $invoice['dic'] ?? '',
                'email'           => $email,
            );
        } else {
            $payload = array(
                'custom_id' => $custom_id,
                'name'      => $context['customer_name'] !== '' ? $context['customer_name'] : $context['ref_id'],
                'email'     => $email,
            );
        }

        $created = $this->api_request('POST', '/subjects.json', $payload);
        if (is_wp_error($created)) {
            return $created;
        }

        return isset($created['id']) ? (int) $created['id'] : new WP_Error('fakturoid_subject', 'Subject create failed');
    }

    /**
     * @param int    $invoice_id
     * @param string $ref_id
     * @return string Relative path under uploads, or empty.
     */
    private function download_invoice_pdf($invoice_id, $ref_id) {
        $response = $this->api_request('GET', '/invoices/' . $invoice_id . '/download.pdf', null, true);
        if (is_wp_error($response) || empty($response['body'])) {
            return '';
        }

        self::ensure_storage_dir();
        $filename = sanitize_file_name($ref_id . '-fakturoid.pdf');
        $relative = self::SUBDIR . '/' . $filename;
        $path     = self::absolute_path($relative);
        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
        file_put_contents($path, $response['body']);

        return $relative;
    }

    public static function ensure_storage_dir() {
        $upload = wp_upload_dir();
        if (!empty($upload['error'])) {
            return false;
        }
        $dir = trailingslashit($upload['basedir']) . self::SUBDIR;
        wp_mkdir_p($dir);
        $htaccess = $dir . '/.htaccess';
        if (!file_exists($htaccess)) {
            // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
            file_put_contents($htaccess, "Deny from all\n");
        }
        return $dir;
    }

    /**
     * @param string $relative
     * @return string
     */
    public static function absolute_path($relative) {
        $upload = wp_upload_dir();
        return trailingslashit($upload['basedir']) . ltrim($relative, '/');
    }

    /**
     * @return string|WP_Error
     */
    private function get_access_token() {
        $client_id     = trim((string) ($this->company['fakturoid_client_id'] ?? ''));
        $client_secret = trim((string) ($this->company['fakturoid_client_secret'] ?? ''));

        if ($client_id === '' || $client_secret === '') {
            return new WP_Error('fakturoid_auth', __('Fakturoid Client ID / Secret missing.', 'lkba-payments'));
        }

        $cache_key = 'lkba_fakturoid_token_' . md5($this->company_id . '|' . $client_id);
        $cached    = get_transient($cache_key);
        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        $response = wp_remote_post('https://app.fakturoid.cz/api/v3/oauth/token', array(
            'timeout' => 30,
            'headers' => array(
                'Authorization' => 'Basic ' . base64_encode($client_id . ':' . $client_secret),
                'User-Agent'    => $this->get_user_agent(),
                'Accept'        => 'application/json',
                'Content-Type'  => 'application/x-www-form-urlencoded',
            ),
            'body' => array(
                'grant_type' => 'client_credentials',
            ),
        ));

        if (is_wp_error($response)) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code($response);
        $raw  = wp_remote_retrieve_body($response);
        $body = json_decode($raw, true);

        if ($code < 200 || $code >= 300 || empty($body['access_token'])) {
            $error   = is_array($body) && isset($body['error']) ? (string) $body['error'] : '';
            $message = isset($body['error_description'])
                ? (string) $body['error_description']
                : ($error !== '' ? $error : __('Fakturoid OAuth failed.', 'lkba-payments'));

            LKBA_Payments_Logger::log('fakturoid_oauth_failed', $message, array(
                'http'  => $code,
                'error' => $error,
            ), $this->company_id);

            return new WP_Error('fakturoid_auth', $message, array('code' => $code));
        }

        $ttl = max(60, (int) ($body['expires_in'] ?? 7200) - 120);
        set_transient($cache_key, $body['access_token'], $ttl);

        return $body['access_token'];
    }

    /**
     * @param string     $method
     * @param string     $path
     * @param array|null $body
     * @param bool       $raw
     * @return array|WP_Error
     */
    private function api_request($method, $path, $body = null, $raw = false) {
        $token = $this->get_access_token();
        if (is_wp_error($token)) {
            return $token;
        }

        $args = array(
            'method'  => $method,
            'timeout' => 45,
            'headers' => array(
                'Authorization' => 'Bearer ' . $token,
                'User-Agent'    => $this->get_user_agent(),
                'Accept'        => $raw ? 'application/pdf' : 'application/json',
                'Content-Type'  => 'application/json',
            ),
        );

        if ($body !== null && !$raw) {
            $args['body'] = wp_json_encode($body);
        }

        $response = wp_remote_request($this->api_base() . $path, $args);
        if (is_wp_error($response)) {
            return $response;
        }

        $code      = wp_remote_retrieve_response_code($response);
        $resp_body = wp_remote_retrieve_body($response);

        $retry_key = 'lkba_fakturoid_token_retry_' . $this->company_id;
        if ($code === 401 && empty($GLOBALS[$retry_key])) {
            $client_id = (string) ($this->company['fakturoid_client_id'] ?? '');
            delete_transient('lkba_fakturoid_token_' . md5($this->company_id . '|' . $client_id));
            $GLOBALS[$retry_key] = true;
            $result = $this->api_request($method, $path, $body, $raw);
            unset($GLOBALS[$retry_key]);
            return $result;
        }

        if ($raw) {
            if ($code >= 200 && $code < 300) {
                return array('body' => $resp_body);
            }
            return new WP_Error('fakturoid_pdf', 'PDF download failed', array('code' => $code));
        }

        $json = json_decode($resp_body, true);
        if ($code >= 200 && $code < 300) {
            return is_array($json) ? $json : array();
        }

        $message = is_array($json) && isset($json['errors'])
            ? wp_json_encode($json['errors'])
            : ('Fakturoid API error HTTP ' . $code);

        return new WP_Error('fakturoid_api', $message, array('code' => $code, 'body' => $json));
    }

    /**
     * @return string
     */
    private static function get_request_authorization() {
        if (!empty($_SERVER['HTTP_AUTHORIZATION'])) {
            return trim((string) wp_unslash($_SERVER['HTTP_AUTHORIZATION']));
        }
        if (!empty($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
            return trim((string) wp_unslash($_SERVER['REDIRECT_HTTP_AUTHORIZATION']));
        }
        if (function_exists('getallheaders')) {
            $headers = getallheaders();
            if (is_array($headers)) {
                foreach ($headers as $name => $value) {
                    if (strtolower((string) $name) === 'authorization') {
                        return trim((string) $value);
                    }
                }
            }
        }
        return '';
    }
}
