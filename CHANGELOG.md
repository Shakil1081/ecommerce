# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

---

## [Unreleased]

---

## [0.7.0] - 2026-10-29

### Added
- **Automated Courier Logistics, Dynamic Delivery Pricing & Payout Reconciliation (`App\Modules\Logistics`, `App\Modules\Admin`, `App\Modules\Order`)**:
  - Dynamic courier gateway configuration schema with encrypted credentials (`encrypted:array`), active toggles, sandbox flags, live base URLs, supported features JSON array, and pre-flight "Test Courier API Connection" action (`CourierGatewaySetting`, `TestCourierConnectionAction`).
  - Unified `CourierGatewayManager` and `CourierAdapterContract` interface for decoupled multi-carrier logistics (Steadfast, Pathao, RedX, Paperfly, Sundarban).
  - Courier consignments tracking ledger (`CourierConsignment`) tracking ULIDs, order IDs, courier codes, consignment IDs, tracking numbers, delivery zones, fees, COD amounts, and webhook payloads with relationships on `Order` (`$order->consignments`, `$order->latestConsignment`).
  - Standardized DTO contracts: `CourierBookingResultDto`, `CourierTrackingResultDto`, and `CourierConnectionTestResultDto`.
  - Database seeder (`CourierGatewaySeeder`) initializing default courier settings for Bangladesh providers.
  - Steadfast Courier driver (`SteadfastCourierAdapter`) with consignment booking (`/create_order`), tracking status inquiry (`/status_by_cid/{id}`), balance check (`/get_balance`), and delivery status mapping.
  - Inbound status webhook listener (`SteadfastWebhookController`) at `/webhooks/courier/steadfast` with optional secret verification, consignment ledger updates, and automated order state transitions to delivered/returned with audit histories.
  - Pathao Courier driver (`PathaoCourierAdapter`) with automated OAuth token management and caching (`/aladdin/api/v1/issue-token`), store resolution (`/aladdin/api/v1/stores`), parcel consignment creation (`/aladdin/api/v1/orders/issue-order`), and parcel status inquiry (`/aladdin/api/v1/orders/{id}/info`).
  - Inbound Pathao status webhook listener (`PathaoWebhookController`) at `/webhooks/courier/pathao` supporting signature validation, consignment status synchronization, and order lifecycle transitions.
  - Dynamic Bangladesh delivery pricing and zone calculation engine (`ShippingZone`, `ShippingRate`, `CalculateShippingRateAction`, `ShippingRateCalculationDto`) featuring zone classifications (`inside_dhaka` ৳70, `dhaka_suburbs` ৳100, `outside_dhaka` ৳130), weight-tier brackets (base 1.0kg + ৳20/kg incremental), cart free delivery thresholds, and courier-specific price overrides.
  - Database seeder (`ShippingZoneSeeder`) initializing Bangladesh shipping zones, estimated delivery windows, and default rates.
  - Consignment booking dispatchers for single and bulk dispatches (`BookCourierConsignmentAction`, `BulkBookCourierConsignmentsAction`) with auto-fallback to active couriers and order audit histories.
  - Printable 4x6 inch (100mm x 150mm) / A6 thermal barcode shipping label generator (`GenerateShippingLabelHtmlAction`, `ShippingLabelController`) with crisp pure-PHP Code128 vector SVG barcodes, recipient details, and prominent COD cash collection boxes.
  - Back-office Courier Gateway Management portal (`CourierSettings.vue`, `CourierAdminController`) featuring masked credentials, pre-flight test API connection handshake with latency measurement, and webhook URL clipboard copy.
  - Shipping Zones & Pricing management UI (`ShippingZones.vue`) allowing merchants to adjust base rates, weight thresholds, and free shipping triggers without code deployments.
  - Automated COD Cash Reconciliation system (`courier_disbursements`, `courier_disbursement_items`, `ReconcileCodPaymentAction`, `CodReconciliation.vue`) tracking collected COD amounts, courier service fees, net disbursements, and automated discrepancy flagging.

---

## [0.6.0] - 2026-10-29

