<?php
/**
 * Payment ref registry — product plugins register E- / V- resolvers and paid handlers.
 */

if (!defined('ABSPATH')) {
    exit;
}

class LKBA_Payments_Registry {

    /** @var array<string, callable> prefix => resolver(ref_id): ?array */
    private static $resolvers = array();

    /** @var array<string, callable> prefix => handler(context, transaction_id, source, extra): bool */
    private static $paid_handlers = array();

    /** @var array<string, callable> */
    private static $failed_handlers = array();

    /**
     * Register a resolver for order refs with a given prefix (e.g. "E", "V").
     * Resolver receives full ref_id (e.g. "E-1001") and must return a payment context array or null.
     *
     * @param string   $prefix  Letter prefix without trailing dash.
     * @param callable $callback
     */
    public static function register_resolver($prefix, $callback) {
        $prefix = strtoupper(preg_replace('/[^A-Za-z]/', '', (string) $prefix));
        if ($prefix === '' || !is_callable($callback)) {
            return;
        }
        self::$resolvers[$prefix] = $callback;
    }

    /**
     * @param string   $prefix
     * @param callable $callback
     */
    public static function register_paid_handler($prefix, $callback) {
        $prefix = strtoupper(preg_replace('/[^A-Za-z]/', '', (string) $prefix));
        if ($prefix === '' || !is_callable($callback)) {
            return;
        }
        self::$paid_handlers[$prefix] = $callback;
    }

    /**
     * @param string   $prefix
     * @param callable $callback
     */
    public static function register_failed_handler($prefix, $callback) {
        $prefix = strtoupper(preg_replace('/[^A-Za-z]/', '', (string) $prefix));
        if ($prefix === '' || !is_callable($callback)) {
            return;
        }
        self::$failed_handlers[$prefix] = $callback;
    }

    /**
     * @param string $ref_id
     * @return string Prefix or empty.
     */
    public static function prefix_from_ref($ref_id) {
        if (!preg_match('/^([A-Za-z]+)-/', (string) $ref_id, $m)) {
            return '';
        }
        return strtoupper($m[1]);
    }

    /**
     * Resolve a payment context by Comgate/Fakturoid ref / custom_id.
     *
     * @param string $ref_id
     * @return array<string, mixed>|null
     */
    public static function resolve($ref_id) {
        $ref_id = (string) $ref_id;
        if ($ref_id === '') {
            return null;
        }

        $prefix = self::prefix_from_ref($ref_id);
        if ($prefix !== '' && isset(self::$resolvers[$prefix])) {
            $context = call_user_func(self::$resolvers[$prefix], $ref_id);
            if (is_array($context)) {
                return LKBA_Payments_Context::normalize($context);
            }
        }

        /**
         * Fallback filter for product plugins.
         *
         * @param array|null $context
         * @param string     $ref_id
         * @param string     $prefix
         */
        $context = apply_filters('lkba_payments_resolve_ref', null, $ref_id, $prefix);
        return is_array($context) ? LKBA_Payments_Context::normalize($context) : null;
    }

    /**
     * Find context by Fakturoid invoice fields (products fill via filter).
     *
     * @param array $invoice
     * @param array $payment
     * @return array<string, mixed>|null
     */
    public static function resolve_fakturoid(array $invoice, array $payment = array()) {
        $ref = '';
        if (!empty($invoice['custom_id'])) {
            $ref = (string) $invoice['custom_id'];
        }
        if ($ref !== '') {
            $context = self::resolve($ref);
            if ($context) {
                return $context;
            }
        }

        /**
         * @param array|null $context
         * @param array      $invoice
         * @param array      $payment
         */
        $context = apply_filters('lkba_payments_resolve_fakturoid', null, $invoice, $payment);
        return is_array($context) ? LKBA_Payments_Context::normalize($context) : null;
    }

    /**
     * Mark payment paid — product handler + global action.
     *
     * @param array  $context
     * @param string $transaction_id
     * @param string $source comgate|fakturoid|manual
     * @param array  $extra
     * @return bool
     */
    public static function mark_paid(array $context, $transaction_id, $source = 'unknown', array $extra = array()) {
        $context = LKBA_Payments_Context::normalize($context);
        $ref_id  = $context['ref_id'];
        $prefix  = self::prefix_from_ref($ref_id);
        $ok      = true;

        if ($prefix !== '' && isset(self::$paid_handlers[$prefix])) {
            $ok = (bool) call_user_func(self::$paid_handlers[$prefix], $context, $transaction_id, $source, $extra);
        }

        /**
         * Fired when a payment is confirmed (Comgate, Fakturoid webhook, or manual).
         *
         * @param array  $context
         * @param string $transaction_id
         * @param string $source
         * @param array  $extra
         */
        do_action('lkba_payments_payment_paid', $context, $transaction_id, $source, $extra);

        LKBA_Payments_Logger::log('payment_paid', 'Payment marked paid', array(
            'ref_id'         => $ref_id,
            'transaction_id' => $transaction_id,
            'source'         => $source,
            'handler_ok'     => $ok,
        ), $context['company_id'] ?? null);

        return $ok;
    }

    /**
     * @param array  $context
     * @param string $reason
     * @param string $source
     * @return bool
     */
    public static function mark_failed(array $context, $reason = '', $source = 'unknown') {
        $context = LKBA_Payments_Context::normalize($context);
        $ref_id  = $context['ref_id'];
        $prefix  = self::prefix_from_ref($ref_id);
        $ok      = true;

        if ($prefix !== '' && isset(self::$failed_handlers[$prefix])) {
            $ok = (bool) call_user_func(self::$failed_handlers[$prefix], $context, $reason, $source);
        }

        do_action('lkba_payments_payment_failed', $context, $reason, $source);

        LKBA_Payments_Logger::log('payment_failed', 'Payment marked failed: ' . $reason, array(
            'ref_id' => $ref_id,
            'source' => $source,
        ), $context['company_id'] ?? null);

        return $ok;
    }
}
