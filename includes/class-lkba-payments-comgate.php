<?php
/**
 * Comgate gateway — company-scoped create + global callback with secret check.
 */

if (!defined('ABSPATH')) {
    exit;
}

class LKBA_Payments_Comgate {

    const REST_NAMESPACE = 'lkba-payments/v1';
    const REST_ROUTE     = '/comgate-callback';
    const LEGACY_QUERY   = 'lkba_comgate_callback';

    private $api_url = 'https://payments.comgate.cz/v1.0';

    /** @var array<string, mixed>|null */
    private $company;

    /** @var string */
    private $company_id = '';

    /**
     * Boot global callback listeners once.
     */
    public static function boot() {
        static $booted = false;
        if ($booted) {
            return;
        }
        $booted = true;

        $instance = new self('');
        add_action('init', array($instance, 'handle_legacy_callback'), 0);
        add_action('rest_api_init', array($instance, 'register_callback_endpoint'));
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
        return !empty($this->company['comgate_enabled'])
            && !empty($this->company['comgate_merchant_id'])
            && !empty($this->company['comgate_secret_key']);
    }

    /**
     * Create a Comgate payment from a payment context.
     *
     * @param array $context See LKBA_Payments_Context.
     * @return array{error?:string,redirect?:string,trans_id?:string}
     */
    public function create_payment(array $context) {
        $context = LKBA_Payments_Context::normalize($context);

        if (!$this->is_configured()) {
            return array('error' => __('Comgate is not configured for this company.', 'lkba-payments'));
        }

        if ($context['ref_id'] === '' || $context['amount'] <= 0) {
            return array('error' => __('Invalid payment context (ref_id / amount).', 'lkba-payments'));
        }

        if ($context['customer_email'] === '' || !is_email($context['customer_email'])) {
            return array('error' => __('Customer e-mail is required for Comgate.', 'lkba-payments'));
        }

        $company = $this->company;
        $test    = !empty($company['comgate_test_mode']);

        $params = array(
            'merchant'      => $company['comgate_merchant_id'],
            'test'          => $test ? 'true' : 'false',
            'price'         => (int) round($context['amount'] * 100),
            'curr'          => $context['currency'],
            'label'         => self::truncate_label($context['label'], 16),
            'refId'         => $context['ref_id'],
            'email'         => $context['customer_email'],
            'prepareOnly'   => 'true',
            'country'       => 'CZ',
            'lang'          => 'cs',
            'method'        => 'ALL',
            'url_paid'      => $context['return_url_paid'] !== '' ? $context['return_url_paid'] : home_url('/'),
            'url_cancelled' => $context['return_url_cancelled'] !== '' ? $context['return_url_cancelled'] : home_url('/'),
            'url_pending'   => $context['return_url_paid'] !== '' ? $context['return_url_paid'] : home_url('/'),
            'secret'        => $company['comgate_secret_key'],
        );

        $response = $this->api_request('create', $params);

        if (isset($response['code']) && (int) $response['code'] === 0 && !empty($response['redirect'])) {
            LKBA_Payments_Logger::log('comgate_created', 'Comgate payment created', array(
                'ref_id'  => $context['ref_id'],
                'transId' => $response['transId'] ?? '',
            ), $this->company_id);

            /**
             * After Comgate create succeeds (product may store transId).
             *
             * @param array $context
             * @param array $response
             */
            do_action('lkba_payments_comgate_created', $context, $response);

            return array(
                'redirect' => $response['redirect'],
                'trans_id' => isset($response['transId']) ? (string) $response['transId'] : '',
            );
        }

        $error = isset($response['message']) ? (string) $response['message'] : __('Payment creation failed.', 'lkba-payments');
        LKBA_Payments_Logger::log('comgate_error', $error, array(
            'ref_id'   => $context['ref_id'],
            'response' => $response,
        ), $this->company_id);

        return array('error' => $error);
    }

    public function register_callback_endpoint() {
        register_rest_route(self::REST_NAMESPACE, self::REST_ROUTE, array(
            'methods'             => 'POST',
            'callback'            => array($this, 'handle_rest_callback'),
            'permission_callback' => '__return_true',
        ));
    }

