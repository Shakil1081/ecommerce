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

## 5. Sprint 5 (Phase 5 — 7-Layer Anti-Fraud, Fake Order Defense & Dynamic Bot Security Settings — COMPLETED)

- **Target Phase**: **Phase 5: 7-Layer Anti-Fraud, Fake Order Defense & Dynamic Bot Security Settings**
- **Sprint Goal**: Implement an enterprise 7-layer defense shield against fake orders, bot submissions, and high-return COD customers: invisible honeypots, client timing guards, Bangladesh telco regex, Shannon entropy gibberish detection, order velocity throttling, cross-courier delivery intelligence APIs, merchant blacklist/whitelist directory, and back-office Google reCAPTCHA / Cloudflare Turnstile toggles.
- **Sprint Status**: `[ ✅ COMPLETED ] (6 of 6 Tickets Passed & 100% Green)`

### Sprint 5 Ticket Breakdown

| Ticket ID | Task Name / User Story | Module | Git Branch | Status | Verification Command |
|---|---|---|---|---|---|
| `P5-T01` | Invisible Bot Honeypots & Submission Timing Guard | `Order`, `Common` | `feat/p5-t01-honeypot-timing` | `[x] DONE` | `php artisan test --filter=HoneypotAndTimingTest` |
| `P5-T02` | BD Phone Operator Integrity, Gibberish Entropy & Velocity Guard | `Order`, `Customer` | `feat/p5-t02-phone-velocity-guard` | `[x] DONE` | `php artisan test --filter=CustomerIntegrityTest` |
| `P5-T03` | Cross-Courier Delivery Intelligence Adapters (Steadfast & Pathao) | `Logistics`, `Order` | `feat/p5-t03-courier-intelligence` | `[x] DONE` | `php artisan test --filter=CourierIntelligenceTest` |
| `P5-T04` | Multi-Signal Order Fraud Risk Scoring Engine (0-100) | `Order` | `feat/p5-t04-fraud-risk-score` | `[x] DONE` | `php artisan test --filter=FraudRiskScoreTest` |
| `P5-T05` | Merchant Fraud Directory: Blacklist & Whitelist UI | `Customer`, `Admin` | `feat/p5-t05-fraud-directory` | `[x] DONE` | `npm run build && php artisan test --filter=FraudDirectoryAdminTest` |
| `P5-T06` | Back-Office Google reCAPTCHA & Cloudflare Turnstile Settings | `Admin`, `Common` | `feat/p5-t06-bot-security-settings` | `[x] DONE` | `npm run build && php artisan test --filter=SecuritySettingsAdminTest` |

---

### Detailed Ticket Specifications (Sprint 5 — COMPLETED)

#### `P5-T01`: Invisible Bot Honeypots & Submission Timing Guard
- **Objective**: Anti-bot honeypot trap with encrypted timing token generation, minimum submission timing guard (< 3.5s = bot flag), and `ensure.human` middleware.
- **Files**:
  - `app/Modules/Common/Security/HoneypotTimingGuardService.php`
  - `app/Modules/Common/Exceptions/BotSubmissionDetectedException.php`
  - `app/Modules/Common/Http/Middleware/EnsureHumanSubmission.php`
  - `tests/Feature/HoneypotAndTimingTest.php`
- **Acceptance Criteria**:
  - [x] Generates cryptographically encrypted timing tokens.
  - [x] Rejects submissions completed in under 3.5 seconds.
  - [x] Detects filled honeypot fields (`user_company_fax`) and aborts with 422 BotSubmissionDetectedException.
  - [x] Feature tests verified (10 passed, 27 assertions), Pint PSR-12 passed.

#### `P5-T02`: BD Phone Operator Integrity, Gibberish Entropy & Velocity Guard
- **Objective**: Bangladesh mobile phone validation rule with operator detection (GP, Banglalink, Robi/Airtel, Teletalk), dummy/repeating digit rejection, Shannon entropy gibberish detection for customer names/addresses, and order velocity throttling (max 1 active unfulfilled COD per phone, max 2 orders/day per IP/device).
- **Files**:
  - `database/migrations/2026_10_29_500201_add_fraud_metadata_to_orders_table.php`
  - `app/Modules/Customer/Rules/BangladeshPhoneNumberRule.php`
  - `app/Modules/Customer/Services/TextEntropyService.php`
  - `app/Modules/Order/DTOs/OrderVelocityResultDto.php`
  - `app/Modules/Order/Actions/CheckOrderVelocityAction.php`
  - `tests/Feature/CustomerIntegrityTest.php`
- **Acceptance Criteria**:
  - [x] Bangladesh mobile phone rule validates all operators, normalizes to local `01XXXXXXXXX` and E.164 `+8801XXXXXXXXX`, and rejects repeating/sequential dummy numbers.
  - [x] Text entropy service computes Shannon entropy in bits/char and scores gibberish (0-100) with support for English and Bengali (বাংলা) UTF-8.
  - [x] Order velocity action enforces max 1 active unfulfilled COD order per phone, and throttles burst orders to max 2/day per IP and device fingerprint.
  - [x] Feature tests verified (10 passed, 97 assertions), Pint PSR-12 passed.

#### `P5-T03`: Cross-Courier Delivery Intelligence Adapters (Steadfast & Pathao)
- **Objective**: Courier delivery intelligence adapters querying cross-courier delivery success rate and return history (Steadfast Return Rate API and Pathao Delivery Success API) with 24-hour cache layer and aggregated multi-courier risk scoring.
- **Files**:
  - `app/Modules/Logistics/Contracts/CourierDeliveryIntelligenceContract.php`
  - `app/Modules/Logistics/DTOs/CourierDeliveryProfileDto.php`
  - `app/Modules/Logistics/Services/SteadfastDeliveryIntelligenceService.php`
  - `app/Modules/Logistics/Services/PathaoDeliveryIntelligenceService.php`
  - `app/Modules/Logistics/Services/AggregatedCourierIntelligenceService.php`
  - `config/services.php`
  - `tests/Feature/CourierIntelligenceTest.php`
- **Acceptance Criteria**:
  - [x] Steadfast adapter checks return rate and delivered/cancelled counts with resilient HTTP error handling.
  - [x] Pathao adapter checks order success rate and returns profile DTO.
  - [x] Aggregated courier intelligence service combines metrics across couriers and caches profile for 24 hours.
  - [x] Returns risk tiers (`low`, `medium`, `high`, `unknown`) and flags high return rate (> 40%).
  - [x] Feature tests verified (9 passed, 50 assertions), Pint PSR-12 passed.

#### `P5-T04`: Multi-Signal Order Fraud Risk Scoring Engine (0-100)
- **Objective**: Multi-signal scoring engine aggregating Honeypots (+100), fast submission timing (+50), BD telco dummy sequences (+50), text entropy gibberish (+25/+30), order velocity limits (+35), and courier return rate (+45). Automatic tier enforcement: Low (0-29, auto-approve), Medium (30-69, mandatory OTP), High (70-100, COD disabled, advance delivery fee required), and Blacklisted (instant rejection).
- **Files**:
  - `app/Modules/Order/DTOs/OrderFraudRiskAssessmentDto.php`
  - `app/Modules/Order/Actions/CalculateOrderFraudRiskScoreAction.php`
  - `app/Modules/Order/Actions/CreateOrderFromCartAction.php`
  - `tests/Feature/FraudRiskScoreTest.php`
- **Acceptance Criteria**:
  - [x] Computes composite 0-100 risk score and resolves risk level (`low`, `medium`, `high`, `blacklisted`).
  - [x] Disables Cash on Delivery when risk score $\ge 70$ or courier return rate $> 40\%$.
  - [x] Rejects blacklisted orders with validation exception.
  - [x] Persists `fraud_risk_score` and `fraud_risk_level` directly on Order record and status history metadata.
  - [x] Feature tests verified (6 passed, 32 assertions), Pint PSR-12 passed.

#### `P5-T05`: Merchant Fraud Directory: Blacklist & Whitelist UI
- **Objective**: Merchant back-office directory to manage blacklisted customer phone numbers, abusive IPs, device fingerprints, and VIP customer whitelist with zero-friction bypass and instant bot/fraud rejection.
- **Files**:
  - `database/migrations/2026_10_29_500301_create_customer_fraud_lists_table.php`
  - `app/Modules/Customer/Models/CustomerFraudList.php`
  - `app/Modules/Customer/DTOs/CustomerFraudListCheckResultDto.php`
  - `app/Modules/Customer/Actions/AddCustomerToFraudListAction.php`
  - `app/Modules/Customer/Actions/RemoveCustomerFromFraudListAction.php`
  - `app/Modules/Customer/Actions/CheckCustomerFraudListAction.php`
  - `app/Modules/Admin/Http/Controllers/FraudDirectoryAdminController.php`
  - `resources/js/Pages/Admin/Fraud/FraudDirectory.vue`
  - `tests/Feature/FraudDirectoryAdminTest.php`
