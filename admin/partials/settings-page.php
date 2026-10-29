<?php
/**
 * Companies + email settings stub.
 *
 * @var array  $companies
 * @var string $support_email
 * @var string $sender_name
 * @var string $sender_email
 */

if (!defined('ABSPATH')) {
    exit;
}

settings_errors('lkba_payments');
?>
<div class="wrap">
    <h1><?php echo esc_html__('LKBA Platby', 'lkba-payments'); ?></h1>
    <p><?php echo esc_html__('Phase 0 — company credentials stub. Gateways are not live yet.', 'lkba-payments'); ?></p>

    <form method="post" action="">
        <?php wp_nonce_field('lkba_payments_save_settings', 'lkba_payments_settings_nonce'); ?>

        <h2><?php echo esc_html__('E-mail / podpora', 'lkba-payments'); ?></h2>
        <table class="form-table" role="presentation">
            <tr>
                <th scope="row"><label for="lkba_support_email"><?php echo esc_html__('Support e-mail', 'lkba-payments'); ?></label></th>
                <td>
                    <input type="email" class="regular-text" id="lkba_support_email" name="lkba_support_email" value="<?php echo esc_attr($support_email); ?>" placeholder="<?php echo esc_attr__('TBD — fill when address is known', 'lkba-payments'); ?>">
                    <p class="description"><?php echo esc_html__('Used for cancel/reschedule mailto and From headers once set.', 'lkba-payments'); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="lkba_sender_name"><?php echo esc_html__('Sender name', 'lkba-payments'); ?></label></th>
                <td><input type="text" class="regular-text" id="lkba_sender_name" name="lkba_sender_name" value="<?php echo esc_attr($sender_name); ?>"></td>
            </tr>
            <tr>
                <th scope="row"><label for="lkba_sender_email"><?php echo esc_html__('Sender e-mail', 'lkba-payments'); ?></label></th>
                <td><input type="email" class="regular-text" id="lkba_sender_email" name="lkba_sender_email" value="<?php echo esc_attr($sender_email); ?>"></td>
            </tr>
        </table>

        <?php foreach ($companies as $index => $company) : ?>
            <?php
            $id = isset($company['id']) ? (string) $company['id'] : 'company-' . ($index + 1);
            $prefix = 'lkba_companies[' . (int) $index . ']';
            ?>
            <hr>
            <h2><?php echo esc_html(sprintf(__('Firma %d (%s)', 'lkba-payments'), $index + 1, $id)); ?></h2>
            <input type="hidden" name="<?php echo esc_attr($prefix); ?>[id]" value="<?php echo esc_attr($id); ?>">

            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row"><?php echo esc_html__('Label', 'lkba-payments'); ?></th>
                    <td><input type="text" class="regular-text" name="<?php echo esc_attr($prefix); ?>[label]" value="<?php echo esc_attr($company['label'] ?? ''); ?>"></td>
                </tr>
                <tr>
                    <th scope="row"><?php echo esc_html__('Company name', 'lkba-payments'); ?></th>
                    <td><input type="text" class="regular-text" name="<?php echo esc_attr($prefix); ?>[name]" value="<?php echo esc_attr($company['name'] ?? ''); ?>"></td>
                </tr>
                <tr>
                    <th scope="row"><?php echo esc_html__('IČO / DIČ', 'lkba-payments'); ?></th>
                    <td>
                        <input type="text" class="regular-text" name="<?php echo esc_attr($prefix); ?>[ic]" value="<?php echo esc_attr($company['ic'] ?? ''); ?>" placeholder="IČO">
                        <input type="text" class="regular-text" name="<?php echo esc_attr($prefix); ?>[dic]" value="<?php echo esc_attr($company['dic'] ?? ''); ?>" placeholder="DIČ">
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php echo esc_html__('Address', 'lkba-payments'); ?></th>
                    <td>
                        <input type="text" class="regular-text" name="<?php echo esc_attr($prefix); ?>[street]" value="<?php echo esc_attr($company['street'] ?? ''); ?>" placeholder="<?php echo esc_attr__('Street', 'lkba-payments'); ?>"><br>
                        <input type="text" class="small-text" name="<?php echo esc_attr($prefix); ?>[zip]" value="<?php echo esc_attr($company['zip'] ?? ''); ?>" placeholder="PSČ">
                        <input type="text" class="regular-text" name="<?php echo esc_attr($prefix); ?>[city]" value="<?php echo esc_attr($company['city'] ?? ''); ?>" placeholder="<?php echo esc_attr__('City', 'lkba-payments'); ?>">
                        <input type="text" class="small-text" name="<?php echo esc_attr($prefix); ?>[country]" value="<?php echo esc_attr($company['country'] ?? 'CZ'); ?>">
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php echo esc_html__('Bank', 'lkba-payments'); ?></th>
                    <td>
                        <input type="text" class="regular-text" name="<?php echo esc_attr($prefix); ?>[bank_account_name]" value="<?php echo esc_attr($company['bank_account_name'] ?? ''); ?>" placeholder="<?php echo esc_attr__('Account name', 'lkba-payments'); ?>"><br>
                        <input type="text" class="regular-text" name="<?php echo esc_attr($prefix); ?>[bank_account_number]" value="<?php echo esc_attr($company['bank_account_number'] ?? ''); ?>" placeholder="<?php echo esc_attr__('Account number', 'lkba-payments'); ?>">
                        <input type="text" class="small-text" name="<?php echo esc_attr($prefix); ?>[bank_code]" value="<?php echo esc_attr($company['bank_code'] ?? ''); ?>" placeholder="<?php echo esc_attr__('Bank code', 'lkba-payments'); ?>"><br>
                        <input type="text" class="regular-text" name="<?php echo esc_attr($prefix); ?>[iban]" value="<?php echo esc_attr($company['iban'] ?? ''); ?>" placeholder="IBAN">
                        <input type="text" class="small-text" name="<?php echo esc_attr($prefix); ?>[bic]" value="<?php echo esc_attr($company['bic'] ?? ''); ?>" placeholder="BIC">
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php echo esc_html__('Payment methods', 'lkba-payments'); ?></th>
                    <td>
                        <?php $pm = $company['payment_methods'] ?? 'both'; ?>
                        <select name="<?php echo esc_attr($prefix); ?>[payment_methods]">
                            <option value="both" <?php selected($pm, 'both'); ?>><?php echo esc_html__('Card + bank transfer', 'lkba-payments'); ?></option>
                            <option value="card" <?php selected($pm, 'card'); ?>><?php echo esc_html__('Card only', 'lkba-payments'); ?></option>
                            <option value="bank_transfer" <?php selected($pm, 'bank_transfer'); ?>><?php echo esc_html__('Bank transfer only', 'lkba-payments'); ?></option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php echo esc_html__('Comgate', 'lkba-payments'); ?></th>
                    <td>
                        <label><input type="checkbox" name="<?php echo esc_attr($prefix); ?>[comgate_enabled]" value="1" <?php checked(!empty($company['comgate_enabled'])); ?>> <?php echo esc_html__('Enabled', 'lkba-payments'); ?></label><br>
                        <label><input type="checkbox" name="<?php echo esc_attr($prefix); ?>[comgate_test_mode]" value="1" <?php checked(!empty($company['comgate_test_mode'])); ?>> <?php echo esc_html__('Test mode', 'lkba-payments'); ?></label><br>
                        <input type="text" class="regular-text" name="<?php echo esc_attr($prefix); ?>[comgate_merchant_id]" value="<?php echo esc_attr($company['comgate_merchant_id'] ?? ''); ?>" placeholder="Merchant ID"><br>
                        <input type="text" class="regular-text" name="<?php echo esc_attr($prefix); ?>[comgate_secret_key]" value="<?php echo esc_attr($company['comgate_secret_key'] ?? ''); ?>" placeholder="Secret"><br>
                        <input type="text" class="regular-text" name="<?php echo esc_attr($prefix); ?>[comgate_push_secret]" value="<?php echo esc_attr($company['comgate_push_secret'] ?? ''); ?>" placeholder="Push secret">
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php echo esc_html__('Fakturoid', 'lkba-payments'); ?></th>
                    <td>
                        <label><input type="checkbox" name="<?php echo esc_attr($prefix); ?>[fakturoid_enabled]" value="1" <?php checked(!empty($company['fakturoid_enabled'])); ?>> <?php echo esc_html__('Enabled', 'lkba-payments'); ?></label><br>
                        <input type="text" class="regular-text" name="<?php echo esc_attr($prefix); ?>[fakturoid_slug]" value="<?php echo esc_attr($company['fakturoid_slug'] ?? ''); ?>" placeholder="Account slug"><br>
                        <input type="text" class="regular-text" name="<?php echo esc_attr($prefix); ?>[fakturoid_client_id]" value="<?php echo esc_attr($company['fakturoid_client_id'] ?? ''); ?>" placeholder="Client ID"><br>
                        <input type="text" class="regular-text" name="<?php echo esc_attr($prefix); ?>[fakturoid_client_secret]" value="<?php echo esc_attr($company['fakturoid_client_secret'] ?? ''); ?>" placeholder="Client secret"><br>
                        <input type="text" class="regular-text" name="<?php echo esc_attr($prefix); ?>[fakturoid_user_agent]" value="<?php echo esc_attr($company['fakturoid_user_agent'] ?? ''); ?>" placeholder="User-Agent"><br>
                        <input type="number" class="small-text" name="<?php echo esc_attr($prefix); ?>[fakturoid_vat_rate]" value="<?php echo esc_attr((string) ($company['fakturoid_vat_rate'] ?? 0)); ?>" min="0" max="100"> % VAT<br>
                        <input type="text" class="regular-text" name="<?php echo esc_attr($prefix); ?>[fakturoid_webhook_auth]" value="<?php echo esc_attr($company['fakturoid_webhook_auth'] ?? ''); ?>" placeholder="Webhook Authorization"><br>
                        <p class="description">
                            <?php echo esc_html__('Webhook URL (Phase 1):', 'lkba-payments'); ?>
                            <code><?php echo esc_html(LKBA_Payments_Fakturoid::get_webhook_url($id)); ?></code>
                        </p>
                    </td>
                </tr>
            </table>
        <?php endforeach; ?>

        <?php submit_button(__('Uložit', 'lkba-payments')); ?>
    </form>
</div>
