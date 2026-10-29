# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

---

## [Unreleased]

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

