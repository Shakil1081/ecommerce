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
- **Sprint Status**: `[ ✅ COMPLETED ] (8 of 8 Tickets Passed & 100% Green)`

| Ticket ID | Task Name / User Story | Module | Git Branch | Status | Verification Command |
|---|---|---|---|---|---|
| `P2-T01` | Bangladesh Geographic Boundaries & Zone Classification | `Customer`, `Logistics` | `feat/p2-t01-bangladesh-geo` | `[x] DONE` | `php artisan test --filter=BangladeshGeoTest` |
| `P2-T02` | Catalog Taxonomy: Categories Tree & Brands with Origin | `Catalog` | `feat/p2-t02-categories-brands` | `[x] DONE` | `php artisan test --filter=CategoryAndBrandTest` |
| `P2-T03` | Matrix Product & Variant Engine with HS Codes & EANs | `Catalog` | `feat/p2-t03-product-matrix-engine` | `[x] DONE` | `php artisan test --filter=ProductMatrixTest` |
| `P2-T04` | Wholesale B2B Tier Pricing & MOQ Engine | `Pricing` | `feat/p2-t04-tier-pricing` | `[x] DONE` | `php artisan test --filter=TierPricingTest` |
| `P2-T05` | Multi-Warehouse Inventory Hubs & Stock Tracking | `Inventory` | `feat/p2-t05-warehouse-inventory` | `[x] DONE` | `php artisan test --filter=WarehouseInventoryTest` |
| `P2-T06` | Inter-Depot Transfers & Mushak 6.5 Transfer Challan | `Inventory`, `TaxCompliance` | `feat/p2-t06-stock-transfers-mushak65` | `[x] DONE` | `php artisan test --filter=StockTransferTest` |
| `P2-T07` | Customer Reviews, Ratings & Photo Attachments | `Catalog` | `feat/p2-t07-customer-reviews` | `[x] DONE` | `php artisan test --filter=ProductReviewTest` |
| `P2-T08` | Back-Office Catalog & Matrix Variant Manager UI | `Catalog`, `Admin` | `feat/p2-t08-catalog-admin-ui` | `[x] DONE` | `npm run build && php artisan test --filter=CatalogAdminTest` |

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
  - [x] Products track statutory HS codes (for customs clearance) and warranty policy.
  - [x] Variant matrix action generates Cartesian product of attributes without duplicate SKUs.
  - [x] Variants store decimal monetary values for purchase cost and selling price with precision.
  - [x] Feature tests verified (2 passed, 16 assertions), Pint PSR-12 passed.

#### `P2-T04`: Wholesale B2B Tier Pricing & MOQ Engine
- **Objective**: Dynamic pricing engine calculating volume discounts, minimum order quantities (MOQ), and customer group pricing (Retail B2C vs Wholesale B2B).
- **Files**:
  - `database/migrations/xxxx_create_pricing_tiers_table.php`
  - `app/Modules/Pricing/Models/PricingTier.php`
  - `app/Modules/Pricing/Actions/CalculateApplicablePriceAction.php`
  - `tests/Feature/TierPricingTest.php`
- **Acceptance Criteria**:
  - [x] Pricing tiers define volume price breaks based on quantity.
  - [x] B2B buyers automatically qualify for wholesale tier pricing when MOQ threshold is met.
  - [x] Retail volume discounts and savings calculations verified via automated feature test (4 passed, 21 assertions).

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
  - [x] Multi-warehouse stock records physical, reserved, and available quantities with bin location.
  - [x] Atomic stock adjustment action validates quantity changes and logs reason codes.
  - [x] Inventory validation prevents negative stock deductions.
  - [x] Feature tests verified (3 passed, 14 assertions), Pint PSR-12 passed.

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
  - [x] Stock transfer atomically reserves and deducts from source and commits to destination upon receiving.
  - [x] Issues legal sequential Mushak 6.5 Transfer Challan serial.
  - [x] Validations prevent transferring to same warehouse or moving non-existent inventory.
  - [x] Feature tests verified (3 passed, 14 assertions), Pint PSR-12 passed.

