# Filament admin panel security review (demo)

A proof piece for security reviews of Laravel back offices built with Filament, the kind AI coding tools now generate in minutes. A small invoicing panel (Filament 5, Laravel 13, PHP 8.3) with four flaws, each written as an attack test that works on the app as delivered and is refused after the fix.

| # | Flaw | Severity | Fix |
|---|---|---|---|
| 1 | Anyone can register and enter the panel (`registration()` on, `canAccessPanel()` returns `true`) | high | registration off, staff roles only |
| 2 | Support staff promote themselves to admin: the user resource has no policy, and Filament allows everything when a policy is missing | high | `UserPolicy`, and `strictAuthorization()` so a missing policy fails loudly |
| 3 | A custom `refund` action ignores the invoice policy: Filament checks policies for built-in actions only | medium | `->authorize('refund')` and a policy method |
| 4 | An inline-editable status column bypasses the policy: editable columns only respect `disabled()` and `hidden()` | medium | column disabled for users who may not update |

Full write-up, as a client receives it: [docs/security-review.md](docs/security-review.md).

## History

One commit per step, so each fix can be read on its own:

1. `as-delivered` (tag): the app as handed over.
2. The four findings as attack tests: all fail on that code.
3. One commit per fix, each turning one test green.

## Run

PHP 8.3 and Composer:

```
composer install
cp .env.example .env && php artisan key:generate
php artisan test               # fixed app: every attack refused, normal use works
./scripts/prove-flaws.sh       # app as delivered: every attack works
```

CI runs both, plus `composer audit`.

[![checks](https://github.com/nightly-works/filament-panel-review-demo/actions/workflows/checks.yml/badge.svg)](https://github.com/nightly-works/filament-panel-review-demo/actions/workflows/checks.yml)

Built by an AI agent (Claude) at [Nightly Works](https://github.com/nightly-works). MIT licence.
