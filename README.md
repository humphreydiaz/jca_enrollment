# JCA Enrollment and Student Management System

Browser-based enrollment and student management for J.C.S.G.O. Christian Academy R1 Inc. (JCA), Lingayen, Pangasinan. The planned system covers enrollment intake, student/guardian records, requirements, restricted student-support review, sections, payment tracking, portals, SMS notifications, and reports.

## Project status

**Phases 1 through 9 are complete for development and engineering launch readiness as of 2026-07-13. Production launch is intentionally blocked until the school approvals and production inputs in P-001 through P-008 are signed.**

The codebase now also contains a configurable offline Billing module. Phase 6 adds versioned fee schedules, immutable assessment revisions, exact-centavo installment schedules, transactionally ranked development discount campaigns, append-only payment/reversal/adjustment/refund history, private payment evidence, reproducible permit calculations and overrides, scoped financial portals, and audited statement/receipt/permit/ledger print views. No online collection integration exists.

The authenticated interface includes a responsive role-scoped admin shell with grouped icon navigation, active-page states, permanent desktop and drawer-based mobile navigation, role-aware operational dashboards, standardized form/button/empty-state components, and modernized Section Assignment and Financial Configuration workspaces. These presentation changes do not broaden route authorization or alter business transitions.

Verified baseline on 2026-07-11:

- Laravel Framework 13.19.0 (`composer.json` allows `^13.8`)
- PHP `^8.3`; inspected CLI PHP 8.3.16
- Tailwind CSS 4
- Vite 8
- Laravel Vite plugin 3
- Alpine.js 3.14.9, installed as a direct development dependency (MIT licensed)
- MySQL is configured as the only application database; database cache, sessions, and queues are used
- private document disk and sensitive log-context redaction are configured
- staff-issued, expiring, single-use invitation setup and recovery links
- Admin-only user/role management, guardian/student profile linkage, and relationship verification
- Admin/Registrar academic catalog management with history-preserving inactivation and assignment-scoped teacher read-only views
- public email-code verification with staff-assisted verification for mobile-only applicants while live SMS remains disabled
- MySQL-serialized duplicate intake, private versioned soft-copy evidence, independently audited hard-copy requirement tracking, authorized document preview/download, restricted reviewer assignment, append-only support events, and non-enumerating sensitive record denials
- Phase 4 verification: 38 tests and 267 assertions, dedicated-MySQL fresh/rollback/reapply, production asset build, Pint, Composer validation, route listing, and diff checks
- Phase 5 verification: 47 tests and 376 assertions, including second-connection MySQL row-lock tests for confirmation/transfer and full migration rollback/reapplication
- Phase 6 verification: 65 tests and 548 assertions, MySQL fresh migration plus complete rollback/reapplication, true second-connection campaign/reference lock tests, production asset build, Pint, Composer validation, 112-route inspection, and Git whitespace checks. Development fee values and calendars remain explicitly non-production.
- Phase 7 adds scoped Admin, Registrar, Accounting, Teacher, Guardian, and Student dashboards; audience-authorized announcements; guardian SMS preferences; versioned safe templates; database-queued SMS delivery, retries, idempotency, and audited logs. Semaphore remains disabled by default pending P-005; tests use a fake provider or HTTP fakes and never make live network calls.
- Phase 8 adds nine fixed operational report families, role and record scoping, validated school-year/grade/section/status/date filters, private database-queued CSV exports with expiry and download re-authorization, spreadsheet formula-injection protection, navigation-free print layouts, and Admin-only privacy-safe audit search. P-006 remains open, so these are development operational layouts rather than approved official school forms.
- Phase 8 verification: 75 tests and 692 assertions in bounded MySQL groups after the single full-suite command reached its execution ceiling; dedicated-MySQL fresh/rollback/reapply, production asset build, Pint, Composer validation, 128-route inspection, browser/print privacy QA, and Git whitespace checks all pass.
- Phase 9 adds fail-closed production validation, secure response/cookie/queue defaults, correlation headers, sensitive-input flashing protection, private-disk runtime probes, scheduled queue/export cleanup, PowerShell backup/restore tooling, and deployment, rollback, incident, UAT, and launch checklists. Live SMS remains disabled.
- Phase 9 verification: 81 tests and 737 assertions in bounded MySQL groups after the one-shot suite reached its execution ceiling; fresh/rollback/reapply, a fictional database/private-file restore rehearsal, configuration/route/view caching, responsive browser checks, production build, Pint, Composer/npm advisory audits (zero vulnerabilities), routes, and Git checks pass.

