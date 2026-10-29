<?php
/**
 * Plugin deactivation.
 */

if (!defined('ABSPATH')) {
    exit;
}

class LKBA_Payments_Deactivator {

    public static function deactivate() {
        // Tables and options intentionally retained.
    }
}
