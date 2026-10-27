# Enterprise Single-Vendor E-Commerce Platform
## Comprehensive Phase-by-Phase Master Development Plan (`PHASE_PLAN.md`)

This master development plan defines the execution roadmap, architecture, exact file creations, domain actions, database schemas, and verification checklists for all **10 Phases** of the commercial hybrid trading platform.

---

## Roadmap Overview

```text
┌────────────────────────────────────────────────────────────────────────────────────────┐
│                               MASTER 10-PHASE ROADMAP                                  │
├────────────────────┬───────────────────────────────────┬───────────────────────────────┤
│ Phase 1            │ Foundation, Infrastructure & SEO  │ Laravel 13, Vue 3, Inertia SSR│
│ Phase 2            │ Anti-Fraud & Fake Order Defense   │ 7-Layer Shield, Courier API   │
│ Phase 3            │ Dynamic Payment Module & Pre-Pay  │ bKash, Nagad, SSLCommerz, COD │
│ Phase 4            │ Automated Courier Logistics       │ Steadfast, Pathao, Labels, COD│
│ Phase 5            │ Omnichannel Messaging & Voice AI  │ SMS, WhatsApp, Voice Calling  │
│ Phase 6            │ Decoupled Backend AI Module       │ Gemini, OpenAI, Restock, SEO  │
│ Phase 7            │ Dual Sourcing & Landed Cost COGS  │ Global Imports + Local BDT PO │
│ Phase 8            │ Catalog, Matrix & Storefront UX   │ Sub-50ms Search, Cart Drawer  │
│ Phase 9            │ NBR VAT & Income Tax Compliance   │ Mushak 6.3, 6.7, 6.6, 9.1     │
│ Phase 10           │ Executive Analytics & QA Testing  │ Trading P&L, Pest Test Suite  │
└────────────────────┴───────────────────────────────────┴───────────────────────────────┘
```

---

## Phase 1: Foundation, Infrastructure, Spatie MediaLibrary, Spatie RBAC & Core SEO

### 1. Objectives
- Establish the Laravel 13.x application with PHP 8.5 compatibility.
- Configure Inertia.js v2 with Server-Side Rendering (SSR) for instant SEO indexing.
- Setup Vue 3 with TypeScript and exclusively Sass/SCSS design tokens auto-injected in Vite.
- Setup Domain-Driven Modular architecture (`app/Modules/`) with PSR-4 autoloading.
- Integrate Spatie Laravel Permission and Spatie Laravel MediaLibrary with Intervention Image v3.
- Establish the base layout system with dynamic `<Head>` metadata and Schema.org JSON-LD rich snippets.

### 2. Files to Create & Configure
- **Core Configuration**:
  - `composer.json`: PSR-4 mapping `"App\\Modules\\": "app/Modules/"`, required packages (`spatie/laravel-permission`, `spatie/laravel-medialibrary`, `intervention/image`, `inertiajs/inertia-laravel:^2.0`).
  - `package.json`: `@inertiajs/vue3`, `@inertiajs/vue3/server`, `vue`, `sass`, `typescript`, `vue-tsc`, `@vitejs/plugin-vue`, `pinia`.
  - `vite.config.ts`: Configured with SCSS `additionalData: '@use "@/scss/abstracts/variables" as *; @use "@/scss/abstracts/mixins" as *;'`.
  - `tsconfig.json`: Strict TypeScript settings.
- **SCSS Design System (`resources/scss/`)**:
  - `resources/scss/abstracts/_variables.scss`: Colors, typography, spacing, shadows, radius, breakpoints.
  - `resources/scss/abstracts/_mixins.scss`: Responsive breakpoints, flex/grid helpers, glassmorphism, button elevation.
  - `resources/scss/abstracts/_functions.scss`: Unit conversions, color adjustments.
  - `resources/scss/base/_reset.scss`: CSS modern reset.
  - `resources/scss/base/_typography.scss`: Google font import (Outfit / Inter), heading scales.
  - `resources/scss/components/_buttons.scss`: Primary, secondary, outline, danger button styles.
  - `resources/scss/components/_cards.scss`: Product, dashboard, and metric card styles.
  - `resources/scss/app.scss`: Main stylesheet entry point.
- **Frontend Architecture (`resources/js/`)**:
  - `resources/js/app.ts`: Client-side Inertia app initializer with Pinia.
  - `resources/js/ssr.ts`: Server-Side Rendering entry point for crawlers and bots.
  - `resources/js/Layouts/StorefrontLayout.vue`: Customer layout with header, footer, SEO `<Head>`.
  - `resources/js/Layouts/AdminLayout.vue`: Admin back-office layout with sidebar, topbar, notifications.
  - `resources/js/Layouts/CheckoutLayout.vue`: Distraction-free checkout layout.
