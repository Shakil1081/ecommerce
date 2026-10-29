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
- **Sprint Status**: `[ ✅ COMPLETED ] (8 of 8 Tickets Passed & 100% Green)`

### Sprint 1 Ticket Breakdown

| Ticket ID | Title | Target Module | Branch | Status | Verification Command |
|---|---|---|---|---|---|
| `P1-T01` | Laravel 13 Core & Modular Autoloading Setup | `Common` | `feat/p1-t01-laravel-skeleton` | `[x] DONE` | `php artisan test --filter=InfrastructureTest` |
| `P1-T02` | SCSS Design Token Architecture (`resources/scss/`) | `Common` | `feat/p1-t02-scss-tokens` | `[x] DONE` | `npm run build` |
| `P1-T03` | Inertia.js v2 with Vue 3 TypeScript & SSR Shell | `Common`, `Seo` | `feat/p1-t03-inertia-ssr-shell` | `[x] DONE` | `npm run build:ssr && php artisan test --filter=InfrastructureTest` |
| `P1-T04` | Dynamic Multi-Cloud Storage Engine (`media_storage_settings`) | `Common`, `Admin` | `feat/p1-t04-storage-engine` | `[x] DONE` | `php artisan test --filter=StorageEngineTest` |
| `P1-T05` | Spatie Laravel MediaLibrary & Responsive Breakpoints | `Common` | `feat/p1-t05-media-conversions` | `[x] DONE` | `php artisan test --filter=MediaServiceTest` |
| `P1-T06` | Spatie RBAC System & Back-Office Admin Management UI | `Admin` | `feat/p1-t06-rbac-admin-ui` | `[x] DONE` | `php artisan test --filter=RoleAndStaffTest` |
| `P1-T07` | Dynamic SEO Engine, Schema.org JSON-LD & Dynamic Sitemaps | `Seo` | `feat/p1-t07-seo-schema-engine` | `[x] DONE` | `php artisan test --filter=SitemapAndSchemaTest` |
| `P1-T08` | In-App Release Log Viewer & Version Manager | `Admin` | `feat/p1-t08-release-log-viewer` | `[x] DONE` | `php artisan test --filter=SystemInfoTest` |

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
  - [x] SCSS tokens accessible inside any Vue SFC `<style lang="scss" scoped>` without manual `@use` imports.
  - [x] `npm run build` compiles stylesheet with 0 errors.
  - [x] TypeScript strictness verified with `npx vue-tsc --noEmit`.

#### `P1-T03`: Inertia.js v2 with Vue 3 TypeScript & SSR Shell
- **Objective**: Setup client-side app (`app.ts`) and server-side rendering script (`ssr.ts`) with typed layouts.
- **Files**:
  - `resources/js/app.ts`
  - `resources/js/ssr.ts`
  - `resources/js/Layouts/StorefrontLayout.vue`
  - `resources/js/Layouts/AdminLayout.vue`
  - `resources/js/Layouts/CheckoutLayout.vue`
- **Acceptance Criteria**:
  - [x] `npm run build:ssr` outputs valid `bootstrap/ssr/ssr.js`.
  - [x] `npx vue-tsc --noEmit` reports 0 TypeScript errors.
  - [x] Inertia route `/` verified via automated feature test (`InfrastructureTest`).
  - [x] Base layouts created (`StorefrontLayout.vue`, `AdminLayout.vue`, `CheckoutLayout.vue`).

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
  - [x] Credentials encrypted at rest in database (`encrypted:array`).
  - [x] Pre-flight test connection uploads test file, verifies integrity read, latency, and cleans up.
  - [x] Artisan `media:migrate-disk --from=local --to=s3` handles asset copying without broken URLs.
  - [x] Dynamic runtime disk binding configured in `CommonServiceProvider`.
  - [x] Admin UI built in `MediaStorageSettings.vue` and tested with 5 passing feature tests.

#### `P1-T05`: Spatie Laravel MediaLibrary & Responsive Breakpoints
- **Objective**: Configure Spatie MediaLibrary with Intervention Image v3 for WebP and AVIF generation, blurhash placeholders, and responsive breakpoints.
- **Files**:
  - `database/migrations/2026_10_29_144437_create_media_table.php`
  - `config/media-library.php`
  - `app/Modules/Common/Models/Media.php`
  - `app/Modules/Common/Services/MediaService.php`
  - `app/Modules/Common/Traits/HasStandardMediaConversions.php`
  - `tests/Feature/MediaServiceTest.php`