#### `P2-T07`: Customer Reviews, Ratings & Photo Attachments
- **Objective**: Customer reviews with 1-5 star ratings, verified buyer flag, customer photo uploads, and moderation workflow.
- **Files**:
  - `database/migrations/xxxx_create_product_reviews_table.php`
  - `app/Modules/Catalog/Models/ProductReview.php`
  - `app/Modules/Catalog/Actions/ModerateProductReviewAction.php`
  - `tests/Feature/ProductReviewTest.php`
- **Acceptance Criteria**:
  - [x] Review submissions validated with 1-5 star rating and customer review text.
  - [x] Moderation action approves, rejects, or flags reviews with auto-recalculated aggregate rating.
  - [x] Public storefront queries filter only approved reviews.
  - [x] Feature tests verified (3 passed, 13 assertions), Pint PSR-12 passed.

#### `P2-T08`: Back-Office Catalog & Matrix Variant Manager UI
- **Objective**: Full Vue 3 Inertia admin management views for Products, Categories, Brands, Matrix Variant Editor, and Inventory.
- **Files**:
  - `resources/js/Pages/Admin/Catalog/ProductIndex.vue`
  - `resources/js/Pages/Admin/Catalog/ProductForm.vue`
  - `resources/js/Pages/Admin/Catalog/ProductMatrixEditor.vue`
  - `tests/Feature/CatalogAdminTest.php`
- **Acceptance Criteria**:
  - [x] Interactive matrix variant editor allows bulk price, SKU, barcode, and attribute generation.
  - [x] Product CRUD and Cartesian matrix generation endpoints verified via feature tests (4 passed, 22 assertions).
  - [x] Vue TypeScript compiler and Vite builds compile cleanly.

---

## 4. Active Sprint: Sprint 3 (Phase 3 — Dual Sourcing Procurement & Landed Cost Engine)

- **Target Phase**: **Phase 3: Dual Sourcing Procurement (Import & Local) & Landed Cost Engine**
- **Sprint Goal**: Implement global commercial import tracking (CI, PI, LC/TT, BOE customs tariffs at Chattogram/Dhaka ICD), local domestic procurement in BDT (৳), supplier BIN/e-TIN verification, unified Landed Cost COGS allocation engine, and Inward Goods Receiving (GRN) with barcode intake.
- **Sprint Status**: `[ ✅ COMPLETED ] (7 of 7 Tickets Passed & 100% Green)`

| Ticket ID | Task Name / User Story | Module | Git Branch | Status | Verification Command |
|---|---|---|---|---|---|
| `P3-T01` | Supplier Directory (International & Local) with BIN/TIN | `Procurement` | `feat/p3-t01-supplier-directory` | `[x] DONE` | `php artisan test --filter=SupplierTest` |
| `P3-T02` | Global Import Purchase Orders & Commercial Invoices | `Procurement` | `feat/p3-t02-import-orders` | `[x] DONE` | `php artisan test --filter=ImportOrderTest` |
| `P3-T03` | Bill of Entry (BOE / বিল অফ এন্ট্রি) & Customs Tariffs | `Procurement`, `TaxCompliance` | `feat/p3-t03-bill-of-entry` | `[x] DONE` | `php artisan test --filter=BillOfEntryTest` |
| `P3-T04` | Local Purchase Orders (BDT ৳) & Supplier Mushak 6.3 | `Procurement`, `TaxCompliance` | `feat/p3-t04-local-purchase-orders` | `[x] DONE` | `php artisan test --filter=LocalPoTest` |
| `P3-T05` | Unified Landed Cost & True COGS Allocation Engine | `Procurement` | `feat/p3-t05-landed-cost-engine` | `[x] DONE` | `php artisan test --filter=LandedCostTest` |
| `P3-T06` | Inward Goods Received Notes (GRN) & Multi-Depot Stock Intake | `Procurement`, `Inventory` | `feat/p3-t06-grn-intake` | `[x] DONE` | `php artisan test --filter=GoodsReceivedNoteTest` |
| `P3-T07` | Back-Office Dual Sourcing Procurement UI | `Procurement`, `Admin` | `feat/p3-t07-procurement-admin-ui` | `[x] DONE` | `npm run build && php artisan test --filter=ProcurementAdminTest` |

---

### Detailed Ticket Specifications (Sprint 3)