### Added
- **Dynamic Payment Gateway Module & Advance COD Delivery Engine (`App\Modules\Payment`, `App\Modules\Admin`, `App\Modules\Order`)**:
  - Dynamic payment gateway configuration schema with encrypted credentials (`encrypted:array`), active toggles, sandbox flags, gateway codes (`bkash`, `nagad`, `sslcommerz`, `stripe`, `cod`, `bank_transfer`), and pre-flight "Test API Connection" verification (`PaymentGatewaySetting`, `TestGatewayConnectionAction`).
  - Unified `PaymentGatewayManager` and `PaymentGatewayContract` interface for decoupled multi-provider digital payments.
  - Payment transactions ledger (`PaymentTransaction`) tracking ULIDs, order IDs, gateway codes, transaction references, fees, and lifecycle statuses.
  - bKash Tokenized Checkout driver (`BkashPaymentAdapter`) with automated grant token retrieval and caching, checkout session creation, execution verification, and customer return callback handler (`BkashCallbackController`).
  - Nagad digital payment adapter (`NagadPaymentAdapter`, `NagadCryptographicService`) with RSA public/private key encryption and SHA256 digital signatures, two-step checkout initialization, return callback validation, and order fulfillment (`NagadCallbackController`).
  - SSLCommerz hosted session gateway adapter (`SslcommerzPaymentAdapter`) with dynamic session creation, return callback routes (success, fail, cancel), Instant Payment Notification (IPN) asynchronous verification (`SslcommerzCallbackController`), and pre-flight validation server handshake.
  - Advance partial delivery fee checkout engine (`ProcessAdvanceDeliveryPaymentAction`, `ProcessAdvanceDeliveryPaymentController`) enabling high-risk Cash on Delivery buyers to prepay courier shipping fees (৳70/৳100/৳130) to confirm orders.
  - Back-office Vue 3 Inertia payment gateway management portal (`PaymentGatewaySettings.vue`, `PaymentAdminController`) featuring masked secret credentials, live pre-flight API test handshake with latency metrics, active/sandbox mode switches, and 1-click Webhook/IPN URL copy shortcuts.

---

## [0.5.0] - 2026-10-29

### Added
- **7-Layer Anti-Fraud & Fake Order Defense Shield (`App\Modules\Order`, `App\Modules\Customer`, `App\Modules\Logistics`, `App\Modules\Common`)**:
  - Multi-signal order fraud risk scoring engine (0-100) combining honeypots, timing guards, BD telco validity, text entropy, order velocity, and cross-courier return rates with tiered automated enforcement (`CalculateOrderFraudRiskScoreAction`, `OrderFraudRiskAssessmentDto`).
  - Automated checkout enforcement disabling Cash on Delivery when fraud risk $\ge 70$ or courier return rate $> 40\%$, and rejecting blacklisted bot submissions.
  - Cross-courier delivery intelligence adapters querying customer delivery history and return rates for **Steadfast** (`SteadfastDeliveryIntelligenceService`) and **Pathao** (`PathaoDeliveryIntelligenceService`).
  - Aggregated multi-courier delivery performance intelligence with 24-hour cache layer and risk tier calculation (`AggregatedCourierIntelligenceService`, `CourierDeliveryProfileDto`).
  - Bangladesh mobile operator validation rule with operator detection (GP, Banglalink, Robi/Airtel, Teletalk), E.164 normalization, and dummy/sequential repeating digit rejection (`BangladeshPhoneNumberRule`).
  - Multi-lingual Shannon text entropy and gibberish scoring engine (0-100) supporting English and Bengali (বাংলা) UTF-8 (`TextEntropyService`).
  - Order velocity throttling enforcing max 1 active unfulfilled COD order per phone, and max 2 orders/day burst limits per IP and device fingerprint (`CheckOrderVelocityAction`).
  - Added indexed `ip_address`, `device_fingerprint`, and `user_agent` metadata tracking on Orders table.
  - Invisible bot honeypot traps and checkout submission timing guards with encrypted tokens (`HoneypotTimingGuardService`).
  - Merchant blacklist and whitelist directory (`customer_fraud_lists`) with Vue 3 Inertia management UI (`FraudDirectory.vue`, `FraudDirectoryAdminController`).
  - Automated directory matching action (`CheckCustomerFraudListAction`) enabling VIP zero-friction checkout for whitelisted customers and instant blocks for blacklisted actors.
  - Back-office Google reCAPTCHA (v2 Checkbox, v3 Invisible) and Cloudflare Turnstile dynamic bot protection settings with encrypted keys, page-level protection toggles, and pre-flight "Test API Connection" validation (`BotSecuritySettingsController`, `BotCaptchaVerificationService`, `BotSecuritySettings.vue`).

---

## [0.4.0] - 2026-10-29

### Added
- **Shopping Cart Engine & Slide-Out Drawer (`App\Modules\Cart`)**:
  - Persistent guest session and authenticated user shopping cart synchronization (`GetActiveCartAction`, `MergeGuestCartAction`).
  - Dynamic volume tier pricing and wholesale customer group price computation on cart items.
  - Dynamic free shipping threshold calculation (৳2,000 threshold) with live progress bar.
  - Slide-out Vue 3 cart drawer with item quantity steppers and cart preview (`CartDrawer.vue`).
- **Promotional Coupon & Discount Code Engine (`App\Modules\Marketing`)**:
  - Percentage discounts (with optional cap), fixed BDT discounts, and free shipping triggers.
  - Minimum cart spend requirements, expiry dates, total usage caps, and per-customer usage limits.
  - Atomic redemption tracking (`coupon_usages`) and cart recomputation.