- **SEO & Metadata (`app/Modules/Seo/`)**:
  - `app/Modules/Seo/DTOs/MetaTagData.php`: Immutable DTO for title, description, OG, Twitter Card.
  - `app/Modules/Seo/Services/SchemaGeneratorService.php`: JSON-LD generator for `Product`, `BreadcrumbList`, `Organization`, `FAQPage`.
  - `app/Modules/Seo/Http/Controllers/SitemapController.php`: Generates dynamic `/sitemap.xml` and `/robots.txt`.
- **Database & Auth**:
  - `database/migrations/xxxx_create_permission_tables.php` (Spatie permission migration).
  - `database/migrations/xxxx_create_media_table.php` (Spatie media library migration).
  - `database/seeders/RolesAndPermissionsSeeder.php`: Seeds default enterprise roles and permissions.

### 3. Verification & Acceptance Criteria
- `php artisan test` runs cleanly.
- `npm run build` and `npm run build:ssr` compile with zero Sass/TypeScript warnings.
- Public page source contains server-rendered HTML and Schema.org JSON-LD script blocks.

---

## Phase 2: Comprehensive Anti-Fraud, Fake Order & Fake Customer Defense Shield

### 1. Objectives
- Implement the 7-Layer anti-fraud defense to protect against fake COD orders, serial refusers, and competitor stock locks.
- Real-time pre-flight query against courier delivery history APIs (Steadfast / Pathao / FraudCheckBD).
- Honeypot bot traps, client submission timing guards, and disposable email blocklists.
- Tiered automated enforcement (Auto-Approve, 4-digit SMS/WhatsApp OTP, or Mandatory Advance Delivery Charge).
- Searchable Merchant Fraud Directory (`customer_fraud_lists`) with 1-click customer blacklisting.

### 2. Files to Create
- **Database Migrations (`app/Modules/Order/Database/Migrations/` & `Customer`)**:
  - `xxxx_create_customer_fraud_lists_table.php`: `id` (ULID), `list_type` (`blacklist`, `whitelist`, `watchlist`), `identifier_type` (`phone`, `ip_address`, `email`, `device_fingerprint`), `identifier_value`, `risk_score_override`, `reason`, `notes`, `created_by_user_id`.
  - `xxxx_create_order_fraud_evaluations_table.php`: `id`, `order_id`, `risk_score` (0-100), `risk_tier` (`low`, `medium`, `high`, `blacklisted`), `triggered_signals` (JSON), `courier_delivery_rate`, `carrier_name`, `ip_geo_country`, `is_vpn_or_proxy`, `created_at`.
- **Domain Actions (`app/Modules/Order/Actions/` & `Customer/Actions/`)**:
  - `CalculateOrderFraudRiskScoreAction.php`: Aggregates weighted risk points across all 7 signals.
  - `QueryCourierDeliveryHistoryAction.php`: Handshake with Steadfast and Pathao delivery rate APIs.
  - `ValidateBangladeshMobileAction.php`: Strict operator regex (`+8801[3-9]\d{8}`) and repetitive digit check.
  - `EvaluateAddressEntropyAction.php`: Shannon entropy and keyboard mash filter.
  - `EnforceOrderVelocityAction.php`: Enforces max 1 active COD per phone number and IP rate limits.
  - `BlacklistCustomerAction.php`: 1-click action to blacklist phone, IP, and fingerprint upon courier return.
- **Middleware & Security (`app/Modules/Order/Http/Middleware/`)**:
  - `VerifyBotHoneypotMiddleware.php`: Verifies hidden dummy fields (`user_company_fax`) are empty.
  - `VerifySubmissionTimingMiddleware.php`: Ensures form submission took $\ge 3.5$ seconds.
  - `VerifyRecaptchaMiddleware.php`: Verifies Google reCAPTCHA v2/v3 or Cloudflare Turnstile token.
- **Admin UI Components (`resources/js/Pages/Admin/Fraud/`)**:
  - `FraudDashboard.vue`: Metrics on fake orders prevented, courier fee savings, risk score distributions.
  - `HighRiskReviewQueue.vue`: Queue of flagged orders with phone verification action and WhatsApp link.
  - `BlacklistDirectory.vue`: Searchable management table with add/remove blacklist entries.

### 3. Verification & Acceptance Criteria
- Fake numbers (e.g. `01711111111`) or gibberish addresses are instantly rejected.
- Orders submitted in $< 3.5$ seconds are caught by the bot timing guard.
- Customers with $> 40\%$ courier return rates automatically have COD disabled and are redirected to prepay delivery charges.