#### `P3-T01`: Supplier Directory (International & Local) with BIN/TIN
- **Objective**: Directory of international manufacturers (China, UAE, USA) and domestic authorized distributors with 9/13-digit BIN, 12-digit e-TIN, and bank/payment terms.
- **Files**:
  - `database/migrations/xxxx_create_suppliers_table.php`
  - `app/Modules/Procurement/Models/Supplier.php`
  - `app/Modules/Procurement/Actions/VerifySupplierBinAction.php`
  - `tests/Feature/SupplierTest.php`
- **Acceptance Criteria**:
  - [x] Supports international suppliers (with FX currency and overseas wire details) and domestic vendors.
  - [x] Validates Bangladesh 9-digit and 13-digit Business Identification Number (BIN) formats.
  - [x] Feature tests verified (4 passed, 26 assertions), Pint PSR-12 passed.

#### `P3-T02`: Global Import Purchase Orders & Commercial Invoices
- **Objective**: Track global commercial imports with Proforma Invoices (PI), Commercial Invoices (CI), Letter of Credit (LC) / TT, foreign currency (USD, RMB, EUR), and bank settlement exchange rates.
- **Files**:
  - `database/migrations/xxxx_create_import_purchase_orders_table.php`
  - `database/migrations/xxxx_create_import_po_items_table.php`
  - `app/Modules/Procurement/Models/ImportPurchaseOrder.php`
  - `app/Modules/Procurement/Models/ImportPoItem.php`
  - `tests/Feature/ImportOrderTest.php`
- **Acceptance Criteria**:
  - [x] Records foreign currency (USD, RMB, EUR) FOB prices and bank settlement exchange rates in BDT (৳).
  - [x] Tracks LC/TT numbers, issuing bank, margin deposit, and settlement date.
  - [x] Feature tests verified (2 passed, 9 assertions), Pint PSR-12 passed.

#### `P3-T03`: Bill of Entry (BOE / বিল অফ এন্ট্রি) & Customs Tariffs
- **Objective**: Model Bangladesh Customs clearance at ports of entry (Chattogram, Dhaka Airport/ICD) with statutory Customs Assessment Value, Customs Duty (CD), Regulatory Duty (RD), Supplementary Duty (SD), Advance Tax (AT - 5%), and Advance Income Tax (AIT - 5%).
- **Files**:
  - `database/migrations/xxxx_create_bills_of_entry_table.php`
  - `database/migrations/xxxx_create_boe_items_table.php`
  - `app/Modules/Procurement/Models/BillOfEntry.php`
  - `app/Modules/Procurement/Models/BoeItem.php`
  - `tests/Feature/BillOfEntryTest.php`
- **Acceptance Criteria**:
  - [x] Tracks port of clearance, BOE serial number, and customs assessment value in BDT.
  - [x] Computes statutory tariffs: CD, RD, SD, AT (5%), and AIT (5%).
  - [x] Feature tests verified (2 passed, 19 assertions), Pint PSR-12 passed.

#### `P3-T04`: Local Purchase Orders (BDT ৳) & Supplier Mushak 6.3
- **Objective**: Domestic procurement in Bangladeshi Taka with supplier Mushak 6.3 Tax Invoice registration, VDS calculation with Mushak 6.6 Certificates, and TDS withholding under the Income Tax Act 2023.
- **Files**:
  - `database/migrations/xxxx_create_local_purchase_orders_table.php`
  - `database/migrations/xxxx_create_local_po_items_table.php`
  - `app/Modules/Procurement/Models/LocalPurchaseOrder.php`
  - `app/Modules/Procurement/Models/LocalPoItem.php`
  - `tests/Feature/LocalPoTest.php`
- **Acceptance Criteria**:
  - [x] Records local POs in BDT (৳) with supplier's Mushak 6.3 serial and invoice date.
  - [x] Computes VDS and TDS withholding deductions with precision.
  - [x] Issues statutory NBR Mushak 6.6 Certificates (উৎসে মূসক কর্তন সনদপত্র) with verified BIN checks.
  - [x] Feature tests verified (3 passed, 14 assertions), Pint PSR-12 passed.

#### `P3-T05`: Unified Landed Cost & True COGS Allocation Engine
- **Objective**: Computes true Cost of Goods Sold (COGS in BDT ৳) per unit batch across import shipments (FOB + Freight + Insurance + Port C&F + Customs Duties + Carrying) and domestic purchases (Purchase Price + Local Transport + Handling).
- **Files**:
  - `database/migrations/xxxx_create_landed_cost_allocations_table.php`
  - `app/Modules/Procurement/Models/LandedCostAllocation.php`
  - `app/Modules/Procurement/Actions/CalculateImportLandedCostAction.php`
  - `app/Modules/Procurement/Actions/CalculateLocalLandedCostAction.php`
  - `tests/Feature/LandedCostTest.php`
