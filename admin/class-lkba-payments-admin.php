<?php
/**
 * Admin menu and companies settings stub.
 */

if (!defined('ABSPATH')) {
    exit;
}

class LKBA_Payments_Admin {

    const MENU_SLUG = 'lkba-payments';

    public function __construct() {
        add_action('admin_menu', array($this, 'register_menu'));
        add_action('admin_init', array($this, 'handle_save'));
    }

    public function register_menu() {
        add_menu_page(
            __('LKBA Platby', 'lkba-payments'),
            __('LKBA Platby', 'lkba-payments'),
            'manage_options',
            self::MENU_SLUG,
            array($this, 'render_settings_page'),
            'dashicons-money-alt',
            58
        );
    }

    public function handle_save() {
        if (!isset($_POST['lkba_payments_settings_nonce'])) {
            return;
        }
        if (!current_user_can('manage_options')) {
            return;
        }
        if (!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['lkba_payments_settings_nonce'])), 'lkba_payments_save_settings')) {
            return;
        }

        $posted = isset($_POST['lkba_companies']) && is_array($_POST['lkba_companies'])
            ? wp_unslash($_POST['lkba_companies'])
            : array();

        $companies = array();
        $defaults  = LKBA_Payments_Settings::default_companies();

        foreach ($defaults as $index => $default) {
            $row = isset($posted[$index]) && is_array($posted[$index]) ? $posted[$index] : array();
            $company = $default;

            $company['label'] = isset($row['label']) ? sanitize_text_field($row['label']) : $default['label'];
            $company['name']  = isset($row['name']) ? sanitize_text_field($row['name']) : '';
            $company['street'] = isset($row['street']) ? sanitize_text_field($row['street']) : '';
            $company['city']   = isset($row['city']) ? sanitize_text_field($row['city']) : '';
            $company['zip']    = isset($row['zip']) ? sanitize_text_field($row['zip']) : '';
            $company['country'] = isset($row['country']) ? sanitize_text_field($row['country']) : 'CZ';
            $company['ic']     = isset($row['ic']) ? sanitize_text_field($row['ic']) : '';
            $company['dic']    = isset($row['dic']) ? sanitize_text_field($row['dic']) : '';

            $company['bank_account_name']   = isset($row['bank_account_name']) ? sanitize_text_field($row['bank_account_name']) : '';
            $company['bank_account_number'] = isset($row['bank_account_number']) ? sanitize_text_field($row['bank_account_number']) : '';
            $company['bank_code']           = isset($row['bank_code']) ? sanitize_text_field($row['bank_code']) : '';
            $company['iban']                = isset($row['iban']) ? sanitize_text_field($row['iban']) : '';
            $company['bic']                 = isset($row['bic']) ? sanitize_text_field($row['bic']) : '';

            $methods = isset($row['payment_methods']) ? sanitize_key($row['payment_methods']) : 'both';
            $company['payment_methods'] = in_array($methods, array('both', 'card', 'bank_transfer'), true) ? $methods : 'both';

            $company['comgate_enabled']     = !empty($row['comgate_enabled']) ? 1 : 0;
            $company['comgate_merchant_id'] = isset($row['comgate_merchant_id']) ? sanitize_text_field($row['comgate_merchant_id']) : '';
            $company['comgate_secret_key']  = isset($row['comgate_secret_key']) ? sanitize_text_field($row['comgate_secret_key']) : '';
            $company['comgate_push_secret'] = isset($row['comgate_push_secret']) ? sanitize_text_field($row['comgate_push_secret']) : '';
            $company['comgate_test_mode']   = !empty($row['comgate_test_mode']) ? 1 : 0;

            $company['fakturoid_enabled']       = !empty($row['fakturoid_enabled']) ? 1 : 0;
            $company['fakturoid_slug']          = isset($row['fakturoid_slug']) ? sanitize_text_field($row['fakturoid_slug']) : '';
            $company['fakturoid_client_id']     = isset($row['fakturoid_client_id']) ? sanitize_text_field($row['fakturoid_client_id']) : '';
            $company['fakturoid_client_secret'] = isset($row['fakturoid_client_secret']) ? sanitize_text_field($row['fakturoid_client_secret']) : '';
            $company['fakturoid_user_agent']    = isset($row['fakturoid_user_agent']) ? sanitize_text_field($row['fakturoid_user_agent']) : $default['fakturoid_user_agent'];
            $company['fakturoid_vat_rate']      = isset($row['fakturoid_vat_rate']) ? (int) $row['fakturoid_vat_rate'] : 0;
            $company['fakturoid_webhook_auth']  = isset($row['fakturoid_webhook_auth']) ? sanitize_text_field($row['fakturoid_webhook_auth']) : '';

            $companies[] = $company;
        }

        LKBA_Payments_Settings::save_companies($companies);

        update_option(
            LKBA_Payments_Settings::OPTION_SUPPORT_EMAIL,
            isset($_POST['lkba_support_email']) ? sanitize_email(wp_unslash($_POST['lkba_support_email'])) : ''
        );
        update_option(
            LKBA_Payments_Settings::OPTION_SENDER_NAME,
            isset($_POST['lkba_sender_name']) ? sanitize_text_field(wp_unslash($_POST['lkba_sender_name'])) : ''
        );
        update_option(
            LKBA_Payments_Settings::OPTION_SENDER_EMAIL,
            isset($_POST['lkba_sender_email']) ? sanitize_email(wp_unslash($_POST['lkba_sender_email'])) : ''
        );

        add_settings_error(
            'lkba_payments',
            'lkba_payments_saved',
            __('Nastavení uloženo (Phase 0 stub).', 'lkba-payments'),
            'updated'
        );
    }

    public function render_settings_page() {
        if (!current_user_can('manage_options')) {
            return;
        }

        $companies     = LKBA_Payments_Settings::get_companies();
        $support_email = get_option(LKBA_Payments_Settings::OPTION_SUPPORT_EMAIL, '');
        $sender_name   = get_option(LKBA_Payments_Settings::OPTION_SENDER_NAME, '');
        $sender_email  = get_option(LKBA_Payments_Settings::OPTION_SENDER_EMAIL, '');

        include LKBA_PAYMENTS_PLUGIN_DIR . 'admin/partials/settings-page.php';
    }
}
