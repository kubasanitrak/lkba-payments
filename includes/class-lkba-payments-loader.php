<?php
/**
 * Plugin loader.
 */

if (!defined('ABSPATH')) {
    exit;
}

class LKBA_Payments_Loader {

    public function __construct() {
        $this->load_dependencies();
    }

    private function load_dependencies() {
        require_once LKBA_PAYMENTS_PLUGIN_DIR . 'includes/class-lkba-payments-activator.php';
        require_once LKBA_PAYMENTS_PLUGIN_DIR . 'includes/class-lkba-payments-db.php';
        require_once LKBA_PAYMENTS_PLUGIN_DIR . 'includes/class-lkba-payments-settings.php';
        require_once LKBA_PAYMENTS_PLUGIN_DIR . 'includes/class-lkba-payments-companies.php';
        require_once LKBA_PAYMENTS_PLUGIN_DIR . 'includes/class-lkba-payments-logger.php';
        require_once LKBA_PAYMENTS_PLUGIN_DIR . 'includes/class-lkba-payments-context.php';
        require_once LKBA_PAYMENTS_PLUGIN_DIR . 'includes/class-lkba-payments-registry.php';
        require_once LKBA_PAYMENTS_PLUGIN_DIR . 'includes/class-lkba-payments-comgate.php';
        require_once LKBA_PAYMENTS_PLUGIN_DIR . 'includes/class-lkba-payments-fakturoid.php';
        require_once LKBA_PAYMENTS_PLUGIN_DIR . 'includes/class-lkba-payments-qr.php';
        require_once LKBA_PAYMENTS_PLUGIN_DIR . 'includes/class-lkba-payments-emails.php';
        require_once LKBA_PAYMENTS_PLUGIN_DIR . 'includes/class-lkba-payments-gateway.php';

        if (is_admin()) {
            require_once LKBA_PAYMENTS_PLUGIN_DIR . 'admin/class-lkba-payments-admin.php';
        }
    }

    public function run() {
        LKBA_Payments_Settings::ensure_defaults();
        LKBA_Payments_DB::maybe_upgrade();

        new LKBA_Payments_Settings();

        LKBA_Payments_Comgate::boot();
        LKBA_Payments_Fakturoid::boot();

        /**
         * Product plugins (events / vouchers) should register resolvers on this action.
         *
         * Example:
         * LKBA_Payments_Registry::register_resolver('E', ...);
         * LKBA_Payments_Registry::register_paid_handler('E', ...);
         */
        do_action('lkba_payments_register');

        if (is_admin()) {
            new LKBA_Payments_Admin();
        }
    }
}
