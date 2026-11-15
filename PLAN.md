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

**Status:** Complete on 2026-07-11. On 2026-07-23, JCA confirmed and populated the grade progression from Nursery through Grade 10; official school years, subjects, sections, advisers, and teaching assignments remain pending. On 2026-08-21, JCA confirmed it does not offer Senior High School, and Grades 11-12 were removed from the catalog.

**Goal:** create school-year academic structure and teacher scopes before enrollment uses them.

**Inputs**

- `docs/REQUIREMENTS.md` sections 3.6 and 7
- `docs/DECISIONS.md` D-010 through D-012, P-003
- `docs/DATABASE.md` section 6
- `docs/UI_GUIDE.md` Admin/Registrar/Teacher patterns

**Prerequisite input**

- Supply the initial school year, subjects, official section names, advisers, and teaching assignments. Grade offerings were confirmed on 2026-07-23. Development fixtures may be used if clearly non-production.

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

**2026-10-05 maintenance:** Owner-confirmed baseline requirements now populate every new academic year through the school-year save workflow. The idempotent backfill repairs older years such as 2025-2026 without overwriting year-specific rules or historical checklists. Local 2025-2026 and 2026-2027 both have seven rules (regular 2, transferee 3, support review 2). MySQL-backed focused tests passed 10 tests / 84 assertions and the full suite passed 231 tests / 1,872 assertions; Pint and the production asset build passed. This does not start Phase 16 promotion policy work.

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
- Requirement rule catalog/snapshot/checklist, D-025 versioned private soft-copy evidence, audited hard-copy receipt/replacement tracking, independent review states, and authorized waiver.
- Restricted support case, observations, assessment decision, approved teacher-safe summary, and access audit.
- Guardian enrollment progress and Registrar review queues.
- Promotion is excluded until Phase 5, but schema retains predecessor links.

**Excluded**

- Section confirmation, fee assessment, payments, live SMS, and reports beyond operational lists.

**Acceptance criteria**

- Invalid transitions are impossible through UI and direct requests.
- Approval requires each blocking item to have a verified soft copy, a recorded hard-copy receipt, or an authorized waiver; soft-copy approval preserves the outstanding hard-copy obligation.
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
- Audited carryover of accepted hard-copy requirement evidence into the linked promotion enrollment.
- Portal display of confirmed section and schedule.

**Excluded**

- Grades/attendance and automatic final promotion without Registrar review.

**Acceptance criteria**

- Recommendation never exceeds capacity.
- Concurrent confirmations cannot overfill a section without an explicit Admin override.
- Support-review students cannot be confirmed without suitability approval.
- Transfers preserve prior assignment.
- Promotion creates a new draft and does not mutate historical enrollment.
- Promotion retains accepted prior hard-copy evidence with explicit source provenance and does not carry rejected or waived evidence as accepted proof.

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

**Status:** Original-baseline engineering implementation complete on 2026-07-13. Technical migration, backup/restore, private-storage, queue/scheduler, security, regression, build, and responsive checks passed for that baseline. A new production/capstone release must also complete Phases 10-17 and close P-001 through P-011.

**Inputs**

- All documentation, with special focus on pending decisions in `docs/DECISIONS.md`
- Actual hosting and operational runbooks

**Prerequisite inputs**

- Close P-001 through P-011 or formally defer a non-launch-blocking item with owner sign-off.

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

---

## Phase 10 - Post-baseline change-program design lock

**Status:** Complete as documentation on 2026-10-03. This is the owner's requested change-program Phase 0; no application feature, schema change, or database reset was performed.

**Goal:** reconcile the new walk-in enrollment, unified login, editable student information, curriculum automation, four-quarter grading, and grade-based promotion direction before implementation.

**Inputs**

- `docs/POST_BASELINE_CHANGE_SPEC.md`
- `docs/DECISIONS.md` D-028 through D-034 and P-003, P-009 through P-011
- `docs/REQUIREMENTS.md` People, Enrollment, Academics, and Grading requirements
- Owner-supplied `class-program-26-27.pdf` and School Calendar 2026-2027 image

**Scope**

- Record the four-quarter arithmetic and incomplete-grade behavior.
- Define the Registrar student-correction allowlist and protected fields.
- Explain and recommend natural-person account ownership.
- Establish the Grading module boundary and promotion dependency.
- Inventory the supplied calendar/class-program evidence and its gaps.
- Bound the development/capstone database reset authorization.
- Sequence the post-baseline implementation phases.

**Excluded**

- Migrations, database reset, seed import, routes, controllers, models, views, grading calculations, authentication changes, and deployment packages.

