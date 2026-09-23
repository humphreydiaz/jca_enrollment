# JCA System Decisions

This file records decisions that future implementation tasks may treat as settled. Change a decision only when JCA explicitly changes the underlying policy; record the replacement here before changing code or schema.

**Decision date:** 2026-07-10  
**Source:** `JCA-OUTLINE.pdf`, project inspection, and owner authorization to select reasonable defaults.

## Status definitions

- **Accepted:** implementation may rely on the decision.
- **Assumed:** safe working default that needs school validation before production.
- **Pending:** implementation must preserve flexibility; the item cannot be treated as policy yet.
- **Superseded:** retained for history and linked to its replacement.

## Accepted decisions

### D-001 - Delivery architecture and technology

**Decision:** Build one Laravel MVC modular monolith using Blade, Tailwind CSS, Alpine.js, MySQL, and Vite. Keep modules in one deployable application and one database while enforcing module boundaries in code.

**Consequences:** Do not introduce React, Vue, Inertia, Livewire, Tabler, Bootstrap, PostgreSQL, Redis, Docker, microservices, or paid packages. Semaphore is the only paid external integration.

### D-002 - Account onboarding

**Decision:** Do not allow unrestricted public account registration. Pre-elementary and Grades 1-6 use a guardian-assisted enrollment application. For Grades 7-12, Admin may invite a Student login before enrollment so the learner can sign in and own the application described in D-023. After either kind of submission is verified as legitimate, the school connects or invites the individual guardian account so the required guardian relationship is retained.

**Consequences:** Applicant ownership, login accounts, and permanent people profiles are separate. A high-school Student login may own an application before it is connected to the permanent learner profile; that profile connection remains post-approval. Invitations expire, are single-use, and must never contain a reusable plaintext password.

### D-003 - Guardian and student relationships

**Decision:** Every person has an individual account; credentials are never shared as a household account. A guardian can be linked to multiple students and a student can be linked to multiple guardians. Each link records relationship type, contact permissions, portal visibility, pickup/legal authority where applicable, and whether the guardian is the primary contact.

### D-004 - Student portal eligibility

**Decision:** Grades 7-12 may receive an Admin-issued Student invitation and submit their own application, while pre-elementary and Grades 1-6 require guardian-assisted enrollment. Permanent Student login-to-profile access is connected only after approval. Pre-elementary and Grades 1-6 use guardian portal access by default; Admin may connect a younger Student login only as a recorded and audited exception.

**Rationale:** This matches the outline's statement that high school students may participate directly while keeping younger-child access with guardians.

### D-005 - Roles and authorization

**Decision:** A staff account may hold multiple staff roles because the school has a small team. Staff roles are Admin, Registrar, Accounting, and Teacher. Guardian and Student are portal identities and cannot be combined with staff roles on the same account. Authorization is enforced with Laravel policies and server-side scope checks, not by hiding navigation.

**Consequences:** Admin does not automatically bypass every confidential-data policy. Highly sensitive support records still require an authorized Admin or Registrar scope, and access is audited.

### D-006 - Teacher and accounting data limits

**Decision:** Teachers may access only current assigned advisory/subject students, ordinary educational information needed for instruction, and an approved support summary. Teachers cannot access raw therapy assessments, uploaded identity/medical-style documents, guardian private notes, or billing ledgers. Accounting sees the minimum identity and enrollment data needed for assessment and collection, but not raw therapy files.

### D-007 - Enrollment and finance are separate state machines

**Decision:** Academic enrollment status and financial status are independent. A student may become officially enrolled with `payment_to_follow`, unpaid, or partially paid status when required documents are complete, any required support assessment is approved, and an authorized staff member finalizes enrollment.

**Authority split:** Registrar verifies identity, requirements, and academic records; Accounting posts and verifies financial transactions; Admin performs final overrides and exceptional decisions. All overrides require a reason and audit entry.

### D-008 - Payment tracking only

