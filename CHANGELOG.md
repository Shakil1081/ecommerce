# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

---

## [Unreleased]

### Planned
- **Phase 10: Backend AI Intelligence, Trading P&L Analytics, Audit Trail & Automated QA**:
  - Multi-provider AI Intelligence engine (Gemini, OpenAI, Claude, Local LLMs) with token metrics and dynamic credential management.
  - Automated AI catalog SEO enrichment from imported manufacturer specs.
  - Smart restocking & procurement demand forecasting.
  - Executive Trading Profit & Loss (P&L) dashboard with true landed COGS in BDT (৳).
  - Tamper-proof audit logging and end-to-end regression validation.

---

## [0.9.0] - 2026-10-30

### Added
- **Statutory Mushak 6.3 Tax Invoice Generation with Atomic Serial Locks (`App\Modules\TaxCompliance`, `App\Modules\Order`)**:
  - Mutex row-level locking on `mushak_sequences` table guaranteeing non-gap sequential numbering formatted as `M6.3-YYYYMM-XXXXXX` (`MushakSequenceService`, `GenerateMushak63InvoiceAction`).
  - Line-by-line statutory VAT computation (15% Standard, 5% Retail Trader, Supplementary Duty, Exempt) with SHA-256 historical cryptographic integrity hash and tamper verification (`VerifyMushak63IntegrityAction`).
  - Back-office administration and official printable government format (`Mushak63Show.vue`, `Mushak63Index.vue`) adhering to NBR statutory standards.
- **Monthly VAT Return (Mushak 9.1) & Automated Input Tax Credit (রেয়াত) Engine (`App\Modules\TaxCompliance`, `App\Modules\Procurement`)**:
  - Automated compilation of monthly NBR Mushak 9.1 VAT Returns (`GenerateMushak91ReturnAction`, `SubmitMushak91ReturnAction`, `Mushak91Return`).
  - Aggregation of Part 3 Outward Supplies (Output VAT), Part 4 Rebatable Input Tax Credit (claiming both 5% Import Advance Tax from Bill of Entry and Local Supplier VAT from verified vendor Mushak 6.3 invoices), Part 5 Increasing/Decreasing Adjustments, and Part 6 Net VAT Payable or Refundable Credit Carry-Forward.
  - Back-office return management, recalculation triggers, and treasury challan deposit recording (`Mushak91Index.vue`).
- **Statutory Withholding Tax (VDS) & Mushak 6.6 Certificates (`App\Modules\TaxCompliance`, `App\Modules\Procurement`)**:
  - Automated statutory Mushak 6.6 Withholding Certificates (`GenerateMushak66CertificateAction`, `Mushak66Certificate`) for local procurement from domestic vendors with 9/13-digit BIN verification.
  - Treasury deposit tracking, withholding tax ledger, and administrative directory (`Mushak66Index.vue`).
- **Inter-Depot Stock Transfers via Mushak 6.5 Transfer Challans (`App\Modules\TaxCompliance`, `App\Modules\Inventory`)**:
  - Statutory Mushak 6.5 Goods Transfer Challan engine (`GenerateMushak65ChallanAction`, `Mushak65Challan`, `Mushak65Item`) for inter-depot movements between physical company warehouse hubs.
  - Transport carrier registration, driver identity details, and frozen inventory valuation across depots.
- **Customer Sales Returns (RMA) & Mushak 6.7 Credit Notes (`App\Modules\TaxCompliance`, `App\Modules\Order`, `App\Modules\Inventory`)**:
  - Complete Return Merchandise Authorization (RMA) state machine (`CreateRmaRequestAction`, `ProcessRmaInspectionAction`, `ReturnMerchandiseAuthorization`, `RmaIndex.vue`).
  - Statutory Mushak 6.7 Credit Notes (`IssueMushak67CreditNoteAction`, `Mushak67CreditNote`) logging statutory decreasing tax adjustments (হ্রাসকারী সমন্বয়) for Mushak 9.1 reporting and reversing COGS inventory into warehouse stock.
- **Vendor Purchase Returns (Mushak 6.8) & Statutory NBR Registers (6.1 & 6.2) (`App\Modules\TaxCompliance`, `App\Modules\Admin`)**:
  - Vendor purchase returns via statutory Mushak 6.8 Debit Notes (`IssueMushak68DebitNoteAction`, `Mushak68DebitNote`) with increasing tax adjustments (বৃদ্ধিকারী সমন্বয়).
  - NBR statutory registers: Mushak 6.1 Purchase Register (ক্রয় হিসাব পুস্তক) and Mushak 6.2/6.2.1 Sales Register (বিক্রয় হিসাব পুস্তক) (`GenerateMushak61RegisterAction`, `GenerateMushak62RegisterAction`, `MushakRegisters.vue`).
  - Unified Tax & NBR Compliance Administration Portal (`TaxComplianceAdminController`, `TaxComplianceDashboard.vue`, `AdminLayout.vue`).

---

## [0.8.0] - 2026-10-29

