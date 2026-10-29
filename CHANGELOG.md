# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

---

## [Unreleased]

### Added
- Comprehensive 10-Phase master execution plan (`PHASE_PLAN.md`) incorporating:
  - Dynamic Multi-Cloud Storage Engine (Local, AWS S3, Cloudflare R2, GCS, MinIO).
  - Promotional SMS Campaign Manager with live Bangla Unicode character counter, audience segmentation, BTRC regulatory sending window enforcement (9:00 AM – 8:00 PM), and delivery promotion ROI tracking.
  - Customer Support & Helpdesk Ticket System with category filters, damaged goods photo viewer, and AI Smart Reply Assistant.
  - In-App Release Log Viewer (`SystemUpdates.vue`) and automated GitHub Release workflow (`.github/workflows/release.yml`).
  - Role-Based Access Control (RBAC) with Spatie Permission and Back-Office Admin Management UI.
  - Customer Reviews, Ratings, and photo uploads with Spatie MediaLibrary.
  - Coupons and promotional discount engine.
  - Dynamic bot defense settings (reCAPTCHA v2/v3, Turnstile) with granular page toggles.
  - Automated Courier Logistics with COD Remittance Batch Reconciliation.
  - Customer Portal with saved address book and B2B Trade Account verification.
  - Marketing engine with multi-step abandoned cart WhatsApp/SMS sequences.
  - NBR-compliant RMA and Sales Return lifecycle with warehouse inspection.
  - Immutable audit logging for price changes, stock adjustments, and refunds.
- GitHub Actions automated CI workflow (`.github/workflows/ci.yml`) for Laravel Pint, Pest/PHPUnit, and Vue 3 / TypeScript compilation.
- GitHub Issue Form templates (`.github/ISSUE_TEMPLATE/feature_task.yml`, `bug_report.yml`, `config.yml`).
- GitHub Pull Request template (`.github/PULL_REQUEST_TEMPLATE.md`) with domain verification checklist.
- Git-tracked living sprint task board (`TASK_BOARD.md`) breaking down Phase 1 into 7 execution tickets (`P1-T01` to `P1-T07`).
- Architecture Decision Records framework (`docs/adr/`).

---

## [0.1.0] - 2026-10-29

### Added
- Initial enterprise repository architecture, domain rules, and commercial hybrid trading specifications (`AGENTS.md`, `GEMINI.md`, `README.md`).
- Editor configuration standards (`.editorconfig`).