**Decision:** The first release does not collect money online and has no payment gateway. It records school-collected payments and maintains fee assessments, fee-item snapshots, installment schedules, discounts, receipts/reference numbers, allocations, balances, and exam-permit eligibility.

**Consequences:** Payment records are append-only after posting. Accounting may create and post entries. A posted payment is corrected by an explicit reversal or adjustment, never silent editing or deletion. Admin approves reversals/voids and permit overrides with a reason. Registrar may view a summary but cannot post financial entries.

### D-009 - Early-enrollee discount

**Decision:** A configurable discount campaign may award a limited number of slots, initially 100, per school year. Rank is determined atomically when an enrollment first reaches `approved`: requirements must be complete and any required support assessment must be approved, but payment is not required. A withdrawn or cancelled award is not automatically recycled unless Admin explicitly reopens the slot with an audit reason.

**Consequences:** Initial submission time alone does not reserve a discount. Campaign limits, amounts, dates, and eligibility remain data, not environment variables or hard-coded constants.

### D-010 - Section assignment

**Decision:** Sections belong to a grade level and school year and have a normal capacity of 15. The system recommends the next available section using enrollment approval order. Registrar or Admin confirms the assignment so staff can maintain balanced groupings. Students with therapy/special support needs always require manual suitability confirmation before assignment.

**Consequences:** Automatic recommendations never exceed capacity. Only Admin may confirm an over-capacity assignment, and must record a reason. Transfers retain assignment history.

### D-011 - Promotion and school-year history

**Decision:** Promotion never mutates a prior enrollment. Closing an eligible enrollment may create a draft enrollment for the next school year and suggested next grade, but Registrar must review and submit/finalize it. Historical grade, section, fees, and status remain immutable records of that school year.

### D-012 - Grade coverage

**Decision:** The offered grade progression is Nursery, Junior Kinder, Senior Kinder, Elementary Grades 1-6, and Junior High Grades 7-10. Grade 10 completes the progression and has no next grade. Store grade levels as configurable records and base authorization on the configured school stage rather than numeric sequence positions.

**Status:** Accepted by the owner on 2026-07-23. Superseded in part on 2026-08-21: JCA confirmed it does not offer and has never offered Senior High School, so Grades 11-12 were removed from the catalog, the seeder, and the Senior High School stage. Official section names, subjects, and teaching assignments remain pending under P-003.

### D-013 - Confidential document storage

**Decision:** Uploaded requirements and any support documents are private files outside the public web root. The database stores metadata and opaque storage keys, not binary documents. Preview/download is always mediated by authorization, logged, and returned with safe content headers. New enrollment-requirement handling is governed by D-025.

### D-014 - Retention and deletion

**Decision:** Retention rules are configurable by document category. Until JCA approves a retention schedule, production deletion automation stays disabled and the absence of an approved schedule is a release blocker. The design must support legal/administrative holds, archival state, approved deletion, and deletion audit evidence.

**Consequences:** Development data may be disposable, but production documents must not be silently retained forever or automatically destroyed under an invented policy.

### D-015 - SMS delivery

**Decision:** Semaphore is the SMS provider. General notifications are sent asynchronously through the database queue. On 2026-09-20, the owner approved using Semaphore's dedicated OTP route for time-sensitive enrollment verification and account activation codes at its two-credit-per-160-character cost. Account invitations use a grouped, unambiguous, high-entropy one-time code instead of embedding a URL in the SMS; recipients enter it from the portal's Activate account page. The hashed, expiring, single-use invitation and authorized staff fallback link remain the source of truth. Store the redacted recipient, purpose, provider reference, provider status, route, message length, timestamps, and a safe failure code. Do not store API keys, raw invitation codes, full SMS bodies, or sensitive document details in audit logs.

**Consequences:** Announcements and routine notifications stay on Semaphore's standard route. The adapter retries one transient network/provider failure and treats provider `Failed` or `Refunded` responses as failures instead of accepted sends. A Semaphore `Queued`, `Pending`, or `Sent` response is described as provider/network acceptance, never proof that the handset received the SMS. A three-minute guard prevents staff from immediately replacing a guardian code that may still be moving through the mobile network. Live delivery remains blocked by P-005 until sender name, consent wording, approved templates, escalation contacts, and credentials are supplied.

