<?php
/**
 * Email chrome wrapper.
 *
 * @var string $brand
 * @var string $title
 * @var string $inner_html
 * @var string $logo_url
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo esc_html($title); ?></title>
</head>
<body style="margin:0;padding:0;background:#f4f1ea;font-family:Georgia,'Times New Roman',serif;color:#1a1a1a;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f4f1ea;padding:24px 12px;">
    <tr>
        <td align="center">
            <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;background:#ffffff;border-radius:4px;overflow:hidden;">
                <tr>
                    <td style="background:#00573f;padding:20px 28px;color:#eee7d6;">
                        <?php if ($logo_url !== '') : ?>
                            <img src="<?php echo esc_url($logo_url); ?>" alt="<?php echo esc_attr($brand); ?>" style="max-height:48px;width:auto;display:block;margin-bottom:8px;">
                        <?php endif; ?>
                        <div style="font-size:18px;letter-spacing:0.02em;"><?php echo esc_html($brand); ?></div>
                    </td>
                </tr>
                <tr>
                    <td style="padding:28px;">
                        <?php if ($title !== '') : ?>
                            <h1 style="margin:0 0 16px;font-size:22px;line-height:1.3;color:#00573f;font-weight:normal;"><?php echo esc_html($title); ?></h1>
                        <?php endif; ?>
                        <div style="font-size:15px;line-height:1.55;color:#222;">
                            <?php echo $inner_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped — composed by trusted callers ?>
                        </div>
                    </td>
                </tr>
                <tr>
                    <td style="padding:16px 28px 24px;font-size:12px;color:#6b6b6b;border-top:1px solid #eee7d6;">
                        <?php echo esc_html($brand); ?>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>
