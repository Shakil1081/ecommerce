# Enterprise E-Commerce: Git-Tracked Task Board (`TASK_BOARD.md`)

This living document tracks the day-to-day sprint execution, tickets, branch names, and completion status across all **10 Phases** of the commercial e-commerce platform.

---

## 1. Project Management & Workflow Rules

- **Branch Naming**: `feat/p[phase]-t[ticket]-[short-slug]` (e.g., `feat/p1-t01-laravel-skeleton`, `feat/p1-t04-storage-engine`).
- **Commit Format**: Conventional Commits: `<type>(<scope>): <summary>` (e.g., `feat(storage): implement dynamic multi-cloud storage driver`).
- **Definition of Done (DoD)**:
  1. Business logic isolated in an Action class (`__invoke`) or Domain Service.
  2. Strict types declared (`declare(strict_types=1);`).
  3. Feature test or Unit test written and assertions passing.
  4. Code formatted with `./vendor/bin/pint`.
  5. TypeScript verified with `npx vue-tsc --noEmit`.
  6. Task status updated in this document (`[ ]` $\rightarrow$ `[x]`).

---

## 2. Active Sprint: Sprint 1 (Phase 1 — Foundation, Storage Engine & RBAC)

- **Target Phase**: **Phase 1: Foundation, Infrastructure, Dynamic Multi-Cloud Storage Engine, Spatie RBAC & Core SEO**
- **Sprint Goal**: Scaffold the Laravel 13 + Vue 3 Inertia SSR architecture, modular layout shell, dynamic multi-cloud storage engine (Local/S3/R2/GCS), and Spatie RBAC back-office management UI.
- **Sprint Status**: `[ ⏳ IN PROGRESS ]`

### Sprint 1 Ticket Breakdown

| Ticket ID | Title | Target Module | Branch | Status | Verification Command |
|---|---|---|---|---|---|
| `P1-T01` | Laravel 13 Core & Modular Autoloading Setup | `Common` | `feat/p1-t01-laravel-skeleton` | `[x] DONE` | `php artisan test --filter=InfrastructureTest` |
| `P1-T02` | SCSS Design Token Architecture (`resources/scss/`) | `Common` | `feat/p1-t02-scss-tokens` | `[ ] TODO` | `npm run build` |
| `P1-T03` | Inertia.js v2 with Vue 3 TypeScript & SSR Shell | `Common`, `Seo` | `feat/p1-t03-inertia-ssr-shell` | `[ ] TODO` | `npm run build:ssr` |
| `P1-T04` | Dynamic Multi-Cloud Storage Engine (`media_storage_settings`) | `Common`, `Admin` | `feat/p1-t04-storage-engine` | `[ ] TODO` | `php artisan test --filter=StorageEngineTest` |
| `P1-T05` | Spatie Laravel MediaLibrary & Responsive Breakpoints | `Common` | `feat/p1-t05-media-conversions` | `[ ] TODO` | `php artisan test --filter=MediaServiceTest` |
| `P1-T06` | Spatie RBAC System & Back-Office Admin Management UI | `Admin` | `feat/p1-t06-rbac-admin-ui` | `[ ] TODO` | `php artisan test --filter=RoleAndStaffTest` |
| `P1-T07` | Dynamic SEO Engine, Schema.org JSON-LD & Dynamic Sitemaps | `Seo` | `feat/p1-t07-seo-schema-engine` | `[ ] TODO` | `php artisan test --filter=SitemapAndSchemaTest` |
| `P1-T08` | In-App Release Log Viewer & Version Manager | `Admin` | `feat/p1-t08-release-log-viewer` | `[ ] TODO` | `php artisan test --filter=SystemInfoTest` |

---

### Detailed Ticket Specifications (Sprint 1)

#### `P1-T01`: Laravel 13 Core & Modular Autoloading Setup
- **Objective**: Initialize the base Laravel skeleton, configure `"App\\Modules\\": "app/Modules/"` in `composer.json`, setup environment templates, and configure Pint PSR-12 rules.
- **Files**:
  - `composer.json`
  - `.env.example`
  - `.pint.json`
  - `app/Modules/Common/Providers/CommonServiceProvider.php`