- **Acceptance Criteria**:
  - [x] Blacklist immediately flags customer as blacklisted (score 100) and blocks order creation.
  - [x] Whitelist allows trusted VIP customers to bypass fraud scoring friction (score 0, low risk).
  - [x] Admin UI provides tabbed view of blacklisted and whitelisted entries with search filter and creation modal.
  - [x] Feature tests verified (8 passed, 38 assertions), Pint PSR-12 passed, Vite build passed.

#### `P5-T06`: Back-Office Google reCAPTCHA & Cloudflare Turnstile Settings
- **Objective**: Back-office configuration interface for Google reCAPTCHA (v2 Checkbox, v3 Invisible) and Cloudflare Turnstile with encrypted database secrets, score thresholds, page-level toggles (Login, Register, Checkout, Reviews, Inquiries), and pre-flight "Test API Connection" validation.
- **Files**:
  - `database/migrations/2026_10_29_500401_create_bot_security_settings_table.php`
  - `app/Modules/Common/Models/BotSecuritySetting.php`
  - `app/Modules/Common/DTOs/CaptchaVerificationResultDto.php`
  - `app/Modules/Common/Services/BotCaptchaVerificationService.php`
  - `app/Modules/Admin/Http/Controllers/BotSecuritySettingsController.php`
  - `resources/js/Pages/Admin/Settings/BotSecuritySettings.vue`
  - `tests/Feature/SecuritySettingsAdminTest.php`
- **Acceptance Criteria**:
  - [x] Supports reCAPTCHA v2 Checkbox, v3 Invisible, and Cloudflare Turnstile.
  - [x] Provider credentials encrypted at rest (`encrypted:string`).
  - [x] Page-level activation toggles with `isPageProtected()` helper.
  - [x] Pre-flight "Test API Connection" verifies credentials against provider verify endpoint.
  - [x] Feature tests verified (9 passed, 38 assertions), Pint PSR-12 passed, Vite build passed.

---

### Detailed Ticket Specifications (Sprint 4 — COMPLETED)

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
  - [x] Order list with status tabs (`pending`, `confirmed`, `processing`, `delivered`, `cancelled`).
  - [x] Order detail view displaying customer info, cascading address, items table with COGS, monetary breakdown, and status timeline.
  - [x] Admin can transition order status with validation and reason prompt.
  - [x] Feature tests verified (4 passed, 48 assertions), Pint PSR-12 passed, Vite build passed.

---

## 6. Sprint 6 (Phase 6 — Dynamic Payment Gateway Module & Advance COD Delivery Engine — COMPLETED)

- **Target Phase**: **Phase 6: Dynamic Payment Gateway Module & Advance COD Delivery Engine**
- **Sprint Goal**: Implement an enterprise pluggable payment architecture with encrypted dynamic gateway configuration (bKash, Nagad, SSLCommerz, Stripe, COD, Bank Transfer), pre-flight "Test API Connection" validation, tokenized/hosted checkout flows, callback webhooks, transaction auditing, and advance partial delivery fee checkout engine for high-risk COD orders.
- **Sprint Status**: `[ ✅ COMPLETED ] (5 of 5 Tickets Passed & 100% Green)`

### Sprint 6 Ticket Breakdown

| Ticket ID | Task Name / User Story | Module | Git Branch | Status | Verification Command |
|---|---|---|---|---|---|
| `P6-T01` | Dynamic Payment Gateway Settings & Encrypted Credential Engine | `Payment`, `Common` | `feat/p6-t01-payment-gateway-settings` | `[x] DONE` | `php artisan test --filter=PaymentGatewaySettingsTest` |
| `P6-T02` | bKash Tokenized Checkout Adapter & Webhook Engine | `Payment` | `feat/p6-t02-bkash-gateway-adapter` | `[x] DONE` | `php artisan test --filter=BkashPaymentAdapterTest` |
| `P6-T03` | Nagad Checkout Adapter & Cryptographic Verification | `Payment` | `feat/p6-t03-nagad-gateway-adapter` | `[x] DONE` | `php artisan test --filter=NagadPaymentAdapterTest` |
| `P6-T04` | SSLCommerz Hosted Checkout & IPN Webhook Engine | `Payment` | `feat/p6-t04-sslcommerz-gateway-adapter` | `[x] DONE` | `php artisan test --filter=SslcommerzPaymentAdapterTest` |
| `P6-T05` | Advance Partial COD Delivery Fee Payment Engine & Back-Office UI | `Payment`, `Admin`, `Order` | `feat/p6-t05-payment-admin-ui` | `[x] DONE` | `npm run build && php artisan test --filter=PaymentAdminTest` |

---

### Detailed Ticket Specifications (Sprint 6 — COMPLETED)

#### `P6-T01`: Dynamic Payment Gateway Settings & Encrypted Credential Engine
- **Objective**: Dynamic payment gateway configuration schema with encrypted credentials (`encrypted:array`), active toggles, sandbox flags, gateway codes (`bkash`, `nagad`, `sslcommerz`, `stripe`, `cod`, `bank_transfer`), surcharge rules, transaction records schema, and pre-flight "Test API Connection" action.
- **Files**:
  - `database/migrations/2026_10_29_600101_create_payment_gateway_settings_table.php`
  - `database/migrations/2026_10_29_600102_create_payment_transactions_table.php`
  - `app/Modules/Payment/Models/PaymentGatewaySetting.php`
  - `app/Modules/Payment/Models/PaymentTransaction.php`
  - `app/Modules/Payment/Contracts/PaymentGatewayContract.php`
  - `app/Modules/Payment/Services/PaymentGatewayManager.php`
  - `app/Modules/Payment/Actions/TestGatewayConnectionAction.php`
  - `tests/Feature/PaymentGatewaySettingsTest.php`
- **Acceptance Criteria**:
  - [x] Gateway settings table stores credentials encrypted at rest (`encrypted:array`).
  - [x] Supports masking sensitive credentials for UI output (`••••••••b7a1`).
  - [x] Payment transactions table records ULID, order_id, gateway code, transaction_id, amount_bdt, fee_bdt, status (`pending`, `successful`, `failed`, `refunded`), and raw response payload.
  - [x] Test gateway connection action performs mock/live pre-flight handshake before activation.
  - [x] Feature tests pass (8 passed, 43 assertions), Pint PSR-12 clean.

#### `P6-T02`: bKash Tokenized Checkout Adapter & Webhook Engine
- **Objective**: Full bKash Tokenized Checkout driver implementing `PaymentGatewayContract`: grant token, create payment, execute payment, query payment, refund, and webhook callback handling.
- **Files**:
  - `app/Modules/Payment/Adapters/BkashPaymentAdapter.php`
  - `app/Modules/Payment/DTOs/PaymentInitiationResultDto.php`
  - `app/Modules/Payment/DTOs/PaymentVerificationResultDto.php`
  - `app/Modules/Payment/Http/Controllers/BkashCallbackController.php`
  - `tests/Feature/BkashPaymentAdapterTest.php`
- **Acceptance Criteria**:
  - [x] Handles grant token caching and refresh.
  - [x] Initiates payment with bKash API and returns payment URL / payment ID.
  - [x] Executes and verifies payment on callback URL with transaction logging and order status update.
  - [x] Feature tests pass (9 passed, 39 assertions), Pint PSR-12 clean.

#### `P6-T03`: Nagad Checkout Adapter & Cryptographic Verification
- **Objective**: Nagad digital payment gateway driver with RSA public/private key cryptographic signature generation, merchant authentication, payment initialization, and callback verification.
- **Files**:
  - `app/Modules/Payment/Adapters/NagadPaymentAdapter.php`
  - `app/Modules/Payment/Services/NagadCryptographicService.php`
  - `app/Modules/Payment/Http/Controllers/NagadCallbackController.php`
  - `tests/Feature/NagadPaymentAdapterTest.php`
- **Acceptance Criteria**:
  - [x] Signs sensitive payload using merchant private key and verifies Nagad public key signature.
  - [x] Initializes payment and returns Nagad redirect checkout URL.
  - [x] Handles callback confirmation and updates order payment status.
  - [x] Feature tests pass (8 passed, 36 assertions), Pint PSR-12 clean.

#### `P6-T04`: SSLCommerz Hosted Checkout & IPN Webhook Engine
- **Objective**: SSLCommerz hosted session gateway adapter, transaction initiation, IPN (Instant Payment Notification) callback listener, and MD5/validation hash verification.
- **Files**:
  - `app/Modules/Payment/Adapters/SslcommerzPaymentAdapter.php`
  - `app/Modules/Payment/Http/Controllers/SslcommerzCallbackController.php`
  - `tests/Feature/SslcommerzPaymentAdapterTest.php`
- **Acceptance Criteria**:
  - [x] Initiates session with store_id/store_passwd and returns gateway redirect URL.
  - [x] Verifies IPN / return callback payload with SSLCommerz transaction validation API.
  - [x] Marks payment transaction as successful and updates order.
  - [x] Feature tests pass (9 passed, 39 assertions), Pint PSR-12 clean.