---

## Phase 3: Dynamic Payment Module & Advance COD Delivery Charge Engine

### 1. Objectives
- Back-office UI to configure and activate Bangladesh MFS (bKash Tokenized, Nagad, SSLCommerz) and global gateways (Stripe, COD, Bank Transfer).
- Encrypted database credential storage (`'credentials' => 'encrypted:array'`) with masked secret keys in API responses.
- Pre-flight automated **"Test API Connection"** action before gateway activation.
- Advance Delivery Charge Engine: Forces high-risk buyers to prepay ৳70–৳150 delivery fee via bKash/Nagad before shipment.

### 2. Files to Create
- **Database Migrations (`app/Modules/Payment/Database/Migrations/`)**:
  - `xxxx_create_payment_gateway_settings_table.php`: `id`, `code` (`bkash`, `nagad`, `sslcommerz`, `stripe`, `cod`), `name`, `is_active`, `is_sandbox`, `credentials` (encrypted JSON), `surcharge_percentage`, `min_order_amount`, `max_order_amount`, `sort_order`.
  - `xxxx_create_payments_table.php`: `id` (ULID), `order_id`, `gateway_code`, `transaction_id`, `amount`, `currency` (`BDT`), `status` (`pending`, `completed`, `failed`, `refunded`), `idempotency_key`, `raw_response` (encrypted JSON).
  - `xxxx_create_advance_delivery_charges_table.php`: `id`, `order_id`, `amount`, `payment_status`, `gateway_code`, `transaction_id`, `expires_at`.
- **Gateway Contracts & Drivers (`app/Modules/Payment/Drivers/`)**:
  - `Contracts/PaymentGatewayInterface.php`: Contract with `initiate()`, `verify()`, `refund()`, `testConnection()`.
  - `BkashTokenizedDriver.php`: Grant token, create payment, execute payment, query, refund.
  - `NagadDriver.php`: Cryptographic public/private key signing, verify payment, IPN callback.
  - `SslcommerzDriver.php`: Hosted session, IPN hash validation, query transaction.
  - `StripeDriver.php`: Payment intents, 3D Secure, webhooks.
  - `CashOnDeliveryDriver.php`: COD handling with advance charge verification.
- **Domain Actions (`app/Modules/Payment/Actions/`)**:
  - `ExecutePaymentAction.php`: Wraps gateway execution in database transaction with idempotency protection.
  - `ProcessRefundAction.php`: Issues full or partial refunds through active gateway.
  - `TestGatewayConnectionAction.php`: Pre-flight automated handshake verifying API credentials.
  - `RequireAdvanceDeliveryChargeAction.php`: Generates bKash/Nagad payment link for high-risk COD orders.
- **Admin UI (`resources/js/Pages/Admin/Payment/`)**:
  - `GatewaySettings.vue`: Card list of gateways with toggle switches, credential inputs, and "Test Connection" button.

### 3. Verification & Acceptance Criteria
- Gateway credentials stored in database are 100% encrypted.
- "Test API Connection" button successfully communicates with Sandbox/Live endpoints.
- High-risk COD orders receive advance delivery charge payment prompts.

---

## Phase 4: Automated Courier Logistics & Dynamic Delivery Pricing Gateway

### 1. Objectives
- Full integration with top Bangladesh couriers: **Steadfast**, **Pathao**, **RedX**, **Paperfly**, **Sundarban**.
- Admin-configurable dynamic delivery pricing matrix: Inside Dhaka, Dhaka Suburbs, Outside Dhaka, extra weight per kg, free delivery threshold.
- 1-click consignment booking and standardized 4x6 inch (A6) thermal barcode shipping label generation.
- Real-time webhook tracking and automated COD cash reconciliation.

### 2. Files to Create
- **Database Migrations (`app/Modules/Logistics/Database/Migrations/`)**:
  - `xxxx_create_courier_gateway_settings_table.php`: `id`, `courier_code` (`steadfast`, `pathao`, `redx`), `name`, `is_active`, `credentials` (encrypted JSON), `webhook_secret`.
  - `xxxx_create_shipping_zones_table.php`: `id`, `name` (`inside_dhaka`, `dhaka_suburbs`, `outside_dhaka`), `base_rate`, `estimated_delivery_time`, `is_active`.
  - `xxxx_create_shipping_weight_tiers_table.php`: `id`, `zone_id`, `min_weight_kg`, `max_weight_kg`, `additional_cost_per_kg`.
  - `xxxx_create_consignments_table.php`: `id` (ULID), `order_id`, `courier_code`, `tracking_code`, `consignment_id`, `cod_amount`, `courier_charge`, `status`, `label_url`.
