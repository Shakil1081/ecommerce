# Enterprise Single-Vendor E-Commerce Platform
## Comprehensive Phase-by-Phase Master Development Plan (`PHASE_PLAN.md`)

This master development plan defines the complete execution roadmap, modular architecture, exact file creations, domain actions, database schemas, event-driven integrations, and verification checklists for all **10 Phases** of the commercial hybrid trading platform.

---

## 1. Master Architecture & Dependency Flow

To guarantee rock-solid maintainability and prevent database foreign key or service dependency bottlenecks, the phases are strictly structured according to their natural domain dependency chain:

```text
┌─────────────────────────────────────────────────────────────────────────────────────────────────┐
│                                   DOMAIN EXECUTION DEPENDENCY PIPELINE                          │
├─────────────────────────────────────────────────────────────────────────────────────────────────┤
│                                                                                                 │
│  [Phase 1: Foundation, Infrastructure, Spatie RBAC, Dynamic Multi-Cloud Storage & SEO]          │
│     │  (Laravel 13, Vue 3, Inertia SSR, SCSS Tokens, Spatie Permission & MediaLibrary,          │
│     │   Multi-Cloud Storage Engine [S3/R2/GCS/Local], Back-Office RBAC Management UI)            │
│     ▼                                                                                           │
│  [Phase 2: Core Catalog, Matrix Variants, Multi-Warehouse Inventory & Reviews]                  │
│     │  (Categories, Brands, Products, Matrix Variants, Pricing Tiers, Warehouses, BD Locations, │
│     │   Product Reviews & Ratings, Inter-Depot Transfers via Mushak 6.5 Challans)               │
│     ▼                                                                                           │
│  [Phase 3: Dual Sourcing Procurement & True Landed Cost COGS Engine]                            │
│     │  (Global Imports/BOE, Local BDT POs, Landed Cost Allocation, Barcode GRN, VDS/TDS)        │
│     ▼                                                                                           │
│  [Phase 4: Cart Engine, Coupons, Order State Machine & Atomic Stock Reservation]                │
│     │  (Slide-out Cart Drawer, Coupons & Discounts, Redis Atomic DECRBY Stock TTL,              │
│     │   Order Transitions, Idempotency Protection)                                              │
│     ▼                                                                                           │
│  [Phase 5: 7-Layer Anti-Fraud, Fake Order Defense & Dynamic Bot Security Settings]              │
│     │  (Courier API Return Rates, Honeypots, Submission Timing Guards, Risk Scoring 0-100,      │
│     │   Merchant Fraud Directory [Blacklist/Whitelist], Dynamic reCAPTCHA & Turnstile)          │
│     ▼                                                                                           │
│  [Phase 6: Dynamic Payment Gateway Module & Advance COD Delivery Engine]                       │
│     │  (bKash Tokenized, Nagad, SSLCommerz, Stripe, Encrypted Keys, Pre-Flight Connection Test, │
│     │   Advance Delivery Charge Engine for High-Risk COD Orders)                                │
│     ▼                                                                                           │
│  [Phase 7: Automated Courier Logistics, Dynamic Delivery Pricing & Payout Reconciliation]       │
│     │  (Steadfast, Pathao, RedX, 4x6 Thermal Barcode Labels, Zones & Weight Matrix,             │
│     │   COD Remittance Batch Reconciliation)                                                    │
│     ▼                                                                                           │
│  [Phase 8: Omnichannel Messaging, Voice Calling AI, Customer Portal, Marketing & Storefront]    │
│     │  (Dynamic SMTP, SMS/WhatsApp OTP, AI Voice Verification Agent, Customer Account Portal,   │
│     │   B2B Trade Accounts, Abandoned Cart Sequences, Loyalty Points, Sub-50ms Search)          │
│     ▼                                                                                           │
│  [Phase 9: Bangladesh NBR VAT, Full Statutory Tax Compliance & RMA Return Engine]               │
│     │  (Atomic Mushak 6.3, RMA Return & Damaged Inspection Lifecycle, Mushak 6.7 Credit Notes,  │
│     │   Input Tax Credit AT 5% + Local VAT, Mushak 9.1 Return, Statutory 6.1/6.2 Registers)     │
│     ▼                                                                                           │
│  [Phase 10: Backend AI Intelligence, Trading P&L Analytics, Audit Trail & Automated Pest QA]    │
│        (Gemini/OpenAI Restock & SEO, Real-time P&L Dashboard, Tamper-Proof Audit Logging,        │
│         Pest PHP Feature Test Suite, TypeScript Strictness, Pint PSR-12)                        │
│                                                                                                 │
└─────────────────────────────────────────────────────────────────────────────────────────────────┘
```

---

## 2. Cross-Module Domain Event Bus Specification

All business domains communicate asynchronously across module boundaries via immutable domain events, eliminating tight coupling:

| Event Class | Dispatched By | Target Module | Concrete Listeners Triggered | Business Outcome |
|---|---|---|---|---|
| `OrderPlacedEvent` | `Order` | `Inventory`, `Order`, `Communication` | `ReserveInventoryStockListener`<br>`EvaluateOrderFraudRiskListener`<br>`TriggerOrderPlacedAlertsListener` | Atomically locks stock with 15m TTL; initiates 7-signal fraud analysis; queues SMS/Voice verification. |
| `FraudEvaluationCompletedEvent` | `Order` | `Order`, `Payment` | `EnforceFraudRiskTierListener` | Low Risk (<30) $\rightarrow$ direct to warehouse picking.<br>Medium (30-70) $\rightarrow$ triggers OTP verification.<br>High (>70) $\rightarrow$ disables COD; enforces advance delivery charge. |
| `PaymentCompletedEvent` | `Payment` | `Order`, `TaxCompliance`, `Inventory` | `MarkOrderAsPaidListener`<br>`IssueMushak6_3VatInvoiceListener`<br>`CommitInventoryDeductionListener` | Updates order state to confirmed; generates statutory non-gap Mushak 6.3 invoice; commits physical inventory deduction. |
| `ConsignmentDispatchedEvent` | `Logistics` | `Order`, `Communication` | `UpdateOrderToShippedListener`<br>`SendConsignmentTrackingAlertListener` | Sets status to `shipped`; dispatches customer SMS/WhatsApp tracking URL with Steadfast/Pathao tracking ID. |
| `ConsignmentDeliveredEvent` | `Logistics` | `Order`, `Logistics`, `Marketing` | `MarkOrderDeliveredListener`<br>`QueueCodReconciliationListener`<br>`AccrueCustomerLoyaltyPointsListener` | Flags order as `delivered`; adds COD cash collection to pending courier payout batch; credits customer reward points. |
| `ReturnInspectionCompletedEvent` | `Order` | `Inventory`, `TaxCompliance`, `Payment`, `Customer` | `ProcessRestockOrQuarantineListener`<br>`IssueMushak6_7CreditNoteListener`<br>`TriggerDynamicRefundListener`<br>`FlagRepeatCourierReturnListener` | Restocks approved items or routes to damaged quarantine; issues statutory Mushak 6.7 Credit Note; disburses gateway refund; updates customer risk score. |
| `GoodsReceivedNoteCompletedEvent` | `Procurement` | `Inventory`, `TaxCompliance` | `InwardInventoryDepotStockListener`<br>`RecordMushak6_1PurchaseEntryListener` | Inwards physical goods into warehouse depot with barcode logging; writes entry into statutory Mushak 6.1 register. |
| `CartAbandonedEvent` | `Cart` | `Marketing`, `Communication` | `QueueAbandonedCartRecoveryListener` | Enqueues automated multi-step WhatsApp/SMS recovery sequence (1h gentle, 24h discount, 72h urgency). |
| `PromotionalCampaignDispatchedEvent` | `Communication` | `Communication`, `Marketing` | `QueueBtrcCompliantSmsBatchListener`<br>`TrackCampaignDeliveryListener` | Batches bulk SMS within legal 9 AM - 8 PM window; records delivery status and conversion tracking cookies. |
| `StorageSettingsUpdatedEvent` | `Admin` | `Common` | `RebindMediaStorageDiskListener` | Dynamically re-binds filesystem disks at runtime across local, S3, R2, or GCS without application restart. |

---

## 3. Order & Return State Machine Specifications

### A. Legal Order State Machine
```text
[pending_verification]
       │
       ├── (Low Risk: Score < 30) ───────────────────────────────────► [confirmed]
       │                                                                   │
       ├── (Medium Risk: Score 30-70) ──► [otp_pending] ──(OTP Valid)─────┤
       │                                                                   │
       └── (High Risk: Score > 70) ────► [advance_payment_pending] ───────┤
                                                │ (bKash/Nagad Paid)       │
                                                ▼                          ▼
                                          [confirmed] ─────────────► [picking]
                                                                        │
                                                                        ▼
                                                                     [packed]
                                                                        │
                                                                        ▼
                                                                    [consigned]
                                                                        │
                                                                        ▼
                                                                    [in_transit]
                                                                        │
                                           ┌────────────────────────────┴───────────────────────────┐
                                           ▼                                                        ▼
                                      [delivered]                                         [returned_to_depot]
                                           │                                                        │
                                    (Cash Reconciled)                                     (Physical Inspection)
                                           │                                                        │
                                           ▼                                                        ▼
                                       [closed]                                              [rma_processing]
```

### B. NBR-Compliant RMA & Sales Return State Machine
```text
[Customer / Courier Return Request]
              │
              ▼
    [RMA Request Created]
              │
              ▼
   [Physical Inward Inspection]
              │
     ┌────────┴──────────────────────────┐
     ▼                                   ▼
[Accepted: Restock]            [Accepted: Damaged Quarantine]
     │                                   │
     ├─────────────────┬─────────────────┤
                       ▼
         [Mushak 6.7 Credit Note Issued]
         (Decreasing Adjustment in VAT 9.1)
                       │
                       ▼
          [Financial Refund Disbursed]
        (bKash / Nagad / Card / Credit)
                       │
                       ▼
               [RMA Completed]
```