**Acceptance criteria**

- Confirmed requirements, delegated engineering decisions, recommendations awaiting approval, and missing school inputs are distinguishable.
- The four-quarter formula is recorded without inventing passing, rounding, or preschool policy.
- Account ownership is defined and the recommended guardian/student split is visible as pending owner confirmation.
- Supplied references are not misrepresented as a complete curriculum import.
- Current implementation remains accurately distinguished from the planned replacement.

**Review**

- Inspect all 23 class-program pages and the complete supplied calendar image.
- Markdown/terminology and cross-reference review.
- Confirm no application code, migration, environment file, or database data changed in this phase.

---

## Phase 11 - Identifier, structured-name, and correction foundation

**Status:** Complete on 2026-10-03. Verified with 77 focused tests / 549 assertions, a true second-connection MySQL namespace-lock test, MySQL fresh migration and Phase 11 rollback/reapply, route inspection, scoped Pint checks, frontend production build, and Git whitespace checks.

**Goal:** establish the People/Identity data foundation needed by unified login and walk-in enrollment.

**Inputs**

- `docs/REQUIREMENTS.md` PPL-001 through PPL-006
- `docs/DECISIONS.md` D-029, D-033, D-036
- `docs/DATABASE.md` People and correction records
- `docs/UI_GUIDE.md` form, validation, accessibility, and Registrar workspace guidance

**Scope**

- Student IDs in `JCA-YY-NNN` form, where `YY` is the learner's entry school-year start and `NNN` is a collision-safe random value from 100 through 999.
- Staff IDs in `YY-JCA-NNN` form, where `YY` is the employment-start year and `NNN` is a collision-safe random value from 100 through 999.
- Structured father, mother/maiden, and guardian suffix fields.
- Bounded Registrar correction for student demographics and parent names with field-level before/after history and a meaningful reason.
- Separate format- and uniqueness-checked Student ID/LRN correction.
- Enrollment-owned previous-school correction history.
- Fresh MySQL rebuild support through deterministic migrations/seeds under D-033.

**Excluded:** Unified login, walk-in account issuance, curriculum, grades, and promotion rules.

**Acceptance criteria**

- Concurrent issuance is serialized per identifier type/year and database uniqueness remains the final guard.
- Existing identifiers are idempotently retained; only the dedicated correction action may replace a Student ID/LRN.
- Staff account creation requires an employment-start date and issues the Staff ID in the same transaction.
- Parent names are captured as components rather than new free-text full names.
- Generic profile correction cannot change religion, identifiers, guardian authority/contact, or records owned by other modules.
- Every changed field preserves actor, time, before value, after value, and reason.

**Required tests**

- Student and Staff format, entry/employment year, idempotency, collision retry, uniqueness, and Student-ID login regression tests.
- Structured enrollment-name validation and persistence tests.
- Registrar correction success/validation tests plus unauthenticated, Teacher, cross-role, protected-field, and identifier-duplicate denial tests.
- MySQL fresh migration, rollback/reapply, route inspection, focused regression suite, Pint, and frontend production build.

---

## Phase 12 - Unified login

**Status:** Complete on 2026-10-03 and amended by owner direction to school-issued identifiers only. Verified with 54 focused MySQL tests / 332 assertions, route inspection, scoped Pint, a production frontend build, Git whitespace checks, and browser QA of the landing and responsive unified-login pages. P-011 remains open only for final guardian account-ownership/issuance details in Phase 13.

**Goal:** replace separate portal/staff sign-in pages with one neutral authentication surface while preserving role and record authorization.

**Inputs**

- `docs/REQUIREMENTS.md` IAM-001 through IAM-011 and DR-014
- `docs/DECISIONS.md` D-005, D-016, D-027, D-031, D-034, D-036, and P-011
- `docs/ARCHITECTURE.md` Identity boundaries and security architecture
- `docs/UI_GUIDE.md` login and invitation guidance

**Scope**

- One `/login` page and one `POST /login` authentication endpoint for Guardian, Student, Admin, Registrar, Accounting, and Teacher accounts.
- Student ID and Staff ID resolution using the Phase 11 formats; email addresses and mobile numbers remain contact data and are not login identifiers.
- A verified guardian with enrollment-management authority may use a linked learner's Student ID while the guardian's private password still authenticates the guardian-owned account and audit actor.
- Neutral validation/authentication failures, identifier-and-IP rate limiting, session regeneration, forced-password handling, login/logout audit, and role-aware dashboard content after authentication.
- Redirect the retired staff-page URL to the unified login and remove the former staff authentication endpoint.
- Reuse the approved two-column Parent/Student login presentation for the unified audience.
- Replace the landing page's separate application/portal/staff choices with one sign-in action and walk-in guidance; Phase 13 still owns removal of the underlying public-start workflow.

