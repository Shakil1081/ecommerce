## Description of Changes

<!-- Briefly describe the technical changes and business rationale of this pull request. -->

### Linked Issue / Phase Reference
- Closes #<!-- Issue Number -->
- Relevant Phase: **Phase [X] — Milestone [X.Y]** (Ref: `PHASE_PLAN.md`)

---

## Type of Change
- [ ] `feat`: New feature or business capability
- [ ] `fix`: Bug fix
- [ ] `refactor`: Code refactoring without functional changes
- [ ] `test`: Added or updated automated tests
- [ ] `docs`: Documentation updates (AGENTS.md, PHASE_PLAN.md, ADR)
- [ ] `perf`: Performance improvement

---

## Domain & Architecture Verification Checklist

### Code Quality & Standards
- [ ] Strict types declared in all PHP files: `declare(strict_types=1);`
- [ ] Thin controllers: Controller delegates directly to Action classes / Domain Services
- [ ] Form Request classes used for all incoming request validation
- [ ] Monetary & Tax precision: Zero raw floats; integer minor units (poisha) or `DECIMAL(14, 4)` used
- [ ] Database transactions (`DB::transaction`) and concurrency row locks (`lockForUpdate()`) applied for state changes
- [ ] PSR-12 code style enforced: `./vendor/bin/pint --test` passes cleanly
- [ ] Frontend type safety: Zero `any`, typed props/emits, and `npx vue-tsc --noEmit` passes cleanly

### Security & Fraud Protection
- [ ] Authorization policies / Spatie permissions checked for protected actions
- [ ] Public entities use ULIDs (`Str::ulid()`) rather than auto-incrementing IDs
- [ ] Sensitive credentials encrypted at rest (`'credentials' => 'encrypted:array'`) and masked in API responses
- [ ] Cryptographic webhook signatures validated

### Tax Compliance (NBR Bangladesh)
- [ ] Mushak 6.3 invoices enforce atomic, non-gap sequence numbers
- [ ] Returns/Cancellations issue Mushak 6.7 Credit Notes with decreasing tax adjustments
- [ ] Local domestic procurement tracks VDS (Mushak 6.6) and TDS withholding

---

## Testing & Verification Output
```bash
# Paste verification command output here (e.g., php artisan test)
```