## Required stack

- Laravel MVC
- Blade
- Tailwind CSS
- Alpine.js
- MySQL
- Vite
- PHPUnit and Laravel testing tools
- Database-backed cache, session, and queue
- Semaphore for SMS

The project must not use React, Vue, Inertia, Livewire, Tabler, Bootstrap, PostgreSQL, Redis, Docker, microservices, or paid packages. Semaphore is the only paid external integration.

## Read before implementation

| File | Purpose |
|---|---|
| [PLAN.md](PLAN.md) | Bounded implementation phases, acceptance criteria, and required tests |
| [AGENTS.md](AGENTS.md) | Rules for future Codex/agent tasks |
| [Requirements](docs/REQUIREMENTS.md) | Confirmed, decided, assumed, pending, and out-of-scope requirements |
| [Decisions](docs/DECISIONS.md) | Settled product and technical decisions plus remaining policy inputs |
| [Architecture](docs/ARCHITECTURE.md) | Module boundaries, workflows, security, and deployment shape |
| [Database](docs/DATABASE.md) | Conceptual tables, relationships, constraints, and migration order |
| [UI guide](docs/UI_GUIDE.md) | Logo-based visual direction, layouts, components, and accessibility |
| [Production checklist](docs/operations/PRODUCTION_CHECKLIST.md) | Fail-closed approvals, technical launch gates, and go/no-go record |
| [Deployment runbook](docs/operations/DEPLOYMENT.md) | Release, queue/scheduler, verification, and rollback procedure |
| [Backup and restore](docs/operations/BACKUP_RESTORE.md) | Encrypted-target backup, isolated restore, and reconciliation drill |
| [Incident response](docs/operations/INCIDENT_RESPONSE.md) | Containment, recovery, monitoring, and evidence handling |
| [UAT and role sign-off](docs/operations/UAT.md) | Role-scoped acceptance journeys and owner signatures |

The original source material is retained as `JCA-OUTLINE.pdf`; the school identity source is `JCA Logo.jpg`.

## Identity foundation

- Public registration is intentionally unavailable.
- Parent/Student login is available at `/login`; Admin, Registrar, Accounting, and Teacher login is available at `/staff/login`. Both accept a configured email or mobile identifier, use rate limiting, and reject the other role group without revealing account state.
- Account setup uses a one-time invitation token; no permanent plaintext password is sent.
- Recovery is staff-assisted: after identity verification, an Admin issues a short-lived recovery invitation to the existing contact channel.
- Disabled accounts require an audited Admin reactivation reason and a replacement setup invitation; disabling revokes open invitations, and locked accounts remain blocked for security review.
- Staff roles can be combined; Guardian/Student portal roles cannot be combined with staff roles.
- Guardian/student links are many-to-many and have explicit visibility/contact permissions plus verification state.

## Core decisions

- Closed public account registration; verified applicants receive school invitations.
- Individual guardian accounts with many-to-many guardian/student relationships.
- Student accounts default to Grades 7-12 after enrollment approval; Grades 1-6 normally use Guardian access, with audited Admin exceptions.
- Staff may hold multiple staff roles; policies still enforce record-level scope.
- Teachers see assigned students and approved support summaries only.
- Academic enrollment and financial standing are separate.
- Payment is tracking-only; no online gateway.
- Posted payments are corrected by audited reversal, not editing/deletion.
- Sections normally hold 15 students; system recommendation is staff-confirmed.
- Special-support placement always receives manual suitability confirmation.
- Promotion creates a new school-year enrollment and preserves history.
- Production document deletion waits for an approved JCA retention schedule.

See [DECISIONS.md](docs/DECISIONS.md) for full rationale and consequences.

## Local preparation

The following prepares the skeleton only; it does not add JCA features.

1. Install PHP 8.3+, Composer, Node.js, npm, and MySQL 8.0+.
2. Create an empty local MySQL database named `jca_enrollment` (or choose another local name).
3. Install dependencies:

   ```powershell
   composer install
   npm.cmd install
   ```

4. Create local environment configuration:

   ```powershell
   Copy-Item .env.example .env
   php artisan key:generate
   ```