- **Courier Drivers (`app/Modules/Logistics/Drivers/`)**:
  - `Contracts/CourierServiceInterface.php`: Contract with `bookConsignment()`, `trackConsignment()`, `getDeliveryCharge()`, `testConnection()`.
  - `SteadfastCourierDriver.php`: Consignment creation, tracking, balance inquiry.
  - `PathaoCourierDriver.php`: Store selection, consignment booking, price calculation.
  - `RedxCourierDriver.php`: Consignment booking, tracking, pickup request.
- **Domain Actions (`app/Modules/Logistics/Actions/`)**:
  - `CalculateDynamicDeliveryFeeAction.php`: Zone + weight + cart value threshold engine.
  - `BookCourierConsignmentAction.php`: 1-click single or bulk booking.
  - `GenerateThermalShippingLabelAction.php`: 4x6 thermal barcode PDF label generator.
  - `HandleCourierWebhookAction.php`: Updates order status upon courier tracking transitions.
  - `ReconcileCodDisbursementAction.php`: Matches courier payment reports against pending COD orders.
  - `TestCourierConnectionAction.php`: Pre-flight API test.
- **Admin UI (`resources/js/Pages/Admin/Logistics/`)**:
  - `CourierSettings.vue`: API credentials, active toggles, test buttons.
  - `DeliveryPricingMatrix.vue`: Zone rates and weight brackets.
  - `CodReconciliation.vue`: Match courier payouts against orders.

### 3. Verification & Acceptance Criteria
- 1-click booking creates Steadfast/Pathao consignment and stores tracking code.
- Thermal shipping labels print with barcode, order ID, address, and COD cash collection amount.
- Delivery fee calculates dynamically at checkout when customer selects Division/District.

---

## Phase 5: Omnichannel Messaging, Dynamic SMTP, AI Voice Calling & Social Feeds

### 1. Objectives
- Admin-configurable dynamic SMTP mail with "Send Test Email" handshake.
- Automated SMS (Greenweb, Reve) & WhatsApp Business API order notifications.
- AI Automated Outbound Voice Calling Verification Agent (Bangla & English with DTMF 1/2 or speech intent).
- Social commerce feeds: Google Merchant Center XML feed, Facebook Catalog sync, TikTok catalog exports.

### 2. Files to Create
- **Database Migrations (`app/Modules/Communication/Database/Migrations/`)**:
  - `xxxx_create_mail_settings_table.php`: `id`, `driver`, `host`, `port`, `encryption`, `username`, `password` (encrypted), `from_address`, `from_name`.
  - `xxxx_create_voice_calling_settings_table.php`: `id`, `provider` (`twilio`, `vapi`, `retell`, `obd`), `is_active`, `credentials` (encrypted JSON), `language` (`bn-BD`, `en-US`), `script_template`.
  - `xxxx_create_voice_call_logs_table.php`: `id`, `order_id`, `phone_number`, `duration_seconds`, `customer_response` (`confirmed`, `cancelled`, `unanswered`), `recording_url`, `transcript`.
- **Domain Actions (`app/Modules/Communication/Actions/`)**:
  - `SendTestEmailAction.php`: Validates SMTP connection and sends test email.
  - `SendSmsOtpAction.php`: Dispatches 4-digit verification code.
  - `VerifySmsOtpAction.php`: Verifies OTP within 15-minute TTL.
  - `TriggerAiVoiceVerificationCallAction.php`: Triggers outbound phone call within 60s of COD order.
  - `HandleVoiceCallWebhookAction.php`: Processes DTMF keypress (1 = confirmed, 2 = cancelled).
  - `TestVoiceCallAction.php`: Places immediate test call to admin's phone.
  - `GenerateGoogleMerchantFeedAction.php`: Generates `/feeds/google-merchant.xml`.
  - `GenerateFacebookCatalogFeedAction.php`: Generates `/feeds/facebook-catalog.csv`.
- **Admin UI (`resources/js/Pages/Admin/Communication/`)**:
  - `SmtpSettings.vue`: Email configuration with "Send Test Email" button.
  - `VoiceCallingSettings.vue`: Voice AI toggle, provider selection, script editor, and "Make Test Call" button.
  - `FeedSettings.vue`: URLs for Google Merchant, Facebook Catalog, and TikTok feeds.

### 3. Verification & Acceptance Criteria
- Admin can send a test email and verify SMTP settings before saving.
- Voice AI dials phone, plays Bangla prompt, and keypress `1` confirms order.
- `/feeds/google-merchant.xml` renders valid Google Shopping XML with pricing, availability, and image URLs.

