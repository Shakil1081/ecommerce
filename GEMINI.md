# Project Instructions: Enterprise Single-Vendor E-Commerce Platform (Hybrid Trading: Import & Local Sourcing)

> [!NOTE]
> Project rules, architectural standards, regulatory tax compliance, dynamic payments, AI intelligence, SEO standards, courier logistics, and security guidelines are defined in [AGENTS.md](file:///c:/working/ts/ecommerce/AGENTS.md).

Refer to [AGENTS.md](file:///c:/working/ts/ecommerce/AGENTS.md) for full instructions:
- **Domain**: Pure Trading Business (Dual Sourcing: Global Importation + Local Bangladesh Domestic Procurement $\rightarrow$ Local Online Storefront Buy & Sell).
  - **Global Importation**: Commercial Invoices (CI), Proforma Invoices (PI), LC/TT tracking, Bill of Entry (BOE / বিল অফ এন্ট্রি), HS codes, Customs Duty (CD), Regulatory Duty (RD), Advance Tax (AT - 5%), and Advance Income Tax (AIT).
  - **Local Domestic Procurement**: Local Bangladesh suppliers/manufacturers, PO in BDT (৳), local supplier Mushak 6.3 invoices, VDS with **Mushak 6.6 Certificates**, TDS under the Income Tax Act 2023, and local freight Landed Cost.
  - **Unified Landed Cost & COGS**: Foreign currency purchases (USD, RMB, EUR) converted at bank settlement exchange rates; allocating freight, insurance, port C&F fees, and customs duties alongside local procurement costs to compute true domestic COGS in BDT (৳).
  - **Local Online Shop**: Selling locally to Bangladesh consumers (B2C) and wholesale trade buyers (B2B) in BDT (৳).
- **Tech Stack**: Laravel 13.x (PHP 8.3+) + Inertia.js v2 with SSR + Vue.js 3 (TypeScript, Pinia, Vite).
- **Media & Image Management**: **Spatie Laravel MediaLibrary** (`spatie/laravel-medialibrary`) + **Intervention Image v3** (`intervention/image`) with automated WebP/AVIF conversions, responsive breakpoints (`thumb_300`, `card_600`, `gallery_1200`, `zoom_2000`), blurhash placeholders for zero layout shift (CLS = 0.0), and Cloudflare R2 / S3 storage.
- **Role-Based Access Control (RBAC)**: **Spatie Laravel Permission** (`spatie/laravel-permission`) with complete back-office admin UI for creating custom roles, assigning grouped domain permissions, and staff user access control.
- **Styling Standard**: **Sass / SCSS (`lang="scss"`) exclusively** with modular design tokens, mixins, and Vite auto-injection.
- **Architecture**: Domain-Driven Modular Monolith (`app/Modules/`) via PSR-4 autoloading with decoupled module boundaries, Actions, DTOs, and Domain Events.
- **Four Pillars of World-Class E-Commerce**:
  1. **Enterprise Security & Anti-Fraud**: Granular risk scoring for COD/online orders, device fingerprinting, multi-session management, CSP nonces, anti-carding rate limits, and tamper-proof audit trails.
  2. **Ultra-Frictionless UX/UI**: Sub-50ms instant search with typo tolerance, 1-page/drawer checkout (zero forced registration, cascading BD addresses), interactive slide-out cart drawer with free shipping progress bar, color-swatch gallery sync, and self-service order tracking timeline.
  3. **High Performance & Sub-Second Speed**: Multi-tier Redis caching, Inertia partial reloads, Core Web Vitals (LCP < 1.2s, INP < 50ms, CLS = 0.0), and Redis atomic decrement (`DECRBY`) flash-sale concurrency resilience.
  4. **Advanced Product Management**: Multi-dimensional matrix variant manager (Size $\times$ Color $\times$ Material), smart rule-based collections, visual drag-and-drop merchandising, barcode scanner GRN intake, and urgency badges.
- **Commercial Turnkey Selling Capabilities**:
  - **Automated Courier Logistics & Dynamic Delivery Pricing (`app/Modules/Logistics/`)**:
    - Admin UI for credentials, active toggles, and pre-flight API test connections for **Steadfast**, **Pathao**, **RedX**, **Paperfly**, **Sundarban**.
    - Dynamic zone pricing engine (Inside Dhaka, Dhaka Suburbs, Outside Dhaka, extra weight per kg, free shipping threshold).
    - One-click consignment booking, 4x6 thermal barcode shipping labels, live tracking webhooks, and automated COD cash reconciliation.
  - **Admin-Configurable Dynamic SMTP Mail (`app/Modules/Communication/`)**:
    - Back-office UI to configure Mailer, Host, Port, Encryption, Username, Password (encrypted), and From Address without touching `.env`.
    - Automated pre-flight **"Send Test Email"** handshake button before saving.
  - **Google reCAPTCHA & Anti-Bot Defense (`app/Modules/Admin/`)**:
    - Admin toggle to enable Google reCAPTCHA (v2 Checkbox or v3 Invisible) or Cloudflare Turnstile with encrypted secret keys.
    - Granular page assignment toggles (Login, Register, Checkout, Inquiries, Reviews) with backend verification middleware.
  - **Omnichannel Messaging & Social Commerce**: Automated SMS & WhatsApp order alerts, Google Merchant Center XML feed, Facebook Catalog / Instagram Shop sync, and TikTok catalog exports.
  - **AI Automated Outbound Voice Calling Verification Agent (`app/Modules/Communication/` & `voice_calling_settings`)**:
    - AI Voice Agent dials customer within 60s of placing a COD order with natural bilingual Bangla (বাংলা) & English prompts.
    - Dual confirmation: DTMF Touch-Tone (Press 1 to confirm, 2 to cancel) or Conversational Speech-to-Intent AI.
    - Automated lifecycle webhooks: 1 = `confirmed` (direct to picking), 2 = `cancelled_by_customer` (stock released).
    - **Admin Back-Office UI**: Instant Active/Inactive toggle, encrypted API keys (Twilio Voice, Retell AI, Vapi.ai, Local Telco OBD), call script customizer, call logs with audio playback, and **"Make Test Call"** pre-flight verification button.
  - **Comprehensive Anti-Fraud, Fake Order & Fake Customer Defense Shield (`app/Modules/Order/` & `app/Modules/Customer/`)**:
    - **7-Layer Anti-Fraud Shield**:
      1. Invisible Honeypot fields (`user_company_fax`) & client submission timing guards (< 3.5s = bot flag).
      2. Bangladesh mobile operator regex (`+8801[3-9]\d{8}`) & repeating dummy digit detection (`01711111111`).
      3. Cross-courier delivery success rate intelligence queries (**Steadfast Return Rate API**, **Pathao Delivery Success API**, `FraudCheckBD`).
      4. Shannon entropy gibberish detection & cascading BD administrative boundary enforcement (Division $\rightarrow$ District $\rightarrow$ Thana).
      5. Order velocity throttling: max 1 active unfulfilled COD per phone number; max 2 orders/day per IP/device fingerprint.
      6. Dynamic Risk Scoring Action (`CalculateOrderFraudRiskScoreAction` from 0 to 100).
      7. Automated Tiered Enforcement:
         - *Low Risk (0–29)*: Direct auto-approval to warehouse picking.
         - *Medium Risk (30–69)*: Mandatory SMS/WhatsApp 4-digit OTP with 15-minute stock hold TTL.
         - *High Risk (70–100 or Courier Return Rate > 40%)*: **COD Disabled!** Mandatory advance partial delivery charge (৳70/৳150) prepaid via bKash/Nagad before dispatch.
         - *Blacklisted*: Instant rejection.
    - **Merchant Fraud Directory (`customer_fraud_lists`)**: Admin UI for 1-click blacklisting of phone/IP/device fingerprint upon courier return, customer whitelist for VIP zero-friction checkout, and high-risk manual review queue.
  - **Abandoned Cart Recovery & Loyalty**: Multi-step automated WhatsApp/SMS/Email abandoned cart recovery, customer reward points, and referral programs.
  - **100% SEO & Social Media Optimization**: Inertia SSR, dynamic `<title>` and `<meta name="description">` (150-160 chars), Open Graph, Twitter Cards, Schema.org JSON-LD rich snippets, and dynamic XML sitemap.
- **Dynamic Payment Module**:
  - Admin configuration UI to add API credentials, toggle Sandbox/Live modes, and activate/deactivate Bangladesh MFS (**bKash**, **Nagad**, **SSLCommerz**) and global gateways.
  - Automated pre-flight **"Test API Connection"** handshake before activating.
  - Encrypted database credential storage (`encrypted:array`) with masked secret keys in API responses.
- **Pluggable AI Intelligence Module (`app/Modules/AI/`)**:
  - Separate decoupled module allowing companies to dynamically connect **Google Gemini**, **OpenAI**, **Anthropic Claude**, or **Ollama / Local LLMs**.
  - Dynamic encrypted credential settings (`ai_model_settings`), feature model mapping, token usage tracking, and automated **"Test AI Connection"** pre-flight verification.
  - Backend AI facilities: One-click catalog SEO enrichment from imported specs, smart restocking demand forecasting, customer dispute/support drafting, and tax anomaly detection.
- **Bangladesh NBR VAT & Income Tax Compliance**:
  - **Mushak 6.3** Tax Invoices for local sales with non-gap atomic serial locks.
  - Claiming both import Advance Tax (AT) and local supplier VAT as **Input Tax Credit (রেয়াত)** on monthly **Mushak 9.1** returns.
  - Automated **Mushak 6.6 VDS Certificates** for withholding tax on local procurement.
  - **Mushak 6.7 Credit Notes** for sales returns, refund adjustments, and decreasing tax adjustments.
  - **Mushak 6.8 Debit Notes** for vendor purchase returns.
  - **Mushak 6.5 Transfer Challans** for multi-warehouse transfers.
  - **Mushak 6.1, 6.2, 6.2.1** statutory registers & TDS/AIT tracking under the Income Tax Act 2023.
- **Coding Best Practices**: Strict typing (`declare(strict_types=1);`), thin controllers, single-action pattern (`__invoke()`), database transactions, concurrency row locking (`lockForUpdate()`), ULID public identifiers.
- **Enterprise Security**: OWASP Top 10 defenses, RBAC policies, payment/AI webhook signature validation, idempotency keys (`X-Idempotency-Key`), tamper-proof audit trails, and strict rate limiting.
- **Phased Implementation**: 10-phase roadmap from core modular infrastructure to dual-sourcing procurement, logistics, dynamic payments, AI intelligence, tax compliance, and executive trading analytics.