#### `P6-T05`: Advance Partial COD Delivery Fee Payment Engine & Back-Office UI
- **Objective**: Integration of dynamic payment options into checkout, advance partial delivery fee collection (৳100/৳150) for high-risk COD orders, and full back-office Vue 3 Inertia management UI for payment gateways.
- **Files**:
  - `app/Modules/Payment/Actions/ProcessAdvanceDeliveryPaymentAction.php`
  - `app/Modules/Admin/Http/Controllers/PaymentAdminController.php`
  - `resources/js/Pages/Admin/Settings/PaymentGatewaySettings.vue`
  - `routes/web.php`
  - `tests/Feature/PaymentAdminTest.php`
- **Acceptance Criteria**:
  - [x] When fraud engine disables full COD, customer can pay partial advance delivery charge via bKash/Nagad/SSLCommerz to confirm order.
  - [x] Order status updates to `confirmed` with `payment_status = 'partial_advance'` and remaining balance marked as `payable_cod_amount_bdt`.
  - [x] Admin UI lists all payment gateways with status toggle, credentials form, test connection button, and IPN webhook URLs.
  - [x] Vue components compile cleanly, TypeScript passes, feature tests pass (7 passed, 35 assertions).

---

## 7. Sprint 7 (Phase 7 — Automated Courier Logistics, Dynamic Delivery Pricing & Payout Reconciliation — COMPLETED)

- **Target Phase**: **Phase 7: Automated Courier Logistics, Dynamic Delivery Pricing & Payout Reconciliation** (`app/Modules/Logistics/`)
- **Sprint Goal**: Scaffold multi-courier gateway adapters with encrypted database credentials for **Steadfast**, **Pathao**, **RedX**, **Paperfly**, and **Sundarban**, 1-click consignment booking, printable 4x6 thermal barcode shipping labels, live tracking webhooks, dynamic zone-based delivery pricing calculation, and automated Cash on Delivery (COD) payout reconciliation.
- **Sprint Status**: `[ ✅ COMPLETED ] (6 of 6 Tickets Passed & 100% Green)`

### Sprint 7 Ticket Breakdown

| Ticket ID | Task Name / User Story | Module | Git Branch | Status | Verification Command |
|---|---|---|---|---|---|
| `P7-T01` | Dynamic Courier Gateway Settings & Encrypted Credential Engine | `Logistics`, `Common` | `feat/p7-t01-courier-gateway-settings` | `[x] DONE` | `php artisan test --filter=CourierGatewaySettingsTest` |
| `P7-T02` | Steadfast Courier Driver & Tracking Webhook Listener | `Logistics` | `feat/p7-t02-steadfast-courier-driver` | `[x] DONE` | `php artisan test --filter=SteadfastCourierAdapterTest` |
| `P7-T03` | Pathao Courier Driver & Tracking Webhook Listener | `Logistics` | `feat/p7-t03-pathao-courier-driver` | `[x] DONE` | `php artisan test --filter=PathaoCourierAdapterTest` |
| `P7-T04` | Dynamic Zone-Based Delivery Pricing Engine | `Logistics`, `Order`, `Cart` | `feat/p7-t04-delivery-pricing-engine` | `[x] DONE` | `php artisan test --filter=DeliveryPricingEngineTest` |
| `P7-T05` | Printable 4x6 Thermal Barcode Shipping Labels & Consignment Booking | `Logistics`, `Order` | `feat/p7-t05-shipping-label-printer` | `[x] DONE` | `php artisan test --filter=ShippingLabelTest` |
| `P7-T06` | Back-Office Courier Gateway Management & COD Cash Reconciliation UI | `Logistics`, `Admin` | `feat/p7-t06-courier-admin-ui` | `[x] DONE` | `npm run build && php artisan test --filter=CourierAdminTest` |

---

### Detailed Ticket Specifications (Sprint 7)

#### `P7-T01`: Dynamic Courier Gateway Settings & Encrypted Credential Engine
- **Objective**: Create database schema for dynamic courier gateway configurations with encrypted credentials (`encrypted:array`), sandbox mode flags, live base URLs, active toggles, supported features JSON array, tracking consignment ledger table (`courier_consignments`), contract interfaces, and pre-flight "Test Courier API Connection" action.
- **Files**:
  - `database/migrations/2026_10_29_700101_create_courier_gateway_settings_table.php`
  - `database/migrations/2026_10_29_700102_create_courier_consignments_table.php`
  - `app/Modules/Logistics/Models/CourierGatewaySetting.php`
  - `app/Modules/Logistics/Models/CourierConsignment.php`
  - `app/Modules/Logistics/Contracts/CourierAdapterContract.php`
  - `app/Modules/Logistics/DTOs/CourierBookingResultDto.php`
  - `app/Modules/Logistics/DTOs/CourierTrackingResultDto.php`
  - `app/Modules/Logistics/DTOs/CourierConnectionTestResultDto.php`
  - `app/Modules/Logistics/Services/CourierGatewayManager.php`
  - `app/Modules/Logistics/Actions/TestCourierConnectionAction.php`
  - `app/Modules/Logistics/Providers/LogisticsServiceProvider.php`
  - `tests/Feature/CourierGatewaySettingsTest.php`
- **Acceptance Criteria**:
  - [x] Migration creates `courier_gateway_settings` with encrypted credentials, provider codes (`steadfast`, `pathao`, `redx`, `paperfly`, `sundarban`), sandbox toggles, and health check audit columns.
  - [x] Migration creates `courier_consignments` tracking consignment ID, tracking code, courier code, delivery status, COD amount in BDT, delivery fee, and webhook payloads.
  - [x] `CourierGatewaySetting` model masks secret keys (`••••••••b7a1`) for UI display.
  - [x] `CourierGatewayManager` dynamically resolves active adapter instance by code.
  - [x] `TestCourierConnectionAction` executes pre-flight credentials validation.
  - [x] Feature tests pass, Pint PSR-12 clean.

#### `P7-T02`: Steadfast Courier Driver & Tracking Webhook Listener
- **Objective**: Steadfast Courier API driver implementing `CourierAdapterContract`: booking orders (`POST /create_order`), status checking (`GET /status_by_cid/{id}` or `GET /status_by_invoice/{inv}`), current balance check (`GET /get_balance`), and inbound status webhook handling with order state transitions.
- **Files**:
  - `app/Modules/Logistics/Adapters/SteadfastCourierAdapter.php`
  - `app/Modules/Logistics/Http/Controllers/SteadfastWebhookController.php`
  - `tests/Feature/SteadfastCourierAdapterTest.php`
- **Acceptance Criteria**:
  - [x] Creates parcel consignment on Steadfast API with invoice number, recipient info, and COD amount.
  - [x] Queries live tracking status from Steadfast API.
  - [x] Inbound webhook listener verifies HMAC / secret token, updates `CourierConsignment` status, and transitions `Order` lifecycle (e.g. `dispatched` -> `delivered` or `delivery_failed`).
  - [x] Feature tests pass, Pint PSR-12 clean.

#### `P7-T03`: Pathao Courier Driver & Tracking Webhook Listener
- **Objective**: Pathao Courier API driver implementing `CourierAdapterContract`: Bearer token management with Redis cache TTL, store lookup, parcel creation (`POST /aladdin/api/v1/orders/issue-order`), parcel tracking (`GET /aladdin/api/v1/orders/{consignment_id}/info`), price calculation, and inbound webhook listener.
- **Files**:
  - `app/Modules/Logistics/Adapters/PathaoCourierAdapter.php`
  - `app/Modules/Logistics/Http/Controllers/PathaoWebhookController.php`
  - `tests/Feature/PathaoCourierAdapterTest.php`
- **Acceptance Criteria**:
  - [x] Obtains OAuth Bearer token from Pathao `/aladdin/api/v1/issue-token` with caching.
  - [x] Issues order parcel with store ID, recipient address, city, zone, area, and amount to collect.
  - [x] Queries parcel tracking information.
  - [x] Inbound webhook listener validates signature, maps Pathao event types (`Pickup_Requested`, `In_Transit`, `Delivered`, `Returned`) to order lifecycle.
  - [x] Feature tests pass, Pint PSR-12 clean.

#### `P7-T04`: Dynamic Zone-Based Delivery Pricing Engine
- **Objective**: Dynamic Bangladesh shipping zone engine: schema for `shipping_zones` & `shipping_rates`, mapping Bangladesh districts/thanas to zones (`inside_dhaka`, `dhaka_suburbs`, `outside_dhaka`), weight brackets, extra weight per kg pricing, and free shipping threshold.
- **Files**:
  - `database/migrations/2026_10_29_700103_create_shipping_zones_and_rates_table.php`
  - `app/Modules/Logistics/Models/ShippingZone.php`
  - `app/Modules/Logistics/Models/ShippingRate.php`
  - `app/Modules/Logistics/Actions/CalculateShippingRateAction.php`
  - `app/Modules/Logistics/DTOs/ShippingRateCalculationDto.php`
  - `tests/Feature/DeliveryPricingEngineTest.php`