### Added
- **Dynamic SMTP Mail Settings Engine & Pre-Flight Handshake (`App\Modules\Communication`, `App\Modules\Admin`)**:
  - Database-driven SMTP settings ledger (`mail_settings`) storing encrypted credentials, transport host, port, TLS/SSL encryption, and sender metadata with runtime Dynamic Swift/Symfony Mailer configuration (`ConfigureDynamicMailerAction`, `SendTestEmailAction`, `MailSetting`).
  - Pre-flight automated handshake test verifying live outbound SMTP connection with latency measurement before activating on production.
- **Dynamic SMS Gateway Engine & OTP Verification (`App\Modules\Communication`, `App\Modules\Order`)**:
  - Multi-provider SMS gateway architecture supporting Bangladesh providers (Greenweb SMS, Reve Systems) and Twilio with encrypted credentials, balance checking, and masked previews (`SmsGatewaySetting`, `SmsGatewayManager`, `SmsGatewayContract`).
  - SMS OTP verification engine (`sms_otps`, `GenerateAndSendOtpAction`, `VerifyOtpAction`) creating 4-digit verification codes, 15-minute TTL, rate limiting, and automated order verification state transitions.
- **WhatsApp Business Cloud API Integration & Lifecycle Alerts (`App\Modules\Communication`, `App\Modules\Order`)**:
  - Direct integration with Meta WhatsApp Business Cloud API (`WhatsAppSetting`, `WhatsAppClient`, `SendOrderWhatsAppAlertAction`) delivering order confirmation, courier dispatch, and delivered alerts with direct NBR Mushak 6.3 digital invoice links.
  - Inbound webhook receiver (`WhatsAppWebhookController`) supporting Meta verification challenge handshake and live delivery status updates.
- **AI Automated Outbound Voice Calling Verification Agent (`App\Modules\Communication`, `App\Modules\Order`)**:
  - AI Voice agent calling Cash on Delivery (COD) customers within 60 seconds with natural bilingual Bangla (বাংলা) and English prompts (`VoiceCallingSetting`, `VoiceCallLog`, `TwilioVoiceAdapter`, `TriggerVoiceVerificationCallAction`, `TestVoiceCallAction`).
  - Dual confirmation support: DTMF Touch-Tone (Key 1 to confirm, Key 2 to cancel) and Conversational Speech-to-Intent AI.
  - Dynamic bilingual TwiML webhook generator (`VoiceCallWebhookController`) with automated order transition triggers (1 $\rightarrow$ confirmed, 2 $\rightarrow$ cancelled with inventory release) and complete audit logging.
- **Abandoned Cart Multi-Step Recovery Engine & Sequencer (`App\Modules\Marketing`, `App\Modules\Cart`)**:
  - Automated 3-step abandoned cart recovery sequencer (`cart_recovery_logs`, `ProcessCartRecoverySequencesAction`, `MarkCartAsRecoveredAction`) re-engaging shoppers via WhatsApp and SMS at 1 hour (Gentle Reminder), 6 hours (Urgency alert), and 24 hours (Incentive coupon `COMEBACK5`).
  - Scheduled Artisan console command (`cart:process-recovery`) for continuous automated cron execution.
- **Social Commerce Product Feeds (`App\Modules\Communication`, `App\Modules\Catalog`)**:
  - Live high-performance product catalog feeds (`SocialCommerceFeedService`, `SocialCommerceFeedController`) generating Google Merchant Center XML (`/feeds/google-merchant.xml`), Facebook / Instagram Catalog CSV (`/feeds/facebook-catalog.csv`), and TikTok Shop CSV (`/feeds/tiktok-shop.csv`) with multi-warehouse stock aggregation and formatted BDT pricing.
- **Customer Self-Service Order Tracking Portal & Storefront UX (`App\Modules\Customer`, `App\Modules\Order`)**:
  - Frictionless public self-service order tracking portal (`TrackOrderAction`, `OrderTrackingController`, `Storefront/OrderTracking.vue`, `/track-order`) accessible with Order Number and Mobile Phone without forced account registration.
  - Interactive Milestone Stepper timeline with bilingual labels (Order Placed $\rightarrow$ Confirmed $\rightarrow$ Picked & Packed $\rightarrow$ Handed to Courier $\rightarrow$ Out for Delivery $\rightarrow$ Delivered), masked customer phone privacy, live courier tracking URL links (Steadfast, Pathao, RedX), and statutory Mushak 6.3 invoice badges.
- **Back-Office Omnichannel & Communication Management UI (`App\Modules\Admin`, `App\Modules\Communication`, `App\Modules\Marketing`)**:
  - Full-featured back-office administrative portals (`CommunicationAdminController`) for SMTP Mail Settings (`MailSettings.vue`), SMS Gateways & Balance Management (`SmsSettings.vue`), AI Voice Calling Settings & Call Logs (`VoiceCallingSettings.vue`), and Abandoned Cart Recovery Funnel Analytics (`CartRecovery.vue`).

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