---

## Phase 6: Pluggable AI Module & Backend Trading Intelligence

### 1. Objectives
- Decoupled AI module allowing administrators to connect any provider (**Google Gemini**, **OpenAI**, **Anthropic Claude**, or **Ollama / Local LLMs**).
- Encrypted database settings (`ai_model_settings`) with pre-flight "Test AI Connection" action.
- Catalog SEO enrichment, smart restocking demand forecasting, dispute support, and tax anomaly detection.

### 2. Files to Create
- **Database Migrations (`app/Modules/AI/Database/Migrations/`)**:
  - `xxxx_create_ai_model_settings_table.php`: `id`, `provider` (`gemini`, `openai`, `claude`, `ollama`), `name`, `is_active`, `credentials` (encrypted JSON), `default_model`, `temperature`, `max_tokens`, `assigned_features`.
  - `xxxx_create_ai_usage_logs_table.php`: `id`, `provider`, `model`, `feature`, `prompt_tokens`, `completion_tokens`, `estimated_cost`, `created_at`.
- **AI Drivers (`app/Modules/AI/Drivers/`)**:
  - `Contracts/AiProviderInterface.php`: Contract with `generateText()`, `extractAttributes()`, `testConnection()`.
  - `GoogleGeminiDriver.php`: Gemini 1.5 Pro / Flash integration.
  - `OpenAiDriver.php`: GPT-4o / GPT-4o-mini integration.
  - `AnthropicClaudeDriver.php`: Claude 3.5 Sonnet integration.
  - `OllamaLocalDriver.php`: Self-hosted local LLM integration.
- **Domain Actions (`app/Modules/AI/Actions/`)**:
  - `EnrichProductCatalogSeoAction.php`: Generates meta titles, descriptions, bullets from specs.
  - `ForecastRestockingDemandAction.php`: Analyzes sales velocity & lead time to recommend PO quantities.
  - `DraftCustomerSupportReplyAction.php`: Drafts polite response for return inquiries.
  - `DetectTaxAnomaliesAction.php`: Scans for abnormal frequencies of Mushak 6.7 Credit Notes.
  - `TestAiConnectionAction.php`: Pre-flight handshake testing credentials and measuring latency.
- **Admin UI (`resources/js/Pages/Admin/AI/`)**:
  - `AiSettings.vue`: Provider toggles, model selector, API key input, and "Test Connection" button.
  - `AiUsageDashboard.vue`: Token analytics, features usage, and cost estimates.

### 3. Verification & Acceptance Criteria
- Admin connects Gemini/OpenAI API key and "Test AI Connection" reports valid handshake.
- 1-click catalog enrichment generates compelling SEO titles and descriptions from imported product specs.

---

## Phase 7: Dual Sourcing Procurement (Import & Local) & Landed Cost Engine

### 1. Objectives
- Support both **Global Commercial Importation** (CI, PI, LC/TT, BOE customs clearance) and **Local Domestic Procurement** (BDT PO, supplier Mushak 6.3, BIN verification).
- Unified Landed Cost allocation: Converts foreign FX at bank settlement rates + Freight + Marine Insurance + C&F port charges + Customs Tariffs (CD, RD, SD, AT, AIT) or domestic transport into 100% accurate COGS in BDT (৳).
- Inward Receiving (GRN) into multi-warehouse depots with FIFO valuation and USB/Bluetooth barcode scanning.
- Automated statutory **Mushak 6.6 (VDS Certificates)** and TDS under Income Tax Act 2023.

### 2. Files to Create
- **Database Migrations (`app/Modules/Procurement/Database/Migrations/`)**:
  - `xxxx_create_suppliers_table.php`: `id`, `name`, `type` (`international`, `local`), `country`, `currency`, `bin_number`, `tin_number`, `contact_person`, `bank_details`.
  - `xxxx_create_import_purchase_orders_table.php`: `id` (ULID), `supplier_id`, `pi_number`, `lc_number`, `issuing_bank`, `fx_currency`, `fx_rate_settlement`, `status`.
  - `xxxx_create_bills_of_entry_table.php`: `id`, `import_po_id`, `boe_number` (বিল অফ এন্ট্রি), `boe_date`, `port_of_clearance`, `customs_assessment_value_bdt`, `cd_amount`, `rd_amount`, `sd_amount`, `advance_tax_at_amount`, `advance_income_tax_ait_amount`.
  - `xxxx_create_local_purchase_orders_table.php`: `id` (ULID), `supplier_id`, `po_number`, `supplier_mushak_6_3_number`, `supplier_mushak_date`, `subtotal_bdt`, `vat_amount`, `vds_amount`, `tds_amount`, `status`.
  - `xxxx_create_goods_received_notes_table.php`: `id` (ULID), `source_type` (`import`, `local`), `source_id`, `warehouse_id`, `grn_number`, `received_date`, `received_by_user_id`, `discrepancy_notes`.
  - `xxxx_create_landed_cost_allocations_table.php`: `id`, `grn_id`, `total_procurement_cost_bdt`, `total_freight_c_and_f_bdt`, `total_duties_bdt`, `allocated_cogs_per_unit_bdt`.