- **Acceptance Criteria**:
  - [x] Zones represent Inside Dhaka (৳70), Dhaka Suburbs (৳100), and Outside Dhaka (৳130) with configurable base weights (e.g. 1.0kg) and incremental per-kg rates (৳20/kg).
  - [x] Automatically maps recipient district/thana to appropriate shipping zone.
  - [x] Grants free shipping when subtotal exceeds `free_shipping_threshold_bdt`.
  - [x] Feature tests pass, Pint PSR-12 clean.

#### `P7-T05`: Printable 4x6 Thermal Barcode Shipping Labels & Consignment Booking
- **Objective**: Consignment booking dispatcher for single and bulk orders, plus thermal label generator (4x6 inch / 100mm x 150mm & A6) with Code128 barcodes for order number and consignment ID, customer info, COD amount in BDT, and merchant details.
- **Files**:
  - `app/Modules/Logistics/Actions/BookCourierConsignmentAction.php`
  - `app/Modules/Logistics/Actions/BulkBookCourierConsignmentsAction.php`
  - `app/Modules/Logistics/Actions/GenerateShippingLabelHtmlAction.php`
  - `app/Modules/Logistics/Http/Controllers/ShippingLabelController.php`
  - `tests/Feature/ShippingLabelTest.php`
- **Acceptance Criteria**:
  - [x] Dispatches order to active courier driver, records `CourierConsignment`, updates order status to `dispatched`.
  - [x] Bulk booking dispatches multiple confirmed orders in a single request.
  - [x] Generates clean, printer-ready 4x6 thermal barcode shipping label view with barcode, COD collection box, recipient address, and merchant branding.
  - [x] Feature tests pass, Pint PSR-12 clean.

#### `P7-T06`: Back-Office Courier Gateway Management & COD Cash Reconciliation UI
- **Objective**: Comprehensive back-office courier management portal: gateway settings editor, pre-flight test connection button, zone rate editor, courier consignment status list, and automated COD cash disbursement reconciliation tracking (`courier_disbursements` and `courier_disbursement_items`).
- **Files**:
  - `database/migrations/2026_10_29_700104_create_courier_disbursements_table.php`
  - `app/Modules/Logistics/Models/CourierDisbursement.php`
  - `app/Modules/Logistics/Models/CourierDisbursementItem.php`
  - `app/Modules/Logistics/Actions/ReconcileCodPaymentAction.php`
  - `app/Modules/Admin/Http/Controllers/CourierAdminController.php`
  - `resources/js/Pages/Admin/Settings/CourierSettings.vue`
  - `resources/js/Pages/Admin/Logistics/ShippingZones.vue`
  - `resources/js/Pages/Admin/Logistics/CodReconciliation.vue`
  - `tests/Feature/CourierAdminTest.php`
- **Acceptance Criteria**:
  - [x] Admin can view, edit, and test all courier gateways (Steadfast, Pathao, RedX, Paperfly, Sundarban) with credentials masking.
  - [x] Admin can configure zone rates and weight tiers directly in Vue 3 Inertia UI.
  - [x] Admin can upload courier settlement reports and reconcile COD amounts collected vs. courier fees vs. net payouts.
  - [x] TypeScript passes (`npx vue-tsc --noEmit`), Vite production/SSR builds pass cleanly, feature tests pass.

---

## 8. Sprint 8 (Phase 8 — Omnichannel Messaging, Voice AI Verification, Social Commerce & Customer Portal — COMPLETED)

- **Target Phase**: **Phase 8: Omnichannel Messaging, Voice AI Verification, Social Commerce & Customer Portal** (`app/Modules/Communication/`, `app/Modules/Marketing/`, `app/Modules/Customer/`)
- **Sprint Goal**: Implement an enterprise omnichannel communication infrastructure: database-configured dynamic SMTP settings with pre-flight handshake, Bangladesh SMS gateway adapters (Greenweb, Reve SMS, Twilio) with medium-risk order OTP verification, WhatsApp Business API order alert triggers with digital invoice delivery, AI Automated Outbound Voice Calling Verification Agent with DTMF touch-tone (1=confirm, 2=cancel) and speech intent detection, multi-step abandoned cart recovery sequencers, social commerce product feeds (Google Merchant Center XML, Facebook Catalog CSV/XML, TikTok Shop), public self-service order tracking portal with milestone timeline, and a full back-office communication administration dashboard.
- **Sprint Status**: `[ ✅ COMPLETED ] (8 of 8 Tickets Passed & 100% Green)`

### Sprint 8 Ticket Breakdown

| Ticket ID | Task Name / User Story | Module | Git Branch | Status | Verification Command |
|---|---|---|---|---|---|
| `P8-T01` | Dynamic SMTP Mail Settings Engine & Pre-Flight Handshake | `Communication`, `Admin` | `feat/p8-t01-smtp-mail-settings` | `[x] DONE` | `php artisan test --filter=MailSettingsTest` |
| `P8-T02` | Dynamic SMS Gateway Engine (Greenweb, Reve, Twilio) & OTP Verification | `Communication`, `Order` | `feat/p8-t02-sms-gateway-otp` | `[x] DONE` | `php artisan test --filter=SmsGatewayTest` |
| `P8-T03` | WhatsApp Business API Integration & Order Lifecycle Alerts | `Communication`, `Order` | `feat/p8-t03-whatsapp-integration` | `[x] DONE` | `php artisan test --filter=WhatsAppNotificationTest` |
| `P8-T04` | AI Automated Outbound Voice Calling Verification Agent | `Communication`, `Order` | `feat/p8-t04-voice-calling-verification` | `[x] DONE` | `php artisan test --filter=VoiceCallingVerificationTest` |
| `P8-T05` | Abandoned Cart Multi-Step Recovery Engine & SMS/WhatsApp Sequencer | `Marketing`, `Cart` | `feat/p8-t05-cart-recovery-sequencer` | `[x] DONE` | `php artisan test --filter=AbandonedCartRecoveryTest` |
| `P8-T06` | Social Commerce Feeds (Google Merchant Center, Facebook, TikTok) | `Communication`, `Catalog` | `feat/p8-t06-social-commerce-feeds` | `[x] DONE` | `php artisan test --filter=SocialCommerceFeedsTest` |
| `P8-T07` | Customer Self-Service Order Tracking Portal & Storefront UX | `Customer`, `Order` | `feat/p8-t07-order-tracking-portal` | `[x] DONE` | `php artisan test --filter=OrderTrackingPortalTest` |
| `P8-T08` | Back-Office Omnichannel & Communication Management UI | `Admin`, `Communication` | `feat/p8-t08-communication-admin-ui` | `[x] DONE` | `npm run build && php artisan test --filter=CommunicationAdminTest` |

---

### Detailed Ticket Specifications (Sprint 8)

#### `P8-T01`: Dynamic SMTP Mail Settings Engine & Pre-Flight Handshake
- **Objective**: Store dynamic SMTP credentials encrypted in database (`mail_settings`), configure runtime Swift/Symfony mailer transport dynamically without requiring `.env` modification, and provide `SendTestEmailAction` for pre-flight verification.
- **Files**:
  - `database/migrations/2026_10_29_800101_create_mail_settings_table.php`
  - `app/Modules/Communication/Models/MailSetting.php`
  - `app/Modules/Communication/Actions/ConfigureDynamicMailerAction.php`
  - `app/Modules/Communication/Actions/SendTestEmailAction.php`
  - `app/Modules/Communication/DTOs/MailTestResultDto.php`
  - `tests/Feature/MailSettingsTest.php`
- **Acceptance Criteria**:
  - [x] Migration creates `mail_settings` (`driver`, `host`, `port`, `encryption`, `username`, `password` encrypted, `from_address`, `from_name`, `is_active`, `last_tested_at`, `last_test_status`, `last_test_message`).
  - [x] Action dynamically applies mail configuration to runtime mailer config (`mail.mailers.smtp`, `mail.from`).
  - [x] `SendTestEmailAction` validates live SMTP connection and sends test email.
  - [x] Feature tests pass (7 passed, 34 assertions), Pint PSR-12 clean.

