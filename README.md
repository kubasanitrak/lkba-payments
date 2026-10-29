# lkba-payments

Shared multi-company payments core for Lenka Krejčová Barre Academy (Comgate, Fakturoid, bank transfer / QR).

Required by `lkba-events` and `lkba-vouchers`.

Repository: https://github.com/kubasanitrak/lkba-payments

## Status

Phase 0 scaffold — settings and class shells only. Full gateways arrive in Phase 1.

## Releasing

1. Bump `Version` and `LKBA_PAYMENTS_VERSION` in `lkba-payments.php`.
2. Add a `## [x.y.z]` section to `CHANGELOG.md`.
3. Push tag `v0.1.0` matching the header.

GitHub Actions builds `lkba-payments.zip` and attaches it to the release. Sites update via Plugin Update Checker under `lib/plugin-update-checker/`.
