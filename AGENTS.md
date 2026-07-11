# AGENTS.md - JCA Project Instructions

These instructions apply to the entire repository.

## Project state

Phases 1 through 5 are implemented. The system now includes the MySQL-first foundation, identity/people, academic catalog, enrollment intake/review, private requirements, generic restricted support review, capacity-safe section recommendation/confirmation/transfer, academic `enrolled` finalization, promotion recommendations/drafts, portal-safe section schedules, and assignment-scoped Teacher summaries. Billing, discounts, payments, permits, live SMS, reporting, grades, and attendance remain unimplemented. The official JCA school-year catalog has not been imported; P-001, P-003, and P-004 remain production-policy inputs.

## Source of truth

Use this priority order:

1. Current user instruction for the active task.
2. This `AGENTS.md`.
3. Accepted decisions in `docs/DECISIONS.md`.
4. Confirmed/decided requirements in `docs/REQUIREMENTS.md`.
5. The active bounded phase in `PLAN.md`.
6. `docs/ARCHITECTURE.md`, `docs/DATABASE.md`, and `docs/UI_GUIDE.md`.
7. `JCA-OUTLINE.pdf` as historical source material.

If sources conflict, stop only when the conflict materially changes data, security, money, or workflow. Otherwise follow the higher-priority source and document the reconciliation.

## Minimal context loading

For a phase implementation task:

1. Read this file completely.
2. Read the selected phase in `PLAN.md`, including its Inputs, exclusions, acceptance criteria, and tests.
3. Read only the input sections named by that phase.
4. Inspect the current files/tests directly affected by the task.
5. Do not load old conversation history or unrelated phase documents unless a discovered dependency requires it.

Implement one phase per task unless the user explicitly authorizes a smaller slice. Never start the next phase as cleanup or a convenience.

## Mandatory technology constraints

- Laravel MVC, Blade, Tailwind CSS, Alpine.js, MySQL, and Vite.
- Modular monolith in one Laravel application and one primary MySQL database.
- Database-backed queue, cache, and sessions.
- PHPUnit/Laravel testing tools already approved by the project.
- Semaphore is the only paid external integration.

Never add React, Vue, Inertia, Livewire, Tabler, Bootstrap, PostgreSQL, Redis, Docker, microservices, a payment gateway, or a paid package.

Before adding any dependency, explain why framework/first-party code is insufficient, verify license/maintenance/security, and confirm it does not violate the constraints. Prefer no new dependency.

## Architecture rules

- Use thin controllers: authorize, validate with a Form Request, call one application Action, return a response.
- Put business transitions and transactions in explicit Actions/services, not controllers, Blade, Alpine, global helpers, or opaque model observers.
- A module writes only its owned tables. Cross-module behavior uses an orchestrating Action and after-commit events/jobs.
- Eloquent models represent persistence; do not turn them into unbounded service containers.
- Use policies plus scoped queries for every read, list, export, document, and mutation.
- Client-side visibility is never authorization.
- Keep Alpine local/progressive; canonical state and calculations stay server-side.
- Do not build a generic repository/service abstraction around Eloquent without a concrete repeated need.

## Module ownership

Follow the module map in `docs/ARCHITECTURE.md`:

- Identity & Access
- People
- Academics
- Enrollment
- Requirements & Documents
- Student Support
- Section Assignment
- Billing
- Communications
- Reporting
- Shared/Audit

If a task needs a new module or changes ownership, update the architecture decision before implementation.

## Data rules

- Target MySQL; do not use SQLite as proof of MySQL locking/constraint behavior.
- Use Laravel migrations for every schema change once the phase permits it.
- Include foreign keys, uniqueness, useful indexes, and deliberate delete behavior.
- Use `DECIMAL` for money, never float/double.
- Preserve historical enrollment, section, assessment, discount, payment, permit, and audit records.
- Posted payments are immutable; corrections are approved reversal/adjustment entries.
- Academic enrollment and financial status are separate.
- Promotion creates a new enrollment; never rewrite a prior school year.
- Store private files outside the public root and store opaque paths/metadata in MySQL.
- Avoid JSON for core relational facts; use it only for safe snapshots/metadata that do not need relational integrity.
- Business policies such as fees, capacities, discount limits, and grade offerings belong in data/configuration records, not `.env` or scattered constants.

