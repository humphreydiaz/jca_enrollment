# JCA Implementation Plan

## How to use this plan

Implement one bounded phase per Codex task. Every task starts by reading `AGENTS.md`, this phase only, and the documents listed under **Inputs**. Do not pull later-phase requirements into the current phase unless a prerequisite must be corrected.

A phase is complete only when its acceptance criteria and test requirements pass, documentation reflects the delivered contract, and no unresolved blocker for that phase remains.

## Global delivery rules

- Follow Laravel MVC and the modular-monolith boundaries in `docs/ARCHITECTURE.md`.
- Use Blade, Tailwind CSS, Alpine.js, MySQL, and Vite.
- Use database-backed cache, sessions, and queue; do not use Redis.
- Do not add React, Vue, Inertia, Livewire, Tabler, Bootstrap, PostgreSQL, Docker, microservices, paid packages, or a payment gateway.
- Semaphore is the only paid external integration and stays disabled/faked until live configuration is approved.
- Add no feature without authorization tests and no schema without MySQL constraint/integrity tests.
- Preserve application, enrollment, assignment, financial, and audit history.

## Phase 0 - Discovery and design baseline

**Status:** Complete as documentation; no application features implemented.

**Scope**

- Inspect Laravel/PHP/frontend baseline, repository structure, outline, and logo.
- Resolve material defaults for accounts, roles, enrollment, payment, discounts, sections, promotion, and retention posture.
- Produce README, plan, agent guidance, requirements, decisions, architecture, database design, UI guide, and environment example.

**Acceptance criteria**

- Required documentation files exist and cross-reference one another.
- Confirmed requirements, selected decisions, assumptions, pending inputs, and out-of-scope features are separately identifiable.
- No JCA migration, model, controller, route, or application feature was added.

**Tests/review**

- Markdown/link and terminology review.
- Confirm banned technologies are absent from active dependencies.
- Confirm `.env.example` contains placeholders only.

---

## Phase 1 - Engineering foundation and UI shell

**Status:** Complete on 2026-07-10.

**Goal:** establish the safe Laravel/MySQL/module foundation without implementing enrollment workflows.

**Inputs**

- `docs/ARCHITECTURE.md` sections 2-5, 7-10, 12-15
- `docs/DATABASE.md` sections 1-4 and 14-16
- `docs/UI_GUIDE.md` sections 1-6, 8-12
- `docs/DECISIONS.md` D-001, D-005, D-013, D-016, D-017

**Scope**

- Initialize version control if authorized and establish contribution/check conventions.
- Configure MySQL, application timezone, database cache/session/queue, private document disk, and safe logging.
- Install Alpine.js as a direct development dependency; configure it in the Vite entry point.
- Establish module directories/providers/routes without business behavior.
- Build the accessible authenticated/public Blade layout, design tokens, and reusable base components.
- Create/rework only infrastructure migrations needed for identity, sessions, jobs, cache, references, and audit foundation.
- Establish factories/test helpers and MySQL test configuration.

**Excluded**

- Login flows, people records, enrollment, fees, sections, SMS, and reports.

**Acceptance criteria**

- Application boots against MySQL with no SQLite dependency in documented setup.
- Database queue, sessions, and cache work; no Redis connection is needed.
- Alpine.js is loaded once and basic progressive components function.
- Base Blade pages meet responsive/focus/contrast review.
- Module boundaries and route registration are documented and exercised by a smoke route without feature data.
- No secrets or school records appear in logs or committed configuration.

**Required tests**

- Fresh MySQL migrate and rollback.
- Framework boot/health feature test.
- Session, cache, and queued test-job integration test.
- Base layout/component rendering tests.
- Frontend production build.
- Dependency/banned-technology audit.

---

## Phase 2 - Identity, roles, people, and invitations

**Status:** Complete on 2026-07-10.

**Goal:** deliver secure account onboarding and relationship-scoped access primitives.

**Inputs**