    /**
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function handle_rest_callback($request) {
        $params = $request->get_body_params();
        if (empty($params)) {
            parse_str($request->get_body(), $params);
        }
        $result = $this->process_callback_request($params);
        return new WP_REST_Response(
            $result['body'],
            $result['code'],
            array('Content-Type' => 'application/x-www-form-urlencoded; charset=utf-8')
        );
    }

    public function handle_legacy_callback() {
        if (!isset($_GET[self::LEGACY_QUERY])) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            return;
        }
        $result = $this->process_callback_request($_POST);
        status_header($result['code']);
        header('Content-Type: application/x-www-form-urlencoded; charset=utf-8');
        echo $result['body']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        exit;
    }

    /**
     * @param array $params
     * @return array{code:int,body:string}
     */
    public function process_callback_request($params) {
        if (!is_array($params)) {
            $params = array();
        }

        $trans_id      = isset($params['transId']) ? sanitize_text_field(wp_unslash($params['transId'])) : '';
        $ref_id        = isset($params['refId']) ? sanitize_text_field(wp_unslash($params['refId'])) : '';
        $post_secret   = isset($params['secret']) ? sanitize_text_field(wp_unslash($params['secret'])) : '';
        $post_merchant = isset($params['merchant']) ? sanitize_text_field(wp_unslash($params['merchant'])) : '';

        if ($trans_id === '' || $ref_id === '') {
            LKBA_Payments_Logger::log('comgate_callback_error', 'Missing callback parameters', array(
                'trans_id' => $trans_id,
                'ref_id'   => $ref_id,
            ));
            return $this->callback_response(400, 'code=1400&message=Missing parameters');
        }

        $company = $this->find_company_for_callback($post_merchant, $ref_id);
        if (!$company) {
            LKBA_Payments_Logger::log('comgate_callback_error', 'No matching company for callback', array(
                'merchant' => $post_merchant,
                'ref_id'   => $ref_id,
            ));
            return $this->callback_response(403, 'code=1400&message=Invalid merchant');
        }

        $gateway = self::for_company($company['id']);
        if (!$gateway->is_valid_callback_secret($post_secret)) {
            LKBA_Payments_Logger::log('comgate_callback_error', 'Invalid callback secret', array(
                'ref_id'     => $ref_id,
                'company_id' => $company['id'],
            ), $company['id']);
            return $this->callback_response(403, 'code=1400&message=Invalid secret');
        }

        if ($post_merchant !== '' && (string) $company['comgate_merchant_id'] !== $post_merchant) {
            LKBA_Payments_Logger::log('comgate_callback_error', 'Merchant ID mismatch', array(
                'expected' => $company['comgate_merchant_id'],
                'received' => $post_merchant,
            ), $company['id']);
            return $this->callback_response(403, 'code=1400&message=Invalid merchant');
        }

        $context = LKBA_Payments_Registry::resolve($ref_id);
        if (!$context) {
            // Brief retry for replication / race with create.
            usleep(500000);
            $context = LKBA_Payments_Registry::resolve($ref_id);
        }

        if (!$context) {
            LKBA_Payments_Logger::log('comgate_callback_error', 'Order not found for callback', array(
                'ref_id'   => $ref_id,
                'trans_id' => $trans_id,
            ), $company['id']);
            return $this->callback_response(404, 'code=1400&message=Order not found');
        }

        $status = $gateway->get_transaction_status($trans_id);
        if ($status === null) {
            LKBA_Payments_Logger::log('comgate_callback_error', 'Payment verification failed', array(
                'ref_id'   => $ref_id,
                'trans_id' => $trans_id,
            ), $company['id']);
            return $this->callback_response(400, 'code=1400&message=Verification failed');
        }

        if ($status === 'PAID' || $status === 'AUTHORIZED') {
            $this->complete_paid($context, $trans_id, $company['id']);
        } elseif ($status === 'CANCELLED' || $status === 'FAILED') {
            LKBA_Payments_Registry::mark_failed($context, $status, 'comgate');
        }

        return $this->callback_response(200, 'code=0&message=OK');
    }