### C. High-Concurrency Stock Reservation Protocol
1. **Cart Checkout Submission**:
   - Executes Redis atomic decrement `DECRBY inventory:stock:{variant_id} {qty}`.
   - If balance $< 0$, transaction rolls back and informs user of instant stock-out.
   - Sets Redis key `reservation:{order_id}:{variant_id}` with TTL of `900` seconds (15 minutes).
2. **Order Finalization (Confirmation or Payment Complete)**:
   - Removes temporary reservation key.
   - Executes database transaction with pessimistic row lock (`DB::table('product_variants')->where('id', $id)->lockForUpdate()->decrement(...)`).
3. **Checkout Abandonment / Cancellation / OTP Expiry**:
   - Scheduled watchdog or event triggers `INCRBY inventory:stock:{variant_id} {qty}` to restore atomic stock counter.

---

## 4. Phase-by-Phase Master Execution Blueprint

---

### Phase 1: Foundation, Infrastructure, Spatie MediaLibrary, Dynamic Multi-Cloud Storage Engine, Spatie RBAC & Core SEO

#### Phase Metadata & Readiness Matrix
- **Phase Status**: `[ ⏳ NOT STARTED ]`
- **Prerequisites**: PHP 8.3+, Composer, Node.js 20+, MySQL 8.0+, Redis.
- **Estimated Complexity**: High (6 Sub-Milestones)
- **Primary Domain Modules**: `app/Modules/Common/`, `app/Modules/Seo/`, `app/Modules/Admin/`
- **Target Artisan Migrations**: Spatie Permission tables, Spatie Media table, Media Storage Settings table.
- **Key Verification Command**: `php artisan test --filter=InfrastructureTest && npm run build:ssr`

#### 1. Objectives
- Establish the Laravel 13.x application with strict types (`declare(strict_types=1);`) and PSR-12 code style via Laravel Pint.
- Configure Inertia.js v2 with Server-Side Rendering (SSR) for instant search engine indexing.
- Setup Vue 3 with TypeScript and exclusively Sass/SCSS design tokens auto-injected in Vite.
- Setup Domain-Driven Modular architecture (`app/Modules/`) with PSR-4 autoloading in `composer.json`.
- Implement **Dynamic Multi-Cloud Storage Engine** (`media_storage_settings`): Back-office dropdown allowing admins to switch between **Local Server**, **Google Cloud Storage (GCS)**, **Amazon AWS S3**, **Cloudflare R2** (zero egress fees), or **MinIO** with pre-flight connection test.
- Integrate Spatie Laravel MediaLibrary with Intervention Image v3 for automated WebP/AVIF conversions and blurhash generation.
- Implement **Role-Based Access Control (RBAC)** via Spatie Laravel Permission with a full back-office UI for custom roles, grouped domain permissions matrix, and staff user assignments.
- Establish base storefront and admin layouts with dynamic `<Head>` metadata and Schema.org JSON-LD rich snippets.

#### 2. Detailed Micro-Milestones & File Creations
- **Milestone 1.1: Core Infrastructure & Tooling**:
  - `composer.json`: Map `"App\\Modules\\": "app/Modules/"`, install `spatie/laravel-permission:^6.0`, `spatie/laravel-medialibrary:^11.0`, `intervention/image:^3.0`, `inertiajs/inertia-laravel:^2.0`, `league/flysystem-aws-s3-v3:^3.0`, `spatie/flysystem-google-cloud-storage:^2.0`.
  - `package.json`: Install `@inertiajs/vue3`, `@inertiajs/vue3/server`, `vue`, `sass`, `typescript`, `vue-tsc`, `@vitejs/plugin-vue`, `pinia`.
  - `vite.config.ts`: Configured with Vue plugin, Inertia SSR entry, and SCSS `additionalData: '@use "@/scss/abstracts/variables" as *; @use "@/scss/abstracts/mixins" as *;'`.
  - `tsconfig.json`: Strict TypeScript settings (`noImplicitAny: true`, `strictNullChecks: true`).
  - `.pint.json`: PSR-12 formatting rules.
- **Milestone 1.2: SCSS Modular Design System (`resources/scss/`)**:
  - `resources/scss/abstracts/_variables.scss`: HSL design tokens, typography scales, spacing, shadows, radius, breakpoints.
  - `resources/scss/abstracts/_mixins.scss`: Breakpoint media queries, flex/grid helpers, glassmorphism, button elevation.
  - `resources/scss/abstracts/_functions.scss`: Unit conversions, color adjustments.
  - `resources/scss/base/_reset.scss`: CSS modern reset.
  - `resources/scss/base/_typography.scss`: Google fonts import (Outfit / Inter), typography scale.
  - `resources/scss/components/_buttons.scss`: Primary, secondary, outline, danger button styles.
  - `resources/scss/components/_cards.scss`: Product, dashboard, and metric card styles.
  - `resources/scss/app.scss`: Main stylesheet entry point.
- **Milestone 1.3: Inertia SSR & Frontend Shell (`resources/js/`)**:
  - `resources/js/app.ts`: Client-side Inertia app initializer with Pinia.
  - `resources/js/ssr.ts`: Server-Side Rendering entry point for web crawlers.
  - `resources/js/Layouts/StorefrontLayout.vue`: Customer layout with header, footer, SEO `<Head>`.
  - `resources/js/Layouts/AdminLayout.vue`: Back-office layout with sidebar, topbar, notifications.
  - `resources/js/Layouts/CheckoutLayout.vue`: Distraction-free checkout layout.
- **Milestone 1.4: Dynamic Multi-Cloud Storage Engine (`media_storage_settings`)**:
  - `database/migrations/xxxx_create_media_storage_settings_table.php`: `id`, `default_disk` (`local`, `s3`, `r2`, `gcs`, `minio`), `is_active`, `credentials` (encrypted JSON: `bucket`, `region`, `key`, `secret`, `endpoint`, `cdn_url`), `updated_by_user_id`, `updated_at`.
  - `database/migrations/xxxx_create_media_table.php`: Spatie MediaLibrary table.
  - `app/Modules/Common/Actions/TestStorageConnectionAction.php`: Handshake action uploading `storage_test_[ulid].txt`, testing read, public CDN check, deleting, measuring latency.
  - `app/Modules/Common/Actions/UpdateMediaStorageSettingsAction.php`: Updates settings and dynamically rebinds filesystem disk.
  - `app/Modules/Common/Commands/MigrateMediaDiskCommand.php`: Artisan command `php artisan media:migrate-disk --from=local --to=s3`.
  - `app/Modules/Common/Services/MediaService.php`: Responsive breakpoint conversion generator (`thumb_300`, `card_600`, `gallery_1200`, `zoom_2000`) with WebP/AVIF and blurhash generation.
  - `resources/js/Pages/Admin/Settings/MediaStorageSettings.vue`: Storage driver dropdown, credentials input, "Test Connection" button, and asset migration progress bar.
- **Milestone 1.5: Role-Based Access Control (RBAC) & Back-Office UI**:
  - `database/migrations/xxxx_create_permission_tables.php`: Spatie Permission migration.
  - `database/seeders/RolesAndPermissionsSeeder.php`: Seeds pre-configured trading roles (`Super Admin`, `Store Operations Manager`, `Procurement & Import Officer`, `Warehouse & Inventory Keeper`, `Accounts & NBR Tax Officer`, `Logistics & Fulfillment Dispatcher`, `Customer Care & Fraud Agent`).
  - `app/Modules/Admin/Http/Controllers/RoleController.php`: Back-office role CRUD controller.
  - `app/Modules/Admin/Http/Controllers/StaffUserController.php`: Staff user management controller.
  - `app/Modules/Admin/Actions/SyncRolePermissionsAction.php`: Atomic sync of permissions per functional domain.
  - `resources/js/Pages/Admin/Roles/RoleIndex.vue`: Role list and action controls.
  - `resources/js/Pages/Admin/Roles/RoleForm.vue`: Interactive permission matrix grouped by functional modules with "Select All in Module" toggles.
  - `resources/js/Pages/Admin/Staff/StaffIndex.vue` & `StaffForm.vue`: Assign roles and staff permission overrides.
- **Milestone 1.6: SEO & Metadata Engine (`app/Modules/Seo/`)**:
  - `app/Modules/Seo/DTOs/MetaTagData.php`: Immutable DTO for title, description, OG, Twitter Card.
  - `app/Modules/Seo/Services/SchemaGeneratorService.php`: JSON-LD generator for `Product`, `BreadcrumbList`, `Organization`, `FAQPage`.
  - `app/Modules/Seo/Http/Controllers/SitemapController.php`: Generates dynamic `/sitemap.xml` and `/robots.txt`.
- **Milestone 1.7: In-App System Version & Release Log Viewer (`app/Modules/Admin/`)**:
  - `app/Modules/Admin/Http/Controllers/SystemInfoController.php`: Parses `CHANGELOG.md` and reports current system version, server environment, and active git commit.
  - `resources/js/Pages/Admin/Settings/SystemUpdates.vue`: Visual release notes timeline modal and system update history browser for store administrators.
  - `.github/workflows/release.yml`: Automated GitHub release generation from semantic version tags (`v*.*.*`).

#### 3. Verification & Acceptance Criteria
- `php artisan test` runs cleanly.
- `npm run build` and `npm run build:ssr` compile with zero Sass/TypeScript warnings.
- Public page source contains server-rendered HTML and Schema.org JSON-LD script blocks.
- Pre-flight "Test Storage Connection" successfully writes, tests, and deletes test files with latency reporting.
- Media upload pipeline successfully outputs WebP, AVIF, and calculates BlurHash strings.
- Custom roles and grouped permissions can be created, edited, and assigned through the Admin UI.