- `docs/REQUIREMENTS.md` sections 3.2, 6.1
- `docs/DECISIONS.md` D-002 through D-006, D-016, D-017, P-008
- `docs/DATABASE.md` sections 4-5
- `docs/ARCHITECTURE.md` sections 6.1, 7-8
- `docs/UI_GUIDE.md` login, guardian, teacher, and accessibility guidance

**Prerequisite input**

- Decide whether email is an official recovery channel or recovery remains staff-assisted.

**Scope**

- Users, roles, role assignment history, student/guardian/staff profiles, guardian/student links.
- Login/logout, invitation acceptance, forced password setup/change, recovery chosen by prerequisite policy, lock/disable/session revocation.
- Admin user/role management and relationship verification.
- Policy/query scopes for Admin, Registrar, Accounting, Teacher, Guardian, and Student foundations.
- Audit role, invitation, authentication-security, and relationship changes.

**Excluded**

- Public enrollment form beyond a test-only invitation fixture; academic enrollment and portals.

**Acceptance criteria**

- No open public account registration exists.
- Invitations are hashed, expiring, single-use, and replay-safe.
- Guardian many-to-many relationships and visibility flags work.
- Staff may hold multiple staff roles; staff/portal role combinations are rejected.
- Teacher, guardian, student, and accounting scopes default to deny without an explicit relationship/assignment.
- Account/role changes revoke or re-evaluate active access.

**Required tests**

- Unit tests for role-combination and account-state rules.
- Feature tests for login, logout, invitation, expiry, replay, forced password setup, recovery, lock/disable, session revocation.
- Policy matrix tests for every actor and cross-family/cross-role denial.
- MySQL uniqueness/concurrency tests for normalized contacts and active role assignments.
- Rate-limit and account-enumeration tests.

---

## Phase 3 - Academic catalog, sections, and teaching assignments

**Status:** Complete on 2026-07-11. The catalog is intentionally empty until JCA supplies its official school-year data; no production fixtures were seeded.

**Goal:** create school-year academic structure and teacher scopes before enrollment uses them.

**Inputs**

- `docs/REQUIREMENTS.md` sections 3.6 and 7
- `docs/DECISIONS.md` D-010 through D-012, P-003
- `docs/DATABASE.md` section 6
- `docs/UI_GUIDE.md` Admin/Registrar/Teacher patterns

**Prerequisite input**

- Confirm grade offerings and supply the initial school year, subjects, official section names, advisers, and teaching assignments. Development fixtures may be used if clearly non-production.

**Scope**

- CRUD/inactivation for school years, grade levels, subjects, sections, section subjects/schedules, and teaching assignments.
- Validation preventing destructive changes to historically referenced catalog entries.
- Teacher assignment-based query scopes.
- Capacity configuration defaulted to 15.

**Excluded**

- Student assignment to sections, enrollment, grades, attendance, and LMS features.

**Acceptance criteria**

- Catalog is data-driven and preserves referenced historical values.
- Section names are unique within school year and grade.
- Only authorized Admin/Registrar users may maintain catalog; teacher access is read-only and assignment scoped.
- Inactive data is unavailable for new choices but remains visible in history.

**Required tests**

- CRUD/validation/authorization feature tests.
- MySQL foreign-key and uniqueness tests.
- Inactivation/history preservation tests.
- Teacher scope tests.
- Responsive catalog UI checks and frontend build.

---

## Phase 4 - Enrollment intake, requirements, and restricted support review

**Status:** Complete on 2026-07-11. Verified with 38 tests / 267 assertions, a dedicated-MySQL fresh migration, complete rollback and reapplication, production asset build, Pint, Composer validation, route listing, and diff checks. P-001 and P-004 remain production-policy inputs; generic private retention and restricted neutral support review are complete without automated deletion or invented school assessment content.

**Goal:** deliver enrollment from public draft through academic approval/rejection, excluding final section and billing workflows.

**Inputs**

