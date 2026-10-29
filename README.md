# lkba-payments

Shared multi-company payments core for Lenka Krejčová Barre Academy (Comgate, Fakturoid, bank transfer / QR).

Required by `lkba-events` and `lkba-vouchers`.

Repository: https://github.com/kubasanitrak/lkba-payments

## Status

Phase 1 — usable payments APIs for product plugins. Events booking and voucher checkout arrive in Phase 2+.

## Integration (product plugins)

On `lkba_payments_register` (or `plugins_loaded` after payments):

```php
LKBA_Payments_Registry::register_resolver('E', function ($ref_id) { /* return context array */ });
LKBA_Payments_Registry::register_paid_handler('E', function ($context, $transaction_id, $source, $extra) { /* persist paid */ return true; });
```

Facade:

- `LKBA_Payments_Gateway::start_comgate($context)`
- `LKBA_Payments_Gateway::start_bank_transfer($context)` → proforma + QR + instructions HTML

Hooks: `lkba_payments_payment_paid`, `lkba_payments_payment_failed`, `lkba_payments_resolve_ref`, `lkba_payments_resolve_fakturoid`.

## Callbacks / webhooks

| Endpoint | Shape |
|----------|--------|
| Comgate REST | `{home}/wp-json/lkba-payments/v1/comgate-callback` |
| Comgate legacy | `/?lkba_comgate_callback=1` |
| Fakturoid | `/?lkba_fakturoid_webhook=1&company=company-1` (Authorization header = company webhook auth) |

## Releasing

1. Bump `Version` and `LKBA_PAYMENTS_VERSION` in `lkba-payments.php`.
2. Add a `## [x.y.z]` section to `CHANGELOG.md`.
3. Push tag `v0.2.0` matching the header.