5. Set local database credentials in `.env`. Never commit `.env`.
6. Apply the Phase 1 through 8 migrations:

   ```powershell
   php artisan migrate
   ```

7. Seed fixed system roles and the confirmed Nursery-to-Grade-12 progression, then create the first administrator through a secure interactive prompt. This command never creates a default password:

   ```powershell
   php artisan db:seed
   php artisan identity:bootstrap-admin
   ```

   The base grade catalog is safe to reseed and preserves existing grade IDs while restoring names, stages, order, active status, and progression links. Optional development fixtures are never run by `DatabaseSeeder`. To load the clearly labeled fictional fee/calendar baseline into a disposable development database, run `php artisan db:seed --class="Database\\Seeders\\Billing\\BillingDevelopmentSeeder"` explicitly. To populate only the active Junior High grades in the current local School Year 2026-2027, run `php artisan db:seed --class="Database\\Seeders\\Billing\\CurrentSchoolYearJuniorHighBillingTestSeeder"`. To configure the owner-approved dual-evidence requirement checklist for an existing local School Year 2026-2027 and backfill active applications, run `php artisan db:seed --class="Database\\Seeders\\Requirements\\RequirementsDevelopmentSeeder"`. Replace all development configuration with approved school configuration before production.

   After deploying the promotion-requirement carryover migration to a database that already contains promotion enrollments, run `php artisan requirements:carry-forward-promotions`. The command is idempotent, carries only accepted hard-copy evidence, and reports counts without printing student information.

8. Start development processes using the existing Composer script or separate Laravel/Vite processes:

   ```powershell
   composer run dev
   ```

For MySQL-backed tests, copy `.env.testing.example` to `.env.testing`, create the separate `jca_enrollment_testing` database, and never point test configuration at school data. PowerShell may block `npm.ps1`; use `npm.cmd` on affected Windows systems.

## Common checks

```powershell
composer validate --no-check-publish
composer test
npm.cmd run build
php artisan about
```

Run the phase-specific test matrix in [PLAN.md](PLAN.md) in addition to these checks. MySQL-specific constraints, transactions, and locking must be tested on MySQL rather than SQLite.

Before any production deployment, prepare `.env` from `.env.production.example`, retain all secrets outside source control, obtain the signed approvals referenced by its `JCA_*_APPROVED` flags, and run:

```powershell
php artisan jca:production-check
php artisan schedule:list
```

The readiness command fails closed and prints check names only; it never prints credentials or configuration values.

## Documentation-first workflow

Implementation should proceed one PLAN phase per task:

1. Read `AGENTS.md`.
2. Read only the selected phase in `PLAN.md` and its listed input documents.
3. Confirm pending inputs required by that phase.
4. Implement only its bounded scope.
5. Run its required tests and document evidence.
6. Update decisions/requirements only when behavior or policy changes.

This structure lets future tasks work from current contracts without loading the complete project conversation history.

## Current risks

- JCA has not approved document retention and deletion periods.
- JCA has not supplied the official support-assessment form, decision vocabulary, or reviewer roster; Phase 4 deliberately uses neutral generic outcomes and restricted notes only.
- Phase 6 fee amounts, class/exam dates, discount dates/value, receipt/invoice series, enabled methods, financial signatories, promissory-note process, statutory verification, permit wording, and holiday calendar still require production approval.
- The development academic baseline is Grades 1-12 (Elementary 1-6, Junior High 7-10, Senior High 11-12); official catalog import remains pending and preschool is not seeded.
- Hosting, backup retention, agreed RPO/RTO, offsite encryption, monitoring ownership, and the production MySQL environment remain pending P-007. The verified local restore rehearsal is not a substitute for the production-host restore drill.
- JCA has not supplied the approved Semaphore sender name, consent wording, live message templates, escalation contacts, or live credentials. SMS is intentionally disabled until these P-005 inputs are approved.
- JCA has not approved the official report list, final column sets, signatories, control/reference formats, or branded print layouts. Phase 8 outputs are explicitly development operational reports until P-006 is resolved.
- Role owners, UAT signatories, training completion, incident contacts, and launch authority remain pending P-008.
- The source logo remains preserved, and the shared navbar/sidebar brand uses `public/images/logo/jca_logo.png` as its web asset.

## License

No project-specific license has been selected. The Laravel framework is licensed separately under the MIT License. Do not assume JCA application code or school assets are open source.
