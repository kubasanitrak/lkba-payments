<?php
/**
 * Payment context shape shared by Comgate / Fakturoid / emails.
 */

if (!defined('ABSPATH')) {
    exit;
}

class LKBA_Payments_Context {

    /**
     * Normalize / fill defaults for a payment context.
     *
     * Expected keys:
     * - ref_id (string, e.g. E-1001 / V-2002)
     * - company_id (string)
     * - amount (float)
     * - currency (string)
     * - customer_email, customer_name
     * - label
     * - line_items: list of {name, quantity, unit_price}
     * - invoice_data: optional buyer company fields
     * - return_url_paid, return_url_cancelled
     * - fakturoid_invoice_id, fakturoid_invoice_number, fakturoid_variable_symbol, fakturoid_pdf
     * - transaction_id, payment_method
     * - meta (array)
     *
     * @param array $context
     * @return array<string, mixed>
     */
    public static function normalize(array $context) {
        $defaults = array(
            'ref_id'                     => '',
            'company_id'                 => '',
            'amount'                     => 0.0,
            'currency'                   => 'CZK',
            'customer_email'             => '',
            'customer_name'              => '',
            'label'                      => '',
            'line_items'                 => array(),
            'invoice_data'               => array(),
            'return_url_paid'            => '',
            'return_url_cancelled'       => '',
            'fakturoid_invoice_id'       => '',
            'fakturoid_invoice_number'   => '',
            'fakturoid_variable_symbol'  => '',
            'fakturoid_pdf'              => '',
            'transaction_id'             => '',
            'payment_method'             => '',
            'meta'                       => array(),
        );

        $out = array_merge($defaults, $context);
        $out['ref_id']     = (string) $out['ref_id'];
        $out['company_id'] = (string) $out['company_id'];
        $out['amount']     = (float) $out['amount'];
        $out['currency']   = strtoupper((string) $out['currency'] ?: 'CZK');
        $out['label']      = (string) ($out['label'] !== '' ? $out['label'] : $out['ref_id']);

        if (!is_array($out['line_items']) || $out['line_items'] === array()) {
            $out['line_items'] = array(
                array(
                    'name'       => $out['label'] !== '' ? $out['label'] : $out['ref_id'],
                    'quantity'   => 1,
                    'unit_price' => $out['amount'],
                ),
            );
        }

        if (!is_array($out['invoice_data'])) {
            $out['invoice_data'] = array();
        }
        if (!is_array($out['meta'])) {
            $out['meta'] = array();
        }

        return $out;
    }

    /**
     * Variable symbol for QR / bank: Fakturoid VS first, else digits from ref.
     *
     * @param array $context
     * @return string
     */
    public static function variable_symbol(array $context) {
        $context = self::normalize($context);
        if ($context['fakturoid_variable_symbol'] !== '') {
            return substr(preg_replace('/\D/', '', (string) $context['fakturoid_variable_symbol']), 0, 10);
        }
        return substr(preg_replace('/\D/', '', (string) $context['ref_id']), 0, 10);
    }
}
