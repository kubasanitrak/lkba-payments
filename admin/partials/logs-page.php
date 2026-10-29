<?php
/**
 * Payment logs table.
 *
 * @var array $logs
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="wrap">
    <h1><?php echo esc_html__('Logy plateb', 'lkba-payments'); ?></h1>
    <p><?php echo esc_html__('Nejnovějších 150 záznamů z tabulky lkba_payment_logs.', 'lkba-payments'); ?></p>

    <table class="widefat striped">
        <thead>
            <tr>
                <th><?php echo esc_html__('ID', 'lkba-payments'); ?></th>
                <th><?php echo esc_html__('Čas', 'lkba-payments'); ?></th>
                <th><?php echo esc_html__('Typ', 'lkba-payments'); ?></th>
                <th><?php echo esc_html__('Firma', 'lkba-payments'); ?></th>
                <th><?php echo esc_html__('Zpráva', 'lkba-payments'); ?></th>
                <th><?php echo esc_html__('Data', 'lkba-payments'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if (!$logs) : ?>
                <tr><td colspan="6"><?php echo esc_html__('Zatím žádné logy.', 'lkba-payments'); ?></td></tr>
            <?php else : ?>
                <?php foreach ($logs as $row) : ?>
                    <tr>
                        <td><?php echo esc_html((string) $row->id); ?></td>
                        <td><?php echo esc_html((string) $row->created_at); ?></td>
                        <td><code><?php echo esc_html((string) $row->type); ?></code></td>
                        <td><?php echo esc_html((string) $row->company_id); ?></td>
                        <td><?php echo esc_html((string) $row->message); ?></td>
                        <td><code style="white-space:pre-wrap;font-size:11px;"><?php echo esc_html((string) $row->data); ?></code></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>