- `docs/REQUIREMENTS.md` sections 3.3-3.5 and 5
- `docs/DECISIONS.md` D-002, D-007, D-013, D-014, D-016, P-001, P-004
- `docs/DATABASE.md` sections 7-9
- `docs/ARCHITECTURE.md` sections 6.1-6.2, 7-8
- `docs/UI_GUIDE.md` enrollment, documents, Registrar, Guardian, and privacy patterns

**Prerequisite input**

- Approve support-review form/outcomes and reviewers. A retention schedule may remain pending, but production deletion automation must remain disabled.

**Scope**

- Minimal unauthenticated application start with contact verification and abuse controls.
- Student/guardian deduplication review and invitation connection.
- Multi-step enrollment form, save/resume, submission, state machine, history, decisions.
- Requirement rule catalog/snapshot/checklist, private uploads, replacement, verification, rejection, authorized waiver.
- Restricted support case, observations, assessment decision, approved teacher-safe summary, and access audit.
- Guardian enrollment progress and Registrar review queues.
- Promotion is excluded until Phase 5, but schema retains predecessor links.

**Excluded**

- Section confirmation, fee assessment, payments, live SMS, and reports beyond operational lists.

**Acceptance criteria**

- Invalid transitions are impossible through UI and direct requests.
- Approval requires all blocking requirements resolved and support approval when required.
- Raw files are not publicly addressable and all access is authorized/logged.
- General/Accounting/Teacher views cannot retrieve raw support data.
- Duplicate active school-year enrollment is transactionally prevented.
- Academic approval does not require payment state.

**Required tests**

- Unit transition/checklist rules.
- End-to-end feature tests for regular, transferee, incomplete, rejected, and support-review paths.
- File validation, private download, replacement, waiver, and cross-user denial tests.
- Support access matrix and sensitive-access audit tests.
- MySQL concurrency/uniqueness tests for duplicate submissions/approvals.
- Rate limiting and unsafe upload tests.
- Responsive enrollment and review UI checks.

---

## Phase 5 - Section assignment, enrollment finalization, and promotion

**Status:** Complete on 2026-07-11. Verified with 47 tests / 376 assertions, dedicated-MySQL fresh migration, complete rollback and reapplication, second-connection row-lock tests for confirmation and transfer, production asset build, Pint, Composer validation, route listing, and diff checks. P-003 remains open for the official catalog import; only fictional test fixtures were used.

**Goal:** place approved students safely and preserve school-year progression.

**Inputs**

- `docs/REQUIREMENTS.md` sections 3.6 and 5
- `docs/DECISIONS.md` D-007, D-010, D-011
- `docs/DATABASE.md` sections 10 and promotion portions of section 7
- `docs/ARCHITECTURE.md` section 6.3

**Scope**

- Section recommendation by approval order and available capacity.
- Registrar/Admin confirmation, support suitability confirmation, transfer, and history.
- Admin-only capacity override with reason.
- Final academic `enrolled` transition independent of finance.
- Promotion recommendation and next-school-year draft creation.
- Portal display of confirmed section and schedule.

**Excluded**

- Grades/attendance and automatic final promotion without Registrar review.

**Acceptance criteria**

- Recommendation never exceeds capacity.
- Concurrent confirmations cannot overfill a section without an explicit Admin override.
- Support-review students cannot be confirmed without suitability approval.
- Transfers preserve prior assignment.
- Promotion creates a new draft and does not mutate historical enrollment.

**Required tests**

- Unit tests for recommendation ordering, capacity, override, suitability, and next-grade logic.
- MySQL concurrent assignment tests.
- Policy tests for Registrar/Admin/Teacher/Guardian views.
- Transfer and promotion history tests.
- Portal/print section rendering tests.
- Assignment-scoped positive Teacher access to the current approved teacher-safe support summary. Phase 4 verifies default Teacher denial because no student-to-section assignment exists before this phase.

---

## Phase 6 - Billing, discounts, payment ledger, and exam permits