### D-016 - Auditability

**Decision:** Security-sensitive reads and all material writes are attributable to an actor. Audit enrollment decisions, requirement verification, support-record access, section overrides/transfers, role changes, invitations, payment posting/reversal, permit overrides, document downloads/deletions, and SMS dispatch.

### D-017 - Time and identifiers

**Decision:** Use database-backed unique public reference numbers for applications, students, receipts, and other documents while retaining internal numeric primary keys. Store timestamps consistently and display them in `Asia/Manila`. Public URLs must not expose sequential internal IDs where enumeration would disclose student data.

### D-018 - Account recovery

**Decision:** Account recovery is staff-assisted. There is no public password-reset request or account lookup. After staff verify the requester's identity through JCA's approved offline process, an Admin may issue a short-lived, single-use recovery invitation to the existing verified email or mobile contact.

**Consequences:** Recovery uses the same private password-setup flow as an invitation and records an audit event. JCA may later approve email as an official automated recovery channel only through a replacement decision and a separate security review.

## Pending decisions

These do not block restrictive generic foundations. The named production capability cannot be enabled or represented as official JCA policy until its relevant inputs are supplied.

| ID | Decision needed | Deadline | Safe implementation posture |
|---|---|---|---|
| P-001 | School-approved retention periods per document category | Before production launch | Configurable policy; no automated production deletion |
| P-002 | Final fee schedules, installment due dates, discount values, and exam-permit thresholds | Before production launch | Phase 6 uses owner-authorized, explicitly development-only data; production configuration remains blocked pending approval |
| P-003 | Official section names, subjects, and teaching assignments | Before Academics phase production import | Grade offerings are confirmed; seed only clearly labeled development fixtures for the remaining catalog |
| P-004 | Exact support-assessment form, decision labels, and authorized reviewer roster | Before official production support-form activation | Phase 4 uses restrictive access, reviewer assignment, neutral generic outcomes, private observations, history, and approved teacher-safe summaries only |
| P-005 | Official SMS sender name, approved templates, consent wording, and escalation contacts | Before enabling live SMS | SMS disabled by default; log driver/test fake |
| P-006 | Required official reports and signatories | Before Reporting phase acceptance | Build reusable filters and exports from normalized data |
| P-007 | Hosting, backup, recovery objectives, and production MySQL version | Before deployment readiness | Target MySQL 8.0+ and document assumptions |
| P-008 | Named role owners, UAT signatories, training completion, incident contacts, and launch authority | Before production launch | Use fictional test data and unsigned UAT templates; readiness fails closed |

### D-019 - Fail-closed production readiness gate

**Status:** Confirmed technical control; it does not confirm any school policy.

**Decision:** Production deployment must pass `php artisan jca:production-check`. The command validates secure environment, database-backed infrastructure, private storage, queue health, absence of development fixtures, backup controls, and explicit approval attestations. Every attestation defaults to false and may become true only after the signed source approval is stored in the launch record.

**Consequences:** Engineering verification can finish while policy remains pending, but the system cannot be represented as production-ready by assumption. The command reports only check names and pass/fail state, never secret values.

### D-020 - Physical enrollment requirements

**Status:** Superseded by D-025 on 2026-07-21.

**Decision:** Enrollment admission requirements are submitted as hard copies to the Registrar. The system stores a checklist snapshot plus who recorded receipt, when it was received, optional receipt notes, review outcome, reviewer, timestamps, and rejection or waiver reason. It does not accept new digital uploads for enrollment requirements.

**Consequences:** Registrar and Admin may record or correct receipt before review, verify a received hard copy, or reject it and request a replacement. Only Admin may waive a requirement with a reason. Receipt and review changes are audited. Historical uploaded files remain private and protected under D-013 and D-014, but no new enrollment-requirement upload route is exposed.