---

### Phase 2: Core Catalog, Matrix Variants, Multi-Warehouse Inventory, Reviews & Inter-Depot Transfers

#### Phase Metadata & Readiness Matrix
- **Phase Status**: `[ ⏳ NOT STARTED ]`
- **Prerequisites**: Phase 1
- **Estimated Complexity**: High (5 Sub-Milestones)
- **Primary Domain Modules**: `app/Modules/Catalog/`, `app/Modules/Inventory/`, `app/Modules/Pricing/`
- **Target Artisan Migrations**: 11 tables
- **Key Verification Command**: `php artisan test --filter=CatalogAndInventoryTest`

#### 1. Objectives
- Create the product taxonomy, multi-dimensional matrix variant manager (Size $\times$ Color $\times$ Material), brands, and HS codes.
- Implement retail (B2C) and wholesale trade (B2B tier pricing with MOQ) pricing engines in BDT (৳).
- Implement Customer Reviews, Star Ratings, and customer photo upload galleries.
- Model multi-warehouse inventory hubs (Central Hub, Regional Depots) with bin location, low-stock reorder thresholds, and physical stock tracking.
- Build Inter-Depot Stock Transfer engine with statutory Mushak 6.5 Transfer Challan generation.
- Seed standard Bangladesh administrative boundary hierarchy (8 Divisions $\rightarrow$ 64 Districts $\rightarrow$ 495+ Thanas).

#### 2. Detailed Micro-Milestones & File Creations
- **Milestone 2.1: Bangladesh Geographic Boundary Tables**:
  - `database/migrations/xxxx_create_bangladesh_divisions_table.php`: `id`, `name_en`, `name_bn`, `is_active`.
  - `database/migrations/xxxx_create_bangladesh_districts_table.php`: `id`, `division_id`, `name_en`, `name_bn`, `zone_type` (`inside_dhaka`, `dhaka_suburbs`, `outside_dhaka`).
  - `database/migrations/xxxx_create_bangladesh_thanas_table.php`: `id`, `district_id`, `name_en`, `name_bn`, `post_code`.
  - `database/seeders/BangladeshGeoLocationsSeeder.php`: Complete 64 districts and 495+ thanas seeder.
- **Milestone 2.2: Catalog Taxonomy & Customer Reviews (`app/Modules/Catalog/`)**:
  - `database/migrations/xxxx_create_categories_table.php`: `id`, `name`, `slug`, `parent_id`, `image`, `is_featured`, `sort_order`, `seo_title`, `seo_description`.
  - `database/migrations/xxxx_create_brands_table.php`: `id`, `name`, `slug`, `logo`, `origin_country`, `is_active`.
  - `database/migrations/xxxx_create_products_table.php`: `id` (ULID), `category_id`, `brand_id`, `name`, `slug`, `hs_code`, `warranty_policy`, `is_published`, `is_featured`, `description`, `specifications` (JSON).
  - `database/migrations/xxxx_create_product_variants_table.php`: `id` (ULID), `product_id`, `sku`, `barcode_ean`, `attributes` (JSON, e.g. `{"color":"Black","size":"XL"}`), `purchase_cost_bdt`, `selling_price_bdt`, `weight_kg`, `low_stock_threshold`.
  - `database/migrations/xxxx_create_product_reviews_table.php`: `id` (ULID), `product_id`, `user_id` (nullable), `order_id` (nullable), `rating` (1-5), `customer_name`, `review_text`, `is_verified_purchase`, `status` (`pending`, `approved`, `rejected`), `created_at`.
  - `app/Modules/Catalog/Actions/ModerateProductReviewAction.php`: Admin approval/rejection of customer reviews and photo uploads.
  - `app/Modules/Catalog/Models/Category.php`, `Brand.php`, `Product.php`, `ProductVariant.php`, `ProductReview.php`.
- **Milestone 2.3: Pricing Engine & Wholesale B2B Tiers (`app/Modules/Pricing/`)**:
  - `database/migrations/xxxx_create_pricing_tiers_table.php`: `id`, `product_variant_id`, `customer_group` (`retail`, `wholesale_b2b`), `min_quantity` (MOQ), `tier_price_bdt`.
  - `app/Modules/Pricing/Actions/CalculateApplicablePriceAction.php`: Resolves unit price based on user tier and order volume.
- **Milestone 2.4: Multi-Warehouse Inventory & Inter-Depot Transfers (`app/Modules/Inventory/`)**:
  - `database/migrations/xxxx_create_warehouses_table.php`: `id`, `name`, `code` (`CENTRAL-DHAKA`, `CHITTAGONG-HUB`), `address`, `contact_number`, `is_active`.
  - `database/migrations/xxxx_create_inventory_stocks_table.php`: `id`, `warehouse_id`, `product_variant_id`, `physical_quantity`, `reserved_quantity`, `available_quantity`, `bin_location`.
  - `database/migrations/xxxx_create_warehouse_transfers_table.php`: `id` (ULID), `transfer_number` (`TR-YYYYMM-XXXXXX`), `source_warehouse_id`, `destination_warehouse_id`, `mushak_6_5_serial`, `status` (`draft`, `in_transit`, `received`, `cancelled`), `initiated_by_user_id`, `received_by_user_id`, `notes`.
  - `database/migrations/xxxx_create_warehouse_transfer_items_table.php`: `id`, `transfer_id`, `product_variant_id`, `quantity`.
  - `app/Modules/Inventory/Actions/AdjustInventoryStockAction.php`: Atomic inventory adjustments with audit trail reason.
  - `app/Modules/Inventory/Actions/QueryAvailableStockAction.php`: Real-time stock status across all hubs.
  - `app/Modules/Inventory/Actions/TransferWarehouseStockAction.php`: Atomically reserves stock from source warehouse, marks transfer `in_transit`, and generates Mushak 6.5 Challan.
  - `app/Modules/Inventory/Actions/ReceiveWarehouseTransferAction.php`: Confirms intake at destination warehouse and commits stock.
- **Milestone 2.5: Admin Matrix Variant Manager UI**:
  - `resources/js/Pages/Admin/Catalog/ProductMatrixEditor.vue`: Bulk matrix grid generator for Size $\times$ Color combinations, SKUs, and barcodes.
  - `resources/js/Pages/Admin/Inventory/StockTransfers.vue`: Inter-depot stock transfer manager and Mushak 6.5 generator.

#### 3. Verification & Acceptance Criteria
- Seeder populates all 64 districts and thanas with correct English/Bangla titles.
- Matrix generator generates variant combinations without duplicate SKUs.
- Inter-depot stock transfers atomically deduct source stock, generate Mushak 6.5 Challans, and commit to destination stock upon receiving.
- Customer reviews support 1-5 star ratings, photo attachments via MediaLibrary, and verified buyer badges.

---

### Phase 3: Dual Sourcing Procurement (Import & Local) & Landed Cost Engine

#### Phase Metadata & Readiness Matrix
- **Phase Status**: `[ ⏳ NOT STARTED ]`
- **Prerequisites**: Phase 2
- **Estimated Complexity**: High (4 Sub-Milestones)
- **Primary Domain Modules**: `app/Modules/Procurement/`, `app/Modules/TaxCompliance/`
- **Target Artisan Migrations**: 6 tables
- **Key Verification Command**: `php artisan test --filter=ProcurementAndLandedCostTest`

#### 1. Objectives
- Implement **Global Commercial Importation** tracking (CI, PI, LC/TT, Bill of Entry clearance at Chattogram/Dhaka ICD).
- Implement **Local Domestic Procurement** (BDT POs, domestic supplier Mushak 6.3 invoices, 9/13-digit BIN verification).
- Build Unified Landed Cost Engine: Converts foreign FX at settlement rates + international freight + marine insurance + port C&F + customs duties (CD, RD, SD, AT, AIT) or domestic carrying into 100% accurate unit COGS in BDT (৳).
- Implement Inward Receiving (GRN) into multi-warehouse depots with USB/Bluetooth barcode scanning and automated Mushak 6.6 (VDS Certificates) & TDS deduction.

#### 2. Detailed Micro-Milestones & File Creations
- **Milestone 3.1: Supplier Directory & Commercial Invoices**:
  - `database/migrations/xxxx_create_suppliers_table.php`: `id`, `name`, `type` (`international`, `local`), `country`, `currency`, `bin_number`, `tin_number`, `contact_person`, `bank_details`, `payment_terms`.
  - `database/migrations/xxxx_create_import_purchase_orders_table.php`: `id` (ULID), `supplier_id`, `pi_number`, `lc_number`, `issuing_bank`, `fx_currency`, `fx_rate_settlement`, `fob_amount_fx`, `status`.
  - `database/migrations/xxxx_create_bills_of_entry_table.php`: `id`, `import_po_id`, `boe_number` (বিল অফ এন্ট্রি), `boe_date`, `port_of_clearance`, `customs_assessment_value_bdt`, `cd_amount`, `rd_amount`, `sd_amount`, `advance_tax_at_amount` (5%), `advance_income_tax_ait_amount` (5%).
- **Milestone 3.2: Domestic Purchase Orders & Mushak 6.3 Registration**:
  - `database/migrations/xxxx_create_local_purchase_orders_table.php`: `id` (ULID), `supplier_id`, `po_number`, `supplier_mushak_6_3_number`, `supplier_mushak_date`, `subtotal_bdt`, `vat_amount`, `vds_amount`, `tds_amount`, `carrying_cost_bdt`, `status`.
  - `app/Modules/Procurement/Actions/VerifySupplierBinAction.php`: Validates domestic supplier BIN format.
