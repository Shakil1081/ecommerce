# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Source-of-truth documents

- **AGENTS.md**: the full domain specification. It covers the Bangladesh hybrid import and local trading model, the anti-fraud scoring tables, the NBR Mushak VAT forms, payment and courier gateways, RBAC permission names and SEO rules. Read the relevant section before working on a domain feature.
- **PHASE_PLAN.md**: the roadmap for the 10 phases, the files each phase creates, and the dependency order between phases.
- **TASK_BOARD.md**: the sprint ticket board. When you finish a ticket, change `[ ]` to `[x]`. Branches are named `feat/p<phase>-t<ticket>-<slug>`.
- **CHANGELOG.md**: the release log. The in-app System Info / release viewer reads it.

## Actual stack vs. documentation

AGENTS.md and README.md say "Laravel 13" and "Pest". The installed stack is different:
- **Laravel 11** (`laravel/framework ^11.0`), PHP ^8.2, with Inertia Laravel adapter v3 and `@inertiajs/vue3` v2.
- Tests use **PHPUnit 10**, not Pest. Write test classes that extend `Tests\TestCase`.
- Base all code on the installed versions.

## Commands

```bash
# Backend
php artisan serve
php artisan migrate                         # migrations live in database/migrations (not per-module)
php artisan test                            # full suite
php artisan test --filter=CartDrawerTest    # single test class (or method name)
php artisan test tests/Feature/BillOfEntryTest.php
./vendor/bin/pint                           # format (pint --test to check only)
php artisan media:migrate-disk --from=local --to=s3

# Frontend
npm run dev
npm run build && npm run build:ssr          # client + SSR bundle (resources/js/ssr.ts)
npx vue-tsc --noEmit                        # type-check
```

**Test environment**: phpunit.xml points tests at **MySQL**, using the `scommerce_testing` database (`DB_CONNECTION=mysql`). That database must exist locally before you run tests. In tests, the queue runs `sync` and the cache uses `array`. Feature tests live in `tests/Feature/`, with one file per feature or ticket. The TASK_BOARD "Verification Command" column gives the `--filter` for each ticket.

`.pint.json` enforces `declare_strict_types`, alphabetical imports, no unused imports and single quotes. Every PHP file must start with `declare(strict_types=1);`.

## Architecture