- **Acceptance Criteria**:
  - [x] Allocates international freight, port C&F fees, and customs duties proportionately onto import variants.
  - [x] 100% of landed costs absorbed into true domestic unit COGS in BDT (৳).
  - [x] Feature tests verified (2 passed, 11 assertions), Pint PSR-12 passed.

#### `P3-T06`: Inward Goods Received Notes (GRN) & Multi-Depot Stock Intake
- **Objective**: Physical inward receiving into designated warehouse hubs regardless of origin (import container or domestic truck) with barcode scans, discrepancy notes, and physical inventory commit.
- **Files**:
  - `database/migrations/xxxx_create_goods_received_notes_table.php`
  - `database/migrations/xxxx_create_grn_items_table.php`
  - `app/Modules/Procurement/Models/GoodsReceivedNote.php`
  - `app/Modules/Procurement/Models/GrnItem.php`
  - `app/Modules/Procurement/Actions/ReceiveGoodsNoteAction.php`
  - `tests/Feature/GoodsReceivedNoteTest.php`
- **Acceptance Criteria**:
  - [x] GRN records ordered, received, and damaged quantities.
  - [x] Inward receiving atomically increments physical inventory stock in the specified warehouse depot.
  - [x] Reconciles parent PO lifecycle status and flags discrepancies.
  - [x] Feature tests verified (3 passed, 13 assertions), Pint PSR-12 passed.

#### `P3-T07`: Back-Office Dual Sourcing Procurement UI
- **Objective**: Full Vue 3 Inertia back-office management views for Global Import Orders, Domestic POs, BOE Tariffs, and GRN Intake.
- **Files**:
  - `app/Modules/Admin/Http/Controllers/ProcurementController.php`
  - `resources/js/Pages/Admin/Procurement/SupplierIndex.vue`
  - `resources/js/Pages/Admin/Procurement/ImportOrderIndex.vue`
  - `resources/js/Pages/Admin/Procurement/LocalPoIndex.vue`
  - `resources/js/Pages/Admin/Procurement/GrnReceiving.vue`
  - `tests/Feature/ProcurementAdminTest.php`
- **Acceptance Criteria**:
  - [x] Vue components compile cleanly with zero TypeScript errors (`npx vue-tsc --noEmit`).
  - [x] `npm run build` and `npm run build:ssr` pass cleanly.
  - [x] Admin can view suppliers, import orders, domestic POs, and GRN receipts.
  - [x] Feature tests verified (5 passed, 56 assertions), Pint PSR-12 passed.

---

## 5. Active Sprint: Sprint 4 (Phase 4 — Cart Engine, Coupons, Order State Machine & Atomic Stock Reservation)

- **Target Phase**: **Phase 4: Cart Engine, Coupons, Order State Machine & Atomic Stock Reservation**
- **Sprint Goal**: Implement high-concurrency cart engine (guest & authenticated session migration), dynamic free shipping bar, coupon engine with percentage/fixed/free shipping rules, immutable order state machine, and atomic concurrency inventory reservation (`lockForUpdate` / Redis atomic decrement).
- **Sprint Status**: `[ ⏳ READY TO EXECUTE ]`

### Sprint 4 Ticket Breakdown

| Ticket ID | Task Name / User Story | Module | Git Branch | Status | Verification Command |
|---|---|---|---|---|---|
| `P4-T01` | Persistent Cart Engine & Session-to-Customer Merge | `Cart` | `feat/p4-t01-cart-engine` | `[x] DONE` | `php artisan test --filter=CartEngineTest` |
| `P4-T02` | Interactive Slide-Out Cart Drawer & Free Shipping Bar | `Cart`, `Common` | `feat/p4-t02-cart-drawer` | `[x] DONE` | `npm run build && php artisan test --filter=CartDrawerTest` |
| `P4-T03` | Discount Coupons & Promotional Code Engine | `Marketing`, `Cart` | `feat/p4-t03-coupons-discounts` | `[x] DONE` | `php artisan test --filter=CouponEngineTest` |
| `P4-T04` | Order Domain Model & Finite State Machine (FSM) | `Order` | `feat/p4-t04-order-state-machine` | `[x] DONE` | `php artisan test --filter=OrderStateTest` |
| `P4-T05` | Atomic Stock Reservation & Flash Sale Concurrency Lock | `Inventory`, `Order` | `feat/p4-t05-atomic-stock-reservation` | `[x] DONE` | `php artisan test --filter=StockReservationTest` |
| `P4-T06` | Back-Office Order Management & Invoice Dispatch UI | `Order`, `Admin` | `feat/p4-t06-order-admin-ui` | `[ ] IN PROGRESS` | `npm run build && php artisan test --filter=OrderAdminTest` |