### D-025 - Dual-evidence enrollment requirements

**Status:** Accepted by the owner on 2026-07-21; supersedes D-020 for new enrollment-requirement handling.

**Decision:** Birth Certificate, Form 137/Form 138 or Report Card, Certificate of LOV, and other configured enrollment requirements may be supported by either a privately uploaded soft copy or a physically submitted hard copy. A blocking checklist item is sufficient for academic approval when the Registrar has verified its current soft copy, or when staff has recorded receipt of the hard copy. Soft-copy submission alone is not sufficient until reviewed. The hard copy remains a separately visible continuing obligation after soft-copy verification and may be submitted later. An authorized Admin waiver remains an exceptional alternative with a required reason.

Authenticated Student applicants and authorized Guardians may upload PDF, JPG, JPEG, or PNG files of at most 5 MB while an application is actively under review. Each replacement creates a new version and archives the previous version. Files remain on the private non-serving document disk, use opaque paths, and have no public URL. Registrar/Admin preview and authorized owner download recheck policy, reject archived/deleted files, use safe response headers, and append both document-access and general audit evidence.

**Consequences:** Enrollment approval evaluates soft-copy evidence and hard-copy receipt independently. Approval does not erase or falsely complete the hard-copy obligation. Registrar review displays one current soft-copy preview in an audited modal and retains separate hard-copy receipt/review controls. Automated retention deletion remains disabled until P-001 is approved.

### D-026 - Promotion requirement evidence carryover

**Status:** Accepted by the owner on 2026-07-23.

**Decision:** When promotion creates a linked next-school-year enrollment, accepted hard-copy evidence from the prior enrollment carries forward into the new checklist. Accepted evidence means a verified hard copy or a recorded hard-copy receipt. Birth Certificate, Form 138/Report Card, and other accepted physical requirements therefore do not need to be submitted again solely because the school year changed.

The new checklist records the source requirement, carryover actor, and carryover timestamp while preserving the prior enrollment and original receipt/review evidence. Waivers, rejected hard copies, unreviewed soft-copy uploads, and soft-copy verification without a received hard copy are not represented as carried hard-copy proof. Current-year requirement rules may still add document types not present in the prior enrollment.

**Consequences:** Promotion remains a new enrollment under D-011, but it does not discard reusable physical-document evidence. The Registrar can see the source grade and school year, and the normal approval check accepts the carried evidence. An explicit rejection on the promoted checklist overrides carryover for that enrollment without rewriting the historical source.

### D-027 - Separate portal and staff sign-in surfaces

**Status:** Accepted by the owner on 2026-07-24.

**Decision:** Guardian/Parent and Student accounts use `/login`. Admin, Registrar, Accounting, and Teacher accounts use `/staff/login`. Both entry points retain the same user table, authenticated session guard, credential rules, rate limiter, session regeneration, and downstream authorization policies. Each authentication endpoint accepts only its assigned role group, and a valid account presented to the wrong endpoint receives the same neutral invalid-credentials response as any other failed sign-in.

**Consequences:** The separate pages improve audience clarity without creating a second identity system or weakening policy checks. A successful logout returns the user to the entry point for their role group. Staff and portal roles remain incompatible on one account under D-005, and authorization after sign-in remains role- and record-scoped.

### D-021 - Account reactivation and student-account connection

**Status:** Accepted by the owner on 2026-07-18.

**Decision:** A disabled account may be returned only to the `invited` state by an Admin after offline identity review. Reactivation requires a reason, revokes sessions, creates a new single-use setup invitation, and records separate reactivation and invitation audit events. Disabling an account also requires a reason and revokes every outstanding invitation. Enrollment review must not create setup links for disabled or locked guardian accounts.

Student accounts are connected to permanent student profiles only through the audited People action after an `approved` or `enrolled` school-year record exists. Grades 7-12 are eligible by default. A younger student requires an Admin-supplied exception reason. Student-profile creation cannot attach an account and bypass these gates.