- **Milestone 3.3: Inward Goods Receiving (GRN) & Multi-Depot Stock Intake**:
  - `database/migrations/xxxx_create_goods_received_notes_table.php`: `id` (ULID), `source_type` (`import`, `local`), `source_id`, `warehouse_id`, `grn_number`, `received_date`, `received_by_user_id`, `discrepancy_notes`.
  - `database/migrations/xxxx_create_grn_items_table.php`: `id`, `grn_id`, `product_variant_id`, `quantity_ordered`, `quantity_received`, `quantity_damaged`, `batch_number`.
  - `app/Modules/Procurement/Actions/ReceiveGoodsNoteAction.php`: Updates warehouse stock, logs barcode scans, and dispatches `GoodsReceivedNoteCompletedEvent`.
- **Milestone 3.4: True Landed Cost Allocation Engine & Tax Withholding**:
  - `database/migrations/xxxx_create_landed_cost_allocations_table.php`: `id`, `grn_id`, `total_procurement_cost_bdt`, `total_freight_c_and_f_bdt`, `total_duties_bdt`, `allocated_cogs_per_unit_bdt`.
  - `app/Modules/Procurement/Actions/CalculateImportLandedCostAction.php`: Computes exact unit COGS from BOE tariffs and FX rates.
  - `app/Modules/Procurement/Actions/CalculateLocalLandedCostAction.php`: Computes unit COGS including domestic freight and unloading.
  - `app/Modules/TaxCompliance/Actions/IssueMushak6_6VdsAction.php`: Generates statutory VDS certificates for domestic purchases.
  - `app/Modules/TaxCompliance/Actions/DeductProcurementTdsAction.php`: Computes withholding tax under the Income Tax Act 2023.
- **Milestone 3.5: Back-Office Procurement UI**:
  - `resources/js/Pages/Admin/Procurement/ImportOrderManager.vue`: CI, LC/TT, and BOE tariff manager.
  - `resources/js/Pages/Admin/Procurement/LocalPoManager.vue`: Domestic PO, Mushak 6.3 recording, VDS calculation.
  - `resources/js/Pages/Admin/Procurement/GrnReceiving.vue`: Warehouse intake with USB barcode scanner integration.

#### 3. Verification & Acceptance Criteria
- Unit COGS in BDT (৳) matches 100% of Landed Cost calculation for both import shipments and domestic POs.
- Inward GRN intake reflects in warehouse physical inventory batches.
- VDS certificates (Mushak 6.6) generate with correct legal withholding calculations.

---

### Phase 4: Cart Engine, Coupons, Order State Machine & Atomic Stock Reservation

#### Phase Metadata & Readiness Matrix
- **Phase Status**: `[ ⏳ NOT STARTED ]`
- **Prerequisites**: Phase 2, Phase 3
- **Estimated Complexity**: High (4 Sub-Milestones)
- **Primary Domain Modules**: `app/Modules/Cart/`, `app/Modules/Order/`, `app/Modules/Marketing/`
- **Target Artisan Migrations**: 6 tables
- **Key Verification Command**: `php artisan test --filter=CartAndOrderTest`

#### 1. Objectives
- Implement persistent cart engine with slide-out drawer, dynamic Free Shipping progress bar, and 1-click upsells.
- Implement promotional Coupons engine (fixed/percentage discount, min order value, usage limits).
- Implement the formal Order State Machine (`pending_verification` $\rightarrow$ `confirmed` $\rightarrow$ `picking` $\rightarrow$ `packed` $\rightarrow$ `consigned` $\rightarrow$ `delivered`).
- Enforce high-concurrency stock reservation protocol: Redis atomic `DECRBY` with 15-minute TTL during checkout.
- Guarantee transaction idempotency (`X-Idempotency-Key`) across order placement and checkout submissions.

#### 2. Detailed Micro-Milestones & File Creations
- **Milestone 4.1: Persistent Cart & Coupon Engine (`app/Modules/Cart/` & `app/Modules/Marketing/`)**:
  - `database/migrations/xxxx_create_carts_table.php`: `id` (ULID), `user_id` (nullable), `session_id`, `coupon_code`, `created_at`, `updated_at`.
  - `database/migrations/xxxx_create_cart_items_table.php`: `id`, `cart_id`, `product_variant_id`, `quantity`, `unit_price_bdt`.
  - `database/migrations/xxxx_create_coupons_table.php`: `id` (ULID), `code`, `discount_type` (`fixed_bdt`, `percentage`), `discount_value`, `min_order_amount_bdt`, `max_discount_bdt`, `max_uses_total`, `max_uses_per_customer`, `starts_at`, `expires_at`, `is_active`.
  - `database/migrations/xxxx_create_coupon_usages_table.php`: `id`, `coupon_id`, `user_id`, `order_id`, `discount_amount_bdt`, `used_at`.
  - `app/Modules/Cart/Actions/AddToCartAction.php`: Validates available stock and adds item to cart.
  - `app/Modules/Cart/Actions/CalculateFreeShippingProgressAction.php`: Calculates amount remaining for free delivery threshold.
  - `app/Modules/Marketing/Actions/ApplyCouponAction.php`: Validates coupon rules, calculates discount, attaches to cart.
- **Milestone 4.2: Order Schema & Order Items (`app/Modules/Order/`)**:
  - `database/migrations/xxxx_create_orders_table.php`: `id` (ULID), `order_number` (e.g. `ORD-2026-000001`), `customer_id` (nullable for guests), `customer_name`, `customer_phone`, `customer_email`, `delivery_address`, `division_id`, `district_id`, `thana_id`, `subtotal_bdt`, `delivery_fee_bdt`, `discount_amount_bdt`, `total_payable_bdt`, `order_status`, `payment_status`, `payment_method`, `idempotency_key`, `created_at`.
  - `database/migrations/xxxx_create_order_items_table.php`: `id`, `order_id`, `product_variant_id`, `product_name`, `variant_sku`, `unit_cogs_bdt`, `unit_price_bdt`, `quantity`, `line_total_bdt`.
  - `database/migrations/xxxx_create_order_status_histories_table.php`: `id`, `order_id`, `from_status`, `to_status`, `changed_by_user_id`, `notes`, `created_at`.
- **Milestone 4.3: Order State Machine & Stock Reservation Engine**:
  - `app/Modules/Order/Enums/OrderStatus.php`: PHP 8.3 backed enum with legal transitions.
  - `app/Modules/Order/Actions/TransitionOrderStatusAction.php`: Validates transition rules and logs audit history.
  - `app/Modules/Inventory/Actions/ReserveStockForCheckoutAction.php`: Atomic Redis `DECRBY` with 15-minute TTL.
  - `app/Modules/Inventory/Actions/CommitStockDeductionAction.php`: Permanent DB stock decrement upon confirmation.
  - `app/Modules/Inventory/Actions/ReleaseExpiredStockReservationAction.php`: Reverts expired reservations.
- **Milestone 4.4: Order Placement Action**:
  - `app/Modules/Order/Actions/CreateOrderAction.php`: Wraps order creation, stock reservation, and `OrderPlacedEvent` dispatch in database transaction with idempotency protection.

#### 3. Verification & Acceptance Criteria
- Concurrent checkout requests cannot over-sell limited inventory (Redis atomic lock verified).
- Submitting identical `X-Idempotency-Key` returns original order response without double billing.
- Order state machine throws exception on illegal state jumps (e.g. `pending` directly to `delivered`).
- Coupons enforce minimum order limits, expiry dates, and single-use constraints.

---

### Phase 5: 7-Layer Anti-Fraud, Fake Order Defense & Dynamic Bot Security Settings

#### Phase Metadata & Readiness Matrix
- **Phase Status**: `[ ⏳ NOT STARTED ]`
- **Prerequisites**: Phase 4
- **Estimated Complexity**: High (5 Sub-Milestones)
- **Primary Domain Modules**: `app/Modules/Order/`, `app/Modules/Customer/`, `app/Modules/Admin/`
- **Target Artisan Migrations**: 3 tables
- **Key Verification Command**: `php artisan test --filter=AntiFraudShieldTest`

#### 1. Objectives
- Implement 7-Layer anti-fraud defense to protect against fake COD orders, serial refusers, and competitor inventory locking.
- Real-time pre-flight query against courier delivery history APIs (Steadfast / Pathao / FraudCheckBD).
- Honeypot bot traps, client submission timing guards, and disposable email blocklists.
- Tiered automated enforcement (Auto-Approve, 4-digit SMS/WhatsApp OTP, or Mandatory Advance Delivery Charge).
- Searchable Merchant Fraud Directory (`customer_fraud_lists`) with 1-click customer blacklisting upon courier return.
- Admin-configurable Google reCAPTCHA v2/v3 and Cloudflare Turnstile bot protection (`security_settings`).

#### 2. Detailed Micro-Milestones & File Creations
- **Milestone 5.1: Database Migrations for Fraud Shield & Security Settings**:
  - `database/migrations/xxxx_create_customer_fraud_lists_table.php`: `id` (ULID), `list_type` (`blacklist`, `whitelist`, `watchlist`), `identifier_type` (`phone`, `ip_address`, `email`, `device_fingerprint`), `identifier_value`, `risk_score_override`, `reason`, `notes`, `created_by_user_id`.
  - `database/migrations/xxxx_create_order_fraud_evaluations_table.php`: `id`, `order_id`, `risk_score` (0-100), `risk_tier` (`low`, `medium`, `high`, `blacklisted`), `triggered_signals` (JSON), `courier_delivery_rate`, `carrier_name`, `ip_geo_country`, `is_vpn_or_proxy`, `created_at`.
  - `database/migrations/xxxx_create_security_settings_table.php`: `id`, `provider` (`recaptcha_v2`, `recaptcha_v3`, `turnstile`), `is_active`, `site_key`, `secret_key` (encrypted), `score_threshold` (0.5), `enable_on_login`, `enable_on_register`, `enable_on_checkout`, `enable_on_reviews`, `enable_on_inquiries`.