#### `P8-T02`: Dynamic SMS Gateway Engine (Greenweb, Reve, Twilio) & OTP Verification
- **Objective**: Multi-provider SMS gateway architecture supporting Bangladesh providers (Greenweb, Reve SMS) and Twilio with encrypted credentials, balance checking, and OTP verification engine (generating 4-digit codes, expiration TTL, rate limiting, and verification).
- **Files**:
  - `database/migrations/2026_10_29_800201_create_sms_gateway_settings_table.php`
  - `database/migrations/2026_10_29_800202_create_sms_otps_table.php`
  - `app/Modules/Communication/Models/SmsGatewaySetting.php`
  - `app/Modules/Communication/Models/SmsOtp.php`
  - `app/Modules/Communication/Contracts/SmsGatewayContract.php`
  - `app/Modules/Communication/DTOs/SmsDispatchResultDto.php`
  - `app/Modules/Communication/DTOs/SmsBalanceResultDto.php`
  - `app/Modules/Communication/DTOs/SmsConnectionTestResultDto.php`
  - `app/Modules/Communication/DTOs/OtpVerificationResultDto.php`
  - `app/Modules/Communication/Adapters/GreenwebSmsAdapter.php`
  - `app/Modules/Communication/Adapters/ReveSmsAdapter.php`
  - `app/Modules/Communication/Adapters/TwilioSmsAdapter.php`
  - `app/Modules/Communication/Services/SmsGatewayManager.php`
  - `app/Modules/Communication/Actions/SendSmsNotificationAction.php`
  - `app/Modules/Communication/Actions/GenerateAndSendOtpAction.php`
  - `app/Modules/Communication/Actions/VerifyOtpAction.php`
  - `database/seeders/SmsGatewaySeeder.php`
  - `tests/Feature/SmsGatewayTest.php`
- **Acceptance Criteria**:
  - [x] Dynamic SMS gateway schema with encrypted credentials and masked keys (`••••••••3456`).
  - [x] Greenweb, Reve SMS, and Twilio adapters with dispatching, balance inquiry, and pre-flight connection test.
  - [x] OTP generation action creates hashed codes, enforces expiration TTL, and dispatches SMS.
  - [x] OTP verification action checks codes, increments attempt counter, and blocks brute-force attempts.
  - [x] Feature tests pass (10 passed, 55 assertions), Pint PSR-12 clean.

#### `P8-T03`: WhatsApp Business API Integration & Order Lifecycle Alerts
- **Objective**: Direct integration with Meta WhatsApp Business Cloud API for templated lifecycle alerts (Order Confirmed, Courier Dispatched, Delivered with Mushak 6.3 digital invoice link), inbound webhook verification handshake, and delivery receipt tracking.
- **Files**:
  - `database/migrations/2026_10_29_800301_create_whatsapp_settings_table.php`
  - `app/Modules/Communication/Models/WhatsAppSetting.php`
  - `app/Modules/Communication/DTOs/WhatsAppDispatchResultDto.php`
  - `app/Modules/Communication/DTOs/WhatsAppConnectionTestResultDto.php`
  - `app/Modules/Communication/Services/WhatsAppClient.php`
  - `app/Modules/Communication/Actions/SendOrderWhatsAppAlertAction.php`
  - `app/Modules/Communication/Http/Controllers/WhatsAppWebhookController.php`
  - `routes/web.php`
  - `bootstrap/app.php`
  - `tests/Feature/WhatsAppNotificationTest.php`
- **Acceptance Criteria**:
  - [x] Schema stores WhatsApp Cloud API credentials encrypted (`phone_number_id`, `access_token`, `webhook_verify_token`, `app_secret`).
  - [x] WhatsAppClient dispatches pre-approved templates and free-form messages with error handling.
  - [x] Automated action formats dynamic payloads for Confirmed, Dispatched, and Delivered orders with tracking and invoice links.
  - [x] Webhook controller handles Meta verification GET handshake and POST status updates.
  - [x] Feature tests pass (7 passed, 26 assertions), Pint PSR-12 clean.

#### `P8-T04`: AI Automated Outbound Voice Calling Verification Agent
- **Objective**: Outbound AI voice calling verification engine connecting Twilio Voice to dial customer phone numbers for Cash on Delivery (COD) orders, dual-mode DTMF touch-tone (1=confirm, 2=cancel) and speech intent detection, automated order transitions, call audit logs, and pre-flight "Make Test Call" action.
- **Files**:
  - `database/migrations/2026_10_29_800401_create_voice_calling_settings_table.php`
  - `database/migrations/2026_10_29_800402_create_voice_call_logs_table.php`
  - `app/Modules/Communication/Models/VoiceCallingSetting.php`
  - `app/Modules/Communication/Models/VoiceCallLog.php`
  - `app/Modules/Communication/Contracts/VoiceCallingContract.php`
  - `app/Modules/Communication/DTOs/VoiceCallInitiationResultDto.php`
  - `app/Modules/Communication/DTOs/VoiceCallTestResultDto.php`
  - `app/Modules/Communication/Services/TwilioVoiceAdapter.php`
  - `app/Modules/Communication/Services/VoiceCallingManager.php`
  - `app/Modules/Communication/Actions/TriggerVoiceVerificationCallAction.php`
  - `app/Modules/Communication/Actions/TestVoiceCallAction.php`
  - `app/Modules/Communication/Http/Controllers/VoiceCallWebhookController.php`
  - `routes/web.php`
  - `bootstrap/app.php`
  - `tests/Feature/VoiceCallingVerificationTest.php`
- **Acceptance Criteria**:
  - [x] Database schema for voice settings with encrypted credentials and call logs table.
  - [x] Twilio Voice adapter initiates outbound calls with TwiML webhook URL and status callbacks.
  - [x] Pre-flight "Make Test Call" action tests live outbound connectivity.
  - [x] Webhook renders dynamic bilingual Bangla prompt with `<Gather numDigits="1">`.
  - [x] DTMF 1 immediately transitions order to confirmed; DTMF 2 cancels order and logs reason.
  - [x] Feature tests pass (8 passed, 43 assertions), Pint PSR-12 clean.

#### `P8-T05`: Abandoned Cart Multi-Step Recovery Engine & SMS/WhatsApp Sequencer
- **Objective**: Automated multi-step cart recovery sequencer scanning abandoned carts and triggering reminders at 1 hour (Gentle Reminder), 6 hours (Urgency trigger), and 24 hours (Incentive coupon COMEBACK5) via WhatsApp/SMS, with conversion tracking and Artisan scheduler command.
- **Files**:
  - `database/migrations/2026_10_29_800501_create_cart_recovery_logs_table.php`
  - `app/Modules/Marketing/Models/CartRecoveryLog.php`
  - `app/Modules/Cart/Models/Cart.php`
  - `app/Modules/Marketing/Actions/ProcessCartRecoverySequencesAction.php`
  - `app/Modules/Marketing/Actions/MarkCartAsRecoveredAction.php`
  - `app/Modules/Marketing/Commands/ProcessCartRecoveryCommand.php`
  - `app/Modules/Marketing/Providers/MarketingServiceProvider.php`
  - `app/Modules/Order/Actions/CreateOrderFromCartAction.php`
  - `tests/Feature/AbandonedCartRecoveryTest.php`
- **Acceptance Criteria**:
  - [x] Schema records cart recovery steps (1, 2, 3), channels (SMS, WhatsApp), incentive coupons, and conversion timestamps.
  - [x] Sequencer evaluates elapsed cart idle time and dispatches progressive recovery messages.
  - [x] Converting cart to order automatically marks recovery log as recovered with order reference.
  - [x] Artisan command `cart:process-recovery` outputs detailed dispatch summary.
  - [x] Feature tests pass (6 passed, 27 assertions), Pint PSR-12 clean.

---

## 9. Active Sprint: Sprint 9 (Phase 9 — Comprehensive NBR VAT, Full Statutory Tax Compliance & RMA Return Engine)

- **Target Phase**: **Phase 9: Comprehensive NBR VAT, Full Statutory Tax Compliance & RMA Return Engine** (`app/Modules/TaxCompliance/`, `app/Modules/Order/`, `app/Modules/Inventory/`)
- **Sprint Goal**: Implement an enterprise Bangladesh National Board of Revenue (NBR) VAT & statutory tax compliance engine under the Value Added Tax and Supplementary Duty Act 2012 and Income Tax Act 2023:
  1. Non-gap sequential Mushak 6.3 Tax Invoice generation (`M6.3-YYYYMM-XXXXXX`) with atomic row-level serial locks, tax rate breakdowns (15% Standard, 5% Retail Trade, Supplementary Duty, Exempt), and SHA-256 historical integrity freezing.
  2. Monthly Mushak 9.1 VAT Return compiler aggregating Sales Output VAT, Import Advance Tax (AT - 5% from BOE), Local Supplier Input VAT (verified vendor Mushak 6.3), decreasing adjustments (Mushak 6.7 Credit Notes), and increasing adjustments (Mushak 6.8 Debit Notes) to calculate net payable VAT / carry-forward tax credits.
  3. Statutory Withholding Tax (VDS) engine with automated Mushak 6.6 Certificates (`M6.6-YYYYMM-XXXXXX`) on domestic procurement with BIN verification.
  4. Inter-depot stock transfers via statutory Mushak 6.5 Transfer Challans (`M6.5-YYYYMM-XXXXXX`) with transport carrier details and multi-warehouse integrity.
  5. Customer Sales Return (RMA) lifecycle with statutory Mushak 6.7 Credit Notes (বিক্রয় ফেরত চালানপত্র - হ্রাসকারী সমন্বয় দলিল), COGS inventory restock reversal, and automated decreasing tax adjustment ledger entries.
  6. Vendor Purchase Returns via Mushak 6.8 Debit Notes (ক্রয় ফেরত চালানপত্র - বৃদ্ধিকারী সমন্বয় দলিল), statutory NBR registers (Mushak 6.1 Purchase Register & Mushak 6.2/6.2.1 Sales Register), and back-office Tax Compliance Administration portal with printable official NBR document layouts.