- **Domain Actions (`app/Modules/Procurement/Actions/`)**:
  - `CalculateImportLandedCostAction.php`: Aggregates FX settlement, BOE tariffs, freight, and C&F into unit COGS.
  - `CalculateLocalLandedCostAction.php`: Purchase price + local carrying/handling into unit COGS.
  - `ReceiveGoodsNoteAction.php`: Inward intake into warehouse with barcode verification.
  - `IssueMushak6_6VdsAction.php`: Generates statutory VDS deduction certificate.
  - `DeductProcurementTdsAction.php`: Deducts income tax on supplier payment vouchers.
- **Admin UI (`resources/js/Pages/Admin/Procurement/`)**:
  - `ImportOrderManager.vue`: CI, LC/TT tracking, BOE entry and tariff calculation.
  - `LocalPoManager.vue`: Domestic PO, Mushak 6.3 recording, VDS calculation.
  - `GrnReceiving.vue`: Warehouse intake with USB barcode scanner support.

### 3. Verification & Acceptance Criteria
- Landed cost correctly computes true unit COGS in BDT (৳) across both imported and domestic batches.
- Inward GRN updates warehouse inventory and logs FIFO purchase register (Mushak 6.1).

---

## Phase 8: Catalog, Matrix Variants, Pricing & Local Storefront (Inertia SSR + SCSS)

### 1. Objectives
- Product taxonomy, multi-dimensional matrix variant manager (Size $\times$ Color $\times$ Material), brands, HS codes.
- Retail (B2C) and Wholesale (B2B tier pricing) pricing engines in BDT (৳).
- Sub-50ms instant search with autocomplete and typo tolerance.
- Interactive slide-out drawer cart with dynamic Free Shipping progress bar.
- Ultra-fast 1-page / drawer checkout with cascading BD administrative boundaries.
- Responsive Vue 3 storefront styled exclusively in SCSS with SSR, Schema.org rich snippets, and social share cards.

### 2. Files to Create
- **Database Migrations (`app/Modules/Catalog/Database/Migrations/` & `Pricing`)**:
  - `xxxx_create_categories_table.php`: `id`, `name`, `slug`, `parent_id`, `image`, `is_featured`, `seo_title`, `seo_description`.
  - `xxxx_create_brands_table.php`: `id`, `name`, `slug`, `logo`, `origin_country`.
  - `xxxx_create_products_table.php`: `id` (ULID), `category_id`, `brand_id`, `name`, `slug`, `hs_code`, `warranty_policy`, `is_published`, `is_featured`.
  - `xxxx_create_product_variants_table.php`: `id` (ULID), `product_id`, `sku`, `barcode_ean`, `purchase_cost_bdt`, `selling_price_bdt`, `stock_quantity`, `weight_kg`.
  - `xxxx_create_pricing_tiers_table.php`: `id`, `product_variant_id`, `min_quantity` (MOQ), `tier_price_bdt`, `customer_group` (`retail`, `wholesale_b2b`).
- **Storefront Components (`resources/js/Components/Storefront/`)**:
  - `HeaderNav.vue`: Bilingual toggle (বাংলা/EN), category mega-menu, cart drawer button.
  - `InstantSearchBar.vue`: Sub-50ms autocomplete dropdown with product image, price, stock badge.
  - `ProductCard.vue`: Aspect-ratio media container, WebP/AVIF responsive breakpoints, badge overlays.
  - `ProductGallery.vue`: High-res zoom lens, thumbnail carousel, color-swatch gallery sync.
  - `SlideOutCartDrawer.vue`: Dynamic Free Shipping progress bar, 1-click upsells, quantity adjustments.
  - `CascadingAddressSelector.vue`: Division $\rightarrow$ District $\rightarrow$ Thana cascading selector.
