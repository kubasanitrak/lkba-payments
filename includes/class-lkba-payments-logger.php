<?php
/**
 * Payment log writer.
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
}