---

### Detailed Ticket Specifications (Sprint 4)

#### `P4-T01`: Persistent Cart Engine & Session-to-Customer Merge
- **Objective**: Persistent cart for guest sessions and authenticated users with dynamic volume tier pricing and session merge on login.
- **Files**:
  - `database/migrations/xxxx_create_carts_table.php`
  - `database/migrations/xxxx_create_cart_items_table.php`
  - `app/Modules/Cart/Models/Cart.php`
  - `app/Modules/Cart/Models/CartItem.php`
  - `app/Modules/Cart/Actions/AddToCartAction.php`
  - `app/Modules/Cart/Actions/UpdateCartItemQuantityAction.php`
  - `app/Modules/Cart/Actions/RemoveFromCartAction.php`
  - `app/Modules/Cart/Actions/MergeGuestCartAction.php`
  - `app/Modules/Cart/Actions/GetActiveCartAction.php`
  - `app/Modules/Cart/Actions/RecalculateCartAction.php`
  - `tests/Feature/CartEngineTest.php`
- **Acceptance Criteria**:
  - [x] Guest cart tracks session cookie token and activity timestamp.
  - [x] Adding/updating item quantities automatically computes volume tier price breaks and unit savings.
  - [x] Merging guest cart into customer account transfers items, combines duplicates, and recalculates totals.
  - [x] Feature tests verified (5 passed, 31 assertions), Pint PSR-12 passed.

#### `P4-T02`: Interactive Slide-Out Cart Drawer & Free Shipping Bar
- **Objective**: High-performance slide-out cart drawer with dynamic free shipping progress bar, quantity stepper, volume savings badge, Pinia state sync, and JSON drawer endpoints.
- **Files**:
  - `app/Modules/Cart/Http/Controllers/CartController.php`
  - `app/Modules/Cart/Http/Resources/CartResource.php`
  - `resources/js/Stores/cartStore.ts`
  - `resources/js/Components/Cart/CartDrawer.vue`
  - `resources/js/Layouts/StorefrontLayout.vue`
  - `tests/Feature/CartDrawerTest.php`
- **Acceptance Criteria**:
  - [x] JSON endpoints for `GET /cart/drawer`, `POST /cart/items`, `PATCH /cart/items/{variant}`, `DELETE /cart/items/{variant}`.
  - [x] Dynamic calculation of Free Shipping threshold (৳2,000.00 BDT) with unlock state, remaining amount, and progress percentage.
  - [x] Responsive Vue 3 slide-out drawer with zero layout shift and smooth transitions.
  - [x] Feature tests verified (6 passed, 34 assertions), Pint PSR-12 passed, Vite build passed.

#### `P4-T03`: Discount Coupons & Promotional Code Engine
- **Objective**: Flexible coupon and promotional discount engine supporting percentage discounts, fixed BDT discounts, and free shipping triggers with minimum spend, max discount caps, expiry date ranges, total usage limits, and per-customer usage limits.
- **Files**:
  - `database/migrations/xxxx_create_coupons_table.php`
  - `database/migrations/xxxx_create_coupon_usages_table.php`
  - `app/Modules/Marketing/Models/Coupon.php`
  - `app/Modules/Marketing/Models/CouponUsage.php`
  - `app/Modules/Marketing/Actions/ApplyCouponToCartAction.php`
  - `app/Modules/Marketing/Actions/RemoveCouponFromCartAction.php`
  - `app/Modules/Marketing/Actions/RecordCouponUsageAction.php`
  - `tests/Feature/CouponEngineTest.php`