- **Storefront Pages (`resources/js/Pages/Storefront/`)**:
  - `Home.vue`: Preloaded hero banner, featured categories, flash deal countdowns, trust badges.
  - `ProductDetail.vue`: Full PDP with Schema.org JSON-LD, specs table, reviews, sticky mobile "Buy Now" drawer.
  - `CategoryList.vue`: Faceted filtering (price range, brand, color), Inertia partial reloads.
  - `Checkout.vue`: 1-page checkout with guest ordering, delivery fee calculator, honeypot traps.
  - `OrderTracking.vue`: Self-service milestone timeline progress portal.

### 3. Verification & Acceptance Criteria
- Search displays live results in $< 50ms$ without full page reload.
- Color swatch change instantly updates gallery images and variant price.
- Core Web Vitals: LCP $< 1.2s$, INP $< 50ms$, CLS $= 0.0$.

---

## Phase 9: Comprehensive NBR VAT, Input Credit (Import & Local) & Income Tax Compliance

### 1. Objectives
- Enforce full statutory compliance under the **Bangladesh Value Added Tax and Supplementary Duty Act 2012** and **Income Tax Act 2023**.
- Automated issuance of statutory **Mushak 6.3** Tax Invoices with non-gap atomic serial numbers.
- Claiming both **Import Advance Tax (AT - 5%)** and **Local Supplier Input VAT** as Input Tax Credit (রেয়াত) on monthly **Mushak 9.1** returns.
- Statutory **Mushak 6.7** Credit Notes for sales returns, cancellations, and decreasing tax adjustments.
- Statutory **Mushak 6.8** Debit Notes, **Mushak 6.5** Transfer Challans, and **Mushak 6.1 / 6.2** registers.

### 2. Files to Create
- **Database Migrations (`app/Modules/TaxCompliance/Database/Migrations/`)**:
  - `xxxx_create_vat_invoices_table.php`: `id` (ULID), `order_id`, `mushak_serial` (`M6.3-YYYYMM-XXXXXX`), `customer_bin`, `subtotal_bdt`, `sd_amount_bdt`, `vat_amount_bdt`, `immutable_hash`, `issued_at`.
  - `xxxx_create_vat_credit_notes_table.php`: `id` (ULID), `original_mushak_6_3_id`, `credit_note_serial` (`M6.7-YYYYMM-XXXXXX`), `return_reason`, `decreasing_vat_adjustment_bdt`, `issued_at`.
  - `xxxx_create_vat_debit_notes_table.php`: `id` (ULID), `debit_note_serial` (`M6.8-YYYYMM-XXXXXX`), `supplier_id`, `purchase_return_reason`, `increasing_adjustment_bdt`.
  - `xxxx_create_vat_transfer_challans_table.php`: `id` (ULID), `challan_serial` (`M6.5-YYYYMM-XXXXXX`), `source_warehouse_id`, `destination_warehouse_id`, `transport_details`.
  - `xxxx_create_vat_vds_certificates_table.php`: `id` (ULID), `certificate_serial` (`M6.6-YYYYMM-XXXXXX`), `supplier_id`, `procurement_po_id`, `vds_amount_deducted_bdt`.
  - `xxxx_create_mushak_9_1_summaries_table.php`: `id`, `tax_period` (`YYYY-MM`), `total_sales_output_vat`, `total_import_at_credit_claimed`, `total_local_input_vat_credit_claimed`, `total_decreasing_adjustments`, `net_vat_payable_bdt`.
- **Domain Actions (`app/Modules/TaxCompliance/Actions/`)**:
  - `GenerateMushak6_3Action.php`: Atomic row-locked non-gap serial generation and SHA-256 integrity hash.
  - `GenerateMushak6_7CreditNoteAction.php`: Linked to original Mushak 6.3 with decreasing VAT adjustment.
  - `ClaimImportAdvanceTaxCreditAction.php`: Automatically extracts 5% AT from Bill of Entry into Mushak 9.1 credit.
  - `ClaimLocalInputVatCreditAction.php`: Extracts input VAT from verified local supplier Mushak 6.3 invoices.
  - `CompileMushak9_1ReturnAction.php`: Compiles monthly NBR VAT Return.
- **Admin UI (`resources/js/Pages/Admin/Tax/`)**:
  - `Mushak63Viewer.vue`: Printable statutory NBR Mushak 6.3 invoice.
  - `Mushak91Return.vue`: Monthly VAT Return dashboard with Output vs Input Tax Credit offsetting.
  - `VatRegisters.vue`: Exportable Mushak 6.1 (Purchase Register) and 6.2 (Sales Register).

### 3. Verification & Acceptance Criteria
- Outward sales issue non-gap Mushak 6.3 invoices before courier dispatch.
- Returns and cancellations generate statutory Mushak 6.7 Credit Notes and reflect decreasing adjustments.
- Monthly Mushak 9.1 correctly claims both Import AT (5%) and local supplier input credit.