- **Sprint Status**: `[ ✅ COMPLETED ] (6 of 6 Tickets Passed & 100% Green)`

### Sprint 9 Ticket Breakdown

| Ticket ID | Task Name / User Story | Module | Git Branch | Status | Verification Command |
|---|---|---|---|---|---|
| `P9-T01` | Statutory Mushak 6.3 Tax Invoice Generation with Atomic Serial Locks | `TaxCompliance`, `Order` | `feat/p9-t01-mushak-6-3-invoice` | `[x] DONE` | `php artisan test --filter=Mushak63InvoiceTest` |
| `P9-T02` | Monthly VAT Return (Mushak 9.1) & Automated Input Tax Credit (রেয়াত) Engine | `TaxCompliance`, `Procurement` | `feat/p9-t02-mushak-9-1-vat-return` | `[x] DONE` | `php artisan test --filter=Mushak91VatReturnTest` |
| `P9-T03` | Statutory Withholding Tax (VDS) & Automated Mushak 6.6 Certificates | `TaxCompliance`, `Procurement` | `feat/p9-t03-mushak-6-6-vds` | `[x] DONE` | `php artisan test --filter=Mushak66VdsTest` |
| `P9-T04` | Inter-Depot Stock Transfers via Statutory Mushak 6.5 Challans | `TaxCompliance`, `Inventory` | `feat/p9-t04-mushak-6-5-transfer-challan` | `[x] DONE` | `php artisan test --filter=Mushak65TransferChallanTest` |
| `P9-T05` | Sales Returns (RMA), Statutory Credit Notes (Mushak 6.7) & Decreasing Tax Adjustments | `TaxCompliance`, `Order`, `Inventory` | `feat/p9-t05-mushak-6-7-credit-notes` | `[x] DONE` | `php artisan test --filter=RmaCreditNoteTest` |
| `P9-T06` | Vendor Purchase Returns (Mushak 6.8), Statutory Registers (6.1/6.2) & Admin Tax Portal | `TaxCompliance`, `Admin` | `feat/p9-t06-mushak-registers-admin-ui` | `[x] DONE` | `npm run build && php artisan test --filter=TaxComplianceAdminTest` |

---

### Detailed Ticket Specifications (Sprint 9)

#### `P9-T01`: Statutory Mushak 6.3 Tax Invoice Generation with Atomic Serial Locks
- **Objective**: Mandatory statutory Mushak 6.3 Tax Invoice generation with atomic row-level mutex locks (`mushak_sequences`) guaranteeing zero non-gap numbering sequences formatted as `M6.3-YYYYMM-XXXXXX`. Freezes invoice item breakdown (quantity, unit value, SD rate/amount, VAT rate/amount, total payable) with SHA-256 historical cryptographic integrity hash.
- **Files**:
  - `database/migrations/2026_10_30_900101_create_mushak_sequences_table.php`
  - `database/migrations/2026_10_30_900102_create_mushak_6_3_invoices_table.php`
  - `database/migrations/2026_10_30_900103_create_mushak_6_3_items_table.php`
  - `app/Modules/TaxCompliance/Models/MushakSequence.php`
  - `app/Modules/TaxCompliance/Models/Mushak63Invoice.php`
  - `app/Modules/TaxCompliance/Models/Mushak63Item.php`
  - `app/Modules/TaxCompliance/Services/MushakSequenceService.php`
  - `app/Modules/TaxCompliance/DTOs/Mushak63ItemDto.php`
  - `app/Modules/TaxCompliance/DTOs/Mushak63GenerationResultDto.php`
  - `app/Modules/TaxCompliance/Actions/GenerateMushak63InvoiceAction.php`
  - `app/Modules/TaxCompliance/Actions/VerifyMushak63IntegrityAction.php`
  - `app/Modules/TaxCompliance/Providers/TaxComplianceServiceProvider.php`
  - `bootstrap/providers.php`
  - `tests/Feature/Mushak63InvoiceTest.php`
- **Acceptance Criteria**:
  - [x] Generates sequential `M6.3-YYYYMM-XXXXXX` invoice serial without gaps under atomic row lock (`lockForUpdate`).
  - [x] Computes line-by-line VAT (15% standard or 5% retail trader), SD, and exempt categories.
  - [x] Generates SHA-256 integrity hash snapshot that can be verified against tampering.
  - [x] Automatically links generated Mushak 6.3 invoice to the parent Order.
  - [x] Feature tests pass (5 passed, 33 assertions), Pint PSR-12 clean.

#### `P9-T02`: Monthly VAT Return (Mushak 9.1) & Automated Input Tax Credit (রেয়াত) Engine
- **Objective**: Automated compiler for Bangladesh NBR Monthly VAT Return (Mushak 9.1). Aggregates outward sales VAT from `mushak_6_3_invoices`, Import Advance Tax (AT - 5%) from `bills_of_entry`, and Local Supplier Input VAT from verified `local_purchase_orders` / vendor Mushak 6.3 invoices as rebatable input credits to compute net tax payable / refundable carry-forward balance.
- **Files**:
  - `database/migrations/2026_10_30_900201_create_mushak_9_1_returns_table.php`
  - `app/Modules/TaxCompliance/Models/Mushak91Return.php`
  - `app/Modules/TaxCompliance/DTOs/Mushak91SummaryDto.php`
  - `app/Modules/TaxCompliance/Actions/GenerateMushak91ReturnAction.php`
  - `app/Modules/TaxCompliance/Actions/SubmitMushak91ReturnAction.php`
  - `tests/Feature/Mushak91VatReturnTest.php`
- **Acceptance Criteria**:
  - [x] Compiles Part 3 (Outward Supplies / Output VAT) from all Mushak 6.3 invoices in the tax period.
  - [x] Compiles Part 4 (Input Tax Credit / রেয়াত) combining Import Advance Tax (AT) and Local Supplier VAT.
  - [x] Compiles Part 5 (Adjustments: decreasing adjustments from returns, increasing adjustments from debit notes).
  - [x] Computes Part 6 (Net Payable VAT or Refundable Carry-Forward).
  - [x] Allows locking / submitting the return with reference code and submission date.
  - [x] Feature tests pass (3 passed, 27 assertions), Pint PSR-12 clean.

#### `P9-T03`: Statutory Withholding Tax (VDS) & Automated Mushak 6.6 Certificates
- **Objective**: Statutory withholding VAT (VDS) deduction engine for local procurement. Generates statutory Mushak 6.6 Certificates (`M6.6-YYYYMM-XXXXXX`) for domestic suppliers with verified 9/13-digit BIN, records treasury deposit challan number and date, and calculates exact VDS amounts.
- **Files**:
  - `database/migrations/2026_10_30_900301_create_mushak_6_6_certificates_table.php`
  - `app/Modules/TaxCompliance/Models/Mushak66Certificate.php`
  - `app/Modules/TaxCompliance/DTOs/Mushak66GenerationResultDto.php`
  - `app/Modules/TaxCompliance/Actions/GenerateMushak66CertificateAction.php`
  - `tests/Feature/Mushak66VdsTest.php`
- **Acceptance Criteria**:
  - [x] Generates sequential `M6.6-YYYYMM-XXXXXX` certificate number with atomic serial lock.
  - [x] Validates supplier 9 or 13 digit BIN.
  - [x] Links to Local Purchase Order and records supplier details, Mushak 6.3 reference, base value, VAT, and VDS withheld.
  - [x] Records government treasury challan deposit details.
  - [x] Feature tests pass (6 passed, 29 assertions), Pint PSR-12 clean.

#### `P9-T04`: Inter-Depot Stock Transfers via Statutory Mushak 6.5 Challans
- **Objective**: Statutory Mushak 6.5 Goods Transfer Challan (পণ্য স্থানান্তর চালানপত্র) engine for moving trading stock between physical company warehouses. Assigns sequential challans (`M6.5-YYYYMM-XXXXXX`), records transport vehicle and driver details, item valuation, and integrates with warehouse stock dispatch and receipt.
- **Files**:
  - `database/migrations/2026_10_30_900401_create_mushak_6_5_challans_table.php`
  - `database/migrations/2026_10_30_900402_create_mushak_6_5_items_table.php`
  - `app/Modules/TaxCompliance/Models/Mushak65Challan.php`
  - `app/Modules/TaxCompliance/Models/Mushak65Item.php`
  - `app/Modules/TaxCompliance/Actions/GenerateMushak65ChallanAction.php`
  - `tests/Feature/Mushak65TransferChallanTest.php`
