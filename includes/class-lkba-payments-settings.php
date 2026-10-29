<?php
/**
 * Options and company stubs.
 */

if (!defined('ABSPATH')) {
    exit;
}

class LKBA_Payments_Settings {

    const OPTION_COMPANIES = 'lkba_payments_companies';
    const OPTION_SUPPORT_EMAIL = 'lkba_payments_support_email';
    const OPTION_SENDER_NAME = 'lkba_payments_email_sender_name';
    const OPTION_SENDER_EMAIL = 'lkba_payments_email_sender_email';

    public function __construct() {
        // Settings UI is registered from admin class.
    }

    public static function ensure_defaults() {
        if (false === get_option(self::OPTION_COMPANIES, false)) {
            update_option(self::OPTION_COMPANIES, self::default_companies());
        }

        $defaults = array(
            self::OPTION_SUPPORT_EMAIL => '',
            self::OPTION_SENDER_NAME   => 'Lenka Krejčová Barre Academy',
            self::OPTION_SENDER_EMAIL  => '',
        );

        foreach ($defaults as $key => $value) {
            if (false === get_option($key, false)) {
                add_option($key, $value);
            }
        }
    }

    /**
     * Two child-company slots (decision §1). Per-company Comgate + Fakturoid (decision §2).
     *
     * @return array<int, array<string, mixed>>
     */
    public static function default_companies() {
        return array(
            self::empty_company('company-1', __('Firma 1', 'lkba-payments')),
            self::empty_company('company-2', __('Firma 2', 'lkba-payments')),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public static function empty_company($id, $label) {
        return array(
            'id'    => $id,
            'label' => $label,
            'name'  => '',
            'street' => '',
            'city'   => '',
            'zip'    => '',
            'country' => 'CZ',
            'ic'     => '',
            'dic'    => '',
            'bank_account_name'   => '',
            'bank_account_number' => '',
            'bank_code'           => '',
            'iban'                => '',
            'bic'                 => '',
            'payment_methods'     => 'both', // both | card | bank_transfer
            'comgate_enabled'     => 0,
            'comgate_merchant_id' => '',
            'comgate_secret_key'  => '',
            'comgate_push_secret' => '',
            'comgate_test_mode'   => 1,
            'fakturoid_enabled'       => 0,
            'fakturoid_slug'          => '',
            'fakturoid_client_id'     => '',
            'fakturoid_client_secret' => '',
            'fakturoid_user_agent'    => 'LKBA Payments (kubasanitrak)',
            'fakturoid_vat_rate'      => 0,
            'fakturoid_webhook_auth'  => '',
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function get_companies() {
        $companies = get_option(self::OPTION_COMPANIES, array());
        if (!is_array($companies) || $companies === array()) {
            return self::default_companies();
        }
        return $companies;
    }

    /**
     * @param string $company_id
     * @return array<string, mixed>|null
     */
    public static function get_company($company_id) {
        foreach (self::get_companies() as $company) {
            if (!empty($company['id']) && $company['id'] === $company_id) {
                return $company;
            }
        }
        return null;
    }

    /**
     * @param array<int, array<string, mixed>> $companies
     */
    public static function save_companies(array $companies) {
        update_option(self::OPTION_COMPANIES, array_values($companies));
    }
}