- **Order Domain Model & Finite State Machine (`App\Modules\Order`)**:
  - Strict lifecycle state machine: `pending` $\rightarrow$ `confirmed` $\rightarrow$ `processing` $\rightarrow$ `picked_and_packed` $\rightarrow$ `handed_to_courier` $\rightarrow$ `out_for_delivery` $\rightarrow$ `delivered` / `returned` / `cancelled`.
  - Immutable status transition audit trail (`order_status_histories`) and domain event dispatching (`OrderStatusChangedEvent`).
  - Order conversion from shopping cart with Bangladesh delivery zone pricing (Inside Dhaka ৳70, Suburbs ৳100, Outside Dhaka ৳130).
- **Atomic Stock Reservation & Flash Sale Concurrency Protection (`App\Modules\Inventory`)**:
  - Row-level pessimistic locking (`lockForUpdate()`) to prevent flash-sale overselling.
  - Stock hold reservations with 15-minute TTL expiration.
  - Automatic reservation release upon timeout or cancellation (`inventory:release-expired-reservations`).
  - Atomic stock commit upon order dispatch (decrements physical & reserved count).
- **Back-Office Order Management & Lifecycle Dispatch UI (`App\Modules\Admin`)**:
  - Vue 3 Inertia order dashboard with status filter tabs and search (`OrderIndex.vue`).
  - Comprehensive order detail view with items, COGS margins, financial breakdown, cascading address, and status transition modal (`OrderDetail.vue`).

---

## [0.3.0] - 2026-10-29

### Added
- **Dual Sourcing Procurement Module (`App\Modules\Procurement`)**:
  - Global Commercial Importation tracking with Proforma Invoices (PI), Commercial Invoices (CI), Letter of Credit (LC), and Telegraphic Transfer (TT).
  - FX Currency conversion at bank settlement exchange rates in Bangladeshi Taka (BDT ৳).
  - Bangladesh Customs Bill of Entry (BOE / বিল অফ এন্ট্রি) with statutory tariffs at Chattogram / Dhaka ICD ports: Customs Duty (CD), Regulatory Duty (RD), Supplementary Duty (SD), Advance Tax (AT - 5%), and Advance Income Tax (AIT - 5%).
  - Domestic Bangladesh Procurement in BDT (৳) with supplier NBR Mushak 6.3 Tax Invoices.
  - VDS (VAT Deducted at Source) calculation and automated issuance of statutory NBR **Mushak 6.6 Certificates (উৎসে মূসক কর্তন সনদপত্র)**.
  - TDS (Tax Deducted at Source) withholding under the Income Tax Act 2023.
  - Unified Landed Cost Allocation Engine (`CalculateImportLandedCostAction`, `CalculateLocalLandedCostAction`) absorbing ocean/air freight, marine insurance, C&F port charges, customs tariffs, and local transport into true BDT unit COGS.
  - Inward Goods Received Notes (GRN) with barcode scanner logging, discrepancy notes, and atomic multi-depot physical inventory intake.
  - Back-office Vue 3 Inertia management UI for Suppliers Directory (`SupplierIndex.vue`), Import Orders (`ImportOrderIndex.vue`), Local Purchase Orders (`LocalPoIndex.vue`), and GRN Intake (`GrnReceiving.vue`).

---

## [0.2.0] - 2026-10-29

### Added
- **Catalog, Matrix Variants, Multi-Warehouse Inventory & Reviews (`App\Modules\Catalog`, `Inventory`, `Pricing`)**:
  - Bangladesh Geographic Administrative Boundaries (8 Divisions, 64 Districts, Thanas) with courier delivery zone tagging (`inside_dhaka`, `dhaka_suburbs`, `outside_dhaka`) and bilingual seeder.
  - Category hierarchy tree with SEO slugs and brand profiles with country of origin.
  - Matrix product engine with Cartesian variant generator (SKUs, Barcodes/EAN-13, and statutory customs HS codes).
  - B2B Wholesale Tier Pricing & MOQ volume discount engine (`CalculateApplicablePriceAction`).
  - Multi-Warehouse Inventory Hubs (Central & Regional) with atomic stock tracking.
  - Inter-Depot Stock Transfers with statutory NBR **Mushak 6.5 Transfer Challan (চুক্তিবদ্ধ বা আন্তঃডিপো স্থানান্তর চালানপত্র)**.
  - Customer 1-5 star reviews with verified buyer badges and Spatie MediaLibrary customer photo attachments.
  - Admin Catalog Management & Matrix Variant Editor UI (`ProductIndex.vue`, `ProductForm.vue`, `ProductMatrixEditor.vue`).

---

## [0.1.0] - 2026-10-29

### Added
- Initial enterprise repository architecture, domain rules, and commercial hybrid trading specifications (`AGENTS.md`, `GEMINI.md`, `README.md`).
- Editor configuration standards (`.editorconfig`).