    /**
     * After browser return — verify status and complete if paid (callback lag fallback).
     *
     * @param array       $context
     * @param string|null $trans_id
     * @return bool
     */
    public function complete_from_return(array $context, $trans_id = null) {
        $context = LKBA_Payments_Context::normalize($context);
        if (!$this->is_configured()) {
            return false;
        }

        if ($trans_id === null || $trans_id === '') {
            $trans_id = $context['transaction_id'];
        }
        if (($trans_id === '' || $trans_id === null) && isset($_GET['id'])) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $trans_id = sanitize_text_field(wp_unslash($_GET['id']));
        } elseif (($trans_id === '' || $trans_id === null) && isset($_GET['transId'])) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $trans_id = sanitize_text_field(wp_unslash($_GET['transId']));
        }

        if (!$trans_id) {
            return false;
        }

        $status = $this->get_transaction_status($trans_id);
        if ($status === 'PAID' || $status === 'AUTHORIZED') {
            return $this->complete_paid($context, $trans_id, $this->company_id);
        }
        if ($status === 'CANCELLED' || $status === 'FAILED') {
            LKBA_Payments_Registry::mark_failed($context, $status, 'comgate');
        }
        return false;
    }

    /**
     * Card path: mark paid, then create Fakturoid invoice (decision §4 — invoice only after Comgate paid).
     *
     * @param array  $context
     * @param string $trans_id
     * @param string $company_id
     * @return bool
     */
    private function complete_paid(array $context, $trans_id, $company_id) {
        $context['transaction_id'] = $trans_id;
        $context['payment_method'] = 'comgate';
        if (empty($context['company_id'])) {
            $context['company_id'] = $company_id;
        }

        $ok = LKBA_Payments_Registry::mark_paid($context, $trans_id, 'comgate');

        $fakturoid = LKBA_Payments_Fakturoid::for_company($context['company_id']);
        if ($fakturoid->is_configured()) {
            $invoice = $fakturoid->create_invoice_after_card_paid($context);
            if (!is_wp_error($invoice)) {
                /**
                 * @param array $context
                 * @param array $invoice
                 */
                do_action('lkba_payments_fakturoid_invoice_after_card', $context, $invoice);
            } else {
                LKBA_Payments_Logger::log('fakturoid_card_invoice_failed', $invoice->get_error_message(), array(
                    'ref_id' => $context['ref_id'],
                ), $context['company_id']);
            }
        }

        return $ok;
    }

    /**
     * @param string $trans_id
     * @return string|null
     */
    public function get_transaction_status($trans_id) {
        if (!$this->company) {
            return null;
        }
        $params = array(
            'merchant' => $this->company['comgate_merchant_id'],
            'transId'  => $trans_id,
            'secret'   => $this->company['comgate_secret_key'],
        );
        $response = $this->api_request('status', $params);
        if (!isset($response['code']) || (int) $response['code'] !== 0) {
            return null;
        }
        return isset($response['status']) ? (string) $response['status'] : null;
    }

    /**
     * @param string $post_secret
     * @return bool
     */
    public function is_valid_callback_secret($post_secret) {
        if ($post_secret === '' || !$this->company) {
            return false;
        }
        $secrets = array_filter(array(
            $this->company['comgate_secret_key'] ?? '',
            $this->company['comgate_push_secret'] ?? '',
        ));
        foreach ($secrets as $secret) {
            if (hash_equals((string) $secret, $post_secret)) {
                return true;
            }
        }
        return false;
    }

    /**
     * @param string $merchant
     * @param string $ref_id
     * @return array<string, mixed>|null
     */
    private function find_company_for_callback($merchant, $ref_id) {
        if ($merchant !== '') {
            foreach (LKBA_Payments_Settings::get_companies() as $company) {
                if (!empty($company['comgate_merchant_id'])
                    && (string) $company['comgate_merchant_id'] === $merchant
                ) {
                    return $company;
                }
            }
        }

        $context = LKBA_Payments_Registry::resolve($ref_id);
        if ($context && !empty($context['company_id'])) {
            return LKBA_Payments_Settings::get_company($context['company_id']);
        }

        return null;
    }

    /**
     * @param string $endpoint
     * @param array  $params
     * @return array
     */
    private function api_request($endpoint, $params) {
        $response = wp_remote_post($this->api_url . '/' . $endpoint, array(
            'body'    => $params,
            'timeout' => 30,
        ));

        if (is_wp_error($response)) {
            return array('error' => $response->get_error_message());
        }

        $body = wp_remote_retrieve_body($response);
        $result = array();
        parse_str($body, $result);
        return $result;
    }

    /**
     * @param int    $code
     * @param string $body
     * @return array{code:int,body:string}
     */
    private function callback_response($code, $body) {
        return array(
            'code' => (int) $code,
            'body' => $body,
        );
    }

    /**
     * @param string $label
     * @param int    $max
     * @return string
     */
    private static function truncate_label($label, $max = 16) {
        $label = (string) $label;
        if (function_exists('mb_substr')) {
            return mb_substr($label, 0, $max);
        }
        return substr($label, 0, $max);
    }

    /**
     * @return string
     */
    public static function get_callback_url() {
        return rest_url(self::REST_NAMESPACE . self::REST_ROUTE);
    }

    /**
     * @return string
     */
    public static function get_legacy_callback_url() {
        return add_query_arg(self::LEGACY_QUERY, '1', home_url('/'));
    }
}