---

## Phase 10: Executive Trading Analytics, Profit & Loss Dashboard & QA Testing

### 1. Objectives
- Executive Trading Analytics Dashboard visualizing true commercial health.
- Real-time calculations: Gross Profit in BDT, Landed COGS in BDT, Sales Revenue, Courier Cash Inflows, NBR Net VAT Payable, and AI Token Costs.
- Comprehensive automated test suite (Pest PHP backend tests and Vue component tests).
- Production build validation and security review.

### 2. Files to Create
- **Domain Analytics (`app/Modules/Admin/Actions/`)**:
  - `CalculateTradingProfitAndLossAction.php`: Real-time P&L (Gross Revenue - True Landed COGS - Courier Return Penalties - Merchant Gateway Fees = True Net Profit in BDT).
  - `ReconcileDisbursedCourierCashAction.php`: Reconciles Steadfast/Pathao bank payouts against order totals.
  - `AuditFraudPreventionMetricsAction.php`: Calculates total BDT saved by blocking fake orders.
- **Admin UI (`resources/js/Pages/Admin/Dashboard/`)**:
  - `ExecutiveDashboard.vue`:
    - Metric Cards: Sales Revenue (৳), True Landed COGS (৳), Gross Margin (%), Net Profit (৳).
    - Courier Logistics Card: Dispatched vs Delivered vs Returned rates, COD pending cash reconciliation.
    - Anti-Fraud Shield Card: Fake orders blocked, advance charges collected, courier fee savings.
    - NBR Tax Card: Output VAT collected, Input Tax Credit (AT + Local) claimed, Net VAT payable.
    - AI Operations Card: Tokens consumed, cost in USD/BDT.
- **Automated Test Suites (`tests/Feature/` & `tests/Unit/`)**:
  - `tests/Feature/Fraud/FraudRiskScoringTest.php`: Tests honeypots, timing guards, and courier delivery rate checks.
  - `tests/Feature/Payment/DynamicGatewayTest.php`: Tests bKash/Nagad encryption and refund execution.
  - `tests/Feature/Logistics/CourierConsignmentTest.php`: Tests Steadfast booking and label generation.
  - `tests/Feature/Procurement/LandedCostAllocationTest.php`: Tests BOE customs duty and domestic transport COGS math.
  - `tests/Feature/Tax/MushakComplianceTest.php`: Tests Mushak 6.3 atomic serials and 6.7 credit note adjustments.
  - `tests/Feature/Seo/SitemapAndSchemaTest.php`: Tests Schema.org JSON-LD and XML sitemap generation.

### 3. Verification & Acceptance Criteria
- All Pest tests pass with 100% assertions green.
- `./vendor/bin/pint --test` passes with zero PSR-12 styling issues.
- `npx vue-tsc --noEmit` reports 0 TypeScript errors.
- `npm run build` and `npm run build:ssr` compile successfully.

---

## Phase Execution Checklist

Use this checklist during implementation to track progress across every phase:

- [ ] **Phase 1**: Foundation, Laravel 13, Vue 3, Sass/SCSS, Inertia SSR, Spatie MediaLibrary, Spatie RBAC, SEO Layout
- [ ] **Phase 2**: 7-Layer Anti-Fraud Shield, Courier Return Rate API, Honeypots, Merchant Blacklist Directory
- [ ] **Phase 3**: Dynamic Payments (bKash, Nagad, SSLCommerz), Encrypted Keys, Advance COD Delivery Engine
- [ ] **Phase 4**: Automated Courier Logistics (Steadfast, Pathao), 4x6 Thermal Labels, Dynamic Delivery Pricing
- [ ] **Phase 5**: Dynamic SMTP with Test Mail, SMS OTP, AI Outbound Voice Calling Verification Agent, Feeds
- [ ] **Phase 6**: Pluggable AI Module (Gemini, OpenAI, Claude), Dynamic Credentials, Restock Forecasting, SEO Enrichment
- [ ] **Phase 7**: Dual Sourcing Procurement (Global Imports + Local BDT), Landed Cost COGS Engine, Barcode GRN
- [ ] **Phase 8**: Matrix Variants, Sub-50ms Search, Slide-Out Cart Drawer, 1-Page Cascading BD Checkout
- [ ] **Phase 9**: Bangladesh NBR VAT (Mushak 6.3, 6.7, 6.6, 6.8, 6.5, 9.1), Input Credit (Import AT + Local VAT)
- [ ] **Phase 10**: Executive Trading P&L Dashboard, Courier Reconciliation, Full Pest Test Suite & Production QA
