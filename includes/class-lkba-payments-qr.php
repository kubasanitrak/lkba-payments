<?php
/**
 * Paylibo QR — company-scoped bank details.
 */

if (!defined('ABSPATH')) {
    exit;
}

class LKBA_Payments_QR {

    private static $api_url = 'https://api.paylibo.com/paylibo/generator/czech/image';

    /**
     * @param string $company_id
     * @param float  $amount
     * @param string $variable_symbol
     * @param string $currency
     * @param string $message
     * @param int    $size
     * @return string|null
     */
    public static function generate_payment_qr(
        $company_id,
        $amount,
        $variable_symbol = '',
        $currency = 'CZK',
        $message = '',
        $size = 250
    ) {
        $company = LKBA_Payments_Settings::get_company($company_id);
        if (!$company) {
            return null;
        }

        $account_number = (string) ($company['bank_account_number'] ?? '');
        $bank_code      = (string) ($company['bank_code'] ?? '');

        if ($account_number === '' || $bank_code === '') {
            return null;
        }

        $vs      = substr(preg_replace('/\D/', '', (string) $variable_symbol), 0, 10);
        $account = self::parse_account_number($account_number);

        $params = array(
            'accountNumber' => $account['base'],
            'bankCode'      => self::sanitize_bank_code($bank_code),
            'amount'        => number_format((float) $amount, 2, '.', ''),
            'currency'      => strtoupper($currency),
            'size'          => (int) $size,
        );

        if ($account['prefix'] !== '') {
            $params['accountPrefix'] = $account['prefix'];
        }
        if ($vs !== '') {
            $params['vs'] = $vs;
        }
        if ($message !== '') {
            $params['message'] = self::sanitize_message($message);
        }

        return add_query_arg($params, self::$api_url);
    }

    /**
     * @param array $context
     * @param int   $size
     * @return string|null
     */
    public static function generate_context_qr(array $context, $size = 250) {
        $context = LKBA_Payments_Context::normalize($context);
        $message = __('Děkujeme. Po přijetí platby pošleme potvrzení.', 'lkba-payments');

        return self::generate_payment_qr(
            $context['company_id'],
            $context['amount'],
            LKBA_Payments_Context::variable_symbol($context),
            $context['currency'],
            $message,
            $size
        );
    }

    /**
     * @param array $context
     * @param int   $size
     * @return string HTML
     */
    public static function render_qr_html(array $context, $size = 250) {
        $url = self::generate_context_qr($context, $size);
        if (!$url) {
            return '<p class="lkba-qr-error">' . esc_html__('QR kód nelze vygenerovat. Použijte údaje výše.', 'lkba-payments') . '</p>';
        }

        return sprintf(
            '<div class="lkba-qr-code"><img src="%s" alt="%s" width="%d" height="%d" loading="lazy"></div>',
            esc_url($url),
            esc_attr__('QR platba', 'lkba-payments'),
            (int) $size,
            (int) $size
        );
    }

    /**
     * @param string $account_number
     * @return array{prefix:string,base:string}
     */
    private static function parse_account_number($account_number) {
        $account_number = preg_replace('/\s+/', '', $account_number);

        if (strpos($account_number, '/') !== false) {
            $account_number = explode('/', $account_number, 2)[0];
        }

        $prefix = '';
        $base   = $account_number;

        if (strpos($account_number, '-') !== false) {
            list($prefix, $base) = explode('-', $account_number, 2);
            $prefix = ltrim($prefix, '0');
        }

        $base = ltrim($base, '0');
        if ($base === '') {
            $base = '0';
        }

        return array(
            'prefix' => $prefix,
            'base'   => $base,
        );
    }

    /**
     * @param string $bank_code
     * @return string
     */
    private static function sanitize_bank_code($bank_code) {
        return str_pad(preg_replace('/\D/', '', $bank_code), 4, '0', STR_PAD_LEFT);
    }

    /**
     * @param string $message
     * @return string
     */
    private static function sanitize_message($message) {
        if (function_exists('iconv')) {
            $converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $message);
            if ($converted !== false) {
                $message = $converted;
            }
        }
        $message = preg_replace('/[^a-zA-Z0-9\s\-_]/', '', $message);
        return substr(trim($message), 0, 60);
    }
}
