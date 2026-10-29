<?php
/**
 * Database schema (payment logs).
 */

if (!defined('ABSPATH')) {
    exit;
}

class LKBA_Payments_DB {

    public static function maybe_upgrade() {
        $installed = get_option('lkba_payments_db_version', '');
        if (version_compare((string) $installed, LKBA_PAYMENTS_VERSION, '>=')) {
            return;
        }
        self::create_tables();
        update_option('lkba_payments_db_version', LKBA_PAYMENTS_VERSION);
    }

    public static function create_tables() {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset = $wpdb->get_charset_collate();
        $logs    = $wpdb->prefix . 'lkba_payment_logs';

        dbDelta("CREATE TABLE $logs (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            type varchar(50) NOT NULL,
            message text NOT NULL,
            data longtext,
            company_id varchar(64) DEFAULT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY type (type),
            KEY company_id (company_id)
        ) $charset;");
    }

    /**
     * @param string $table_suffix Without prefix, e.g. lkba_payment_logs.
     */
    public static function table_exists($table_suffix) {
        global $wpdb;
        $table = $wpdb->prefix . $table_suffix;
        return $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) === $table;
    }
}
