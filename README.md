# JCA Enrollment and Student Management System

Browser-based enrollment and student management for J.C.S.G.O. Christian Academy R1 Inc. (JCA), Lingayen, Pangasinan. The planned system covers enrollment intake, student/guardian records, requirements, restricted student-support review, sections, payment tracking, portals, SMS notifications, and reports.

## Project status

**Phases 1 through 5 are complete as of 2026-07-11. Billing, communications, reporting, grades, and attendance remain future phases.**

The codebase now contains MySQL infrastructure, identity/people, a data-driven academic catalog, enrollment intake and decisions, private requirements, and restricted support review. Phase 5 adds deterministic capacity-aware recommendations, locked staff confirmation, Admin-only capacity overrides, transfer history, academic `enrolled` finalization independent of payment, promotion recommendations and predecessor-linked drafts, guardian/student section schedules, printable portal-safe placement copies, and current-assignment-scoped Teacher access to approved instructional summaries.

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
- MySQL-serialized duplicate intake, private document history, restricted reviewer assignment, append-only support events, and non-enumerating sensitive record denials
- Phase 4 verification: 38 tests and 267 assertions, dedicated-MySQL fresh/rollback/reapply, production asset build, Pint, Composer validation, route listing, and diff checks
- Phase 5 verification: 47 tests and 376 assertions, including second-connection MySQL row-lock tests for confirmation/transfer and full migration rollback/reapplication

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

The original source material is retained as `JCA-OUTLINE.pdf`; the school identity source is `JCA Logo.jpg`.

## Identity foundation

- Public registration is intentionally unavailable.
- Login accepts a configured email or mobile identifier and uses rate limiting.
- Account setup uses a one-time invitation token; no permanent plaintext password is sent.
- Recovery is staff-assisted: after identity verification, an Admin issues a short-lived recovery invitation to the existing contact channel.
- Staff roles can be combined; Guardian/Student portal roles cannot be combined with staff roles.
- Guardian/student links are many-to-many and have explicit visibility/contact permissions plus verification state.

## Core decisions

- Closed public account registration; verified applicants receive school invitations.
- Individual guardian accounts with many-to-many guardian/student relationships.
- Student accounts default to Grades 7-10 after enrollment approval.
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
6. Apply the Phase 1 through 5 migrations:

   ```powershell
   php artisan migrate
   ```

7. Seed fixed system roles, then create the first administrator through a secure interactive prompt. This command never creates a default password:

   ```powershell
   php artisan db:seed
   php artisan identity:bootstrap-admin
   ```

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
- Fee schedules, permit thresholds, official reports, subjects, sections, and live SMS content are not yet supplied.
- The assumed grade offering (Nursery through Grade 10) needs confirmation.
- The future hosting, backup, recovery, and production MySQL environment are not selected.
- The source logo is a JPEG in the project root and needs non-destructive web optimization during a later UI refinement task.

## License

No project-specific license has been selected. The Laravel framework is licensed separately under the MIT License. Do not assume JCA application code or school assets are open source.
