<?php
/**
 * HTML email chrome + payment-instructions partial.
 */

if (!defined('ABSPATH')) {
    exit;
}

class LKBA_Payments_Emails {

    /**
     * Wrap body HTML in LKBA-branded chrome.
     *
     * @param string $title
     * @param string $inner_html Already safe / built by caller.
     * @return string
     */
    public static function wrap($title, $inner_html) {
        $brand = get_option(LKBA_Payments_Settings::OPTION_SENDER_NAME, 'Lenka Krejčová Barre Academy');
        if ($brand === '') {
            $brand = 'Lenka Krejčová Barre Academy';
        }

        $logo_url = apply_filters('lkba_payments_email_logo_url', '');
        $title    = (string) $title;
        $inner_html = (string) $inner_html;

        ob_start();
        include LKBA_PAYMENTS_PLUGIN_DIR . 'templates/emails/chrome.php';
        return (string) ob_get_clean();
    }

    /**
     * Bank / card payment instructions block (HTML).
     *
     * @param array $context
     * @return string
     */
    public static function payment_instructions_html(array $context) {
        $context = LKBA_Payments_Context::normalize($context);
        $company = LKBA_Payments_Settings::get_company($context['company_id']);

        $vs      = LKBA_Payments_Context::variable_symbol($context);
        $qr_html = '';
        $bank    = array(
            'account_name'   => '',
            'account_number' => '',
            'bank_code'      => '',
            'iban'           => '',
        );

        if ($company) {
            $bank = array(
                'account_name'   => (string) ($company['bank_account_name'] ?? $company['name'] ?? ''),
                'account_number' => (string) ($company['bank_account_number'] ?? ''),
                'bank_code'      => (string) ($company['bank_code'] ?? ''),
                'iban'           => (string) ($company['iban'] ?? ''),
            );
            if (($context['payment_method'] === '' || $context['payment_method'] === 'bank_transfer')) {
                $qr_html = LKBA_Payments_QR::render_qr_html($context, 200);
            }
        }

        $amount_fmt = self::format_price($context['amount'], $context['currency']);

        ob_start();
        include LKBA_PAYMENTS_PLUGIN_DIR . 'templates/emails/payment-instructions.php';
        return (string) ob_get_clean();
    }

    /**
     * Send a simple HTML mail using shared chrome.
     *
     * @param string       $to
     * @param string       $subject
     * @param string       $title
     * @param string       $inner_html
     * @param array        $attachments Absolute paths.
     * @return bool
     */
    public static function send($to, $subject, $title, $inner_html, array $attachments = array()) {
        $from_name  = get_option(LKBA_Payments_Settings::OPTION_SENDER_NAME, get_bloginfo('name'));
        $from_email = get_option(LKBA_Payments_Settings::OPTION_SENDER_EMAIL, get_option('admin_email'));

        $headers = array('Content-Type: text/html; charset=UTF-8');
        if ($from_email) {
            $headers[] = 'From: ' . $from_name . ' <' . $from_email . '>';
        }

        $body = self::wrap($title, $inner_html);
        return wp_mail($to, $subject, $body, $headers, $attachments);
    }

    /**
     * @param float  $amount
     * @param string $currency
     * @return string
     */
    public static function format_price($amount, $currency = 'CZK') {
        $formatted = number_format_i18n((float) $amount, 2);
        if (strtoupper($currency) === 'CZK') {
            return $formatted . ' Kč';
        }
        return $formatted . ' ' . strtoupper($currency);
    }
}