- **Acceptance Criteria**:
  - [x] Generates sequential `M6.5-YYYYMM-XXXXXX` challan serial.
  - [x] Records source warehouse BIN/address and destination warehouse BIN/address.
  - [x] Records carrier vehicle registration number, driver name, and phone.
  - [x] Freezes item quantities, unit cost valuation, and total transferred value.
  - [x] Interlocks with `WarehouseTransfer` model.
  - [x] Feature tests pass (4 passed, 29 assertions), Pint PSR-12 clean.

#### `P9-T05`: Sales Returns (RMA), Statutory Credit Notes (Mushak 6.7) & Decreasing Tax Adjustments
- **Objective**: Complete Return Merchandise Authorization (RMA) workflow and statutory Mushak 6.7 Credit Note (বিক্রয় ফেরত চালানপত্র - হ্রাসকারী সমন্বয় দলিল) engine. Reverses COGS into warehouse inventory, links directly to parent Mushak 6.3 invoice, logs statutory decreasing tax adjustment in VAT ledger, and executes customer refund.
- **Files**:
  - `database/migrations/2026_10_30_900501_create_rmas_and_credit_notes_tables.php`
  - `app/Modules/TaxCompliance/Models/ReturnMerchandiseAuthorization.php`
  - `app/Modules/TaxCompliance/Models/RmaItem.php`
  - `app/Modules/TaxCompliance/Models/Mushak67CreditNote.php`
  - `app/Modules/TaxCompliance/Models/Mushak67Item.php`
  - `app/Modules/TaxCompliance/Actions/CreateRmaRequestAction.php`
  - `app/Modules/TaxCompliance/Actions/ProcessRmaInspectionAction.php`
  - `app/Modules/TaxCompliance/Actions/IssueMushak67CreditNoteAction.php`
  - `tests/Feature/RmaCreditNoteTest.php`
- **Acceptance Criteria**:
  - [x] RMA state machine: `requested` -> `approved` -> `inspected` -> `restocked` -> `refunded` / `rejected`.
  - [x] Mushak 6.7 Credit Note generated with sequential `M6.7-YYYYMM-XXXXXX` number, linking to original Mushak 6.3 invoice serial.
  - [x] Computes decreasing tax adjustment (হ্রাসকারী সমন্বয়) and marks credit note for Mushak 9.1 reporting.
  - [x] Restocks approved items atomically to warehouse inventory (reversing COGS) or marks as damaged quarantine stock.
  - [x] Disburses customer refund with audit transaction log.
  - [x] Feature tests pass (5 passed, 34 assertions), Pint PSR-12 clean.

#### `P9-T06`: Vendor Purchase Returns (Mushak 6.8), Statutory Registers (6.1/6.2) & Admin Tax Portal
- **Objective**: Vendor purchase returns via statutory Mushak 6.8 Debit Notes (ক্রয় ফেরত চালানপত্র - বৃদ্ধিকারী সমন্বয় দলিল) with increasing tax adjustment, NBR statutory registers (Mushak 6.1 Purchase Register & Mushak 6.2/6.2.1 Sales Register), and comprehensive Vue 3 Inertia back-office tax compliance admin portal with printable official NBR forms.
- **Files**:
  - `database/migrations/2026_10_30_900601_create_mushak_6_8_debit_notes_tables.php`
  - `app/Modules/TaxCompliance/Models/Mushak68DebitNote.php`
  - `app/Modules/TaxCompliance/Models/Mushak68Item.php`
  - `app/Modules/TaxCompliance/Actions/IssueMushak68DebitNoteAction.php`
  - `app/Modules/TaxCompliance/Actions/GenerateMushak61RegisterAction.php`
  - `app/Modules/TaxCompliance/Actions/GenerateMushak62RegisterAction.php`
  - `app/Modules/Admin/Http/Controllers/TaxComplianceAdminController.php`
  - `resources/js/Pages/Admin/TaxCompliance/Mushak63Index.vue`
  - `resources/js/Pages/Admin/TaxCompliance/Mushak63Show.vue`
  - `resources/js/Pages/Admin/TaxCompliance/Mushak91Index.vue`
  - `resources/js/Pages/Admin/TaxCompliance/Mushak66Index.vue`
  - `resources/js/Pages/Admin/TaxCompliance/RmaIndex.vue`
  - `resources/js/Pages/Admin/TaxCompliance/MushakRegisters.vue`
  - `routes/web.php`
  - `tests/Feature/TaxComplianceAdminTest.php`
- **Acceptance Criteria**:
  - [x] Mushak 6.8 Debit Note generated with sequential `M6.8-YYYYMM-XXXXXX` serial, linking to vendor invoice/PO.
  - [x] Mushak 6.1 Purchase Register generates opening, inward receipts (imports + local), issues, and closing balances.
  - [x] Mushak 6.2 Sales Register generates outward sales ledger with VAT and SD breakdown.
  - [x] Back-office admin views render with printable official bilingual NBR headers, BIN, tax circles, and statutory tables.
  - [x] TypeScript passes (`npx vue-tsc --noEmit`), Vite production/SSR builds pass cleanly, feature tests pass (8 passed, 102 assertions).

---

---

## 10. Active Sprint: Sprint 10 (Phase 10 — Backend AI Intelligence, Trading P&L Analytics, Audit Trail & Final QA)

- **Target Phase**: **Phase 10: Backend AI Intelligence, Trading P&L Analytics, Audit Trail & Final Automated QA** (`app/Modules/AI/`, `app/Modules/Admin/`)
- **Sprint Goal**: Implement an enterprise backend AI intelligence engine and executive analytics system for the hybrid trading platform:
  1. Multi-provider AI Intelligence engine with dynamic settings (`ai_model_settings`) and encrypted API keys supporting Google Gemini, OpenAI, Anthropic Claude, and Ollama/Local LLMs with pre-flight connection verification and token usage logging (`ai_usage_logs`).
  2. Automated AI operational capabilities: 1-click catalog SEO enrichment from imported specs, smart restocking demand forecasting & reorder points, customer dispute/RMA support reply drafting, and statutory tax/return anomaly detection.
  3. Executive Trading Profit & Loss (P&L) Analytics engine aggregating dual-sourcing landed COGS in BDT (৳), gross sales turnover, VAT offsets, shipping loss/gain, payment gateway fees, and net trading margins.
  4. Immutable enterprise Audit Logging engine (`audit_logs`) tracking price changes, stock adjustments, order state changes, and statutory tax entries with JSON diffs and admin inspector.
  5. Back-Office Admin UI: AI Provider Configuration & Feature Assignment (`AiSettings.vue`), Executive Trading P&L Analytics Dashboard (`TradingPnL.vue`), and Audit Trail Inspector (`AuditLogIndex.vue`).
  6. End-to-end integration, 100% green full test regression, zero TypeScript diagnostics, clean production/SSR builds, and official Release `v1.0.0` milestone tagging.
- **Sprint Status**: `[ ⚙️ IN PROGRESS ] (0 of 6 Tickets Completed)`

### Sprint 10 Ticket Breakdown

| Ticket ID | Task Name / User Story | Module | Git Branch | Status | Verification Command |
|---|---|---|---|---|---|
| `P10-T01` | Dynamic Multi-Provider AI Engine & Credentials Architecture | `AI`, `Admin` | `feat/p10-t01-ai-provider-engine` | `[x] DONE` | `php artisan test --filter=AiIntelligenceTest` |
| `P10-T02` | AI Catalog Enrichment, Restocking Insights & Dispute Assistant Facilities | `AI`, `Catalog`, `Procurement` | `feat/p10-t02-ai-facilities` | `[x] DONE` | `php artisan test --filter=AiFacilitiesTest` |
| `P10-T03` | Executive Trading Profit & Loss (P&L) Analytics Engine | `Admin`, `Order`, `Procurement` | `feat/p10-t03-trading-pnl-analytics` | `[ ] TODO` | `php artisan test --filter=TradingPnLAnalyticsTest` |
| `P10-T04` | Immutable Enterprise Audit Logging Engine | `Admin` | `feat/p10-t04-audit-trail-engine` | `[ ] TODO` | `php artisan test --filter=AuditLogTest` |
| `P10-T05` | Back-Office AI, Trading P&L Analytics & Audit Trail UI Portal | `Admin`, `AI` | `feat/p10-t05-ai-analytics-admin-ui` | `[ ] TODO` | `npm run build && php artisan test --filter=AdminAiAndAnalyticsTest` |
| `P10-T06` | Full System Regression, Production SSR Verification & Release `v1.0.0` | `All Modules` | `feat/p10-t06-full-regression-v1` | `[ ] TODO` | `./vendor/bin/pint --test && npx vue-tsc --noEmit && npm run build:ssr && php artisan test` |