**Consequences:** Repeated links cannot make a disabled account redeemable; Admin must deliberately reactivate it. Standalone Student-role users do not gain student-record access until linked to exactly one eligible profile. A clearly mismatched login/profile connection renders no student data. Admin may disconnect a wrong connection only with a reason, audit entry, and session revocation; both records and all enrollment history remain intact. Erroneous accounts themselves are disabled with history preserved rather than deleted.

### D-022 - DepEd-aligned learner profile intake

**Status:** Accepted by the owner on 2026-07-21 for the enrollment form implementation.

**Decision:** The public application collects the learner identity, background indicators, address, parent/guardian, and previous-school facts represented by the DepEd Basic Education Enrollment Form, while retaining JCA's separate grade, school-year, category, and contact-verification workflow. LRN is optional and must contain exactly 12 digits when supplied. The 4Ps household ID and disability type are conditionally required. Religion, Indigenous Peoples group, and parent-name fields remain optional because they are sensitive or may not apply. Age is calculated from birth date and is never stored as an independent fact.

Permanent learner facts belong to `student_profiles`; guardian identity/contact belongs to `guardian_profiles`; guardian relationship and previous-school facts belong to the school-year `enrollments` record. Duplicate candidates are still reviewed by staff and are never merged automatically.

**Consequences:** The form is DepEd-aligned but does not claim direct LIS integration or replace the school's official submission duties. Access to collected child and family data remains role-scoped and auditable under the existing privacy rules. Conditional fields are validated server-side even when the browser enhancement is unavailable.

### D-023 - Pre-approval Student application ownership

**Status:** Accepted by the owner on 2026-07-21.

**Decision:** When a signed-in Student starts a public enrollment application, the enrollment records that Student account as its applicant owner without connecting the account to the permanent student profile. The owner may view the application reference, grade, school year, status timeline, dual requirement statuses, and upload private soft-copy evidence during active review, and may resume the application only while it remains a draft. Anonymous applicants continue to use the hashed private resume-token flow.

One Student account may have at most one non-terminal application per school year. A nullable unique active-account/year key enforces this alongside a transaction and account lock. `rejected`, `withdrawn`, and `cancelled` release the key; all other states, including `approved` and `enrolled`, remain active for that year.

**Consequences:** Application ownership grants access only to that application and its non-confidential checklist status. It does not grant access to a student profile, section, financial record, support record, or another applicant's application. The existing post-approval Admin connection rule in D-021 remains unchanged.

### D-024 - Removal of unused mistaken student profiles

**Status:** Accepted by the owner on 2026-07-21.

**Decision:** Admin may permanently remove an active student profile created by mistake only when it has no login connection, enrollment history, guardian relationship history, or communication targeting history. Profiles already marked duplicate or inactive remain historical and cannot be deleted. A reason and explicit confirmation are required. An unresolved draft/submitted enrollment may reference the active profile only as a pending duplicate candidate; removal clears and audits that candidate reference without deleting or reassigning the application.

**Rationale:** Online enrollment creates the authoritative applicant profile. The manual People form exists for verified legacy or offline records and must not be used before a new application. Allowing narrow cleanup of a truly unused mistaken record prevents permanent duplicate clutter without weakening the rule that student history is preserved.

**Consequences:** Any historical or access-bearing reference blocks deletion server-side. The deletion and each cleared duplicate-candidate reference are audited with opaque identifiers. Profiles with any enrollment remain preserved even when that enrollment is rejected, withdrawn, or cancelled.

### D-025 - Shared household mobile number for controlled testing

**Status:** Owner-authorized on 2026-09-19 for the Laragon and Hostinger capstone testing environments only. It is not the production default.

**Decision:** When `ALLOW_SHARED_CONTACT_ACCOUNTS=true` and the opt-in shared-mobile migration has run, separate Student and Guardian accounts may reuse one normalized mobile number. Accounts remain individual under D-003 and are resolved at sign-in by their own passwords. Email addresses remain unique, staff accounts are not automatically merged, and Guardian links are still explicit and audited.