**Status:** Complete for development on 2026-07-11 under the owner-authorized assumptions in `docs/PHASE6_IMPLEMENTED_POLICY.md`. Production financial configuration remains blocked pending official JCSGO approval.

**Goal:** deliver auditable offline payment tracking without a payment gateway.

**Inputs**

- `docs/REQUIREMENTS.md` section 3.7 and financial workflow in section 5
- `docs/DECISIONS.md` D-007 through D-009, D-016, P-002
- `docs/DATABASE.md` section 11
- `docs/ARCHITECTURE.md` section 6.4
- `docs/UI_GUIDE.md` Accounting and print guidance

**Prerequisite input**

- Approved fees, schedules, discount campaign, receipt/reference rules, and exam-permit thresholds.

**Scope**

- Fee types/schedules and immutable enrollment assessment snapshots.
- Payment plan/due schedule selection including payment-to-follow.
- Early-enrollee campaign allocation at approved enrollment, concurrency-safe.
- Payment posting/allocation, balances, approved reversals/adjustments.
- Exam periods, calculated permit eligibility, issue/print, Admin override.
- Authorized accounting and guardian financial views.

**Excluded**

- Online payments, bank/card storage, external accounting software, inventory fulfillment.

**Acceptance criteria**

- Historical assessments survive fee-catalog changes.
- First 100 (or configured limit) cannot over-award under concurrency.
- Posted payments cannot be edited/deleted.
- Reversal retains the original, reason, requester, and Admin approval.
- Balance equals assessment minus approved discounts/waivers and net posted allocations.
- Permit logic is reproducible and overrides are visible/audited.

**Required tests**

- Unit money, due, discount, balance, and permit calculations using exact decimals.
- MySQL transaction/concurrency tests for discount ranks, receipt uniqueness, posting, and reversal.
- Full Accounting/Admin/Registrar/Guardian authorization matrix.
- Assessment revision and immutable-history tests.
- Receipt/permit print authorization and content tests.
- Explicit test proving there is no payment-gateway route/integration.

---

## Phase 7 - Portals, announcements, and Semaphore SMS

**Status:** Complete for development on 2026-07-12. Live Semaphore delivery remains disabled until P-005 production inputs are approved.

**Goal:** complete role-appropriate dashboards and reliable school communications.

**Inputs**

- `docs/REQUIREMENTS.md` section 3.8
- `docs/DECISIONS.md` D-015, D-016, P-005
- `docs/DATABASE.md` section 12
- `docs/ARCHITECTURE.md` sections 6.5, 7, 11
- `docs/UI_GUIDE.md` role navigation, dashboards, portals, and accessibility

**Prerequisite input**

- Approved sender name, consent wording, message templates, recipient rules, and live escalation contacts.

**Scope**

- Role-specific dashboards/portals with scoped next actions.
- Announcements and audience targeting.
- Notification template/version management.
- Database-queued Semaphore adapter, disabled/log/fake modes, idempotency, retries, and delivery logs.
- Enrollment/requirements/concern triggers approved by policy.
- Guardian contact consent/preferences and recipient selection.

**Excluded**

- Chat, email marketing, WebSockets, mobile push, and mass export of contact lists.

**Acceptance criteria**

- Portal users see only linked/self records.
- Staff dashboards respect module scopes.
- No live SMS can be sent when disabled or without approved configuration.
- Retried jobs do not duplicate already accepted sends.
- Message bodies avoid sensitive support/document/payment detail.
- Failed delivery is visible and safely retryable by authorized staff.

**Required tests**

- Portal authorization and cross-family denial tests.
- Announcement audience tests.
- Semaphore HTTP fake contract tests; no real network calls in the automated suite.
- Queue retry/idempotency/failure tests.
- Consent and recipient-resolution tests.
- Template escaping and sensitive-content tests.
- Responsive/accessibility smoke tests for each role dashboard.

---

## Phase 8 - Reports, exports, print outputs, and audit review