### Modular monolith (`app/Modules/<Module>/`)
`App\Modules\` is PSR-4 mapped to `app/Modules/`. A module contains some or all of these folders: `Actions/` (single-purpose classes with `__invoke`), `Services/`, `Contracts/`, `Adapters/`, `DTOs/` (readonly), `Models/`, `Http/{Controllers,Requests}`, `Events/`, `Providers/`. `Common` holds shared infrastructure: the media/storage engine, security, traits and the `EnsureHumanSubmission` middleware.

Some conventions are not obvious from the folder layout:
- **All routes are in `routes/web.php`**. Modules do not register their own routes. Public storefront, cart, checkout, payment callbacks, courier/voice/WhatsApp webhooks, feeds and the whole `/admin` group are all defined there.
- **Admin back-office controllers are all in `app/Modules/Admin/Http/Controllers/`**. This includes catalog, procurement, payment, courier, tax and AI admin screens. They call into the Actions and Services of the owning domain module.
- **Migrations, seeders and factories live in the top-level `database/`**. Module `Database/` folders are not used. Migration filenames are grouped by numeric prefix: `2xxxxx` catalog/inventory, `3xxxxx` procurement, `4xxxxx` cart/order, `5xxxxx` fraud, `6xxxxx` payment, `7xxxxx` logistics, `8xxxxx` communication, `9xxxxx` Mushak tax.
- **Module service providers are registered in `bootstrap/providers.php`**. Some modules (Catalog, Order, Customer, Seo, Admin, Pricing) have no provider. A new provider must be added to that file.

### Driver / manager pattern for admin-configurable integrations
Payment gateways, couriers, SMS, voice calling, AI providers, mail and media storage are all configured at runtime from database settings tables, not from `.env`. Credentials are stored with `encrypted:array` casts. The settings tables include `payment_gateway_settings`, `courier_gateway_settings` and `ai_model_settings`. Each integration uses a singleton manager that is bound in its module provider and resolves the active driver from the database:
- `PaymentGatewayManager` (Payment)
- `CourierGatewayManager` (Logistics)
- `SmsGatewayManager` and `VoiceCallingManager` (Communication)
- `AiManager` (AI)
- `ConfigureDynamicMailerAction` (Communication)

Drivers implement interfaces in the module's `Contracts/` folder and live under `Adapters/`. Every integration has a pre-flight `Test*ConnectionAction`. To add a new provider, write an adapter and register it with the manager. Do not hard-code calls to the provider.

`CommonServiceProvider::bootDynamicMediaDisk()` rewrites the `media` filesystem disk at boot from `media_storage_settings`. If no setting is active, it falls back to local `storage/app/public/media`. Spatie MediaLibrary writes to this `media` disk.

### Order pipeline
`CreateOrderFromCartAction` creates orders. `CalculateOrderFraudRiskScoreAction` scores them, using `CheckOrderVelocityAction` among other checks. `TransitionOrderStatusAction` performs status changes and records order status history. The risk tiers (below 30 auto-confirm, 30–69 OTP, 70 or higher advance delivery charge, blacklist reject) and the point values are defined in AGENTS.md §8. Stock is held in `stock_reservations`. Checkout routes use the `ensure.human` middleware alias, which handles the honeypot and the submission-timing check.

### Tax compliance
Each Mushak form has a `Generate*`/`Issue*` action in `app/Modules/TaxCompliance/Actions/`. Mushak 6.3 numbering must have no gaps, so `MushakSequenceService` (a singleton) issues numbers from `mushak_sequences` under row locks. Mushak 6.3 invoices are hashed with SHA-256, and `VerifyMushak63IntegrityAction` checks the hash. Never edit an issued invoice. Issue a 6.7 credit note or a 6.8 debit note instead.

### Auth / RBAC
Access control uses Spatie Permission. `AppServiceProvider` defines a `Gate::before` super-admin bypass. The permission names actually in use are the ones in `database/seeders/RolesAndPermissionsSeeder.php`, for example `catalog.view`, `orders.edit` and `settings.edit`. AGENTS.md §16C lists a different scheme. `HandleInertiaRequests` shares auth, permissions and storefront categories as Inertia props.

The `/admin` route group uses the `auth` and `staff` middleware (`EnsureUserIsStaff`, which requires at least one role). Each route also has a `permission:` middleware: the `.view` permission for reads and a specific write permission for changes. A new admin route needs a permission middleware too. In tests, use `$this->actingAsSuperAdmin()` or `$this->actingAsStaffWith([...])` from `tests/TestCase.php`, not a plain `actingAs()` with a user who has no role.

### Storefront order & payment safety
- Order submission routes use `ensure.human` (honeypot field plus a signed timing token) and `throttle:checkout`. Any form that posts there must send `_form_timing_token`, generated by `HoneypotTimingGuardService::generateTimingToken()`. In tests, use `$this->humanTimingToken()`.
- Shipping fees come only from `CalculateShippingRateAction` (admin-configured zones). Never hard-code fees.
- Every gateway callback and IPN must settle through `SettleGatewayPaymentAction`. It locks the transaction row, enforces idempotency and checks the paid amount.
- Courier webhooks authenticate through `CourierWebhookAuthenticator`. It fails closed in production when no `webhook_secret` is configured.

### Pint gotcha
Pint's `php_unit_method_casing` rule snake_cases every `test*` method inside test files, including methods of anonymous classes. Put fake adapters that implement `testConnection()` in `tests/Support/`, not inline in a test.

### Webhooks / CSRF
`bootstrap/app.php` exempts `payment/sslcommerz/*`, `webhooks/courier/*`, `webhooks/whatsapp` and `webhooks/voice/*` from CSRF. Any new external callback route needs an entry there, and it must verify the signature itself.

### Money
Never use floats for money. Use integer minor units (poisha) or `DECIMAL(14,4)` columns.

## Frontend

- Entry points are `resources/js/app.ts` (client) and `resources/js/ssr.ts` (SSR). The Blade root view is `resources/views/app.blade.php`.
- Pages are in `resources/js/Pages/{Storefront,Admin,Auth}`. Layouts are `StorefrontLayout`, `AdminLayout` and `CheckoutLayout`. Pinia stores are in `resources/js/Stores`, and shared types are in `resources/js/types/index.d.ts`.
- Aliases: `@` → `resources/js`, `@scss` → `resources/scss`.
- Use SCSS only, in `<style lang="scss" scoped>`. Vite auto-injects `abstracts/_variables` and `abstracts/_mixins` into every SFC, so do not `@use` them manually. Global styles enter through `resources/scss/app.scss`.
- Use `<script setup lang="ts">` and never `any`. Public pages need full `<Head>` SEO metadata (title, description, canonical, OG/Twitter, JSON-LD) that renders through SSR. Rules are in AGENTS.md §10.
- The storefront brand is **"Pick & Pearl"**.

## Before finishing a task

Run `./vendor/bin/pint --test`, `php artisan test`, `npx vue-tsc --noEmit` and `npm run build`. Use Conventional Commits, for example `feat(fraud): ...`.