**Consequences:** The setting remains false by default. A production launch must either restore mobile uniqueness after reconciling duplicates or obtain an explicit replacement decision. Disabling the setting does not delete or merge test accounts; duplicate mobile records must be reconciled before the unique database index can be restored.

## Phase 6 development-policy status

P6-01 through P6-21 are **Assumed – Owner-authorized for development**. They satisfy the Phase 6 development/testing prerequisite, but they are not accepted production policies. The configurable implementation and fictional fixtures may be replaced without rewriting historical financial snapshots. The questionnaire remains the production-confirmation checklist.

| ID | Owner-authorized development policy area | Production status |
|---|---|---|
| P6-01 | Fee catalog and charge behavior | Assumed – Owner-authorized for development; official catalog pending |
| P6-02 | Grade-band fee schedules and amounts | Assumed – Owner-authorized for development; actual amounts pending |
| P6-03 | Immutable assessment revision lifecycle | Assumed – Owner-authorized for development; production procedure pending |
| P6-04 | Full, semiannual, quarterly, and monthly plans | Assumed – Owner-authorized for development; enabled offerings pending |
| P6-05 | Exact due generation and business-day movement | Assumed – Owner-authorized for development; actual calendar pending |
| P6-06 | Payment-to-follow and Admin extension | Assumed – Owner-authorized for development; official procedure pending |
| P6-07 | Partial/advance/late payment and student credit | Assumed – Owner-authorized for development; production procedure pending |
| P6-08 | First-100 tuition discount development campaign | Assumed – Owner-authorized for development; official campaign pending |
| P6-09 | Approval-time atomic rank and non-recycled slots | Assumed – Owner-authorized for development; official campaign pending |
| P6-10 | One campaign discount and capped reductions | Assumed – Owner-authorized for development; official combination rules pending |
| P6-11 | Append-only discount correction/cancellation | Assumed – Owner-authorized for development; official approval procedure pending |
| P6-12 | Payment/receipt references and reprint numbering | Assumed – Owner-authorized for development; official series pending |
| P6-13 | Offline methods and private evidence | Assumed – Owner-authorized for development; enabled methods pending |
| P6-14 | Financial authorization matrix | Assumed – Owner-authorized for development; staff assignments pending |
| P6-15 | Independent reversal, adjustment, and refund approval | Assumed – Owner-authorized for development; legal procedure pending |
| P6-16 | Four configurable exam periods and permit references | Assumed – Owner-authorized for development; actual dates pending |
| P6-17 | Permit-critical due coverage at cutoff | Assumed – Owner-authorized for development; official threshold pending |
| P6-18 | Restricted permit exceptions and Admin overrides | Assumed – Owner-authorized for development; verification procedure pending |
| P6-19 | Linked Guardian/self Student financial visibility | Assumed – Owner-authorized for development; production wording pending |
| P6-20 | Statement, receipt, permit, and internal ledger printouts | Assumed – Owner-authorized for development; layouts/signatories pending |
| P6-21 | Approved/enrolled assessment boundary without section/payment prerequisite | Assumed – Owner-authorized for development; production confirmation pending |

Actual fee amounts were supplied and applied on 2026-08-21 from the DepEd Region 1 approval letter of 11 June 2025 and the school's itemised fee sheet (scans in `public/images/school_fees_scanned/`). Production release remains blocked on class/examination dates, official receipt or invoice series, enabled methods, authorized signatories, official discount amount/dates, promissory-note procedure, statutory-certification verification, final permit wording, and the school holiday/business calendar.

## Out-of-scope decisions

- Online payments and payment gateways.
- Learning management, online classes, grading, attendance, payroll, HR, library, and inventory systems.
- Native mobile applications.
- Biometric login, social login, and public APIs for third parties.
- Automatic clinical or special-needs diagnosis, scoring, or placement decisions.