- **Milestone 5.2: Signal Analyzers & Courier Intelligence**:
  - `app/Modules/Order/Actions/ValidateBangladeshMobileAction.php`: Strict operator regex (`+8801[3-9]\d{8}`) and repetitive digit check (`01711111111`).
  - `app/Modules/Order/Actions/QueryCourierDeliveryHistoryAction.php`: Handshake with Steadfast and Pathao delivery rate APIs.
  - `app/Modules/Order/Actions/EvaluateAddressEntropyAction.php`: Shannon entropy and keyboard mash filter.
  - `app/Modules/Order/Actions/EnforceOrderVelocityAction.php`: Max 1 active unfulfilled COD per phone number; max 2 orders/day per IP/device fingerprint.
- **Milestone 5.3: Composite Risk Engine & Enforcement**:
  - `app/Modules/Order/Actions/CalculateOrderFraudRiskScoreAction.php`: Aggregates weighted risk points (0–100) across all 7 signals.
  - `app/Modules/Order/Actions/EnforceFraudRiskTierAction.php`:
    - Low Risk (0–29): Direct to warehouse picking.
    - Medium Risk (30–69): Dispatches OTP verification.
    - High Risk (70–100 or Courier Return Rate > 40%): COD disabled; requires advance delivery charge.
    - Blacklisted: Instant rejection.
- **Milestone 5.4: Honeypots & Security Middleware**:
  - `app/Modules/Order/Http/Middleware/VerifyBotHoneypotMiddleware.php`: Verifies hidden dummy fields (`user_company_fax`) are blank.
  - `app/Modules/Order/Http/Middleware/VerifySubmissionTimingMiddleware.php`: Ensures submission took $\ge 3.5$ seconds.
  - `app/Modules/Order/Http/Middleware/VerifyRecaptchaMiddleware.php`: Verifies Google reCAPTCHA v2/v3 or Cloudflare Turnstile token.
  - `app/Modules/Admin/Actions/UpdateSecuritySettingsAction.php`: Updates bot defense configurations.
- **Milestone 5.5: Admin Fraud & Security Back-Office UI**:
  - `resources/js/Pages/Admin/Fraud/FraudDashboard.vue`: Metrics on fake orders prevented, courier fee savings, risk score distributions.
  - `resources/js/Pages/Admin/Fraud/HighRiskReviewQueue.vue`: Queue of flagged orders with phone call confirmation action.
  - `resources/js/Pages/Admin/Fraud/BlacklistDirectory.vue`: Searchable management table with add/remove blacklist entries.
  - `resources/js/Pages/Admin/Settings/SecuritySettings.vue`: Provider selector, encrypted keys, page toggles, sensitivity threshold.

#### 3. Verification & Acceptance Criteria
- Fake phone numbers (`01700000000`) or mash addresses (`asdf jkl;`) are instantly flagged or rejected.
- Orders submitted in $< 3.5$ seconds trigger the bot timing guard.
- Customers with $> 40\%$ courier return rates automatically have COD disabled and are redirected to prepay delivery charges.
- reCAPTCHA/Turnstile settings toggleable per page from the Admin UI.

---

### Phase 6: Dynamic Payment Gateway Module & Advance COD Delivery Engine

#### Phase Metadata & Readiness Matrix
- **Phase Status**: `[ ⏳ NOT STARTED ]`
- **Prerequisites**: Phase 4, Phase 5
- **Estimated Complexity**: High (4 Sub-Milestones)
- **Primary Domain Modules**: `app/Modules/Payment/`
- **Target Artisan Migrations**: 3 tables
- **Key Verification Command**: `php artisan test --filter=PaymentModuleTest`

#### 1. Objectives
- Back-office UI to configure and activate Bangladesh MFS (bKash Tokenized, Nagad, SSLCommerz) and global gateways (Stripe, COD, Bank Transfer).
- Encrypted database credential storage (`'credentials' => 'encrypted:array'`) with masked secret keys in API responses.
- Pre-flight automated **"Test API Connection"** action before gateway activation.
- Advance Delivery Charge Engine: Forces high-risk buyers to prepay ৳70–৳150 delivery fee via bKash/Nagad before shipment.

#### 2. Detailed Micro-Milestones & File Creations
- **Milestone 6.1: Database Migrations for Payments**:
  - `database/migrations/xxxx_create_payment_gateway_settings_table.php`: `id`, `code` (`bkash`, `nagad`, `sslcommerz`, `stripe`, `cod`, `bank_transfer`), `name`, `is_active`, `is_sandbox`, `credentials` (encrypted JSON), `surcharge_percentage`, `min_order_amount`, `max_order_amount`, `sort_order`.
  - `database/migrations/xxxx_create_payments_table.php`: `id` (ULID), `order_id`, `gateway_code`, `transaction_id`, `amount`, `currency` (`BDT`), `status` (`pending`, `completed`, `failed`, `refunded`), `idempotency_key`, `raw_response` (encrypted JSON).
  - `database/migrations/xxxx_create_advance_delivery_charges_table.php`: `id`, `order_id`, `amount`, `payment_status`, `gateway_code`, `transaction_id`, `expires_at`.
- **Milestone 6.2: Gateway Contracts & Concrete Drivers**:
  - `app/Modules/Payment/Drivers/Contracts/PaymentGatewayInterface.php`: Contract with `initiate()`, `verify()`, `refund()`, `testConnection()`.
  - `app/Modules/Payment/Drivers/BkashTokenizedDriver.php`: Grant token, create payment, execute payment, query, refund.
  - `app/Modules/Payment/Drivers/NagadDriver.php`: Cryptographic public/private key signing, verify payment, IPN callback.
  - `app/Modules/Payment/Drivers/SslcommerzDriver.php`: Hosted session, IPN hash validation, query transaction.
  - `app/Modules/Payment/Drivers/StripeDriver.php`: Payment intents, 3D Secure, webhooks.
  - `app/Modules/Payment/Drivers/CashOnDeliveryDriver.php`: COD handling with advance charge verification.
- **Milestone 6.3: Domain Actions & Webhook Verification**:
  - `app/Modules/Payment/Actions/ExecutePaymentAction.php`: Wraps payment callback in database transaction with idempotency protection.
  - `app/Modules/Payment/Actions/ProcessRefundAction.php`: Issues full or partial refunds through active gateway.
  - `app/Modules/Payment/Actions/TestGatewayConnectionAction.php`: Pre-flight automated handshake verifying API credentials.
  - `app/Modules/Payment/Actions/RequireAdvanceDeliveryChargeAction.php`: Generates bKash/Nagad payment link for high-risk COD orders.
- **Milestone 6.4: Admin Payment Settings UI**:
  - `resources/js/Pages/Admin/Payment/GatewaySettings.vue`: Card list of gateways with toggle switches, credential inputs, and "Test Connection" button.

#### 3. Verification & Acceptance Criteria
- Gateway credentials stored in database are 100% encrypted and masked on retrieval.
- "Test API Connection" button successfully communicates with Sandbox/Live endpoints.
- High-risk COD orders receive advance delivery charge payment prompts; paid advance charge dispatches `PaymentCompletedEvent`.

---

### Phase 7: Automated Courier Logistics, Dynamic Delivery Pricing & COD Cash Reconciliation Batches

#### Phase Metadata & Readiness Matrix
- **Phase Status**: `[ ⏳ NOT STARTED ]`
- **Prerequisites**: Phase 4, Phase 6
- **Estimated Complexity**: High (4 Sub-Milestones)
- **Primary Domain Modules**: `app/Modules/Logistics/`
- **Target Artisan Migrations**: 5 tables
- **Key Verification Command**: `php artisan test --filter=CourierLogisticsTest`

#### 1. Objectives
- Full integration with top Bangladesh couriers: **Steadfast**, **Pathao**, **RedX**, **Paperfly**, **Sundarban**.
- Admin-configurable dynamic delivery pricing matrix: Inside Dhaka, Dhaka Suburbs, Outside Dhaka, extra weight per kg, free delivery threshold.
- 1-click consignment booking and standardized 4x6 inch (A6) thermal barcode shipping label generation.
- Real-time webhook tracking and automated COD remittance batch reconciliation.

#### 2. Detailed Micro-Milestones & File Creations
- **Milestone 7.1: Courier Schema & Delivery Pricing Matrix**:
  - `database/migrations/xxxx_create_courier_gateway_settings_table.php`: `id`, `courier_code` (`steadfast`, `pathao`, `redx`, `paperfly`, `sundarban`), `name`, `is_active`, `credentials` (encrypted JSON), `webhook_secret`.
  - `database/migrations/xxxx_create_shipping_zones_table.php`: `id`, `name` (`inside_dhaka`, `dhaka_suburbs`, `outside_dhaka`), `base_rate`, `estimated_delivery_time`, `is_active`.
  - `database/migrations/xxxx_create_shipping_weight_tiers_table.php`: `id`, `zone_id`, `min_weight_kg`, `max_weight_kg`, `additional_cost_per_kg`.
  - `database/migrations/xxxx_create_consignments_table.php`: `id` (ULID), `order_id`, `courier_code`, `tracking_code`, `consignment_id`, `cod_amount`, `courier_charge`, `status`, `label_url`.
  - `database/migrations/xxxx_create_courier_payout_batches_table.php`: `id` (ULID), `courier_code`, `batch_reference`, `total_parcels`, `total_cod_collected_bdt`, `total_courier_charge_bdt`, `net_disbursed_bdt`, `disbursement_date`, `status` (`unreconciled`, `reconciled`, `discrepancy_detected`), `reconciled_by_user_id`.