- **Acceptance Criteria**:
  - [x] PSR-4 autoloading compiles cleanly (`composer dump-autoload`).
  - [x] `./vendor/bin/pint --test` passes with zero violations.
  - [x] Feature test verified (`php artisan test --filter=InfrastructureTest`).

#### `P1-T02`: SCSS Design Token Architecture (`resources/scss/`)
- **Objective**: Build pure SCSS design token architecture with HSL color variables, responsive typography, spacing tokens, and mixins automatically injected in `vite.config.ts`.
- **Files**:
  - `resources/scss/abstracts/_variables.scss`
  - `resources/scss/abstracts/_mixins.scss`
  - `resources/scss/abstracts/_functions.scss`
  - `resources/scss/base/_reset.scss`
  - `resources/scss/base/_typography.scss`
  - `resources/scss/components/_buttons.scss`
  - `resources/scss/components/_cards.scss`
  - `resources/scss/app.scss`
  - `vite.config.ts`
- **Acceptance Criteria**:
  - [ ] SCSS tokens accessible inside any Vue SFC `<style lang="scss" scoped>` without manual `@use` imports.
  - [ ] `npm run build` compiles stylesheet with 0 errors.

#### `P1-T03`: Inertia.js v2 with Vue 3 TypeScript & SSR Shell
- **Objective**: Setup client-side app (`app.ts`) and server-side rendering script (`ssr.ts`) with typed layouts.
- **Files**:
  - `resources/js/app.ts`
  - `resources/js/ssr.ts`
  - `resources/js/Layouts/StorefrontLayout.vue`
  - `resources/js/Layouts/AdminLayout.vue`
  - `resources/js/Layouts/CheckoutLayout.vue`
- **Acceptance Criteria**:
  - [ ] `npm run build:ssr` outputs valid `bootstrap/ssr/ssr.js`.
  - [ ] `npx vue-tsc --noEmit` reports 0 TypeScript errors.

#### `P1-T04`: Dynamic Multi-Cloud Storage Engine (`media_storage_settings`)
- **Objective**: Allow administrators to configure storage disks (Local, S3, Cloudflare R2 [zero egress fees], Google Cloud Storage, MinIO) directly in the back-office with pre-flight connection testing.
- **Files**:
  - `database/migrations/xxxx_create_media_storage_settings_table.php`
  - `app/Modules/Common/Models/MediaStorageSetting.php`
  - `app/Modules/Common/Actions/TestStorageConnectionAction.php`
  - `app/Modules/Common/Actions/UpdateMediaStorageSettingsAction.php`
  - `app/Modules/Common/Commands/MigrateMediaDiskCommand.php`
  - `resources/js/Pages/Admin/Settings/MediaStorageSettings.vue`
- **Acceptance Criteria**:
  - [ ] Credentials encrypted at rest in database (`encrypted:array`).
  - [ ] Pre-flight test connection uploads test file, verifies public CDN read, and cleans up.
  - [ ] Artisan `media:migrate-disk --from=local --to=s3` handles asset copying.

#### `P1-T05`: Spatie Laravel MediaLibrary & Responsive Breakpoints
- **Objective**: Configure Spatie MediaLibrary with Intervention Image v3 for WebP and AVIF generation, blurhash placeholders, and responsive breakpoints.
- **Files**:
  - `database/migrations/xxxx_create_media_table.php`
  - `app/Modules/Common/Services/MediaService.php`
- **Acceptance Criteria**:
  - [ ] Responsive conversions (`thumb_300`, `card_600`, `gallery_1200`, `zoom_2000`) generated asynchronously.
  - [ ] Blurhash strings computed and stored to guarantee CLS = 0.0.