- **Acceptance Criteria**:
  - [x] Supports discount types: `percentage`, `fixed_amount`, `free_shipping`.
  - [x] Enforces validation rules: minimum spend in BDT, start/end dates, total usage limit, per-customer usage limit.
  - [x] Recomputes cart discount amount and total with precision.
  - [x] Feature tests verified (9 passed, 31 assertions), Pint PSR-12 passed.

#### `P4-T04`: Order Domain Model & Finite State Machine (FSM)
- **Objective**: Robust Order entity, items, status transition audit history, and deterministic state machine transitions with validation and event dispatching.
- **Files**:
  - `database/migrations/xxxx_create_orders_table.php`
  - `database/migrations/xxxx_create_order_items_table.php`
  - `database/migrations/xxxx_create_order_status_histories_table.php`
  - `app/Modules/Order/Models/Order.php`
  - `app/Modules/Order/Models/OrderItem.php`
  - `app/Modules/Order/Models/OrderStatusHistory.php`
  - `app/Modules/Order/Actions/CreateOrderFromCartAction.php`
  - `app/Modules/Order/Actions/TransitionOrderStatusAction.php`
  - `tests/Feature/OrderStateTest.php`
- **Acceptance Criteria**:
  - [x] Strict lifecycle state machine: `pending` $\rightarrow$ `confirmed` $\rightarrow$ `processing` $\rightarrow$ `picked_and_packed` $\rightarrow$ `handed_to_courier` $\rightarrow$ `out_for_delivery` $\rightarrow$ `delivered` / `returned` / `cancelled`.
  - [x] Prevents illegal status transitions (e.g., delivered to pending).
  - [x] Logs immutable transition audit history (`from_status`, `to_status`, `changed_by_user_id`, `reason`).
  - [x] Feature tests verified (7 passed, 43 assertions), Pint PSR-12 passed.

#### `P4-T05`: Atomic Stock Reservation & Flash Sale Concurrency Lock
- **Objective**: High-concurrency inventory reservation mechanism with row locking (`lockForUpdate()`), stock holds for checkout orders, TTL expiration, and release of unfulfilled reservations back to available stock.
- **Files**:
  - `database/migrations/xxxx_create_stock_reservations_table.php`
  - `app/Modules/Inventory/Models/StockReservation.php`
  - `app/Modules/Inventory/Actions/ReserveStockForOrderAction.php`
  - `app/Modules/Inventory/Actions/CommitStockReservationAction.php`
  - `app/Modules/Inventory/Actions/ReleaseStockReservationAction.php`
  - `app/Modules/Inventory/Actions/ReleaseExpiredStockReservationsAction.php`
  - `app/Modules/Inventory/Commands/ReleaseExpiredReservationsCommand.php`
  - `tests/Feature/StockReservationTest.php`
- **Acceptance Criteria**:
  - [x] Reserves variant stock atomically in designated warehouse using pessimistic row locking (`lockForUpdate()`).
  - [x] Prevents overselling during high-traffic flash sales when quantity requested exceeds available stock.
  - [x] Releases expired reservations (e.g. 15-minute TTL) back into available stock.
  - [x] Commits stock upon order confirmation (decrements physical and reserved stock).
  - [x] Feature tests verified (6 passed, 23 assertions), Pint PSR-12 passed.

#### `P4-T06`: Back-Office Order Management & Invoice Dispatch UI
- **Objective**: Vue 3 Inertia order management dashboard with filtering by lifecycle status, customer details, courier consignment booking link, status transition modal, and order printable summary.
- **Files**:
  - `app/Modules/Admin/Http/Controllers/OrderAdminController.php`
  - `resources/js/Pages/Admin/Orders/OrderIndex.vue`
  - `resources/js/Pages/Admin/Orders/OrderDetail.vue`
  - `tests/Feature/OrderAdminTest.php`
- **Acceptance Criteria**:
  - [ ] Order list with status tabs (`pending`, `confirmed`, `processing`, `delivered`, `cancelled`).
  - [ ] Order detail view displaying customer info, cascading address, items table with COGS, monetary breakdown, and status timeline.
  - [ ] Admin can transition order status with validation and reason prompt.
  - [ ] Feature tests pass, Pint PSR-12 passed, Vite build passed.

---

## 6. Product Backlog (Upcoming Sprints)

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