- **Milestone 7.2: Concrete Courier Drivers**:
  - `app/Modules/Logistics/Drivers/Contracts/CourierServiceInterface.php`: Contract with `bookConsignment()`, `trackConsignment()`, `getDeliveryCharge()`, `testConnection()`.
  - `app/Modules/Logistics/Drivers/SteadfastCourierDriver.php`: Consignment creation, tracking, balance inquiry.
  - `app/Modules/Logistics/Drivers/PathaoCourierDriver.php`: Store selection, consignment booking, price calculation.
  - `app/Modules/Logistics/Drivers/RedxCourierDriver.php`: Consignment booking, tracking, pickup request.
- **Milestone 7.3: Domain Actions & Label Generator**:
  - `app/Modules/Logistics/Actions/CalculateDynamicDeliveryFeeAction.php`: Zone + weight + cart value threshold engine.
  - `app/Modules/Logistics/Actions/BookCourierConsignmentAction.php`: 1-click single or bulk booking.
  - `app/Modules/Logistics/Actions/GenerateThermalShippingLabelAction.php`: 4x6 thermal barcode PDF label generator.
  - `app/Modules/Logistics/Actions/HandleCourierWebhookAction.php`: Updates order status upon courier tracking transitions.
  - `app/Modules/Logistics/Actions/ReconcileCodDisbursementAction.php`: Matches courier payment reports against pending COD orders.
  - `app/Modules/Logistics/Actions/UploadCourierPayoutSheetAction.php`: Parses courier CSV/Excel payout reports, matches tracking codes, and flags discrepancies.
  - `app/Modules/Logistics/Actions/TestCourierConnectionAction.php`: Pre-flight API test.
- **Milestone 7.4: Admin Logistics Back-Office UI**:
  - `resources/js/Pages/Admin/Logistics/CourierSettings.vue`: API credentials, active toggles, test buttons.
  - `resources/js/Pages/Admin/Logistics/DeliveryPricingMatrix.vue`: Zone rates and weight brackets.
  - `resources/js/Pages/Admin/Logistics/CodReconciliation.vue`: Visual reconciliation interface with discrepancy highlights.

#### 3. Verification & Acceptance Criteria
- 1-click booking creates Steadfast/Pathao consignment and stores tracking code.
- Thermal shipping labels print with barcode, order ID, address, and COD cash collection amount.
- Delivery fee calculates dynamically at checkout when customer selects Division/District.
- Courier remittance upload correctly parses CSV/Excel, matches order totals, and reconciles COD balances.

---

### Phase 8: Omnichannel Messaging, Voice Calling AI, Customer Portal, Marketing & Storefront UX

#### Phase Metadata & Readiness Matrix
- **Phase Status**: `[ ⏳ NOT STARTED ]`
- **Prerequisites**: Phase 2, Phase 4, Phase 7
- **Estimated Complexity**: High (8 Sub-Milestones)
- **Primary Domain Modules**: `app/Modules/Communication/`, `app/Modules/Customer/`, `app/Modules/Marketing/`, `resources/js/Pages/Storefront/`
- **Target Artisan Migrations**: 10 tables
- **Key Verification Command**: `php artisan test --filter=StorefrontAndVoiceTest`

#### 1. Objectives
- Admin-configurable dynamic SMTP mail with "Send Test Email" handshake.
- Automated Transactional SMS (OTP, tracking links) via Greenweb / Reve SMS & WhatsApp Business API.
- **Promotional SMS Campaign Manager & BTRC Time Guard**: Full back-office campaign builder for flash sales and delivery promotions (free/discounted delivery) with audience segmentation, live Bangla Unicode character counter, BTRC regulatory sending window enforcement (9:00 AM – 8:00 PM), and ROI conversion tracking.
- AI Automated Outbound Voice Calling Verification Agent (Bangla & English with DTMF 1/2 or speech intent).
- **Customer Support & Helpdesk Ticket System**: In-app support desk for order delays, damaged goods (photo upload), and returns with AI Smart Reply Assistant.
- Customer Account Portal: Saved addresses (BD cascading hierarchy), order history, loyalty points, and **B2B Trade Account Verification** (BIN/TIN upload).
- Marketing Engine: Automated Abandoned Cart WhatsApp/SMS sequences, customer loyalty points, and referral rewards.
- High-performance responsive Storefront: Sub-50ms instant search with autocomplete and typo tolerance.
- Ultra-fast 1-page / drawer checkout and self-service order tracking timeline.
- Social commerce feeds: Google Merchant Center XML feed, Facebook Catalog sync, TikTok catalog exports.

#### 2. Detailed Micro-Milestones & File Creations
- **Milestone 8.1: Dynamic SMTP & Messaging Engine**:
  - `database/migrations/xxxx_create_mail_settings_table.php`: `id`, `driver`, `host`, `port`, `encryption`, `username`, `password` (encrypted), `from_address`, `from_name`.
  - `database/migrations/xxxx_create_voice_calling_settings_table.php`: `id`, `provider` (`twilio`, `vapi`, `retell`, `obd`), `is_active`, `credentials` (encrypted JSON), `language` (`bn-BD`, `en-US`), `script_template`.
  - `database/migrations/xxxx_create_voice_call_logs_table.php`: `id`, `order_id`, `phone_number`, `duration_seconds`, `customer_response` (`confirmed`, `cancelled`, `unanswered`), `recording_url`, `transcript`.
  - `app/Modules/Communication/Actions/SendTestEmailAction.php`: Validates SMTP connection and sends test email.
  - `app/Modules/Communication/Actions/SendSmsOtpAction.php`: Dispatches 4-digit verification code.
  - `app/Modules/Communication/Actions/VerifySmsOtpAction.php`: Verifies OTP within 15-minute TTL.
  - `app/Modules/Communication/Actions/TriggerAiVoiceVerificationCallAction.php`: Outbound phone call within 60s of COD order.
  - `app/Modules/Communication/Actions/HandleVoiceCallWebhookAction.php`: Processes DTMF keypress (1 = confirmed, 2 = cancelled).
  - `app/Modules/Communication/Actions/TestVoiceCallAction.php`: Immediate live test call to admin's phone.
- **Milestone 8.2: Customer Domain, Addresses & B2B Trade Accounts (`app/Modules/Customer/`)**:
  - `database/migrations/xxxx_create_customer_addresses_table.php`: `id` (ULID), `user_id`, `recipient_name`, `phone_number`, `division_id`, `district_id`, `thana_id`, `street_address`, `is_default`, `address_label` (`Home`, `Office`).
  - `database/migrations/xxxx_add_b2b_fields_to_users_table.php`: `is_b2b_account`, `company_name`, `bin_number` (9/13 digits), `tin_number` (12 digits), `trade_license_url`, `b2b_verification_status` (`unverified`, `pending`, `verified`, `rejected`), `b2b_verified_at`.
  - `app/Modules/Customer/Actions/VerifyB2bTradeAccountAction.php`: Approves B2B trade accounts for wholesale tier pricing.
  - `app/Modules/Customer/Actions/ManageCustomerAddressAction.php`: Add, update, set default delivery addresses.
- **Milestone 8.3: Customer Support & Helpdesk Ticket System (`app/Modules/Customer/` & `app/Modules/Admin/`)**:
  - `database/migrations/xxxx_create_support_tickets_table.php`: `id` (ULID), `ticket_number` (`TKT-YYYYMM-XXXXXX`), `customer_id` (nullable), `order_id` (nullable), `customer_name`, `customer_phone`, `customer_email`, `category` (`order_delay`, `damaged_item`, `payment_issue`, `return_refund`, `general`, `b2b_wholesale`), `priority` (`low`, `medium`, `high`, `urgent`), `status` (`open`, `in_progress`, `waiting_customer`, `resolved`, `closed`), `assigned_staff_user_id`.
  - `database/migrations/xxxx_create_support_ticket_messages_table.php`: `id`, `ticket_id`, `sender_type` (`customer`, `staff`, `system`), `sender_id`, `message`, `attachment_url`, `created_at`.
  - `app/Modules/Customer/Actions/CreateSupportTicketAction.php`: Dispatches support ticket from order timeline or contact page.
  - `app/Modules/Customer/Actions/ReplySupportTicketAction.php`: Posts customer/staff replies and triggers SMS/WhatsApp alerts.
  - `resources/js/Components/Storefront/SupportInquiryModal.vue`: Customer storefront order-linked modal.
  - `resources/js/Pages/Admin/Support/TicketInbox.vue`: Support queue with category filtering, priority badges, and staff assignment.
  - `resources/js/Pages/Admin/Support/TicketDetail.vue`: Conversation timeline with damaged photo viewer and AI Smart Reply Assistant draft button.
- **Milestone 8.3: Marketing & Abandoned Cart Recovery Engine (`app/Modules/Marketing/`)**:
  - `database/migrations/xxxx_create_abandoned_carts_table.php`: `id` (ULID), `cart_id`, `customer_phone`, `customer_email`, `total_amount_bdt`, `recovery_step` (0, 1, 2, 3), `last_notified_at`, `is_recovered`, `recovered_order_id`.
  - `database/migrations/xxxx_create_customer_loyalty_points_table.php`: `id`, `user_id`, `order_id` (nullable), `points_change`, `balance_after`, `transaction_type` (`earned`, `redeemed`, `expired`), `description`.
  - `database/migrations/xxxx_create_customer_referrals_table.php`: `id` (ULID), `referrer_user_id`, `referred_user_id`, `referral_code`, `reward_amount_bdt`, `status` (`pending`, `completed`).
  - `app/Modules/Marketing/Actions/TrackAbandonedCartAction.php`: Identifies dormant checkout sessions (> 60m).
  - `app/Modules/Marketing/Actions/DispatchAbandonedCartRecoveryAction.php`: Triggers WhatsApp/SMS/Email recovery reminders.
  - `app/Modules/Marketing/Actions/AccrueLoyaltyPointsAction.php`: Calculates reward points on completed orders.
  - `app/Modules/Marketing/Actions/RedeemLoyaltyPointsAction.php`: Applies loyalty discount to cart.