- **Acceptance Criteria**:
  - [x] Responsive conversions (`thumb_300`, `card_600`, `gallery_1200`, `zoom_2000`) in both JPEG and WebP registered and generated.
  - [x] Blurhash strings and LQIP data URIs computed and stored in media custom properties guaranteeing CLS = 0.0.
  - [x] Enterprise media serialization payload (`toEnterpriseArray()`) formatted for Inertia/Vue components.
  - [x] Photography watermark protection method tested and verified.

#### `P1-T06`: Spatie RBAC System & Back-Office Admin Management UI
- **Objective**: Implement enterprise roles, grouped permission matrix, and staff user assignment UI.
- **Files**:
  - `database/migrations/2026_10_29_145028_create_permission_tables.php`
  - `database/seeders/RolesAndPermissionsSeeder.php`
  - `app/Modules/Admin/Http/Controllers/RoleController.php`
  - `app/Modules/Admin/Http/Controllers/StaffUserController.php`
  - `app/Modules/Admin/Actions/SyncRolePermissionsAction.php`
  - `resources/js/Pages/Admin/Roles/RoleIndex.vue`
  - `resources/js/Pages/Admin/Roles/RoleForm.vue`
  - `resources/js/Pages/Admin/Staff/StaffIndex.vue`
  - `resources/js/Pages/Admin/Staff/StaffForm.vue`
  - `tests/Feature/RoleAndStaffTest.php`
- **Acceptance Criteria**:
  - [x] Super Admin role bypasses checks via `Gate::before`.
  - [x] Enterprise roles seeded (`Super Admin`, `Operations Manager`, `Procurement Officer`, `Accounts & VAT Officer`, `Warehouse Staff`, `Customer Support`).
  - [x] Interactive Vue 3 permission matrix allows toggling individual or all module permissions ("Select All" per group).
  - [x] Staff user creation assigns roles with direct validation and credential management.
  - [x] All 6 feature tests verified (32 assertions), Pint PSR-12 passed, Vue TypeScript compiler passed, and Vite builds passed.

#### `P1-T07`: Dynamic SEO Engine, Schema.org JSON-LD & Dynamic Sitemaps
- **Objective**: Provide structured metadata DTOs, Schema.org JSON-LD generators, and dynamic sitemap/robots endpoints.
- **Files**:
  - `app/Modules/Seo/DTOs/MetaTagData.php`
  - `app/Modules/Seo/Services/SchemaGeneratorService.php`
  - `app/Modules/Seo/Http/Controllers/SitemapController.php`
  - `tests/Feature/SitemapAndSchemaTest.php`
- **Acceptance Criteria**:
  - [x] Valid JSON-LD scripts rendered for Product, Breadcrumbs, Organization, WebSite with SearchAction, and FAQPage.
  - [x] Dynamic `/sitemap.xml` with priority, frequency, and lastmod tags generated and accessible.
  - [x] Dynamic `/robots.txt` with crawler directives and sitemap linkage generated and accessible.
  - [x] Feature tests verified (4 passed, 32 assertions), Pint PSR-12 passed.

#### `P1-T08`: In-App Release Log Viewer & Version Manager
- **Objective**: Parse `CHANGELOG.md` to render visual release notes timeline modal in admin topbar and track system version.
- **Files**:
  - `app/Modules/Admin/Services/ChangelogParserService.php`
  - `app/Modules/Admin/Http/Controllers/SystemInfoController.php`
  - `resources/js/Pages/Admin/Settings/SystemUpdates.vue`
  - `.github/workflows/release.yml`
  - `tests/Feature/SystemInfoTest.php`
- **Acceptance Criteria**:
  - [x] In-app `ChangelogParserService` parses `CHANGELOG.md` into structured release entries (version, date, unreleased, added, changed, fixed, security).
  - [x] Admin UI built in `SystemUpdates.vue` rendering runtime diagnostics and interactive visual release timeline.
  - [x] Tagging `v*.*.*` automatically triggers `.github/workflows/release.yml` on GitHub.
  - [x] Feature tests verified (2 passed, 21 assertions), Pint PSR-12 passed, Vue TypeScript compiler passed, and Vite build passed.

---

## 3. Active Sprint: Sprint 2 (Phase 2 — Core Catalog, Matrix Variants, Multi-Warehouse Inventory & Reviews)

- **Target Phase**: **Phase 2: Core Catalog, Matrix Variants, Multi-Warehouse Inventory, Reviews & Inter-Depot Transfers**
- **Sprint Goal**: Implement catalog taxonomy, multi-dimensional matrix variant manager, HS code tracking, B2B wholesale tier pricing, multi-warehouse inventory hubs, Mushak 6.5 inter-depot transfers, customer reviews, and Bangladesh geographic boundaries.
- **Sprint Status**: `[ ⏳ IN PROGRESS ]`

