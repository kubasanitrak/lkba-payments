<?php
/**
 * Plugin Name: LKBA Payments
 * Plugin URI: https://github.com/kubasanitrak/lkba-payments
 * Description: Shared multi-company Comgate, Fakturoid, bank transfer and payment helpers for LKBA Events and Vouchers.
 * Version: 0.1.0
 * Author: kubasanitrak
 * Author URI: https://github.com/kubasanitrak
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: lkba-payments
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 7.4
 */

if (!defined('ABSPATH')) {
    exit;
}

define('LKBA_PAYMENTS_VERSION', '0.1.0');
define('LKBA_PAYMENTS_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('LKBA_PAYMENTS_PLUGIN_URL', plugin_dir_url(__FILE__));
define('LKBA_PAYMENTS_PLUGIN_BASENAME', plugin_basename(__FILE__));

/** Expected number of child companies (day-one). */
define('LKBA_PAYMENTS_COMPANY_SLOTS', 2);

$lkba_payments_autoload = LKBA_PAYMENTS_PLUGIN_DIR . 'vendor/autoload.php';
if (is_readable($lkba_payments_autoload)) {
    require_once $lkba_payments_autoload;
}

require_once LKBA_PAYMENTS_PLUGIN_DIR . 'lib/plugin-update-checker/plugin-update-checker.php';

use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

$lkba_payments_update_checker = PucFactory::buildUpdateChecker(
    'https://github.com/kubasanitrak/lkba-payments/',
    __FILE__,
    'lkba-payments'
);
$lkba_payments_update_checker->getVcsApi()->enableReleaseAssets('/lkba-payments\.zip($|[?&#])/i');

function lkba_payments_activate() {
    require_once LKBA_PAYMENTS_PLUGIN_DIR . 'includes/class-lkba-payments-activator.php';
    LKBA_Payments_Activator::activate();
}
register_activation_hook(__FILE__, 'lkba_payments_activate');

function lkba_payments_deactivate() {
    require_once LKBA_PAYMENTS_PLUGIN_DIR . 'includes/class-lkba-payments-deactivator.php';
    LKBA_Payments_Deactivator::deactivate();
}
register_deactivation_hook(__FILE__, 'lkba_payments_deactivate');

require_once LKBA_PAYMENTS_PLUGIN_DIR . 'includes/class-lkba-payments-loader.php';

function lkba_payments_init() {
    $loader = new LKBA_Payments_Loader();
    $loader->run();
}
add_action('plugins_loaded', 'lkba_payments_init');

function lkba_payments_load_textdomain() {
    load_plugin_textdomain(
        'lkba-payments',
        false,
        dirname(LKBA_PAYMENTS_PLUGIN_BASENAME) . '/languages'
    );
}
add_action('init', 'lkba_payments_load_textdomain');