---

### Detailed Ticket Specifications (Sprint 10)

#### `P10-T01`: Dynamic Multi-Provider AI Engine & Credentials Architecture
- **Objective**: Pluggable AI engine supporting Google Gemini, OpenAI, Claude, and Ollama with encrypted credentials (`ai_model_settings`), pre-flight API test connection handshake, token usage metering (`ai_usage_logs`), and runtime feature assignment.
- **Files**:
  - `database/migrations/2026_10_30_100101_create_ai_model_settings_table.php`
  - `database/migrations/2026_10_30_100102_create_ai_usage_logs_table.php`
  - `app/Modules/AI/Models/AiModelSetting.php`
  - `app/Modules/AI/Models/AiUsageLog.php`
  - `app/Modules/AI/Contracts/AiProviderContract.php`
  - `app/Modules/AI/Services/AiManager.php`
  - `app/Modules/AI/Services/Providers/GeminiProviderAdapter.php`
  - `app/Modules/AI/Services/Providers/OpenAiProviderAdapter.php`
  - `app/Modules/AI/Services/Providers/ClaudeProviderAdapter.php`
  - `app/Modules/AI/Services/Providers/OllamaProviderAdapter.php`
  - `app/Modules/AI/DTOs/AiPromptRequestDto.php`
  - `app/Modules/AI/DTOs/AiResponseDto.php`
  - `app/Modules/AI/DTOs/AiConnectionTestResultDto.php`
  - `app/Modules/AI/Actions/TestAiConnectionAction.php`
  - `app/Modules/AI/Actions/RecordAiUsageAction.php`
  - `app/Modules/AI/Providers/AiServiceProvider.php`
  - `tests/Feature/AiIntelligenceTest.php`
- **Acceptance Criteria**:
  - [x] Schema stores provider settings (`gemini`, `openai`, `claude`, `ollama`) with encrypted credentials array.
  - [x] Schema logs token usage (prompt tokens, completion tokens, estimated cost USD/BDT).
  - [x] Pre-flight `TestAiConnectionAction` tests outbound API connectivity and returns latency.
  - [x] Feature tests pass with mocked HTTP responses, Pint PSR-12 clean.

#### `P10-T02`: AI Catalog Enrichment, Restocking Insights & Dispute Assistant Facilities
- **Objective**: High-value AI facilities powered by the unified AI manager:
  1. Product Catalog & SEO Enrichment: Generates meta title, description, keywords, bullet points, and extracted specifications from raw import spec sheets.
  2. Smart Restocking & Demand Forecasting: Analyzes sales velocity, lead times, safety stock, and calculates suggested reorder quantities.
  3. Customer Support & Dispute Assistant: Formulates professional, empathetic bilingual replies for RMA return requests and dispute resolution.
  4. Statutory Tax & Return Anomaly Detection: Detects unusual spikes in Mushak 6.7 Credit Notes or tax variances.
- **Files**:
  - `app/Modules/AI/Actions/EnrichProductSeoAction.php`
  - `app/Modules/AI/Actions/GenerateRestockInsightsAction.php`
  - `app/Modules/AI/Actions/DraftCustomerSupportReplyAction.php`
  - `app/Modules/AI/Actions/DetectTaxAnomaliesAction.php`
  - `app/Modules/AI/DTOs/ProductSeoEnrichmentDto.php`
  - `app/Modules/AI/DTOs/RestockInsightDto.php`
  - `tests/Feature/AiFacilitiesTest.php`
- **Acceptance Criteria**:
  - [x] Catalog enrichment generates structured SEO titles, descriptions, and feature bullet points.
  - [x] Restocking insights action computes suggested PO order quantity considering inventory on hand and safety stock.
  - [x] Customer dispute assistant drafts contextual replies based on RMA condition and policy.
  - [x] Tax anomaly detection identifies abnormal credit note ratios.
  - [x] Feature tests pass, Pint PSR-12 clean.

#### `P10-T03`: Executive Trading Profit & Loss (P&L) Analytics Engine
- **Objective**: Executive Trading Profit & Loss (P&L) Analytics engine calculating true commercial trading profitability in Bangladeshi Taka (৳ BDT) across import and local sourcing:
  - Gross Sales Turnover (B2C + B2B)
  - Output VAT & Net Revenue
  - True Landed Cost of Goods Sold (COGS in BDT from landed cost allocation)
  - Gross Trading Margin (BDT and %)
  - Logistics Cost vs Delivery Fees Collected (Shipping Gain/Loss)
  - Payment Gateway Fees & MFS surcharges
  - RMA Damaged Scrap Losses
  - Net Operating Margin (BDT and %)
  - Category and Top-Performing Variant margin rankings
- **Files**:
  - `app/Modules/Admin/Actions/CalculateTradingPnLAction.php`
  - `app/Modules/Admin/DTOs/TradingPnLSummaryDto.php`
  - `tests/Feature/TradingPnLAnalyticsTest.php`
- **Acceptance Criteria**:
  - [ ] Calculates accurate Gross Revenue, Net Revenue, COGS, Gross Profit, and Gross Margin %.
  - [ ] Deducts payment processing fees, shipping variances, and scrapped RMA losses to determine Net Trading Profit.
  - [ ] Breaks down profitability by product category and top SKUs.
  - [ ] Feature tests pass, Pint PSR-12 clean.

#### `P10-T04`: Immutable Enterprise Audit Logging Engine
- **Objective**: Tamper-proof enterprise audit log system recording critical operational events across the trading platform:
  - Price modifications (base price, variant selling price, wholesale tier pricing)
  - Physical inventory stock adjustments and discrepancies
  - Order state transitions and manual cancellations
  - Statutory NBR VAT document creations and adjustments
  - Merchant fraud blacklist/whitelist additions
- **Files**:
  - `database/migrations/2026_10_30_100401_create_audit_logs_table.php`
  - `app/Modules/Admin/Models/AuditLog.php`
  - `app/Modules/Admin/Actions/LogAuditTrailAction.php`
  - `tests/Feature/AuditLogTest.php`
- **Acceptance Criteria**:
  - [ ] Schema captures user, IP address, user agent, event, auditable type/ID, old values JSON, and new values JSON.
  - [ ] Service records mutations atomically without interrupting business transactions.
  - [ ] Feature tests pass, Pint PSR-12 clean.

#### `P10-T05`: Back-Office AI, Trading P&L Analytics & Audit Trail UI Portal
- **Objective**: Vue 3 Inertia back-office administration interfaces:
  1. AI Settings Portal (`AiSettings.vue`): Multi-provider configuration, API key encryption, feature-to-model mapping, pre-flight test connection button, and usage token log table.
  2. Trading P&L Analytics Dashboard (`TradingPnL.vue`): Period selectors (Today, This Week, Month, Quarter, Custom), executive metric summary cards, margin waterfall charts, and category profitability table.
  3. Audit Trail Inspector (`AuditLogIndex.vue`): Searchable audit trail with event filters, date range filters, and visual old-vs-new JSON diff inspector.
- **Files**:
  - `app/Modules/Admin/Http/Controllers/AiAdminController.php`
  - `app/Modules/Admin/Http/Controllers/TradingPnLAdminController.php`
  - `app/Modules/Admin/Http/Controllers/AuditLogAdminController.php`
  - `resources/js/Pages/Admin/AI/AiSettings.vue`
  - `resources/js/Pages/Admin/Analytics/TradingPnL.vue`
  - `resources/js/Pages/Admin/Audit/AuditLogIndex.vue`
  - `resources/js/Layouts/AdminLayout.vue`
  - `routes/web.php`
  - `tests/Feature/AdminAiAndAnalyticsTest.php`
- **Acceptance Criteria**:
  - [ ] Responsive Vue 3 Inertia views styled with Sass (`<style lang="scss" scoped>`).
  - [ ] Admin sidebar contains navigation links for AI Engine, Trading P&L, and Audit Trails.
  - [ ] Controllers deliver verified data; tests pass, TypeScript passes (`vue-tsc --noEmit`), Vite compiles cleanly.

#### `P10-T06`: Full System Regression, Production SSR Verification & Release `v1.0.0`
- **Objective**: Final enterprise production verification across all 10 phases. Full automated regression suite execution, PSR-12 compliance check, TypeScript verification, production client and SSR asset compilation, and official Release `v1.0.0` tagging.
- **Acceptance Criteria**:
  - [ ] 100% test pass rate across the complete application test suite (~340+ tests).
  - [ ] Zero Pint style errors (`./vendor/bin/pint --test`).
  - [ ] Zero TypeScript errors (`npx vue-tsc --noEmit`).
  - [ ] Production Vite build (`npm run build`) and SSR build (`npm run build:ssr`) compile with zero warnings or errors.
  - [ ] Merge to `main` branch, update `TASK_BOARD.md` and `CHANGELOG.md`, and tag `v1.0.0`.

