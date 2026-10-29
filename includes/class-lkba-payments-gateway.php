<?php
/**
 * Facade for product plugins (events / vouchers).
 */

if (!defined('ABSPATH')) {
    exit;
}

class LKBA_Payments_Gateway {

    /**
     * Start Comgate card payment for a context.
     *
     * @param array $context
     * @return array{error?:string,redirect?:string,trans_id?:string}
     */
    public static function start_comgate(array $context) {
        $context = LKBA_Payments_Context::normalize($context);
        if ($context['company_id'] === '') {
            return array('error' => __('Missing company_id on payment context.', 'lkba-payments'));
        }
        $context['payment_method'] = 'comgate';
        return LKBA_Payments_Comgate::for_company($context['company_id'])->create_payment($context);
    }

    /**
     * Create bank-transfer proforma + return instructions-friendly meta.
     *
     * @param array $context
     * @return array|WP_Error
     */
    public static function start_bank_transfer(array $context) {
        $context = LKBA_Payments_Context::normalize($context);
        if ($context['company_id'] === '') {
            return new WP_Error('missing_company', __('Missing company_id on payment context.', 'lkba-payments'));
        }
        $context['payment_method'] = 'bank_transfer';

        $client = LKBA_Payments_Fakturoid::for_company($context['company_id']);
        $result = $client->create_proforma($context);
        if (is_wp_error($result)) {
            return $result;
        }

        $merged = array_merge($context, array(
            'fakturoid_invoice_id'      => $result['invoice_id'],
            'fakturoid_invoice_number'  => $result['number'],
            'fakturoid_variable_symbol' => $result['variable_symbol'],
            'fakturoid_pdf'             => $result['pdf'],
            'payment_method'            => 'bank_transfer',
        ));

        return array(
            'invoice'      => $result,
            'context'      => $merged,
            'qr_url'       => LKBA_Payments_QR::generate_context_qr($merged),
            'instructions' => LKBA_Payments_Emails::payment_instructions_html($merged),
        );
    }

    /**
     * Allowed methods for a company: both|card|bank_transfer → list.
     *
     * @param string $company_id
     * @return string[]
     */
    public static function allowed_methods($company_id) {
        $company = LKBA_Payments_Settings::get_company($company_id);
        if (!$company) {
            return array();
        }
        $mode = $company['payment_methods'] ?? 'both';
        if ($mode === 'card') {
            return array('comgate');
        }
        if ($mode === 'bank_transfer') {
            return array('bank_transfer');
        }
        return array('comgate', 'bank_transfer');
    }
}
