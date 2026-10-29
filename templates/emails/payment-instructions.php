<?php
/**
 * Payment instructions block.
 *
 * @var array  $context
 * @var array  $bank
 * @var string $vs
 * @var string $amount_fmt
 * @var string $qr_html
 */

if (!defined('ABSPATH')) {
    exit;
}

$method = $context['payment_method'] ?? '';
?>
<div class="lkba-payment-instructions" style="margin:20px 0;padding:16px;background:#f7f4ec;border:1px solid #eee7d6;">
    <h2 style="margin:0 0 12px;font-size:18px;color:#00573f;font-weight:normal;">
        <?php echo esc_html__('Platební údaje', 'lkba-payments'); ?>
    </h2>

    <p style="margin:0 0 8px;">
        <strong><?php echo esc_html__('Částka:', 'lkba-payments'); ?></strong>
        <?php echo esc_html($amount_fmt); ?>
    </p>
    <p style="margin:0 0 8px;">
        <strong><?php echo esc_html__('Reference:', 'lkba-payments'); ?></strong>
        <?php echo esc_html($context['ref_id']); ?>
    </p>

    <?php if ($method === 'comgate' || $method === 'card') : ?>
        <p style="margin:12px 0 0;">
            <?php echo esc_html__('Platba kartou — dokončete ji na platební bráně Comgate, pokud jste tak ještě neučinili.', 'lkba-payments'); ?>
        </p>
    <?php else : ?>
        <?php if ($bank['account_name'] !== '') : ?>
            <p style="margin:0 0 8px;"><strong><?php echo esc_html__('Název účtu:', 'lkba-payments'); ?></strong> <?php echo esc_html($bank['account_name']); ?></p>
        <?php endif; ?>
        <?php if ($bank['account_number'] !== '') : ?>
            <p style="margin:0 0 8px;">
                <strong><?php echo esc_html__('Číslo účtu:', 'lkba-payments'); ?></strong>
                <?php echo esc_html($bank['account_number']); ?>
                <?php if ($bank['bank_code'] !== '') : ?>
                    / <?php echo esc_html($bank['bank_code']); ?>
                <?php endif; ?>
            </p>
        <?php endif; ?>
        <?php if ($bank['iban'] !== '') : ?>
            <p style="margin:0 0 8px;"><strong>IBAN:</strong> <?php echo esc_html($bank['iban']); ?></p>
        <?php endif; ?>
        <?php if ($vs !== '') : ?>
            <p style="margin:0 0 8px;"><strong><?php echo esc_html__('Variabilní symbol:', 'lkba-payments'); ?></strong> <?php echo esc_html($vs); ?></p>
        <?php endif; ?>
        <?php if ($qr_html !== '') : ?>
            <div style="margin-top:16px;"><?php echo $qr_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
        <?php endif; ?>
    <?php endif; ?>
</div>
