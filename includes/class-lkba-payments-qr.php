<?php
/**
 * Paylibo QR helper — Phase 0 shell (implementation in Phase 1).
 */

if (!defined('ABSPATH')) {
    exit;
}

class LKBA_Payments_QR {

    /**
     * @param string               $company_id
     * @param float                $amount
     * @param string               $variable_symbol
     * @param string               $currency
     * @param string               $message
     * @param int                  $size
     * @return string|null URL or null when not ready.
     */
    public static function generate_payment_qr(
        $company_id,
        $amount,
        $variable_symbol = '',
        $currency = 'CZK',
        $message = '',
        $size = 250
    ) {
        unset($company_id, $amount, $variable_symbol, $currency, $message, $size);
        // Phase 1: company bank details + Paylibo image URL.
        return null;
    }
}