- **Milestone 8.4: Promotional SMS Campaign Manager & BTRC Time Guard (`app/Modules/Communication/`)**:
  - `database/migrations/xxxx_create_sms_campaigns_table.php`: `id` (ULID), `title`, `campaign_type` (`promotional`, `flash_sale`, `free_delivery_promo`), `gateway_code` (`reve_sms`, `greenweb`, `whatsapp`), `sender_id`, `message_body`, `is_unicode`, `sms_part_count`, `target_audience_filter` (JSON), `total_recipients`, `delivered_count`, `failed_count`, `cost_per_sms_bdt`, `total_cost_bdt`, `revenue_generated_bdt`, `scheduled_at`, `status` (`draft`, `scheduled`, `sending`, `completed`, `cancelled`), `created_by_user_id`.
  - `database/migrations/xxxx_create_sms_campaign_logs_table.php`: `id`, `campaign_id`, `recipient_phone`, `status`, `gateway_message_id`, `cost_bdt`, `error_message`, `sent_at`.
  - `app/Modules/Communication/Actions/DispatchPromotionalSmsCampaignAction.php`: Enforces BTRC delivery window (9:00 AM – 8:00 PM), batches delivery, calls Reve/Greenweb API, and logs costs.
  - `app/Modules/Communication/Actions/CalculateSmsPartAction.php`: Detects GSM 7-bit vs UCS-2 Unicode (Bangla) and calculates multi-part character counts (160 vs 70 chars).
  - `app/Modules/Communication/Actions/TrackCampaignConversionAction.php`: Tracks orders placed using campaign promotional links/coupons to calculate revenue ROI.
  - `resources/js/Pages/Admin/Communication/SmsCampaignManager.vue`: Promotional campaign builder with audience filters, Bangla character counter, BTRC time guard scheduler, and ROI analytics.
- **Milestone 8.5: Social Commerce Feeds**:
  - `app/Modules/Communication/Actions/GenerateGoogleMerchantFeedAction.php`: Generates `/feeds/google-merchant.xml`.
  - `app/Modules/Communication/Actions/GenerateFacebookCatalogFeedAction.php`: Generates `/feeds/facebook-catalog.csv`.
- **Milestone 8.6: Storefront & Customer Portal Components**:
  - `HeaderNav.vue`: Bilingual toggle (বাংলা/EN), mega-menu, cart drawer button.
  - `InstantSearchBar.vue`: Sub-50ms autocomplete dropdown with product image, price, stock badge.
  - `ProductCard.vue`: Aspect-ratio media container, WebP/AVIF responsive breakpoints, badge overlays.
  - `ProductGallery.vue`: High-res zoom lens, thumbnail carousel, color-swatch gallery sync.
  - `ProductReviews.vue`: Customer star ratings, text comments, photo gallery attachments.
  - `SlideOutCartDrawer.vue`: Dynamic Free Shipping progress bar, 1-click upsells, quantity adjustments.
  - `CascadingAddressSelector.vue`: Division $\rightarrow$ District $\rightarrow$ Thana cascading selector.
- **Milestone 8.7: Storefront & Customer Portal Pages**:
  - `Home.vue`: Preloaded hero banner, featured categories, flash deal countdowns, trust badges.
  - `ProductDetail.vue`: Full PDP with Schema.org JSON-LD, specs table, reviews, sticky mobile "Buy Now" drawer.
  - `CategoryList.vue`: Faceted filtering (price range, brand, color), Inertia partial reloads.
  - `Checkout.vue`: 1-page checkout with guest ordering, delivery fee calculator, honeypot traps.
  - `OrderTracking.vue`: Self-service milestone timeline progress portal.
  - `resources/js/Pages/Customer/AccountDashboard.vue`: Profile overview, order history, loyalty points balance.
  - `resources/js/Pages/Customer/AddressBook.vue`: Customer address book manager.
  - `resources/js/Pages/Customer/B2bApplication.vue`: Trade account application with BIN/TIN/License upload.
  - `resources/js/Pages/Admin/Customer/B2bVerificationQueue.vue`: Admin corporate trade account verification queue.
  - `resources/js/Pages/Admin/Marketing/AbandonedCarts.vue`: Abandoned cart recovery analytics and triggers.

#### 3. Verification & Acceptance Criteria
- Admin can send a test email and verify SMTP settings before saving.
- Voice AI dials phone, plays Bangla prompt, and keypress `1` confirms order.
- Promotional SMS campaigns strictly enforce the BTRC 9:00 AM – 8:00 PM sending window and correctly calculate Bangla multi-part credits.
- Search displays live autocomplete results in $< 50ms$ without full page reload.
- Customer address book saves and cascades Division $\rightarrow$ District $\rightarrow$ Thana accurately.
- B2B trade account applications verify corporate BIN/TIN and grant wholesale tier access.
- Abandoned cart recovery triggers automated WhatsApp/SMS sequences.
- Core Web Vitals: LCP $< 1.2s$, INP $< 50ms$, CLS $= 0.0$.

---

### Phase 9: Comprehensive NBR VAT, Full Statutory Tax Compliance & RMA Sales Return Engine

#### Phase Metadata & Readiness Matrix
- **Phase Status**: `[ ⏳ NOT STARTED ]`
- **Prerequisites**: Phase 3, Phase 4, Phase 6
- **Estimated Complexity**: High (4 Sub-Milestones)
- **Primary Domain Modules**: `app/Modules/TaxCompliance/`, `app/Modules/Order/`
- **Target Artisan Migrations**: 7 tables
- **Key Verification Command**: `php artisan test --filter=VatComplianceTest`

#### 1. Objectives
- Enforce full statutory compliance under the **Bangladesh Value Added Tax and Supplementary Duty Act 2012** and **Income Tax Act 2023**.
- Automated issuance of statutory **Mushak 6.3** Tax Invoices with non-gap atomic serial numbers.
- Formal **RMA / Sales Return Lifecycle**: Return request $\rightarrow$ Warehouse physical inspection $\rightarrow$ Restock or Quarantine Damaged Goods $\rightarrow$ Statutory **Mushak 6.7 Credit Notes** with decreasing tax adjustments $\rightarrow$ Dynamic gateway refund disbursement.
- Claiming both **Import Advance Tax (AT - 5%)** and **Local Supplier Input VAT** as Input Tax Credit (রেয়াত) on monthly **Mushak 9.1** returns.
- Statutory **Mushak 6.8** Debit Notes, **Mushak 6.5** Transfer Challans, and statutory **Mushak 6.1 / 6.2** registers.

#### 2. Detailed Micro-Milestones & File Creations
- **Milestone 9.1: Tax Compliance & RMA Schema**:
  - `database/migrations/xxxx_create_vat_invoice_sequences_table.php`: `id`, `tax_year`, `tax_month`, `last_sequence_number`.
  - `database/migrations/xxxx_create_vat_invoices_table.php`: `id` (ULID), `order_id`, `mushak_serial` (`M6.3-YYYYMM-XXXXXX`), `customer_bin`, `subtotal_bdt`, `sd_amount_bdt`, `vat_amount_bdt`, `immutable_hash`, `issued_at`.
  - `database/migrations/xxxx_create_order_returns_table.php`: `id` (ULID), `order_id`, `order_item_id`, `return_number` (`RMA-YYYYMM-XXXXXX`), `customer_id`, `quantity`, `return_reason`, `inspection_status` (`pending`, `accepted_restock`, `accepted_quarantine_damaged`, `rejected`), `mushak_6_7_id` (nullable), `refund_id` (nullable), `inspected_by_user_id`, `inspection_notes`.
  - `database/migrations/xxxx_create_vat_credit_notes_table.php`: `id` (ULID), `original_mushak_6_3_id`, `credit_note_serial` (`M6.7-YYYYMM-XXXXXX`), `return_reason`, `decreasing_vat_adjustment_bdt`, `issued_at`.
  - `database/migrations/xxxx_create_vat_debit_notes_table.php`: `id` (ULID), `debit_note_serial` (`M6.8-YYYYMM-XXXXXX`), `supplier_id`, `purchase_return_reason`, `increasing_adjustment_bdt`.
  - `database/migrations/xxxx_create_vat_transfer_challans_table.php`: `id` (ULID), `challan_serial` (`M6.5-YYYYMM-XXXXXX`), `source_warehouse_id`, `destination_warehouse_id`, `transport_details`.
  - `database/migrations/xxxx_create_mushak_9_1_summaries_table.php`: `id`, `tax_period` (`YYYY-MM`), `total_sales_output_vat`, `total_import_at_credit_claimed`, `total_local_input_vat_credit_claimed`, `total_decreasing_adjustments`, `net_vat_payable_bdt`.
- **Milestone 9.2: Domain Actions, Atomic Serial Locking & RMA Inspection**:
  - `app/Modules/TaxCompliance/Actions/GenerateMushak6_3Action.php`: Row-locked (`SELECT ... FOR UPDATE`) non-gap serial generation and SHA-256 integrity hash.
  - `app/Modules/Order/Actions/InitiateOrderReturnAction.php`: Creates RMA request.
  - `app/Modules/Order/Actions/ProcessReturnInspectionAction.php`: Warehouse keeper inspects returned goods (restocks or quarantines damaged stock; triggers Mushak 6.7 and refund).
  - `app/Modules/TaxCompliance/Actions/GenerateMushak6_7CreditNoteAction.php`: Linked to original Mushak 6.3 with decreasing VAT adjustment.
  - `app/Modules/TaxCompliance/Actions/ClaimImportAdvanceTaxCreditAction.php`: Extracts 5% AT from Bill of Entry into Mushak 9.1 credit.
  - `app/Modules/TaxCompliance/Actions/ClaimLocalInputVatCreditAction.php`: Extracts input VAT from verified local supplier Mushak 6.3 invoices.
  - `app/Modules/TaxCompliance/Actions/CompileMushak9_1ReturnAction.php`: Compiles monthly NBR VAT Return.
  - `app/Modules/TaxCompliance/Services/CompileMushak6_1RegisterService.php`: Statutory Purchase Register from BOE and local purchases.
  - `app/Modules/TaxCompliance/Services/CompileMushak6_2RegisterService.php`: Statutory Trader's Sales Register.