#### `P1-T06`: Spatie RBAC System & Back-Office Admin Management UI
- **Objective**: Implement enterprise roles, grouped permission matrix, and staff user assignment UI.
- **Files**:
  - `database/migrations/xxxx_create_permission_tables.php`
  - `database/seeders/RolesAndPermissionsSeeder.php`
  - `app/Modules/Admin/Http/Controllers/RoleController.php`
  - `app/Modules/Admin/Http/Controllers/StaffUserController.php`
  - `app/Modules/Admin/Actions/SyncRolePermissionsAction.php`
  - `resources/js/Pages/Admin/Roles/RoleIndex.vue`
  - `resources/js/Pages/Admin/Roles/RoleForm.vue`
  - `resources/js/Pages/Admin/Staff/StaffIndex.vue`
  - `resources/js/Pages/Admin/Staff/StaffForm.vue`
- **Acceptance Criteria**:
  - [ ] Super Admin role bypasses checks via `Gate::before`.
  - [ ] Interactive Vue 3 permission matrix allows toggling individual or all module permissions.
  - [ ] Staff user creation assigns roles with direct permission overrides.

#### `P1-T07`: Dynamic SEO Engine, Schema.org JSON-LD & Dynamic Sitemaps
- **Objective**: Provide structured metadata DTOs, Schema.org JSON-LD generators, and dynamic sitemap/robots endpoints.
- **Files**:
  - `app/Modules/Seo/DTOs/MetaTagData.php`
  - `app/Modules/Seo/Services/SchemaGeneratorService.php`
  - `app/Modules/Seo/Http/Controllers/SitemapController.php`
- **Acceptance Criteria**:
  - [ ] Valid JSON-LD scripts rendered server-side for Product, Breadcrumbs, Organization, and FAQ.
  - [ ] Dynamic `/sitemap.xml` and `/robots.txt` generated and accessible.

#### `P1-T08`: In-App Release Log Viewer & Version Manager
- **Objective**: Parse `CHANGELOG.md` to render visual release notes timeline modal in admin topbar and track system version.
- **Files**:
  - `app/Modules/Admin/Http/Controllers/SystemInfoController.php`
  - `resources/js/Pages/Admin/Settings/SystemUpdates.vue`
  - `.github/workflows/release.yml`
- **Acceptance Criteria**:
  - [ ] Version and release timeline rendered visually in admin modal.
  - [ ] Tagging `v*.*.*` automatically triggers `.github/workflows/release.yml` on GitHub.

---

## 3. Product Backlog (Upcoming Sprints)

| Sprint / Phase | Phase Name | Primary Domain Modules | Estimated Tickets |
|---|---|---|---|
| **Sprint 2 (Phase 2)** | Core Catalog, Matrix Variants, Multi-Warehouse Inventory & Reviews | `Catalog`, `Inventory`, `Pricing` | 8 Tickets |
| **Sprint 3 (Phase 3)** | Dual Sourcing Procurement (Import & Local) & Landed Cost Engine | `Procurement`, `TaxCompliance` | 6 Tickets |
| **Sprint 4 (Phase 4)** | Cart Engine, Coupons, Order State Machine & Atomic Stock Reservation | `Cart`, `Order`, `Marketing` | 6 Tickets |
| **Sprint 5 (Phase 5)** | 7-Layer Anti-Fraud, Fake Order Defense & Dynamic Bot Security Settings | `Order`, `Customer`, `Admin` | 6 Tickets |
| **Sprint 6 (Phase 6)** | Dynamic Payment Gateway Module & Advance COD Delivery Engine | `Payment` | 5 Tickets |
| **Sprint 7 (Phase 7)** | Automated Courier Logistics, Dynamic Delivery Pricing & Payout Reconciliation | `Logistics` | 6 Tickets |
| **Sprint 8 (Phase 8)** | Omnichannel Messaging, Voice AI, Promotional SMS & Delivery Campaign Manager, Customer Portal & Storefront UX | `Communication`, `Customer`, `Marketing`, `Storefront` | 9 Tickets |
| **Sprint 9 (Phase 9)** | Comprehensive NBR VAT, Full Statutory Tax Compliance & RMA Return Engine | `TaxCompliance`, `Order` | 6 Tickets |
| **Sprint 10 (Phase 10)** | Backend AI Intelligence, Trading P&L Analytics, Audit Trail & Automated QA | `AI`, `Admin` | 5 Tickets |