**Excluded**

- Registrar-issued enrollment access, final guardian account-owner/issuance handling, removal of the underlying public enrollment routes, curriculum, grading, and promotion rules.

**Acceptance criteria**

- Every supported role can authenticate through the same endpoint without selecting or revealing a role first.
- Student IDs and Staff IDs resolve only to active accounts with a valid password; email/mobile attempts fail neutrally.
- A Student ID may resolve a learner and eligible linked guardians, but exactly one account password must match or authentication fails closed.
- The former staff page redirects to `/login`, and no second login POST endpoint remains.
- Invalid, inactive, locked, unknown, and wrong-password attempts retain neutral responses and rate limiting.
- Successful authentication regenerates the session, records the login, honors forced password change, and reaches role-scoped dashboard content without weakening downstream authorization.
- The landing page presents one primary sign-in choice and no public-application or separate-role sign-in button.

**Required tests**

- Unified page/route assertions and successful login/logout for all six roles.
- Student ID, Staff ID, linked-guardian, ambiguous-password, email/mobile rejection, inactive/locked, forced-password, neutral-failure, rate-limit, session-regeneration, and audit regression tests.
- Route inspection proving one login POST endpoint and the legacy staff-page redirect.
- Relevant authorization regression tests, Pint, production frontend build, and Git whitespace checks.

---

## Phase 13 - Walk-in enrollment and Registrar-issued access

**Status:** Complete on 2026-10-04. P-011 is closed by the owner's confirmed guardian-owned Nursery-Grade 6 and Student-owned Grade 7-10 handoff. Public application start, resume tokens, and enrollment contact/SMS verification routes are retired; Registrar-issued expiring access now owns every new draft.

**Goal:** replace unrestricted public application start with offline Registrar verification, one-time access, and authenticated application completion.

**Scope:** Duplicate review before issuance; guardian/student account ownership; expiring one-time setup; forced password establishment; authenticated draft ownership; retirement of public start/resume/contact-verification paths; authorization and audit coverage.

**Excluded:** Curriculum, grading, and promotion thresholds.

**Delivered:** Registrar duplicate review before creation; transactionally coordinated Identity/People/Enrollment records; immediate Student ID issuance; three-day hashed temporary passwords; forced private-password establishment; one-time printable credential display; safe pre-use reissue; authenticated application completion/submission; natural-person ownership; audited issuance/reissue; and cross-role/cross-family denial.

**Verification:** The final MySQL-backed full suite passed with 210 tests / 1,687 assertions. Focused walk-in and identity coverage passed with 38 tests / 328 assertions, while enrollment and student-progress coverage passed with 20 tests / 133 assertions. Fresh migration/seeding, Phase 13 rollback/reapply, route inspection, scoped formatting, production asset build, Git whitespace checks, and desktop/mobile browser checks also passed.

---

## Phase 14 - Versioned curriculum automation

**Status:** Implemented on 2026-10-04. The owner-supplied 35-page class program is loaded as a reviewable draft for 2026-2027; P-003 remains open for timetable and real teacher confirmation before official publication.

**Goal:** apply approved curriculum versions to grade/section subject offerings without rewriting historical school years.

**Inputs:** `docs/REQUIREMENTS.md` ACA-001, ACA-008 through ACA-010; `docs/DECISIONS.md` D-012, D-037, and P-003; `docs/DATABASE.md` academic catalog; `docs/UI_GUIDE.md` Admin/Registrar patterns; all pages of the owner-supplied 35-page official class program and interview notes.

**Scope:** Curriculum versions, grade-to-subject mappings, effective school year, reviewed section-subject application, schedule data, and explicit teaching assignments. The later 35-page 2026-2027 class program covers every offered grade and is imported as a draft pending timetable and staff review.

**Excluded:** Quarter-grade entry and grade-based promotion.

**Acceptance criteria:** Admin can draft, review, and publish a year-specific curriculum; publishing creates its grade/section subject set without manual row-by-row entry or changing another year's facts. Published definitions cannot be edited. A later year can copy a published version, retaining stable section names and subjects. Registrar may review but not publish. Teacher assignments are explicit, and recognized weekday schedule overlaps are rejected. Preschool activity programs do not imply numeric graded subjects.

**Required tests:** successful publication and copy-forward; validation and forbidden/unauthenticated access; MySQL uniqueness/foreign keys and transaction behavior; history immutability; schedule conflict and correction; complete official-draft import count; production asset build and responsive UI review.

