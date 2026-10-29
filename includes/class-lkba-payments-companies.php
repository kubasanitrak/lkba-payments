<?php
/**
 * Company helpers (Phase 0 stub).
 */

if (!defined('ABSPATH')) {
    exit;
}

class LKBA_Payments_Companies {

    /**
     * @return array<int, array{id:string,label:string}>
     */
    public static function get_choices() {
        $choices = array();
        foreach (LKBA_Payments_Settings::get_companies() as $company) {
            $id    = isset($company['id']) ? (string) $company['id'] : '';
            $label = isset($company['label']) ? (string) $company['label'] : $id;
            if ($id === '') {
                continue;
            }
            $name = !empty($company['name']) ? (string) $company['name'] : $label;
            $choices[] = array(
                'id'    => $id,
                'label' => $name !== '' ? $name : $label,
            );
        }
        return $choices;
    }
}