## Security and privacy

This system contains children's records and highly sensitive support documents. Default to deny.

- Never expose sequential internal IDs in public sensitive URLs when enumeration is possible; use public opaque references.
- Hash passwords and invitation/recovery tokens; never send permanent plaintext passwords.
- Use CSRF protection, session regeneration, rate limits, secure validation, and production-safe cookies.
- Authorize every download, preview, search result, count, export, and queued notification.
- Teachers see assigned students and approved support summaries only.
- Accounting sees minimum student/enrollment identity and finance, not raw support records.
- Guardians see only students linked by an active authorized relationship.
- Do not log secrets, tokens, document contents, unnecessary personal data, or raw provider responses.
- Audit sensitive reads and all material state changes listed in D-016.
- Keep live SMS disabled until sender, consent, templates, and credentials are approved.
- Do not invent a production document-retention period. The configurable retention design and launch blocker in D-014 remain in force until JCA approval.

## UI rules

- Follow `docs/UI_GUIDE.md`: light, lively, logo-derived, accessible, responsive.
- Use reusable Blade components for repeated patterns.
- Use semantic HTML, real labels, visible focus, accessible contrast, and status text plus color/icon.
- Preserve submitted form values and show summary plus field errors.
- Critical journeys must remain usable without relying on Alpine for authorization or data integrity.
- Print/export views use explicit field allowlists and omit navigation/unnecessary confidential data.
- Preserve `JCA Logo.jpg`; create optimized derivatives rather than overwriting the source.

## Test requirements

Every change needs tests proportional to risk. At minimum:

- success and validation failure;
- unauthenticated and forbidden access;
- cross-role/cross-family/cross-assignment denial;
- relevant state-transition failures;
- MySQL constraints/transactions for schema or concurrency-sensitive work;
- queued work faked or HTTP-faked; never send real SMS in tests;
- historical/financial immutability where affected;
- frontend production build for asset/UI changes.

Run the exact test matrix from the active PLAN phase. Do not claim tests passed without running them. Report commands and outcomes. If a required environment is unavailable, clearly report the unverified acceptance criterion.

## Change discipline

- Inspect before editing and preserve unrelated user changes.
- Do not modify `.env`; update `.env.example` with placeholders only when configuration changes.
- Do not commit secrets, live personal data, generated exports, uploads, runtime caches, or temporary PDF renders.
- Keep migrations reversible where safe; document irreversible data transformations and backups.
- Do not delete historical data to make tests pass.
- Update relevant docs in the same task when a contract, decision, environment variable, or module boundary changes.
- Add an entry to `docs/DECISIONS.md` when choosing among materially different data/security/workflow options.
- Keep `README.md` current for setup and actual implementation status.

## Pending-decision behavior

Check the active phase prerequisites and `docs/DECISIONS.md` pending table. Ask the user only when a missing decision materially affects that phase's database, authorization, enrollment, payment, parent accounts, section assignment, live communication, retention, or production safety.

If a pending item does not block safe progress, use the documented restrictive/data-driven posture and keep it visibly pending. Never silently turn an assumption into confirmed school policy.

## Definition of done

A task is done only when:

- scope stays inside the selected phase/slice;
- implementation follows module ownership and technology constraints;
- acceptance criteria are demonstrated;
- required automated tests pass or limitations are explicitly reported;
- manual UI/security checks appropriate to the change are performed;
- no secret or unauthorized data is introduced;
- relevant documentation and `.env.example` are current;
- the handoff lists files, tests, remaining risks, and the next recommended phase without starting it.
