# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

---

## [Unreleased]

### Added
- **7-Layer Anti-Fraud & Fake Order Defense Shield (`App\Modules\Order`, `App\Modules\Customer`)**:
  - Bangladesh mobile operator validation rule with operator detection (GP, Banglalink, Robi/Airtel, Teletalk), E.164 normalization, and dummy/sequential repeating digit rejection (`BangladeshPhoneNumberRule`).
  - Multi-lingual Shannon text entropy and gibberish scoring engine (0-100) supporting English and Bengali (বাংলা) UTF-8 (`TextEntropyService`).
  - Order velocity throttling enforcing max 1 active unfulfilled COD order per phone, and max 2 orders/day burst limits per IP and device fingerprint (`CheckOrderVelocityAction`).
  - Added indexed `ip_address`, `device_fingerprint`, and `user_agent` metadata tracking on Orders table.
  - Invisible bot honeypot traps and checkout submission timing guards with encrypted tokens (`HoneypotTimingGuardService`).
  - Merchant blacklist and whitelist directory (`customer_fraud_lists`).
  - Google reCAPTCHA / Cloudflare Turnstile dynamic bot protection settings.

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