**Goal:** deliver authorized operational reporting without creating a data-exfiltration path.

**Status:** Complete for development on 2026-07-13. The implemented catalog is a restrictive operational baseline; official school forms, signatories, and control/reference requirements remain pending under P-006.

**Inputs**

- `docs/REQUIREMENTS.md` report requirements and NFRs
- `docs/DECISIONS.md` D-016, P-006
- `docs/DATABASE.md` section 13
- `docs/UI_GUIDE.md` tables, filters, and print guidance

**Prerequisite input**

- Approved report list, columns, filters, formats, signatories, and control/reference requirements.

**Scope**

- Search/filter lists and approved reports for enrollment, requirements, sections, finance, and SMS.
- Private asynchronous exports where needed, expiry/deletion, re-authorization on download.
- Printable official layouts and generation audit.
- Audit viewer restricted by role and sensitivity.

**Excluded**

- General-purpose database query/report builders and public download links.

**Acceptance criteria**

- Each report has an explicit permission, field allowlist, and scope.
- Filters and totals match source records and school-year boundaries.
- Exports are private, expire, and cannot be downloaded after permission loss.
- Sensitive support data is absent unless a separately approved restricted report requires it.
- Printouts are legible, branded, and do not contain navigation or accidental fields.

**Required tests**

- Report query/filter/total tests using representative fixtures.
- Authorization matrix and crafted-filter bypass tests.
- Export lifecycle, expiry, deletion, and re-authorization tests.
- Spreadsheet formula-injection protection when CSV is supported.
- Print snapshot/manual visual QA for every official layout.

---

## Phase 9 - Security hardening, data readiness, and production launch

**Goal:** prove the complete system is safe, recoverable, supportable, and based on approved school policy.

**Status:** Engineering implementation complete on 2026-07-13. Technical migration, backup/restore, private-storage, queue/scheduler, security, regression, build, and responsive checks pass. Production launch remains blocked until JCA closes P-001 through P-008 and records the required role-owner/UAT, recovery-objective, and policy approvals.

**Inputs**

- All documentation, with special focus on pending decisions in `docs/DECISIONS.md`
- Actual hosting and operational runbooks

**Prerequisite inputs**

- Close P-001 through P-008 or formally defer a non-launch-blocking item with owner sign-off.

**Scope**

- Approve and configure retention/holds/deletion workflow.
- Production environment hardening, HTTPS/cookies, least-privilege credentials, queue/scheduler supervision.
- Backups, encrypted/offsite posture as approved, restore drill, recovery objectives.
- Data-import rehearsal, reconciliation, and rollback plan.
- Security/privacy/accessibility/performance review.
- User acceptance testing, role training, support/escalation, launch and rollback runbooks.

**Acceptance criteria**

- No default credentials, development data, debug mode, or live secrets in source.
- Retention policy, backup/restore, incident response, and account recovery are approved.
- Restore drill meets the agreed recovery objective.
- Role owners sign off access and workflow behavior.
- Critical/high security findings are resolved; other risks have owners and dates.
- Production SMS, exports, and document access have monitoring and incident procedures.

**Required tests**

- Full automated suite on production-like MySQL configuration.
- Fresh deployment and rollback rehearsal.
- Backup and restore verification including private documents.
- Authorization regression matrix across all roles/modules.
- OWASP-oriented manual review for authentication, access control, uploads, injection, CSRF, rate limits, session security, and data exposure.
- Accessibility and responsive journey review.
- Load checks for enrollment submission, list filtering, report generation, queue processing, and concurrent section/discount/payment operations.
- User acceptance test scripts signed by designated JCA users.

## Phase handoff template

Every implementation task should end with:

- phase and bounded scope delivered;
- files created/modified;
- migrations and rollback behavior;
- acceptance criteria result;
- tests run and exact result;
- manual checks performed;
- pending decisions or risks discovered;
- documentation updated;
- recommended next phase, without beginning it.