**Delivered:** Versioned curriculum drafts and published snapshots; grade-subject mappings; recurring section-name templates; editable subject-time notes and preschool activity programs; explicit Admin publication that fills sections and subjects in the effective year; copy-forward to a future year; overlap checks for teacher assignments; and an Admin/Registrar grade-organized curriculum screen. The class-program draft contains 73 grade-subject mappings and 20 sections. No teacher account or assignment is inferred from the PDF.

**Inputs reviewed:** Every page of the 35-page `class-program-26-27 official.pdf`, plus the owner's interview notes and PSU screen as functional UI references. See `docs/ACADEMICS_2026_CLASS_PROGRAM.md` for the transcription and review points.

**Verification:** The MySQL-backed full suite passed with 216 tests / 1,738 assertions; focused Phase 14 coverage passed with 6 tests / 51 assertions. Incremental migration and official draft import, route and server-rendered view checks, scoped formatting, and production asset build passed. Authenticated responsive browser review remains open because available staff accounts require a first-login password change.

---

## Phase 14.1 - Year-scoped Academics workspace

**Status:** Implemented on 2026-10-06 as an owner-requested slice of Phase 14 (no Phase 16 work started).

**Goal:** stop showing every school year's catalog and sections at once, and keep full Admin control after the default class program is loaded.

**Scope:** Class schedules / Curriculum / Setup tabs; school-year, grade-level, and section filters defaulting to the current year; registrar-style subject rows with days, from/to times, and teachers; structured class times; Admin-only archive/restore and delete-when-unused for school years, grade levels, subjects, sections, section subjects, and teaching assignments; adding missing curriculum subjects to a section; extra sections inheriting curriculum subjects; teacher replacement that keeps history. See D-042.

**Excluded:** Rooms, printable class programs, teacher timetable view, grade-based promotion.

**Verification:** MySQL-backed `php artisan test` passed (see README). `npm run build`, `php artisan view:cache`, and Pint passed. Pages were checked in Chromium at 1366 px and 390 px with no horizontal page scroll.

---

## Phase 15 - Four-quarter grading

**Status:** Implemented for JCA-approved numeric Grades 1-10 on 2026-10-04. Promotion criteria and Nursery/Kindergarten assessment remain pending under P-010.

**Goal:** let assigned teachers maintain four quarter grades and produce an auditable server-calculated final subject grade.

**Scope:** Grading periods, teacher assignment scope, four quarter inputs, incomplete results, formula calculation, draft/submitted/finalized transitions, correction/reopen history, authorized portal/report visibility, and MySQL integrity tests.

**Excluded:** Attendance, LMS behavior, online examinations, and promotion implementation.

**Verification:** MySQL-backed `php artisan test` passed with 228 tests / 1,850 assertions, including grading page rendering, locked-account denial, and finalized-only report coverage. The Phase 15 migration ran on the local MySQL database, `php artisan view:cache` compiled the Blade views, `npm run build` passed, and Pint passed on Phase 15 PHP files. A read-only browser check confirmed unauthenticated teacher-route redirection to unified login. Authenticated visual review remains to be performed with a school-authorized test account.

---

## Phase 16 - Final-grade-based promotion

**Status:** Eligibility slice implemented on 2026-10-06 (D-043): promotion requires a Registrar-approved final grade in every current subject, the Registrar approves final grades (individually or a whole class at once) on the Grade approval page, the promotion review lists each subject's final grade and state, and draft creation re-checks grades. A passing mark and override-with-reason remain open under P-010. Verified with the full MySQL suite (266 tests), production build, Pint, and a Chromium check of the promotion review and Grade approval pages.

**Goal:** base promotion recommendations on complete finalized academic results while preserving the prior school year.

**Scope:** Eligibility evaluation, incomplete/failed/manual-review outcomes, authorized override/reason, creation of a new enrollment, and historical grade/enrollment immutability.

**Excluded:** Automatic promotion without staff confirmation and mutation of prior enrollment or grades.

---

## Phase 17 - Integrated regression and Hostinger capstone release

**Status:** Planned; depends on Phases 11-16.

**Goal:** prove and package the integrated change program for the fictional Hostinger capstone environment.

**Scope:** Full MySQL regression and authorization matrix, clean migration/seed rehearsal, backup/restore verification, production asset build, responsive/security checks, versioned release archive, checksums/manifest, exact PowerShell SSH and server Bash deployment commands, smoke checks, and rollback instructions.

**Excluded:** Representing the capstone deployment as an approved live production system or importing real student data before all launch blockers are closed.

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