| Ticket ID | Task Name / User Story | Module | Git Branch | Status | Verification Command |
|---|---|---|---|---|---|
| `P2-T01` | Bangladesh Geographic Boundaries & Zone Classification | `Customer`, `Logistics` | `feat/p2-t01-bangladesh-geo` | `[x] DONE` | `php artisan test --filter=BangladeshGeoTest` |
| `P2-T02` | Catalog Taxonomy: Categories Tree & Brands with Origin | `Catalog` | `feat/p2-t02-categories-brands` | `[x] DONE` | `php artisan test --filter=CategoryAndBrandTest` |
| `P2-T03` | Matrix Product & Variant Engine with HS Codes & EANs | `Catalog` | `feat/p2-t03-product-matrix-engine` | `[ ] TODO` | `php artisan test --filter=ProductMatrixTest` |
| `P2-T04` | Wholesale B2B Tier Pricing & MOQ Engine | `Pricing` | `feat/p2-t04-tier-pricing` | `[ ] TODO` | `php artisan test --filter=TierPricingTest` |
| `P2-T05` | Multi-Warehouse Inventory Hubs & Stock Tracking | `Inventory` | `feat/p2-t05-warehouse-inventory` | `[ ] TODO` | `php artisan test --filter=WarehouseInventoryTest` |
| `P2-T06` | Inter-Depot Transfers & Mushak 6.5 Transfer Challan | `Inventory`, `TaxCompliance` | `feat/p2-t06-stock-transfers-mushak65` | `[ ] TODO` | `php artisan test --filter=StockTransferTest` |
| `P2-T07` | Customer Reviews, Ratings & Photo Attachments | `Catalog` | `feat/p2-t07-customer-reviews` | `[ ] TODO` | `php artisan test --filter=ProductReviewTest` |
| `P2-T08` | Back-Office Catalog & Matrix Variant Manager UI | `Catalog`, `Admin` | `feat/p2-t08-catalog-admin-ui` | `[ ] TODO` | `npm run build && php artisan test --filter=CatalogAdminTest` |

---

### Detailed Ticket Specifications (Sprint 2)

#### `P2-T01`: Bangladesh Geographic Boundaries & Zone Classification
- **Objective**: Create migrations, models, and comprehensive seeder for 8 Divisions, 64 Districts, and Thanas with delivery zone tags (`inside_dhaka`, `dhaka_suburbs`, `outside_dhaka`).
- **Files**:
  - `database/migrations/xxxx_create_bangladesh_divisions_table.php`
  - `database/migrations/xxxx_create_bangladesh_districts_table.php`
  - `database/migrations/xxxx_create_bangladesh_thanas_table.php`
  - `database/seeders/BangladeshGeoLocationsSeeder.php`
  - `app/Modules/Common/Models/BangladeshDivision.php`
  - `app/Modules/Common/Models/BangladeshDistrict.php`
  - `app/Modules/Common/Models/BangladeshThana.php`
  - `tests/Feature/BangladeshGeoTest.php`
- **Acceptance Criteria**:
  - [x] 8 Divisions, 64 Districts, and major Thanas seeded with both English and Bangla names.
  - [x] Districts tagged with correct courier delivery zone (`inside_dhaka`, `dhaka_suburbs`, `outside_dhaka`).
  - [x] Cascading relationship queries (Division $\rightarrow$ Districts $\rightarrow$ Thanas) verified via feature test (3 passed, 28 assertions).

#### `P2-T02`: Catalog Taxonomy: Categories Tree & Brands with Origin
- **Objective**: Nested categories with SEO slugs and banner media collections; Brand profiles with Country of Origin and logo media.
- **Files**:
  - `database/migrations/xxxx_create_categories_table.php`
  - `database/migrations/xxxx_create_brands_table.php`
  - `app/Modules/Catalog/Models/Category.php`
  - `app/Modules/Catalog/Models/Brand.php`
  - `tests/Feature/CategoryAndBrandTest.php`
- **Acceptance Criteria**:
  - [x] Category hierarchy supports parent-child trees and active status.
  - [x] Brand model tracks country of origin and active status.
  - [x] Media collections for category banners, thumbnails, and brand logos registered with Spatie MediaLibrary.
  - [x] Feature tests verified (3 passed, 18 assertions), Pint PSR-12 passed.

#### `P2-T03`: Matrix Product & Variant Engine with HS Codes & EANs
- **Objective**: Base Product model with ULID identifier, HS code, warranty policy, JSON specifications, and Variant combinations (Size $\times$ Color $\times$ Material) with SKU and Barcode/EAN.
- **Files**:
  - `database/migrations/xxxx_create_products_table.php`
  - `database/migrations/xxxx_create_product_variants_table.php`
  - `app/Modules/Catalog/Models/Product.php`
  - `app/Modules/Catalog/Models/ProductVariant.php`
  - `app/Modules/Catalog/Actions/GenerateProductVariantsAction.php`
  - `tests/Feature/ProductMatrixTest.php`
