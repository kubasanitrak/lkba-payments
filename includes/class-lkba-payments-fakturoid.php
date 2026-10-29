<?php
/**
 * Fakturoid client — Phase 0 shell (implementation in Phase 1).
 */

if (!defined('ABSPATH')) {
    exit;
}

class LKBA_Payments_Fakturoid {

    /** @var array<string, mixed>|null */
    private $company;

    /**
     * @param string $company_id
     */
    public function __construct($company_id = '') {
        $this->company = $company_id !== ''
            ? LKBA_Payments_Settings::get_company($company_id)
            : null;
    }

    /**
     * @param string $company_id
     * @return self
     */
    public static function for_company($company_id) {
        return new self($company_id);
    }

    /**
     * @return bool
     */
    public function is_configured() {
        if (!$this->company) {
            return false;
        }
        return !empty($this->company['fakturoid_enabled'])
            && !empty($this->company['fakturoid_slug'])
            && !empty($this->company['fakturoid_client_id'])
            && !empty($this->company['fakturoid_client_secret']);
    }

    /**
     * Suggested webhook URL for this company (Phase 1 will handle it).
     *
     * @param string $company_id
     * @return string
     */
    public static function get_webhook_url($company_id) {
        return add_query_arg(
            array(
                'lkba_fakturoid_webhook' => '1',
                'company'                => $company_id,
            ),
            home_url('/')
        );
    }

    /**
     * Phase 1: create bank-transfer proforma.
     *
     * @param array<string, mixed> $context
     * @return array|WP_Error
     */
    public function create_proforma(array $context) {
        return new WP_Error(
            'lkba_fakturoid_stub',
            __('Fakturoid is not implemented yet (Phase 1).', 'lkba-payments')
        );
    }
}
