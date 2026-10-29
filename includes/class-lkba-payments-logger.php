<?php
/**
 * Payment log writer + reader.
 */

if (!defined('ABSPATH')) {
    exit;
}

class LKBA_Payments_Logger {

    /**
     * @param string               $type
     * @param string               $message
     * @param array<string, mixed> $data
     * @param string|null          $company_id
     */
    public static function log($type, $message, array $data = array(), $company_id = null) {
        global $wpdb;

        if (!LKBA_Payments_DB::table_exists('lkba_payment_logs')) {
            return;
        }

        $wpdb->insert(
            $wpdb->prefix . 'lkba_payment_logs',
            array(
                'type'       => sanitize_key($type),
                'message'    => $message,
                'data'       => $data ? wp_json_encode($data) : null,
                'company_id' => $company_id ? sanitize_text_field($company_id) : null,
                'created_at' => current_time('mysql'),
            ),
            array('%s', '%s', '%s', '%s', '%s')
        );
    }

    /**
     * @param int         $limit
     * @param string|null $company_id
     * @param string|null $type
     * @return array<int, object>
     */
    public static function get_logs($limit = 100, $company_id = null, $type = null) {
        global $wpdb;

        if (!LKBA_Payments_DB::table_exists('lkba_payment_logs')) {
            return array();
        }

        $table = $wpdb->prefix . 'lkba_payment_logs';
        $where = array('1=1');
        $args  = array();

        if ($company_id) {
            $where[] = 'company_id = %s';
            $args[]  = $company_id;
        }
        if ($type) {
            $where[] = 'type = %s';
            $args[]  = sanitize_key($type);
        }

        $sql = "SELECT * FROM $table WHERE " . implode(' AND ', $where) . ' ORDER BY id DESC LIMIT %d';
        $args[] = max(1, (int) $limit);

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        return $wpdb->get_results($wpdb->prepare($sql, $args));
    }
}
