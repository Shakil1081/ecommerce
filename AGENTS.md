# Project Instructions: Enterprise Single-Vendor E-Commerce Platform (Hybrid Trading: Import & Local Sourcing)

Welcome to the **E-Commerce** project. This file (`AGENTS.md`) defines the domain model, modular architecture, software engineering best practices, enterprise security standards, regulatory tax compliance, dynamic payment module configuration, decoupled AI intelligence, SEO & Social Media standards, automated courier logistics, omnichannel communications, comprehensive anti-fraud & fake order defense, and operational rules for Antigravity and pair-programming agents working within this workspace.

---

## 1. Project Overview & Commercial Value Proposition

- **Repository**: [ecommerce](https://github.com/Shakil1081/ecommerce)
- **Domain**: High-scale, enterprise-ready **Turnkey Commercial Single-Vendor Trading Platform**
- **Business Model**: **Hybrid Trading (Global Importation + Local Domestic Procurement $\rightarrow$ Local Bangladesh Storefront Buy & Sell)**
  - **No Manufacturing / Production**: Exclude factory work orders, raw materials conversion, assembly routing, or production Bill of Materials (BOM).
  - **Dual Sourcing & Domestic Selling Lifecycle**:
    1. **Global Commercial Importation**: Purchase finished goods from international manufacturers/suppliers (China, UAE, Europe, USA, etc.) via Commercial Invoices (CI), Proforma Invoices (PI), and Letter of Credit (LC) / Telegraphic Transfer (TT).
    2. **Local Domestic Sourcing**: Purchase finished goods directly from domestic Bangladesh manufacturers, authorized distributors, and local wholesalers in BDT (৳) with supplier Mushak 6.3 invoices and BIN verification.
    3. **Customs Clearance & Bill of Entry (BOE)**: For imports, track Bill of Entry (বিল অফ এন্ট্রি), HS codes, Customs Duty (CD), Regulatory Duty (RD), Supplementary Duty (SD), Advance Tax (AT - 5%), and Advance Income Tax (AIT - 5%) at ports of entry (Chattogram, Dhaka Airport/ICD).
    4. **Unified Landed Cost Allocation**: 
       - *For Imports*: Converts foreign currency (USD, RMB, EUR) at bank settlement rates, adding international freight, marine insurance, C&F port charges, and customs duties.
       - *For Local Purchases*: Allocates local transport, handling, and non-rebatable levies onto purchase price.
       - Result: 100% accurate, consolidated Cost of Goods Sold (COGS in BDT ৳) across all inventory batches.
    5. **Inward Receiving (GRN) & Multi-Warehouse Control**: Inward goods verification into physical hubs/depots with FIFO / Weighted Average inventory valuation, barcode labeling, and automated reorder points.
    6. **Domestic Online Storefront (Local B2C & B2B Sales)**: Sell directly to Bangladesh consumers (B2C) and trade/bulk wholesale buyers (B2B tier pricing, MOQ) in Bangladeshi Taka (৳ BDT).
    7. **100% SEO & Social Media Engine**: Inertia SSR, Schema.org JSON-LD, Open Graph, Twitter Cards, dynamic XML sitemaps, and Core Web Vitals (CLS = 0.0).
    8. **Social Commerce & Feeds**: Automated Google Merchant Center XML feed, Facebook Catalog / Instagram Shop sync, and TikTok catalog exports.
    9. **Automated Courier Logistics & Dynamic Shipping Pricing**: 1-click booking, printable thermal barcode shipping labels (4x6 / A6), real-time webhook tracking, zone-based delivery pricing (Inside Dhaka, Suburbs, Outside Dhaka), and automated COD cash reconciliation for **Steadfast**, **Pathao**, **RedX**, **Paperfly**, and **Sundarban**.
    10. **Comprehensive Anti-Fraud & Fake Order Protection**: Multi-signal risk scoring, courier delivery success rate checking, advance partial delivery charges for high-risk COD, invisible bot honeypots, and SMS OTP verification.
    11. **Omnichannel Messaging**: Automated SMS & WhatsApp alerts (order updates, tracking links, invoices).
    12. **Conversion & Abandoned Cart Recovery**: Multi-step automated WhatsApp/SMS/Email abandoned cart recovery, bundle builder, and countdown flash sales.
    13. **Customer Loyalty & Referrals**: Reward points on purchases, wallet balance, and referral reward programs.
    14. **Comprehensive NBR Tax Compliance & Input Tax Credit**: 
        - Automated issuance of statutory NBR VAT invoices (Mushak 6.3) on local sales.
        - Claiming both **Import Advance Tax (AT)** and **Local Supplier Input VAT** as Input Tax Credit (রেয়াত) on monthly VAT Returns (Mushak 9.1).
        - Automated **Mushak 6.6 (VDS Certificates)** for VAT deducted at source on local procurement.
        - Inter-depot stock transfers via **Mushak 6.5**.
    15. **Dynamic Payments**: Admin-configurable Bangladesh (bKash, Nagad, SSLCommerz) and global gateways with automated API connection verification.
    16. **Backend AI Intelligence**: Decoupled, multi-provider AI engine (Gemini, OpenAI, Claude, Local LLM) for catalog enrichment, smart restocking, and customer operations.
    17. **Admin-Configurable SMTP & Security Controls**: Self-service SMTP configuration with "Send Test Email" validation, and Google reCAPTCHA / Cloudflare Turnstile bot protection toggles.
    18. **Returns, Refunds & Adjustments**: NBR-compliant sales return adjustments via statutory Credit Notes (Mushak 6.7) with automated decreasing tax adjustments.
    19. **Localization**: Full bilingual support (**Bangla বাংলা** & **English**) with standard Bangladesh Division $\rightarrow$ District $\rightarrow$ Thana address cascading.

---

## 2. Tech Stack & Engineering Standards

### Backend: Laravel 13.x (PHP 8.3+)
- **Coding Style**: PSR-12, enforced via **Laravel Pint** (`./vendor/bin/pint`).
- **Strict Typing**: Declare `declare(strict_types=1);` in all PHP files. Use explicit parameter types, return types, and typed properties.
- **Controller Philosophy**: Ultra-thin controllers. Controllers must only dispatch incoming requests to dedicated **Action classes** or **Domain Services** and return responses.
- **Validation**: Always use dedicated **Form Request** classes (`App\Modules\{Module}\Http\Requests\...`); never perform validation directly inside controllers.
- **Data Transformation**: Use **API Resources** or Inertia props with typed, immutable **Data Transfer Objects (DTOs)**.
- **Monetary & Tax Precision**: Never use floating-point types for currency or tax math. Store monetary values and tax breakdowns in integer minor units (poisha/cents) or `DECIMAL(14, 4)` to eliminate rounding discrepancies.
- **Enterprise Media & Image Management**: Standardize on **Spatie Laravel MediaLibrary (`spatie/laravel-medialibrary:^11.0`)** combined with **Intervention Image v3 (`intervention/image:^3.0`)**.
  - All product images, category banners, brand logos, and commercial import/tax documents are stored via typed Media Collections.
  - Automated queued generation of **WebP** and **AVIF** formats.
  - Responsive breakpoint conversions: `thumb_300` (300x300), `card_600` (600x600), `gallery_1200` (1200x1200), and `zoom_2000` (2000x2000).
  - Blurhash / LQIP (Low-Quality Image Placeholder) generation stored in database to eliminate layout shift (guaranteeing `CLS = 0.0`).
  - Automated watermark overlay option to protect proprietary import photography.
  - Storage driver support: Local disk, Amazon S3, MinIO, or **Cloudflare R2** (zero egress bandwidth costs).
- **Role-Based Access Control (RBAC) & Permissions**: Standardize on **Spatie Laravel Permission (`spatie/laravel-permission:^6.0`)**. Enforces granular roles and module permissions across all API actions and Inertia controllers, fully manageable via back-office admin UI. Super Admin role bypasses checks via `Gate::before`.

### Frontend: Vue.js 3 + TypeScript + Sass (SCSS) + Inertia SSR
- **Framework Integration**: **Inertia.js v2** with **Vue 3** (`<script setup lang="ts">`), **Inertia SSR (Server-Side Rendering)** for public pages, and Vite.
- **Styling Architecture**: **Sass / SCSS (`lang="scss"`) exclusively**.
  - Structured SCSS architecture under `resources/scss/`:
    - `abstracts/_variables.scss` (Design tokens, palette, spacing, typography)
    - `abstracts/_mixins.scss` (Breakpoints, flex/grid helpers, elevation)
    - `abstracts/_functions.scss` (Color manipulations, unit conversions)
    - `base/_reset.scss` & `base/_typography.scss` (Typography hierarchy and resets)
    - `components/_components.scss` (Reusable component primitives)
  - Scoped styling: Every component uses `<style lang="scss" scoped>`.
  - Vite auto-injection: Configure `additionalData` in `vite.config.ts` so `_variables.scss` and `_mixins.scss` are automatically injected into all Vue SFCs without manual `@use` imports.
- **State Management**: Pinia stores with explicit TypeScript interfaces for state, getters, and actions.
- **Type Safety**: Zero `any`. Define interface contracts for API payloads, models, and component props/emits.

---

## 3. Four Pillars of World-Class E-Commerce: Security, UX, Performance & Product Management

To stand out as a world-class, commercial-grade platform, the system strictly implements these four core pillars:

### Pillar 1: World-Class Security & Anti-Fraud Architecture (Fake Order & Fake Customer Defense)
Fake orders and bogus Cash on Delivery (COD) customers destroy merchant profitability through courier return penalties and tied-up stock. The platform implements a multi-tier defense:

1. **Multi-Signal Anti-Fraud Risk Scoring Engine**:
   - Evaluates every order dynamically upon submission across multiple risk signals:
     - **Phone Number Integrity & Courier Delivery History**: Validates standard Bangladesh mobile numbers (`013-019`) and queries courier delivery intelligence APIs (Steadfast Return Rate API / Pathao Success Rate API) to assess customer delivery completion rate across Bangladesh networks.
     - **IP Geolocation & Proxy/VPN Detection**: Flags orders placed via foreign proxies, Tor, or data center IPs while ordering for domestic delivery.
     - **Order Velocity & Burst Checking**: Detects rapid duplicate orders from the same IP, device fingerprint, or phone number within a short window.
     - **Disposable Email Blacklist**: Rejects throwaway email addresses (Mailinator, TempMail, 10MinuteMail).
     - **Gibberish Name & Address Filter**: Flags nonsensical input (e.g., `"asdfgh"`, `"test test"`, `"no address"`).
2. **Tiered Risk-Based Automated Actions**:
   - **Low Risk (Score < 30)**: Instant approval, directly dispatched to warehouse picking.
   - **Medium Risk (Score 30–70)**: Mandatory **SMS / WhatsApp OTP Verification** (4-digit one-time code) before order moves to confirmed state.
   - **High Risk (Score > 70 or Courier Return Rate > 40%)**:
     - Automatically **disables Cash on Delivery (COD)**; requires advance payment of either the full order or a partial advance delivery charge (৳100–৳150 via bKash/Nagad) before confirmation.
     - Flagged in the admin dashboard under **"High-Risk Review Queue"** requiring phone call confirmation before booking courier consignment.
   - **Blacklisted**: Immediate rejection with a polite error message.
3. **Bot & Fake Registration Traps**:
   - **Invisible Honeypot Fields**: Hidden input fields invisible to human users but filled out by automated spambots, silently rejecting the submission.
   - **Submission Timing Guard**: Rejects orders submitted in `< 3 seconds` from page load (indicative of automated scripts).
4. **Merchant Blacklist & Whitelist Directory (`customer_fraud_lists`)**:
   - Searchable admin directory allowing merchants to block repeat offending phone numbers, IP addresses, or device fingerprints.
   - Whitelist for verified repeat VIP customers who bypass all verification friction.
5. **Session & Access Hardening**:
   - Multi-session management with active device list and remote session termination.
   - Login alerts triggered when account access occurs from an unrecognized device or IP.
6. **Checkout & Payment Security**:
   - Anti-carding / BIN attack rate limiting on payment submission endpoints.
   - Strict Content Security Policy (CSP) with nonces to prevent payment form skimming and Magecart-style attacks.
   - Cryptographic signature validation for all incoming payment and courier webhooks.
7. **Tamper-Proof Audit Logging**:
   - Immutable audit logs recording all price updates, stock adjustments, purchase order authorizations, Mushak adjustments, and refunds.

### Pillar 2: World-Class User-Friendliness (Ultra-Frictionless UX/UI)
1. **Sub-50ms Instant Search with Typo Tolerance**:
   - Live autocomplete dropdown displaying product thumbnail, title, price, brand, and stock badge.
   - Handles phonetic typos (e.g., searching "samsng" suggests "Samsung") without requiring full page reload.
2. **Ultra-Fast 1-Page / Drawer Checkout**:
   - Zero forced account creation: guest checkout with just Name, Mobile Phone, and Delivery Address.
   - Cascading Bangladesh Division $\rightarrow$ District $\rightarrow$ Thana dropdowns with instant real-time delivery fee calculation.
   - Sticky mobile "Buy Now" button opening a bottom slide-up sheet for frictionless 10-second checkout.
3. **Interactive Slide-Out Drawer Cart**:
   - AJAX / Inertia slide-out cart drawer that keeps shoppers on their current page.
   - Dynamic **Free Shipping Progress Bar** (e.g., "Add ৳450 more for FREE delivery!").
   - 1-click bundle upsells and cross-sells directly inside the cart.
4. **Rich Product Media & Interactive PDP**:
   - High-definition image gallery with pinch-to-zoom on mobile and magnifier lens on desktop.
   - Color-swatch variant switching: selecting "Midnight Black" automatically updates the gallery to display black images.
   - Embedded video demonstration reels and customer photo review galleries.
5. **Self-Service Order Tracking Portal**:
   - Customers can view live milestone progress (`Order Confirmed` $\rightarrow$ `Packed` $\rightarrow$ `Handed to Steadfast/Pathao` $\rightarrow$ `Out for Delivery` $\rightarrow$ `Delivered`) using only their Order ID and Phone Number.

### Pillar 3: World-Class Performance (Sub-Second Core Web Vitals)
1. **Multi-Tier Caching & Rapid Query Engine**:
   - Multi-tier Redis caching: warm caching for Homepage, Category hierarchy, and Bestsellers.
   - HTTP 304 conditional validation via ETags to serve unchanged pages instantly.
   - Inertia Partial Reloads (`only: ['products', 'pagination']`) so facet filter updates fetch only the product grid without re-rendering the layout.
2. **Strict Core Web Vitals Standards**:
   - **Largest Contentful Paint (LCP)**: `< 1.2s` via preloaded hero image with `fetchpriority="high"`.
   - **Interaction to Next Paint (INP)**: `< 50ms` via lightweight, tree-shaken Vue components.
   - **Cumulative Layout Shift (CLS)**: `0.0` guaranteed by explicit aspect-ratio containers on all product media and skeleton loaders during state transitions.
3. **Flash-Sale Concurrency Resilience**:
   - Redis distributed locking and atomic decrement (`DECRBY`) during traffic spikes, ensuring that high-volume flash sales execute without database lock contention.
   - Background queued jobs offload PDF invoice generation, transactional SMS, and social feed exports.

### Pillar 4: Advanced Product Management & Smart Merchandising
1. **Multi-Dimensional Matrix Variant Manager**:
   - Multi-attribute combinations (Size $\times$ Color $\times$ Material) with a bulk matrix editor for SKUs, barcodes/EANs, supplier purchase costs, selling prices, and stock counts.
   - Individual weight, dimensions, and low-stock reorder thresholds per variant.
2. **Smart Dynamic Collections**:
   - Rule-based automated collections (e.g., "Items with Stock > 0 AND Discount > 15% auto-populate Flash Deals").
   - Drag-and-drop manual merchandising to curate featured product order in categories.
3. **Bulk Operations & Barcode Scanner Integration**:
   - 1-click bulk price adjustments (percentage or fixed amount) across categories or brands.
   - Rapid stock intake via USB / Bluetooth barcode scanner during warehouse receiving (GRN).
   - CSV / Excel import and export for catalog synchronization.
4. **Urgency & Marketing Badges**:
   - Dynamic badges: `Official Warranty`, `100% Genuine Import`, `Best Seller`, `Limited Stock ("Only 2 Left!")`.

---

## 4. Modular Architecture (Domain-Driven Modular Monolith)

The application is structured into decoupled, cohesive domain modules under `app/Modules/`. Each module encapsulates its own domain models, business actions, HTTP handlers, events, and database assets:

```text
app/
├── Modules/
│   ├── Catalog/              # Products, Categories, Brands, Matrix Variants, HS Codes, Origin, SEO Slugs
│   ├── Procurement/          # International & Local Suppliers, PO, Commercial Invoices, LC/TT, BOE, Landed Cost/COGS
│   ├── Inventory/            # Multi-Warehouse/Hubs, Stock Tracking, Barcode Scans, FIFO Valuation, Adjustments
│   ├── Pricing/              # B2C Retail Pricing (BDT), B2B Tier Pricing, Discounts, Volume Breaks
│   ├── Cart/                 # Persistent Cart, Slide-Out Drawer, Free Shipping Bar, Abandoned Cart Tracking
│   ├── Order/                # Order State Machine, Invoicing, Dispatch & Tracking, Fraud Risk Scoring, Honeypots
│   ├── Payment/              # Gateway Adapters (bKash, Nagad, SSLCommerz, Stripe), Advance Delivery Charge Engine
│   ├── Logistics/            # Courier Adapters (Steadfast, Pathao, RedX), Shipping Labels, Zones & Delivery Pricing, COD Reconciliation
│   ├── Communication/        # SMS & WhatsApp Gateways, OTP Verification, Dynamic SMTP Settings, Feeds (FB/Google)
│   ├── Marketing/            # Abandoned Cart Sequences, Rewards/Loyalty Points, Referrals, Flash Sales
│   ├── AI/                   # Pluggable Multi-Provider AI Engine (Gemini, OpenAI, Claude)
│   ├── Seo/                  # Dynamic Sitemaps, Schema.org Generators, Meta DTOs, Robots.txt
│   ├── TaxCompliance/        # Bangladesh NBR VAT (Mushak 6.3, 6.7, 6.8, 6.5, 6.6, 9.1), Import AT & Local Input Credit, TDS, AIT
│   ├── Customer/             # Auth, Profiles, Addresses (BD Cascading), Fraud Lists (Blacklist/Whitelist), Trade Accounts (BIN/TIN)
│   └── Admin/                # Trading Profit & Loss Analytics, Security Settings (reCAPTCHA), High-Risk Order Queue, NBR Audit Logs
```

### Module Component Blueprint
Each module adheres to this internal structure:
- `Actions/`: Single-responsibility action classes implementing `__invoke()`.
- `Contracts/`: Interfaces for inter-module service boundaries.
- `DTOs/`: Readonly typed data transfer objects for safe boundary crossing.
- `Events/` & `Listeners/`: Async domain events for decoupled cross-module communication.
- `Http/`: Module-specific Controllers, Form Requests, and API Resources.
- `Models/`: Eloquent models with typed relationships, accessors, and local query scopes.
- `Database/`: Migrations, Seeders, and Model Factories scoped to the module.
- `Providers/`: Module-specific Service Provider registering routes, views, and event listeners.

---

## 5. Dual Sourcing Procurement: Global Importation & Local Domestic Purchase (`app/Modules/Procurement/`)

Supports both global imports and domestic wholesale buying with unified Landed Cost and inventory receiving:

### A. Global Commercial Importation
1. **Overseas Supplier Profiles**: Foreign manufacturer bank details, country of origin, and currency preferences (USD, RMB, EUR).
2. **LC / TT Tracking**: Letter of Credit number, issuing bank, margin deposit, and settlement exchange rates.
3. **Bill of Entry (BOE / বিল অফ এন্ট্রি)**:
   - Customs assessment value, port of clearance (Chattogram, Dhaka Airport/ICD).
   - HS Code tracking per item.
   - Statutory tariffs: Customs Duty (CD), Regulatory Duty (RD), Supplementary Duty (SD), Advance Tax (AT - 5%), Advance Income Tax (AIT - 5%).
4. **Import Landed Cost Allocation**:
   - Converts foreign currency into BDT (৳) at bank settlement rate.
   - Total landed cost includes FOB + International Freight + Marine Insurance + C&F port charges + Customs Tariffs + Handling.

### B. Local Domestic Procurement (Bangladesh Market Buying)
1. **Local Supplier Directory**: Domestic manufacturer/distributor profiles, 9/13-digit BIN, 12-digit e-TIN, trade license, and payment terms.
2. **Local Purchase Orders (PO)**:
   - Generated in Bangladeshi Taka (৳ BDT).
   - Records supplier's **Mushak 6.3 Tax Invoice** number and date upon delivery.
   - Logs purchase into statutory **Mushak 6.1 (Purchase Register)**.
3. **Local Landed Cost Allocation**:
   - Purchase Price (BDT) + Domestic Freight / Courier / Carrying + Loading/Unloading = Domestic COGS.
4. **VDS (VAT Deducted at Source)**:
   - Automatically computes VDS when procuring from domestic vendors as an authorized withholding entity.
   - Issues legal **Mushak 6.6 Certificates (উৎসে মূসক কর্তন সনদপত্র)** to local vendors.
5. **TDS (Tax Deducted at Source)**:
   - Automated deduction of income tax on supplier payment vouchers under the Income Tax Act 2023.

### C. Goods Received Notes (GRN) & Multi-Warehouse Intake
- Unified physical receiving into designated warehouses regardless of origin (import container or local truck delivery).
- Discrepancy tracking for missing, damaged, or returned goods.

---

## 6. Automated Courier Logistics & Delivery Pricing Gateway (`app/Modules/Logistics/`)

Seamless integration with all top Bangladesh courier services and dynamic delivery pricing management:

### A. Dynamic Courier Settings & Drivers (`courier_gateway_settings` Schema)
The module persists courier gateway configurations with encrypted credentials in the database:
1. **Steadfast Courier Driver**:
   - Credentials: `api_key`, `secret_key`, `base_url`.
   - Capabilities: Automatic consignment booking, tracking code generation, live status webhooks, delivery fee inquiry, and COD disbursement reconciliation.
2. **Pathao Courier Driver**:
   - Credentials: `client_id`, `client_secret`, `username`, `password`, `store_id`.
   - Capabilities: OAuth / API token authorization, store address selection, bulk dispatch, parcel tracking, and price calculation.
3. **RedX Driver**:
   - Credentials: `token`, `sandbox_mode`.
   - Capabilities: Consignment creation, pickup requests, real-time tracking, and digital label generation.
4. **Paperfly & Sundarban Courier Drivers**:
   - Credentials: API keys, account credentials, and dispatch hub IDs.
   - Capabilities: Doorstep nationwide delivery and regional hub pickups.
5. **International Logistics (DHL / FedEx / Aramex)**: Export documents and international air waybill (AWB) generation.
6. **"Test Courier API Connection" Action (`TestCourierConnectionAction`)**: Pre-flight automated handshake verifies API credentials against the courier endpoint before allowing activation.

### B. Dynamic Delivery Pricing & Shipping Zone Engine
Admin-configurable delivery pricing matrix configured in the back-office without code changes:
1. **Zone-Based Delivery Rates**:
   - **Inside Dhaka (ঢাকা সিটি)**: Flat rate (e.g. ৳60 or ৳70), estimated delivery (24-48 hours).
   - **Dhaka Suburbs (ঢাকা উপশহর - Savar, Gazipur, Keraniganj, Narayanganj)**: Flat rate (e.g. ৳100), estimated delivery (48-72 hours).
   - **Outside Dhaka (ঢাকার বাইরে সারা বাংলাদেশ)**: Flat rate (e.g. ৳130 to ৳150), estimated delivery (3-5 days).
2. **Weight & Dimension Tier Calculations**:
   - Base weight threshold (e.g., up to 1 kg included in base price).
   - Extra charge per additional kg (e.g. +৳20 per extra kg).
3. **Free Shipping Threshold**: Configurable minimum cart order value (e.g., Free delivery on orders over ৳2,500).
4. **Live Courier Rate API Calculation**: Optional real-time query to courier APIs (Steadfast/Pathao price calculation endpoint) to fetch exact live shipping charges during checkout.

### C. Courier Management & Fulfillment Features
- **One-Click Consignment Booking**: Dispatch single or bulk orders to selected couriers directly from the admin order table.
- **Printable Thermal Shipping Labels**: Generates standardized 4x6 inch (A6) thermal barcode shipping labels with customer address, order barcode, merchant details, and COD cash collection amount.
- **Automated Live Status Webhooks**: Updates order statuses automatically upon courier lifecycle events (`picked_up`, `in_transit`, `out_for_delivery`, `delivered`, `returned`, `cancelled`).
- **Automated COD Cash Reconciliation**:
  - Matches courier payment disbursement reports against pending Cash on Delivery orders.
  - Automatically identifies discrepancies between courier cash payouts and recorded order totals.

---

## 7. Omnichannel Messaging, Dynamic SMTP & Social Commerce (`app/Modules/Communication/`)

### A. Admin-Configurable Dynamic SMTP Settings (`mail_settings` Schema)
Administrators configure email delivery directly from the back-office UI without touching `.env`:
- Fields: Mail Driver (`smtp`, `sendmail`, `mailgun`, `postmark`, `resend`, `ses`), Host, Port (465 SSL / 587 TLS), Encryption, Username, Password (encrypted at rest), From Address, From Name.
- **"Send Test Email" Action (`SendTestEmailAction`)**: Pre-flight test button in admin allows testing the live SMTP handshake and sending a test email to verify credentials before saving.

### B. SMS & WhatsApp Notifications
- **Bangladesh SMS Gateway Integrations**: Greenweb, Reve SMS, Banglalink, Grameenphone, Twilio.
- **Automated Triggers**: Order confirmation, courier tracking updates, delivery completion with digital Mushak 6.3 invoice link, and return approvals.
- **WhatsApp Commerce Integration**: Direct chat, support, and order updates via WhatsApp Business API.

### C. AI Automated Outbound Voice Calling Verification Agent (`voice_calling_settings` Schema)
To completely automate the confirmation of Cash on Delivery (COD) orders without requiring manual call-center staff, the system provides an AI-powered interactive voice calling agent:
- **Interactive Voice Operations**:
  - Automatically dials the customer's phone number within 60 seconds of order placement.
  - Native bilingual conversational capability (**Bangla বাংলা** & **English**).
  - Dynamic TTS message interpolation: *"হ্যালো {{customer_name}}, {{store_name}} থেকে আপনার {{order_id}} নম্বর অর্ডারের মোট মূল্য ৳{{total_amount}}। অর্ডারটি কনফার্ম করতে ১ চাপুন, বাতিল করতে ২ চাপুন।"*
- **Dual Verification Modes**:
  1. **DTMF Keypress (Touch-Tone)**: Customer presses `1` on phone keypad to confirm, `2` to cancel.
  2. **Conversational Speech Recognition (AI Voice)**: Uses speech-to-intent understanding to capture verbal replies (*"হ্যাঁ, কনফার্ম করেন"* or *"না, দরকার নেই"*).
- **Automated Lifecycle Webhooks**:
  - Keypress `1` (Confirmed): Order state immediately transitions to `confirmed` and routes to picking.
  - Keypress `2` (Cancelled): Order state transitions to `cancelled_by_customer`; reserved stock is returned to inventory.
  - No Answer / Busy: Retries up to 3 times (15m, 1h, 3h); if still unanswered, automatically queues in Admin **"Pending Phone Verification Queue"**.
- **Admin Configuration & Controls (`voice_calling_settings`)**:
  - **One-Click Active/Inactive Toggle**: Store owner can toggle the automated calling service on/off from the back-office with zero downtime.
  - **Provider Drivers**: Supports **Twilio Voice**, **Retell AI**, **Vapi.ai**, and local Bangladesh telecom OBD (Outbound Dialing) gateways.
  - **Encrypted Credentials**: Stored securely in database (`'credentials' => 'encrypted:array'`).
  - **"Make Test Call" Action (`TestVoiceCallAction`)**: Pre-flight test button in the admin interface allows the merchant to input their own phone number and receive an immediate live AI test call before activating the service.
  - **Call Logs & Recording Player**: Back-office audio player and transcript viewer for each verification call.

### D. Social Commerce Feeds
- **Google Merchant Center XML Feed**: Real-time product feed formatted to Google Shopping specs.
- **Facebook Catalog & Instagram Shop Sync**: Auto-updating CSV/XML catalog feed for dynamic retargeting ads.
- **TikTok Shop Catalog Sync**: Product catalog feed for social video commerce.

---

## 8. Anti-Fraud, Fake Order & Fake Customer Defense Architecture (`app/Modules/Order/` & `app/Modules/Customer/`)

Fake orders, frivolous shoppers, and abusive Cash on Delivery (COD) cancellations represent the single most destructive margin leak for Bangladesh e-commerce merchants. Courier return penalties (forward delivery ৳70–৳150 + 50% courier return fee = ৳105–৳225 pure cash loss per returned parcel) along with tied-up inventory during 7–10 day transit cycles demand a hardened, automated anti-fraud shield.

The platform implements a multi-tier, multi-signal defense engine operating in real time across the entire order lifecycle:

### A. The 7-Layer Anti-Fraud Defense Shield

#### Layer 1: Pre-Submission Invisible Bot Traps & Client Signals
1. **Invisible Honeypot Fields**:
   - Hidden dummy inputs (`user_company_fax`, `billing_address_secondary_hidden`) rendered off-screen via CSS (`opacity: 0; position: absolute; left: -9999px; pointer-events: none; tabIndex: -1;`).
   - If populated upon form submission, the request is flagged as an automated spambot and silently rejected without reserving stock.
2. **Client Submission Timing Guard**:
   - Cryptographically signed timestamp token (`_submission_token`) generated when the checkout page mounts.
   - If an order form is submitted in under **3.5 seconds**, the submission is flagged as automated bot execution.
3. **Browser & Device Fingerprinting**:
   - Canvas, AudioContext, and WebGL browser signature computed client-side and hashed into a persistent device fingerprint (`fingerprint_hash`).
   - Tracks serial abusers who change phone numbers or toggle airplane mode to acquire fresh dynamic mobile IPs.

#### Layer 2: Bangladesh Phone Number Validation & Carrier Integrity
1. **Operator Prefix Regex & Digit Validation**:
   - Strictly enforces authentic Bangladesh mobile prefixes: `/^(?:\+?8801|01)[3-9]\d{8}$/`.
   - Validates authentic carriers: Grameenphone (`017`, `013`), Robi (`018`), Banglalink (`019`, `014`), Airtel (`016`), Teletalk (`015`).
2. **Fake / Repetitive Digit Detection**:
   - Automated heuristic rejection for repetitive or sequential dummy numbers (e.g., `01711111111`, `01800000000`, `01912345678`, `01799999999`).

#### Layer 3: Cross-Courier Historical Delivery Success Rate Check
1. **Automated Courier Intelligence Handshake**:
   - Pre-flight asynchronous query executing against courier intelligence APIs (**Steadfast Return Rate API**, **Pathao Delivery Success Rate API**, or centralized Bangladesh fraud databases such as `FraudCheckBD`).
   - Queries historical delivery metrics linked to the buyer's phone number across Bangladesh delivery networks:
     - `total_orders_placed`
     - `successful_deliveries`
     - `returned_parcels`
     - `cancellation_rate_percent`
2. **Delivery Metric Risk Scoring**:
   - **Delivery Success Rate $\ge 85\%$**: Trusted customer (-10 risk points).
   - **Delivery Success Rate $60\% - 84\%$**: Standard customer (0 risk points).
   - **Delivery Success Rate $40\% - 59\%$**: Risky customer (+35 risk points).
   - **Delivery Success Rate $< 40\%$ (or $\ge 3$ courier returns)**: Extreme risk (+60 risk points).

#### Layer 4: Natural Language Address Sanity & Administrative Boundary Enforcer
1. **Gibberish & Keyboard-Mash Filter**:
   - Shannon entropy and consonant/vowel density analysis detecting random keystrokes (e.g., `"asdfjkl"`, `"qwertyuiop"`, `"test order"`, `"fake address"`).
2. **Cascading Bangladesh Administrative Validation**:
   - Enforces structured Bangladesh administrative hierarchy: **Division $\rightarrow$ District $\rightarrow$ Thana / Upazila** selected from official postal datasets.
   - Prevents vague or undeliverable destination entries ("Dhaka", "Near the mosque"). Free-form text is restricted to Street Address and House/Flat number.

#### Layer 5: Velocity & Concurrency Throttling (Anti-Stock Exhaustion)
1. **Per-Phone COD Concurrency Limiter**:
   - A customer phone number is restricted to **maximum 1 active unfulfilled COD order**.
   - If a customer places an order, subsequent COD orders with that phone number are blocked until the first order is either marked as `delivered` or paid online.
2. **IP & Device Velocity Limiter**:
   - Maximum 2 orders per IP address or device fingerprint within a 24-hour window.
3. **Rapid Duplicate Order Interceptor**:
   - Prevents rapid-click accidental or intentional duplicate orders: Identical cart items submitted from the same IP or phone number within **30 minutes** are intercepted and merged or rejected.

#### Layer 6: Dynamic Risk Scoring Action (`CalculateOrderFraudRiskScoreAction`)
The engine computes a composite Fraud Risk Score (0 to 100) on every checkout submission:

| Risk Signal | Condition | Point Impact |
| :--- | :--- | :--- |
| **Courier Delivery History** | Courier Return Rate $> 40\%$ across Steadfast/Pathao | **+45 pts** |
| **Foreign IP on Domestic COD** | Geolocation outside Bangladesh for local delivery | **+35 pts** |
| **Proxy / VPN / Tor** | Request originates from known datacenter or proxy ASN | **+30 pts** |
| **High Order Velocity** | $> 1$ order submitted in the last 60 minutes | **+25 pts** |
| **Disposable / Temp Email** | Email matches disposable domain list (Mailinator, TempMail) | **+20 pts** |
| **Gibberish Input / Low Entropy** | Nonsensical customer name or address detected | **+20 pts** |
| **New Phone / Zero History** | Authentic number but 0 courier history | **+10 pts** |
| **Repeat Loyal Customer** | $\ge 2$ previous successfully delivered orders on storefront | **-20 pts** |
| **Verified Merchant Whitelist** | Customer phone or email present on Merchant Whitelist | **Score = 0** |

#### Layer 7: Automated Tiered Enforcement Actions

```text
[Order Submitted] ──> [CalculateOrderFraudRiskScoreAction]
                               │
       ┌───────────────────────┼───────────────────────┬───────────────────────┐
       ▼                       ▼                       ▼                       ▼
 [Low Risk: 0-29]     [Medium Risk: 30-69]    [High Risk: 70-100]       [Blacklisted]
 ├─ Auto-Approved     ├─ Status: Pending OTP  ├─ COD Disabled           └─ Instant Rejection
 └─ Direct to Picking ├─ SMS/WhatsApp 4-Digit ├─ Mandatory Advance       (Polite error)
                         Code Required           Delivery Charge (bKash)
                      └─ 15-min TTL Auto-Drop └─ Refusal = No Dispatch
```

1. **Tier 1: Low Risk (Score < 30) — Instant Auto-Approval**:
   - Order status moves directly to `confirmed`.
   - Dispatched immediately to warehouse picking and packing.
2. **Tier 2: Medium Risk (Score 30–69) — Mandatory OTP Verification**:
   - Order status placed in `pending_verification`.
   - Automated 4-digit One-Time Password (OTP) dispatched instantly via SMS (Greenweb/Reve) or WhatsApp Business API.
   - Customer enters OTP on checkout confirmation screen or clicks WhatsApp 1-tap verification button.
   - **15-Minute Auto-Release TTL**: If OTP is not validated within 15 minutes, the order is automatically cancelled and reserved stock is immediately returned to the inventory pool.
3. **Tier 3: High Risk (Score $\ge 70$ or Courier Return Rate $> 40\%$) — Mandatory Advance Delivery Charge**:
   - **Cash on Delivery (COD) is strictly disabled**.
   - Customer must pay either the entire order amount or an **Advance Partial Delivery Charge** (৳70 Inside Dhaka, ৳130–৳150 Outside Dhaka) via bKash, Nagad, or card gateway.
   - Once delivery charge is prepaid, order is marked `confirmed` with remaining balance collectable on delivery (`partial_cod`).
   - If customer refuses advance payment within 2 hours, order is dropped without warehouse fulfillment or courier dispatch.
4. **Tier 4: Blacklisted (Instant Block)**:
   - Direct match in `customer_fraud_lists` with `list_type = 'blacklist'`.
   - Order submission is cleanly rejected: *"We are unable to process your order at this time. Please contact customer support."*

---

### B. Merchant Fraud Directory & Controls (`customer_fraud_lists` Schema)

The back-office provides store administrators with complete governance over fraud controls:

1. **Database Schema (`customer_fraud_lists`)**:
   - `id`: ULID primary identifier.
   - `list_type`: `blacklist`, `whitelist`, `watchlist`.
   - `identifier_type`: `phone`, `ip_address`, `email`, `device_fingerprint`.
   - `identifier_value`: E.g., `01712345678`, `103.205.132.45`, `user@example.com`, `fp_9a8b7c6d`.
   - `risk_score_override`: Optional manual score override (e.g., force 100 or 0).
   - `reason`: `serial_refuser`, `fake_address`, `abusive_customer`, `stolen_card_attempt`, `competitor_bot`.
   - `notes`: Administrator internal documentation.
   - `created_by_user_id`: Reference to admin user who authorized the entry.
   - `created_at` & `updated_at`: Timestamps.

2. **Admin Back-Office Fraud Operations**:
   - **1-Click "Report & Blacklist" Action**: From the Admin Order Details view, if a courier reports a refusal or fake address, the admin clicks one button to immediately blacklist the phone number, IP, and device fingerprint.
   - **Customer Whitelist**: Trusted, VIP, and repeat corporate buyers are whitelisted to bypass all verification friction, OTP requests, and delivery charge prerequisites.
   - **High-Risk Review Queue**: Orders flagged with high risk scores appear in a dedicated back-office queue with visual signal breakdown and one-click WhatsApp order confirmation link generation.
   - **Stock Hold Safeguard**: Unverified or unpaid orders hold inventory with an atomic Redis expiration timer; expired reservations automatically unlock inventory without database deadlocks.

---

## 9. Marketing, Conversion & Customer Retention (`app/Modules/Marketing/`)

### A. Abandoned Cart Recovery Engine
- Automated multi-step recovery sequence (1h gentle reminder, 24h with 5% discount code, 72h final urgency alert) via WhatsApp/SMS/Email.
- Conversion tracking measuring recovered revenue in the admin dashboard.

### B. Customer Loyalty, Reward Points & Referrals
- Loyalty points earned per ৳100 spent, redeemable as discounts on future orders.
- Referral program ("Give ৳100, Get ৳100") and tiered VIP membership clubs.

---

## 10. 100% SEO & Social Media Optimization Standards (All Public Pages)

Every public storefront route (Home, Category/PLP, Product/PDP, Brand, Blog, CMS) must strictly enforce the following SEO & social media requirements:

### A. Server-Side Rendering (Inertia SSR)
- Enable **Inertia SSR** (`resources/js/ssr.ts`) so search engine web crawlers (Googlebot, Bingbot, Yandex) and social bot crawlers (Facebook, WhatsApp, LinkedIn, Twitter/X, Telegram) receive fully rendered, hydration-ready HTML on initial page request without waiting for client-side JavaScript.

### B. Dynamic Head Management & Canonical URLs
- Every public Vue view utilizes Inertia's `<Head>` component to bind:
  - **Dynamic Title**: Meaningful and structured title tags (e.g., `{{ product.name }} | {{ product.brand.name }} - StoreName`).
  - **Meta Description**: 150–160 characters describing the page value, price, and key benefits.
  - **Canonical URL**: `<link rel="canonical" :href="canonicalUrl">` on all pages to prevent duplicate content penalties from URL query parameters (e.g., pagination, sorting, or marketing UTM tags).
  - **Robots Directive**: `<meta name="robots" content="index, follow, max-image-preview:large">` on public pages; `<meta name="robots" content="noindex, nofollow">` on checkout, account, cart, and admin routes.
  - **Single H1 Tag**: Exactly one semantic `<h1>` element per page; logical `<h2>` to `<h4>` hierarchy without skipped levels.
  - **Clean Slugs**: Human-readable URL slugs with 301 redirects on slug updates (`/products/apple-macbook-pro-m3`).

### C. Open Graph (OG) & Social Sharing Cards
- **Open Graph Protocol**:
  - `og:site_name`, `og:type` (`website` or `product`), `og:title`, `og:description`, `og:url`.
  - `og:image`: High-resolution preview image (minimum 1200x630 pixels) with `og:image:width`, `og:image:height`, and `og:image:alt`.
  - Product-specific tags: `product:price:amount`, `product:price:currency` (`BDT`), `product:availability` (`instock` / `outofstock`).
- **Twitter / X Cards**: `<meta name="twitter:card" content="summary_large_image">` with title, description, and preview image.
- **Social Sharing Actions**: One-click share buttons on product pages (WhatsApp Direct, Facebook, Messenger, LinkedIn, and Native Web Share API).

### D. Structured Data / Rich Snippets (Schema.org JSON-LD)
Injected into the `<head>` of public pages as valid JSON-LD:
1. **Product Schema (`schema.org/Product`)**: Includes `name`, `image`, `description`, `sku`, `mpn`, `brand`, `offers` (price in `BDT`, availability: `https://schema.org/InStock`, itemCondition: `https://schema.org/NewCondition`), and `aggregateRating`.
2. **BreadcrumbList Schema (`schema.org/BreadcrumbList`)**: Full hierarchy enabling rich breadcrumbs in Google SERPs.
3. **Organization & LocalBusiness Schema (`schema.org/Organization`)**: Company name, logo URL, official contact phone, email, BIN/TIN, address, and social profile links (`sameAs`).
4. **FAQPage Schema (`schema.org/FAQPage`)**: Expandable FAQ accordions in search results.

### E. Automated XML Sitemap & Robots.txt
- **Dynamic XML Sitemap (`/sitemap.xml`)**: Automated generation including all active products, categories, brands, and public CMS pages with Google Image Sitemap extensions (`<image:image>`).
- **Dynamic `robots.txt` (`/robots.txt`)**: Allows search engine spiders to crawl `/`, `/categories/`, `/products/`, `/brands/` while disallowing private routes: `/admin/`, `/checkout/`, `/cart/`, `/customer/`, `/api/`.

### F. Core Web Vitals & Media Optimization
- **Modern Image Formats**: Convert uploaded product imagery into **WebP** and **AVIF** formats with responsive breakages (`srcset`).
- **Layout Shift Prevention**: Always define explicit `width` and `height` attributes on `<img>` elements for a Cumulative Layout Shift (CLS) of `0.0`.
- **Lazy Loading**: Apply `loading="lazy"` and `decoding="async"` to all below-the-fold catalog images; apply `fetchpriority="high"` to primary PDP hero images.

---

## 11. Payment Module: Dynamic Bangladesh & Global Gateway Management

The `Payment` module provides a dynamic, user-friendly back-office interface allowing administrators to configure, test, and activate payment methods without altering code or `.env` files:

### A. Dynamic Gateway Settings (`payment_gateway_settings` Schema)
The module persists gateway settings in the database with strict field encryption:
- `code`: Unique identifier (`bkash`, `nagad`, `sslcommerz`, `stripe`, `cod`, `bank_transfer`).
- `name`: Display name on checkout (e.g., "bKash Direct Checkout").
- `is_active`: Boolean toggle to instantly enable/disable the gateway on the customer storefront.
- `is_sandbox`: Toggle between Sandbox/Test environment and Live/Production environment.
- `credentials`: JSON column encrypted at rest using Laravel's `'credentials' => 'encrypted:array'` cast.
- `surcharge_percentage`: Optional merchant gateway processing fee passed to the buyer.
- `min_order_amount` & `max_order_amount`: Threshold limits for gateway availability.
- `sort_order`: Display priority order at checkout.
- `instructions`: Offline/COD guidelines displayed to the customer upon selection.

### B. Supported Bangladesh Gateways & Credential Models
1. **bKash Tokenized Checkout**: `app_key`, `app_secret`, `username`, `password` (Tokenized checkout, payment execution, query, refund API).
2. **Nagad**: `merchant_id`, `merchant_private_key`, `pg_public_key` (Cryptographic signatures, payment URL, IPN callback, refund API).
3. **SSLCommerz**: `store_id`, `store_passwd` (Hosted checkout session, IPN webhook validation, refund API).
4. **Stripe**: `publishable_key`, `secret_key`, `webhook_secret` (Card payments, 3D Secure).
5. **Cash on Delivery (COD)**: Configurable advance partial delivery charge or SMS OTP verification on order submission.
6. **Bank Transfer / Trade Credit**: For B2B wholesale buyers; manual slip upload and back-office verification.

### C. Admin Self-Service UI & Safety Features
1. **One-Click Active/Inactive Switch**: Immediately toggles checkout availability without requiring server restart or deployment.
2. **"Test API Connection" Action (`TestGatewayConnectionAction`)**: Pre-flight automated handshake verifies credentials against gateway endpoint before allowing activation.
3. **Webhook / IPN URL Generator**: Dynamically displays exact Webhook/IPN URLs with copy buttons and setup guides.
4. **Credential Masking**: Secrets are masked in responses (e.g. `••••••••b7a1`) to protect against shoulder-surfing.

---

## 12. AI Module: Pluggable Multi-Provider AI Engine (`app/Modules/AI/`)

The `AI` module provides an extensible, modular architecture allowing administrators to connect any AI model/provider to assist with backend trading operations without hardcoding APIs or credentials:

### A. Dynamic Model Provider Settings (`ai_model_settings` Schema)
Persisted in the database with strict field encryption:
- `provider`: Unique provider code (`gemini`, `openai`, `claude`, `ollama`).
- `name`: Display label (e.g., "Google Gemini 1.5 Pro / Flash", "OpenAI GPT-4o", "Anthropic Claude 3.5 Sonnet").
- `is_active`: Global toggle to enable/disable the AI provider.
- `credentials`: JSON column encrypted at rest (`'credentials' => 'encrypted:array'`) storing API keys/endpoints.
- `default_model`: Model identifier (`gemini-1.5-flash`, `gpt-4o-mini`, etc.).
- `temperature` & `max_tokens`: Configurable hyperparameters.
- `assigned_features`: JSON mapping defining which model handles specific backend capabilities.

### B. Admin Backend AI Facilities
1. **Catalog & SEO Enrichment**: Generates compelling product descriptions, bullet points, meta titles, SEO tags, and attribute extraction from imported manufacturer specs with a single click.
2. **Smart Restocking & Demand Insights**: Analyzes sales velocity, seasonal trends, and international/domestic supplier lead times to suggest optimal Purchase Order (PO) quantities.
3. **Customer Support & Dispute Assistant**: Drafts responses for return inquiries, RMA disputes, and tickets.
4. **AI Outbound Voice Calling Verification Engine**: Connects conversational voice models (Vapi, Retell, ElevenLabs, OpenAI Realtime) to conduct natural Bangla/English phone verification calls to confirm Cash on Delivery orders.
5. **Statutory Tax & Return Anomaly Detection**: Alerts management if abnormal frequencies of Mushak 6.7 Credit Notes are detected.
6. **Pre-Flight "Test AI Connection" Action (`TestAiConnectionAction`)**: Validates credentials and measures latency.
7. **Token & Cost Tracking**: Logs prompt and completion tokens per request with cost estimates.

---

## 13. Bangladesh NBR VAT & Income Tax Compliance Engine

The `TaxCompliance` module enforces strict regulatory compliance under the **Bangladesh Value Added Tax and Supplementary Duty Act 2012** and **Income Tax Act 2023**:

### A. Statutory NBR Invoices, Notes & Registers
1. **Mushak 6.3 (Tax Invoice / কর চালানপত্র)**:
   - Mandatory generation for every outward sale before dispatch.
   - Non-gap sequential numbering (`M6.3-YYYYMM-XXXXXX`) using atomic row-level locks on sequence records.
   - Immutable historical snapshot: Product details and tax math are frozen with an SHA-256 hash.
2. **Input Tax Credit on Imports & Local Purchases (রেয়াত - Mushak 9.1)**:
   - **Import Advance Tax (AT - 5%)**: Automatically claimed as Input Tax Credit from Bill of Entry records.
   - **Local Purchase Input VAT**: Automatically claimed as Input Tax Credit from local suppliers' verified Mushak 6.3 invoices.
   - Aggregated in monthly **Mushak 9.1** VAT Returns to offset Output Tax.
3. **Mushak 6.7 (Credit Note / বিক্রয় ফেরত চালানপত্র - হ্রাসকারী সমন্বয় দলিল)**:
   - Mandatory for sales returns, price reductions, cancellations, and customer refunds.
   - Explicitly references original Mushak 6.3 invoice number and date.
   - Posts a statutory Decreasing Adjustment (হ্রাসকারী সমন্বয়) into the VAT ledger for the active tax period.
4. **Mushak 6.8 (Debit Note / ক্রয় ফেরত চালানপত্র - বৃদ্ধিকারী সমন্বয় দলিল)**:
   - Generated when returning damaged or sub-standard procured finished goods to suppliers (local or foreign).
5. **Mushak 6.5 (Goods Transfer Challan / পণ্য স্থানান্তর চালানপত্র)**:
   - Required for moving trading stock between multiple registered company warehouses/branches.
6. **Mushak 6.6 (VDS Certificate / উৎসে মূসক কর্তন সনদপত্র)**:
   - Automatic generation when VAT is deducted at source on local domestic procurement.
7. **Statutory Registers**:
   - **Mushak 6.1**: Purchase Register (ক্রয় হিসাব পুস্তক) tracking both imported BOE and domestic Mushak 6.3 purchases.
   - **Mushak 6.2 / 6.2.1**: Sales Register / Trader's Purchase & Sales Book (ব্যবসায়ী ক্রয়-বিক্রয় হিসাব পুস্তক).

### B. Income Tax Compliance (Income Tax Act 2023)
1. **Withholding Tax / Tax Deducted at Source (TDS)**: Automated TDS deduction on domestic supplier procurement invoices based on statutory rates.
2. **Advance Income Tax (AIT)**: Accurate tracking of 5% AIT paid at customs import port to ensure proper annual corporate tax reconciliation.
3. **B2B / Trade Buyer Compliance**: Verification and capture of B2B client e-TIN and 13-digit / 9-digit BIN for corporate invoicing.

---

## 14. Sales Return, Refund & Tax Adjustment Lifecycle

All customer returns and refunds must strictly execute through this NBR-compliant state machine:

```text
[Return Request] -> [RMA Approved] -> [Warehouse Inward Inspection]
                                                │
                 ┌──────────────────────────────┴──────────────────────────────┐
                 ▼                                                             ▼
     [Mushak 6.7 Credit Note Issued]                                [Inventory Reversal]
 (Linked to original Mushak 6.3;                                (Restocked to Available or
 Decreasing Adjustment logged for VAT 9.1)                      Quarantined as Damaged Stock)
                 │
                 ▼
     [Financial Refund Disbursed]
 (Dynamic Gateway / MFS: bKash/Nagad/Card /
 Trade Credit Balance; Idempotency protected)
```

1. **Validation & Statutory Timeline**: Verify return eligibility against NBR statutory timeframes and return policy.
2. **Credit Note (Mushak 6.7)**: Instantly generated upon physical inspection and acceptance; decreasing tax adjustment (হ্রাসকারী সমন্বয়) recorded in the VAT ledger.
3. **Inventory Restocking**: Reversal of COGS using FIFO / Weighted Average cost based on original intake value.
4. **Refund Execution**: Dynamically calls the refund API of the active gateway wrapped in a database transaction with idempotency protection.

---

## 15. Architectural Best Approaches & Pre-Development Strategy

To guarantee enterprise scalability, maintainability, and clean execution, the project follows these specific architectural patterns:

### A. Full-Stack Monolithic SPA via Inertia.js v2 + SSR
- Combines rapid developer experience of Laravel with dynamic Vue 3 SFCs and TypeScript.
- **Inertia SSR** renders public catalog pages server-side for instant search crawler indexing and rich social bot unfurling.
- Secure HTTP session cookies with built-in CSRF protection instead of risky localStorage JWTs.

### B. Modular Autoloading (PSR-4 Clean Monolith)
- `composer.json` PSR-4 module namespace mapping: `"App\\Modules\\": "app/Modules/"`.
- Clean individual `ModuleServiceProvider` per module.

### C. Primary Key & Identifier Strategy
- Use **ULIDs (`Str::ulid()`)** for all public-facing entity identifiers to prevent enumeration and scraping attacks while keeping indexes sortable.

### D. Pluggable Payment & Refund Architecture
- Unified `PaymentGatewayManager` resolving dynamic settings from `payment_gateway_settings`.
- Redis-backed `X-Idempotency-Key` protection.

### E. Concurrency & Concurrency Control
- Inventory deducted inside `DB::transaction()` using `lockForUpdate()`.
- 15-minute checkout reservation locks managed via Redis TTL.

---

## 16. Enterprise Software Security, Bot Defense & Hardening

Security and anti-bot protections are treated as first-class engineering priorities:

### A. Admin-Configurable Google reCAPTCHA & Bot Defense (`security_settings` Schema)
- Non-technical administrators can configure and toggle bot defenses from the back-office UI:
  - **Provider Selector**: Google reCAPTCHA v2 (Checkbox), Google reCAPTCHA v3 (Score-based invisible), or Cloudflare Turnstile.
  - **Credentials**: `site_key` and `secret_key` (encrypted at rest `'secret_key' => 'encrypted'`).
  - **Granular Page Toggles**: Enable/disable individually on:
    - Customer Login & Registration
    - Password Reset
    - Checkout Submission (stops automated card testing and fake order bots)
    - Contact Us / Product Inquiries
    - Customer Product Reviews
  - **Verification Middleware**: Server-side verification via `VerifyRecaptchaMiddleware` using Guzzle HTTP POST before allowing controller execution.

### B. Role-Based Access Control (RBAC) & Back-Office Permission Management (`spatie/laravel-permission`)

Internal staff authorization and access control are standardized on **Spatie Laravel Permission (`spatie/laravel-permission:^6.0`)**, fully configurable via a dedicated back-office administrative UI:

1. **Pre-Configured Enterprise Roles**:
   - `Super Admin`: Full system access; bypasses all permission checks via `Gate::before`. Protected from deletion or demotion.
   - `Store Operations Manager`: Manages catalog, orders, shipping, and marketing without access to system security or credentials.
   - `Procurement & Import Officer`: Manages international suppliers, LC/TT, Bill of Entry (BOE), local purchase orders (PO), and Landed Cost allocation.
   - `Warehouse & Inventory Keeper`: Access to multi-hub stock management, GRN inward receiving, barcode scans, and Mushak 6.5 transfer challans.
   - `Accounts & NBR Tax Officer`: Manages Mushak 6.3 invoices, Mushak 6.7 Credit Notes, Mushak 6.6 VDS certificates, VAT 9.1 returns, and financial ledgers.
   - `Logistics & Fulfillment Dispatcher`: 1-click courier consignment booking, thermal shipping label printing, and COD cash reconciliation.
   - `Customer Care & Fraud Agent`: Order verification, customer communications, OTP triggers, AI voice calling review, and customer blacklist management.

2. **Back-Office Roles & Permission Management UI (`app/Modules/Admin/`)**:
   - **Custom Role Creator & Editor**: Administrators can create unlimited custom roles, clone existing roles, and adjust permissions at any time.
   - **Granular Domain Permission Matrix**: Interactive Vue 3 matrix grouping permissions by functional modules with quick **"Select All in Module"** toggles:
     - **Catalog**: `catalog.view`, `catalog.create`, `catalog.edit`, `catalog.delete`, `catalog.publish`, `catalog.bulk_pricing`
     - **Procurement**: `procurement.view`, `procurement.po.create`, `procurement.boe.manage`, `procurement.landed_cost.calculate`
     - **Inventory**: `inventory.view`, `inventory.grn.receive`, `inventory.transfer.mushak6_5`, `inventory.adjust_stock`
     - **Orders & Anti-Fraud**: `order.view`, `order.confirm`, `order.dispatch`, `order.cancel`, `order.fraud.review`, `order.fraud.blacklist`
     - **Logistics**: `logistics.view`, `logistics.book_consignment`, `logistics.print_label`, `logistics.reconcile_cod`
     - **Payments**: `payment.view`, `payment.refund.issue`, `payment.settings.manage`
     - **NBR VAT**: `tax.mushak6_3.issue`, `tax.mushak6_7.credit_note`, `tax.mushak9_1.view`, `tax.registers.export`
     - **AI & Omnichannel**: `ai.settings.manage`, `voice.call.trigger`, `communication.smtp.manage`
     - **Staff & Security**: `admin.staff.manage`, `admin.roles.manage`, `security.settings.manage`, `admin.audit_logs.view`
   - **Staff User Assignment**: Assign single or multiple roles to staff users, with optional direct permission overrides.
   - **Gate & Policy Enforcement**: Enforced uniformly across backend Form Requests, Controllers, Inertia page props (`$page.props.auth.permissions`), and navigation sidebar items.

### C. Authorization, OWASP & Anti-Abuse Controls
- **Role-Based Access Control (RBAC)**: Enforced via dedicated Model Policies (`$this->authorize(...)`) and Spatie permission middleware (`permission:...`).
- **OWASP Top 10 Defenses**: Eloquent parameter binding only, Vue template auto-escaping, DOMPurify for HTML descriptions, strict CSRF validation, guarded models.
- **Financial & Webhook Integrity**: Idempotency keys (`X-Idempotency-Key`), cryptographic signature verification, immutable audit trail logging.
- **Rate Limiting & Anti-Abuse**: Throttling on auth, checkout, AI calls, coupons, and public catalog endpoints.
- **Data Protection**: Sensitive credentials encrypted at rest (`encrypted:array`), PII encrypted, secure cookie attributes (`HttpOnly`, `SameSite=Strict`, `Secure`), HSTS/CSP headers.

---

## 17. Implementation Roadmap (Phase-by-Phase)

1. **Phase 1: Foundation, Infrastructure & SEO**:
   - Initialize Laravel 13.x, Inertia.js v2 with SSR, and Vue 3 (TypeScript).
   - Setup modular directory structure (`app/Modules/`) and PSR-4 autoloading.
   - Configure Sass (SCSS) design tokens and auto-injection in Vite.
   - Implement SEO foundation: `<Head>` metadata layout, Open Graph, Twitter Cards, Schema.org JSON-LD generators, and dynamic XML sitemap.
   - Setup User Authentication, Role-Based Access Control (RBAC), and Audit Logging.
2. **Phase 2: Anti-Fraud Engine, Fake Customer Shields & Bot Defense**:
   - Multi-signal order fraud risk scoring (`app/Modules/Order/Actions/CalculateOrderFraudRiskScoreAction`).
   - Courier delivery success rate query integration (Steadfast / Pathao return history check).
   - Honeypot bot traps, submission timing guards, and disposable email blacklist.
   - Merchant Blacklist/Whitelist management directory (`customer_fraud_lists`).
   - Google reCAPTCHA v2/v3 / Cloudflare Turnstile dynamic configuration.
3. **Phase 3: Dynamic Payment Module & Advance COD Delivery Charge Engine**:
   - Gateway settings database schema with encryption (`payment_gateway_settings`).
   - Dynamic gateway drivers (bKash, Nagad, SSLCommerz, Stripe, COD).
   - Advance partial delivery charge engine (requires high-risk COD buyers to prepay ৳100–৳150 delivery fee via bKash/Nagad).
   - Back-office UI for activation, API key management, and pre-flight connection testing (`TestGatewayConnectionAction`).
4. **Phase 4: Automated Courier Logistics & Delivery Pricing Gateway**:
   - Courier database settings (`courier_gateway_settings`) and drivers for Steadfast, Pathao, RedX, Paperfly, Sundarban.
   - Delivery pricing matrix (Inside Dhaka, Suburbs, Outside Dhaka, weight brackets, free shipping).
   - 1-click consignment booking and 4x6 inch thermal barcode shipping label generation.
   - Webhook listeners for real-time tracking updates and automated COD reconciliation.
5. **Phase 5: Omnichannel Messaging, Dynamic SMTP & Social Feeds**:
   - Dynamic SMTP mail settings (`mail_settings`) with "Send Test Email" action.
   - Bangladesh SMS gateways (Greenweb, Reve SMS) and WhatsApp API triggers.
   - Automated COD OTP verification for medium-risk orders.
   - Google Merchant Center and Facebook Catalog sync feeds.
6. **Phase 6: Pluggable AI Module & Backend Intelligence**:
   - Dynamic AI settings schema with encryption (`ai_model_settings`).
   - Multi-provider drivers (Google Gemini, OpenAI, Anthropic Claude, Ollama/Local).
   - Back-office UI for connecting AI models, feature mappings, and testing connections (`TestAiConnectionAction`).
   - Catalog SEO enrichment, smart restocking demand insights, and support replies.
7. **Phase 7: Dual Sourcing Procurement (Import & Local) & Landed Cost Engine**:
   - International suppliers, Commercial Invoices (CI), Letter of Credit (LC/TT), Bill of Entry (BOE), and HS codes.
   - Local Bangladesh supplier directory, Local Purchase Orders (PO), and local Mushak 6.3 recording.
   - Unified Landed Cost allocation (Import duties/freight or local transport = True COGS in BDT).
   - Multi-warehouse inventory receiving (GRN) and FIFO valuation.
8. **Phase 8: Catalog, Pricing & Local Storefront (100% SEO-Optimized & Frictionless UX)**:
   - Product taxonomy, matrix variants (Size $\times$ Color $\times$ Material), brands, and custom attributes.
   - Sub-50ms instant search with autocomplete and typo tolerance.
   - Interactive slide-out cart drawer with free shipping progress bar.
   - Retail (B2C) and Wholesale/Trade (B2B tier pricing) engines in BDT (৳).
   - High-performance, responsive Vue 3 storefront styled exclusively in SCSS with SSR, Schema.org rich snippets, and social share cards.
9. **Phase 9: Comprehensive NBR VAT, Input Credit (Import & Local) & Income Tax Compliance**:
   - Automated Mushak 6.3 Tax Invoices with non-gap atomic serial generation.
   - Claiming both import Advance Tax (AT) and local purchase VAT as Input Tax Credit (রেয়াত) on monthly Mushak 9.1 returns.
   - Automated Mushak 6.6 VDS certificate generation for local procurement.
   - Sales Return state machine with automated Mushak 6.7 Credit Notes and Decreasing Adjustments.
   - Mushak 6.8 Debit Notes, Mushak 6.5 Transfer Challans, Mushak 6.1/6.2 registers.
   - TDS (Tax Deducted at Source) and import AIT reconciliation under Income Tax Act 2023.
10. **Phase 10: Admin Analytics, Profit & Loss & QA**:
    - Executive trading dashboard (Gross Profit, Landed COGS in BDT, Sales Revenue, Courier COD payouts, Fraud prevention analytics, Tax liabilities, AI usage).
    - Comprehensive Pest backend test suites and frontend component testing.

---

## 18. Development Workflow & Commands

### Backend Commands
- `php artisan serve`: Run local Laravel development server
- `php artisan test`: Run backend test suites (Pest / PHPUnit)
- `php artisan migrate`: Run database migrations
- `./vendor/bin/pint`: Format PHP code according to PSR-12 / Laravel style

### Frontend Commands
- `npm run dev`: Start Vite development server with Sass compiler
- `npm run build`: Compile frontend production assets and SSR bundle (`npm run build:ssr`)
- `npx vue-tsc --noEmit`: Type-check Vue components and TypeScript files

### Agent Verification Checklist
Before completing any task or pull request:
1. **Anti-Fraud & Risk Score Verification**: Ensure fraud risk scoring algorithms, honeypot traps, and OTP triggers work accurately on checkout submissions.
2. **SEO & Social Media Validation**: Check that page `<title>`, `<meta name="description">`, Open Graph, Twitter Cards, canonical URL, and Schema.org JSON-LD are generated correctly without client-only dependency.
3. **Security & Authorization Review**: Verify policy authorization checks, reCAPTCHA verification, and ensure no mass assignment or unescaped queries exist.
4. **NBR Tax & Accounting Review**: Ensure any financial/stock change reflects accurately in VAT registers and Credit/Debit Note lifecycles, and import AT / local input VAT are claimed accurately as input credit.
5. **Logistics, SMTP & Gateway Verification**: Ensure courier consignment payloads, SMTP test mail, and gateway encryption are properly tested.
6. **PHP Lint & Style**: Run `./vendor/bin/pint --test` (or `pint` to auto-fix).
7. **Backend Tests**: Run `php artisan test` to verify no regressions.
8. **Frontend Type Check**: Run `npx vue-tsc --noEmit` and ensure zero type diagnostics.
9. **Sass & Asset Compilation**: Run `npm run build` to confirm SCSS and asset compilation succeeds without syntax errors.

---

## 19. Git & Commit Guidelines

- Use **Conventional Commits**:
  - `feat(fraud): implement multi-signal order fraud risk scoring and courier history check`
  - `feat(fraud): add advance partial delivery charge requirement for high-risk COD`
  - `feat(security): add invisible honeypots and submission timing guards on checkout`
  - `feat(customer): create searchable merchant fraud blacklist and whitelist directory`
  - `feat(catalog): implement multi-dimensional matrix variant manager and instant search`
  - `feat(checkout): add 1-page checkout with cascading BD addresses and slide-out cart`
  - `feat(logistics): add Steadfast and Pathao courier consignment booking drivers`
  - `feat(compliance): claim import AT and local VAT as input credit on Mushak 9.1`
- Keep commits focused, clean, and atomic.
