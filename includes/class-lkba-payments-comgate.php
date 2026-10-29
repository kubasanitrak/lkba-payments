<?php
/**
 * Comgate gateway — Phase 0 shell (implementation in Phase 1).
 */

if (!defined('ABSPATH')) {
    exit;
}

class LKBA_Payments_Comgate {

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
        return !empty($this->company['comgate_enabled'])
            && !empty($this->company['comgate_merchant_id'])
            && !empty($this->company['comgate_secret_key']);
    }

    /**
     * Phase 1: create payment and return redirect URL.
     *
     * @param array<string, mixed> $context
     * @return array{error?:string,redirect?:string}
     */
    public function create_payment(array $context) {
        return array(
            'error' => __('Comgate is not implemented yet (Phase 1).', 'lkba-payments'),
        );
    }
}