- **Milestone 9.3: Admin Tax & RMA Back-Office UI**:
  - `resources/js/Pages/Admin/Tax/Mushak63Viewer.vue`: Printable statutory NBR Mushak 6.3 invoice.
  - `resources/js/Pages/Admin/Returns/ReturnManager.vue`: RMA request approval, physical inward inspection, and 1-click credit note/refund execution.
  - `resources/js/Pages/Admin/Tax/Mushak91Return.vue`: Monthly VAT Return dashboard with Output vs Input Tax Credit offsetting.
  - `resources/js/Pages/Admin/Tax/VatRegisters.vue`: Exportable Mushak 6.1 (Purchase Register) and 6.2 (Sales Register).

#### 3. Verification & Acceptance Criteria
- Outward sales issue non-gap Mushak 6.3 invoices with zero sequence skipping during concurrent orders.
- RMA physical inspection accurately separates restocked goods from damaged quarantine goods.
- Returns and cancellations generate statutory Mushak 6.7 Credit Notes and reflect decreasing adjustments.
- Monthly Mushak 9.1 correctly claims both Import AT (5%) and local supplier input credit.

---

### Phase 10: Backend AI Intelligence, Trading P&L Analytics, Audit Trail & Automated Pest QA

#### Phase Metadata & Readiness Matrix
- **Phase Status**: `[ ⏳ NOT STARTED ]`
- **Prerequisites**: Phases 1 through 9
- **Estimated Complexity**: High (4 Sub-Milestones)
- **Primary Domain Modules**: `app/Modules/AI/`, `app/Modules/Admin/`
- **Target Artisan Migrations**: 3 tables
- **Key Verification Command**: `php artisan test && ./vendor/bin/pint --test && npx vue-tsc --noEmit`

#### 1. Objectives
- Decoupled AI module allowing administrators to connect any provider (**Google Gemini**, **OpenAI**, **Anthropic Claude**, or **Ollama / Local LLMs**).
- Catalog SEO enrichment, smart restocking demand forecasting, dispute support, and tax anomaly detection.
- Executive Trading Analytics Dashboard visualizing true commercial health (Gross Profit in BDT, Landed COGS in BDT, Courier Cash Inflows, NBR Net VAT Payable, AI Token Costs).
- Immutable, tamper-proof Audit Logging (`audit_logs`) recording price changes, stock adjustments, purchase orders, refunds, and permission edits.
- Comprehensive automated test suite (Pest PHP backend tests and Vue component tests).
- Production build validation and security review.

#### 2. Detailed Micro-Milestones & File Creations
- **Milestone 10.1: Decoupled AI Module (`app/Modules/AI/`)**:
  - `database/migrations/xxxx_create_ai_model_settings_table.php`: `id`, `provider` (`gemini`, `openai`, `claude`, `ollama`), `name`, `is_active`, `credentials` (encrypted JSON), `default_model`, `temperature`, `max_tokens`, `assigned_features`.
  - `database/migrations/xxxx_create_ai_usage_logs_table.php`: `id`, `provider`, `model`, `feature`, `prompt_tokens`, `completion_tokens`, `estimated_cost`, `created_at`.
  - `app/Modules/AI/Drivers/GoogleGeminiDriver.php`, `OpenAiDriver.php`, `AnthropicClaudeDriver.php`, `OllamaLocalDriver.php`.
  - `app/Modules/AI/Actions/EnrichProductCatalogSeoAction.php`: Generates meta titles, descriptions, bullets from specs.
  - `app/Modules/AI/Actions/ForecastRestockingDemandAction.php`: Analyzes sales velocity & lead time to recommend PO quantities.
  - `app/Modules/AI/Actions/TestAiConnectionAction.php`: Pre-flight handshake testing credentials and measuring latency.
- **Milestone 10.2: Executive Trading P&L & Immutable Audit Trail (`app/Modules/Admin/`)**:
  - `database/migrations/xxxx_create_audit_logs_table.php`: `id` (ULID), `user_id`, `event_type`, `auditable_type`, `auditable_id`, `old_values` (JSON), `new_values` (JSON), `ip_address`, `user_agent`, `created_at`.
  - `app/Modules/Admin/Actions/RecordAuditLogAction.php`: Tamper-proof audit logger for sensitive operations.
  - `app/Modules/Admin/Actions/CalculateTradingProfitAndLossAction.php`: Real-time P&L (Gross Revenue - True Landed COGS - Courier Return Penalties - Merchant Gateway Fees = True Net Profit in BDT).
  - `app/Modules/Admin/Actions/ReconcileDisbursedCourierCashAction.php`: Reconciles Steadfast/Pathao bank payouts against order totals.
  - `app/Modules/Admin/Actions/AuditFraudPreventionMetricsAction.php`: Calculates total BDT saved by blocking fake orders.
  - `resources/js/Pages/Admin/Dashboard/ExecutiveDashboard.vue`: Executive dashboard visualizing Revenue, True Landed COGS, Gross Margin, Courier COD Cash Inflows, and NBR Net VAT.
  - `resources/js/Pages/Admin/Audit/AuditLogIndex.vue`: Searchable tamper-proof audit trail browser.
- **Milestone 10.3: Automated Test Suites (`tests/Feature/` & `tests/Unit/`)**:
  - `tests/Feature/Fraud/FraudRiskScoringTest.php`: Tests honeypots, timing guards, and courier delivery rate checks.
  - `tests/Feature/Payment/DynamicGatewayTest.php`: Tests bKash/Nagad encryption and refund execution.
  - `tests/Feature/Logistics/CourierConsignmentTest.php`: Tests Steadfast booking and label generation.
  - `tests/Feature/Procurement/LandedCostAllocationTest.php`: Tests BOE customs duty and domestic transport COGS math.
  - `tests/Feature/Tax/MushakComplianceTest.php`: Tests Mushak 6.3 atomic serials and 6.7 credit note adjustments.
  - `tests/Feature/Seo/SitemapAndSchemaTest.php`: Tests Schema.org JSON-LD and XML sitemap generation.
- **Milestone 10.4: Production Build & Quality Gate**:
  - Full Pest PHP test run: `php artisan test`.
  - Code style audit: `./vendor/bin/pint --test`.
  - TypeScript strictness check: `npx vue-tsc --noEmit`.
  - Vite production bundles: `npm run build && npm run build:ssr`.

#### 3. Verification & Acceptance Criteria
- All Pest tests pass with 100% assertions green.
- `./vendor/bin/pint --test` passes with zero PSR-12 styling issues.
- `npx vue-tsc --noEmit` reports 0 TypeScript errors.
- Executive dashboard loads real-time P&L with accurate Landed COGS and net margin calculations.
- Audit logs accurately capture every sensitive change to prices, stocks, and permissions.

---

## 5. Master Progress & Execution Tracker

Use this table during implementation to track status across all phases:

| Phase | Phase Name | Primary Domain Modules | Status | Verification Command |
|---|---|---|---|---|
| **Phase 1** | Foundation, Infrastructure, Dynamic Multi-Cloud Storage Engine, Spatie RBAC & Core SEO | `Common`, `Seo`, `Admin` | `[ ⏳ NOT STARTED ]` | `php artisan test --filter=InfrastructureTest` |
| **Phase 2** | Core Catalog, Matrix Variants, Multi-Warehouse Inventory, Reviews & Inter-Depot Transfers | `Catalog`, `Inventory`, `Pricing` | `[ ⏳ NOT STARTED ]` | `php artisan test --filter=CatalogAndInventoryTest` |
| **Phase 3** | Dual Sourcing Procurement (Import & Local) & Landed Cost Engine | `Procurement`, `TaxCompliance` | `[ ⏳ NOT STARTED ]` | `php artisan test --filter=ProcurementAndLandedCostTest` |
| **Phase 4** | Cart Engine, Coupons, Order State Machine & Atomic Stock Reservation | `Cart`, `Order`, `Marketing` | `[ ⏳ NOT STARTED ]` | `php artisan test --filter=CartAndOrderTest` |
| **Phase 5** | 7-Layer Anti-Fraud, Fake Order Defense & Dynamic Bot Security Settings | `Order`, `Customer`, `Admin` | `[ ⏳ NOT STARTED ]` | `php artisan test --filter=AntiFraudShieldTest` |
| **Phase 6** | Dynamic Payment Gateway Module & Advance COD Delivery Engine | `Payment` | `[ ⏳ NOT STARTED ]` | `php artisan test --filter=PaymentModuleTest` |
| **Phase 7** | Automated Courier Logistics, Dynamic Delivery Pricing & Payout Reconciliation Batches | `Logistics` | `[ ⏳ NOT STARTED ]` | `php artisan test --filter=CourierLogisticsTest` |
| **Phase 8** | Omnichannel Messaging, Voice Calling AI, Customer Portal, Marketing & Storefront UX | `Communication`, `Customer`, `Marketing`, `Storefront` | `[ ⏳ NOT STARTED ]` | `php artisan test --filter=StorefrontAndVoiceTest` |
| **Phase 9** | Comprehensive NBR VAT, Full Statutory Tax Compliance & RMA Sales Return Engine | `TaxCompliance`, `Order` | `[ ⏳ NOT STARTED ]` | `php artisan test --filter=VatComplianceTest` |
| **Phase 10** | Backend AI Intelligence, Trading P&L Analytics, Audit Trail & Automated Pest QA | `AI`, `Admin` | `[ ⏳ NOT STARTED ]` | `php artisan test` |
