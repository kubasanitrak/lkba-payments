<?php
/**
 * Plugin activation.
 */

if (!defined('ABSPATH')) {
    exit;
}

class LKBA_Payments_Activator {

    public static function activate() {
        require_once LKBA_PAYMENTS_PLUGIN_DIR . 'includes/class-lkba-payments-settings.php';
        require_once LKBA_PAYMENTS_PLUGIN_DIR . 'includes/class-lkba-payments-db.php';

        LKBA_Payments_Settings::ensure_defaults();
        LKBA_Payments_DB::create_tables();

        update_option('lkba_payments_db_version', LKBA_PAYMENTS_VERSION);
        update_option('lkba_payments_plugin_activated', true);
    }
}