- **Acceptance Criteria**:
  - [ ] Products track statutory HS codes (for customs clearance) and warranty policy.
  - [ ] Variant matrix action generates Cartesian product of attributes without duplicate SKUs.
  - [ ] Variants store integer minor units or decimal monetary values for purchase cost and selling price.

#### `P2-T04`: Wholesale B2B Tier Pricing & MOQ Engine
- **Objective**: Dynamic pricing engine calculating volume discounts, minimum order quantities (MOQ), and customer group pricing (Retail B2C vs Wholesale B2B).
- **Files**:
  - `database/migrations/xxxx_create_pricing_tiers_table.php`
  - `app/Modules/Pricing/Models/PricingTier.php`
  - `app/Modules/Pricing/Actions/CalculateApplicablePriceAction.php`
  - `tests/Feature/TierPricingTest.php`
- **Acceptance Criteria**:
  - [ ] Pricing tiers define volume price breaks based on quantity.
  - [ ] B2B buyers automatically qualify for wholesale tier pricing when MOQ threshold is met.

#### `P2-T05`: Multi-Warehouse Inventory Hubs & Stock Tracking
- **Objective**: Warehouse depot entity (Central Hub, Regional Hubs) tracking physical, reserved, and available stock per variant with bin locations.
- **Files**:
  - `database/migrations/xxxx_create_warehouses_table.php`
  - `database/migrations/xxxx_create_inventory_stocks_table.php`
  - `app/Modules/Inventory/Models/Warehouse.php`
  - `app/Modules/Inventory/Models/InventoryStock.php`
  - `app/Modules/Inventory/Actions/AdjustInventoryStockAction.php`
  - `tests/Feature/WarehouseInventoryTest.php`
- **Acceptance Criteria**:
  - [ ] Multi-warehouse stock records physical, reserved, and available quantities.
  - [ ] Atomic stock adjustment action validates quantity changes and logs reason codes.

#### `P2-T06`: Inter-Depot Transfers & Mushak 6.5 Transfer Challan
- **Objective**: Inter-depot stock transfer state machine generating statutory NBR Mushak 6.5 Transfer Challan with atomic source stock deduction.
- **Files**:
  - `database/migrations/xxxx_create_warehouse_transfers_table.php`
  - `database/migrations/xxxx_create_warehouse_transfer_items_table.php`
  - `app/Modules/Inventory/Models/WarehouseTransfer.php`
  - `app/Modules/Inventory/Models/WarehouseTransferItem.php`
  - `app/Modules/Inventory/Actions/TransferWarehouseStockAction.php`
  - `app/Modules/Inventory/Actions/ReceiveWarehouseTransferAction.php`
  - `tests/Feature/StockTransferTest.php`
- **Acceptance Criteria**:
  - [ ] Stock transfer atomically reserves from source and commits to destination upon receiving.
  - [ ] Issues legal sequential Mushak 6.5 Transfer Challan serial.

#### `P2-T07`: Customer Reviews, Ratings & Photo Attachments
- **Objective**: Customer reviews with 1-5 star ratings, verified buyer flag, customer photo uploads, and moderation workflow.
- **Files**:
  - `database/migrations/xxxx_create_product_reviews_table.php`
  - `app/Modules/Catalog/Models/ProductReview.php`
  - `app/Modules/Catalog/Actions/ModerateProductReviewAction.php`
  - `tests/Feature/ProductReviewTest.php`
- **Acceptance Criteria**:
  - [ ] Review submissions validated with 1-5 star rating and optional photo gallery.
  - [ ] Moderation action approves, rejects, or flags reviews with auto-recalculated aggregate rating.

#### `P2-T08`: Back-Office Catalog & Matrix Variant Manager UI
- **Objective**: Full Vue 3 Inertia admin management views for Products, Categories, Brands, Matrix Variant Editor, and Inventory.
- **Files**:
  - `resources/js/Pages/Admin/Catalog/ProductIndex.vue`
  - `resources/js/Pages/Admin/Catalog/ProductForm.vue`
  - `resources/js/Pages/Admin/Catalog/ProductMatrixEditor.vue`
  - `tests/Feature/CatalogAdminTest.php`
- **Acceptance Criteria**:
  - [ ] Interactive matrix variant editor allows bulk price, SKU, barcode, and attribute generation.
  - [ ] Vue TypeScript and Vite builds compile cleanly.

---

## 4. Product Backlog (Upcoming Sprints)

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

