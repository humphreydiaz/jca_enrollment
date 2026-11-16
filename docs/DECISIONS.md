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

**Status:** Superseded for the post-baseline change program by D-032. This remains a description of the currently implemented flow until the replacement phase is delivered.

**Decision:** Do not allow unrestricted public account registration. Pre-elementary and Grades 1-6 use a guardian-assisted enrollment application. For Grades 7-10, Admin may invite a Student login before enrollment so the learner can sign in and own the application described in D-023. After either kind of submission is verified as legitimate, the school connects or invites the individual guardian account so the required guardian relationship is retained.

**Consequences:** Applicant ownership, login accounts, and permanent people profiles are separate. A high-school Student login may own an application before it is connected to the permanent learner profile; that profile connection remains post-approval. Invitations expire, are single-use, and must never contain a reusable plaintext password.

### D-003 - Guardian and student relationships

**Decision:** Every person has an individual account; credentials are never shared as a household account. A guardian can be linked to multiple students and a student can be linked to multiple guardians. Each link records relationship type, contact permissions, portal visibility, pickup/legal authority where applicable, and whether the guardian is the primary contact.

### D-004 - Student portal eligibility

**Decision:** Grades 7-10 may receive an Admin-issued Student invitation and submit their own application, while pre-elementary and Grades 1-6 require guardian-assisted enrollment. Permanent Student login-to-profile access is connected only after approval. Pre-elementary and Grades 1-6 use guardian portal access by default; Admin may connect a younger Student login only as a recorded and audited exception.

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

**Consequences:** Announcements and routine notifications stay on Semaphore's standard route (superseded by D-052: announcements and Admin direct messages use the priority route by default). The adapter retries one transient network/provider failure and treats provider `Failed` or `Refunded` responses as failures instead of accepted sends. A Semaphore `Queued`, `Pending`, or `Sent` response is described as provider/network acceptance, never proof that the handset received the SMS. A three-minute guard prevents staff from immediately replacing a guardian code that may still be moving through the mobile network. Live delivery remains blocked by P-005 until sender name, consent wording, approved templates, escalation contacts, and credentials are supplied.

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
| P-003 | Final review of 2026-2027 schedules and named teacher assignments | Before official Academics publication | The 35-page official class program now identifies all offered grade/section programs and Grade 1-10 subject sets; Phase 14 loads a reviewable draft. Row-time inconsistencies and teacher account matching still require school confirmation |
| P-004 | Exact support-assessment form, decision labels, and authorized reviewer roster | Before official production support-form activation | Phase 4 uses restrictive access, reviewer assignment, neutral generic outcomes, private observations, history, and approved teacher-safe summaries only |
| P-005 | Official SMS sender name, approved templates, consent wording, and escalation contacts | Before enabling live SMS | SMS disabled by default; log driver/test fake |
| P-006 | Required official reports and signatories | Before Reporting phase acceptance | Build reusable filters and exports from normalized data |
| P-007 | Hosting, backup, recovery objectives, and production MySQL version | Before deployment readiness | Target MySQL 8.0+ and document assumptions |
| P-008 | Named role owners, UAT signatories, training completion, incident contacts, and launch authority | Before production launch | Use fictional test data and unsigned UAT templates; readiness fails closed |
| P-010 | Passing mark/retention rule and Nursery/Kinder assessment format | Before applying a pass mark to promotion or preschool assessment implementation | D-038 resolves Grades 1-10 grading; D-043 resolves that promotion requires Registrar-approved final grades in every subject. Do not infer a passing mark or preschool numeric grades |
| P-012 | Documentary confirmation of JCA's four-quarter and numeric Grade 1 private-school adaptation under the current DepEd policy | Before production use of Grades 1-10 results | Owner's JCA policy confirmation permits development; do not present it as DepEd Regional Office approval without the school's evidence |

### D-019 - Fail-closed production readiness gate

**Status:** Confirmed technical control; it does not confirm any school policy.

**Decision:** Production deployment must pass `php artisan jca:production-check`. The command validates secure environment, database-backed infrastructure, private storage, queue health, absence of development fixtures, backup controls, and explicit approval attestations. Every attestation defaults to false and may become true only after the signed source approval is stored in the launch record.

**Consequences:** Engineering verification can finish while policy remains pending, but the system cannot be represented as production-ready by assumption. The command reports only check names and pass/fail state, never secret values.

### D-020 - Physical enrollment requirements

**Status:** Superseded by D-025 on 2026-07-21.

**Decision:** Enrollment admission requirements are submitted as hard copies to the Registrar. The system stores a checklist snapshot plus who recorded receipt, when it was received, optional receipt notes, review outcome, reviewer, timestamps, and rejection or waiver reason. It does not accept new digital uploads for enrollment requirements.

**Consequences:** Registrar and Admin may record or correct receipt before review, verify a received hard copy, or reject it and request a replacement. Only Admin may waive a requirement with a reason. Receipt and review changes are audited. Historical uploaded files remain private and protected under D-013 and D-014, but no new enrollment-requirement upload route is exposed.

### D-025 - Dual-evidence enrollment requirements

**Status:** Accepted by the owner on 2026-07-21; supersedes D-020 for new enrollment-requirement handling. **Superseded by D-062 (1) on 2026-11-15:** new soft-copy uploads are retired.

**Decision:** Birth Certificate, Form 137, School Form 9 (SF9), Certificate of LOV, and other configured enrollment requirements may be supported by either a privately uploaded soft copy or a physically submitted hard copy. A blocking checklist item is sufficient for academic approval when the Registrar has verified its current soft copy, or when staff has recorded receipt of the hard copy. Soft-copy submission alone is not sufficient until reviewed. The hard copy remains a separately visible continuing obligation after soft-copy verification and may be submitted later. An authorized Admin waiver remains an exceptional alternative with a required reason.

Authenticated Student applicants and authorized Guardians may upload PDF, JPG, JPEG, or PNG files of at most 5 MB while an application is actively under review. Each replacement creates a new version and archives the previous version. Files remain on the private non-serving document disk, use opaque paths, and have no public URL. Registrar/Admin preview and authorized owner download recheck policy, reject archived/deleted files, use safe response headers, and append both document-access and general audit evidence.

**Consequences:** Enrollment approval evaluates soft-copy evidence and hard-copy receipt independently. Approval does not erase or falsely complete the hard-copy obligation. Registrar review displays one current soft-copy preview in an audited modal and retains separate hard-copy receipt/review controls. Automated retention deletion remains disabled until P-001 is approved.

### D-026 - Promotion requirement evidence carryover

**Status:** Accepted by the owner on 2026-07-23.

**Decision:** When promotion creates a linked next-school-year enrollment, accepted hard-copy evidence from the prior enrollment carries forward into the new checklist. Accepted evidence means a verified hard copy or a recorded hard-copy receipt. Birth Certificate, School Form 9 (SF9), and other accepted physical requirements therefore do not need to be submitted again solely because the school year changed.

The new checklist records the source requirement, carryover actor, and carryover timestamp while preserving the prior enrollment and original receipt/review evidence. Waivers, rejected hard copies, unreviewed soft-copy uploads, and soft-copy verification without a received hard copy are not represented as carried hard-copy proof. Current-year requirement rules may still add document types not present in the prior enrollment.

**Consequences:** Promotion remains a new enrollment under D-011, but it does not discard reusable physical-document evidence. The Registrar can see the source grade and school year, and the normal approval check accepts the carried evidence. An explicit rejection on the promoted checklist overrides carryover for that enrollment without rewriting the historical source.

### D-027 - Separate portal and staff sign-in surfaces

**Status:** Accepted by the owner on 2026-07-24; superseded in implementation by D-031 on 2026-10-03.

**Decision:** Guardian/Parent and Student accounts use `/login`. Admin, Registrar, Accounting, and Teacher accounts use `/staff/login`. Both entry points retain the same user table, authenticated session guard, credential rules, rate limiter, session regeneration, and downstream authorization policies. Each authentication endpoint accepts only its assigned role group, and a valid account presented to the wrong endpoint receives the same neutral invalid-credentials response as any other failed sign-in.

**Consequences:** The separate pages improve audience clarity without creating a second identity system or weakening policy checks. A successful logout returns the user to the entry point for their role group. Staff and portal roles remain incompatible on one account under D-005, and authorization after sign-in remains role- and record-scoped.

### D-021 - Account reactivation and student-account connection

**Status:** Accepted by the owner on 2026-07-18.

**Decision:** A disabled account may be returned only to the `invited` state by an Admin after offline identity review. Reactivation requires a reason, revokes sessions, creates a new single-use setup invitation, and records separate reactivation and invitation audit events. Disabling an account also requires a reason and revokes every outstanding invitation. Enrollment review must not create setup links for disabled or locked guardian accounts.

Student accounts are connected to permanent student profiles only through the audited People action after an `approved` or `enrolled` school-year record exists. Grades 7-10 are eligible by default. A younger student requires an Admin-supplied exception reason. Student-profile creation cannot attach an account and bypass these gates.

**Consequences:** Repeated links cannot make a disabled account redeemable; Admin must deliberately reactivate it. Standalone Student-role users do not gain student-record access until linked to exactly one eligible profile. A clearly mismatched login/profile connection renders no student data. Admin may disconnect a wrong connection only with a reason, audit entry, and session revocation; both records and all enrollment history remain intact. Erroneous accounts themselves are disabled with history preserved rather than deleted.

### D-022 - DepEd-aligned learner profile intake

**Status:** Accepted by the owner on 2026-07-21 for the enrollment form implementation; religion policy amended by the owner on 2026-09-23.

**Decision:** The public application collects the learner identity, background indicators, address, parent/guardian, and previous-school facts represented by the DepEd Basic Education Enrollment Form, while retaining JCA's separate grade, school-year, category, and contact-verification workflow. LRN is optional and must contain exactly 12 digits when supplied. The 4Ps household ID and disability type are conditionally required. Religion is fixed to Christianity by school policy, displayed as a read-only field, and enforced server-side. Indigenous Peoples group and parent-name fields remain optional because they are sensitive or may not apply. Age is calculated from birth date and is never stored as an independent fact.

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

### D-035 - Shared household mobile number for controlled testing

**Status:** Owner-authorized on 2026-09-19 for the Laragon and Hostinger capstone testing environments only. It is not the production default.

**Decision:** When `ALLOW_SHARED_CONTACT_ACCOUNTS=true` and the opt-in shared-mobile migration has run, separate Student and Guardian accounts may reuse one normalized mobile number. Accounts remain individual under D-003. Email addresses remain unique, staff accounts are not automatically merged, and Guardian links are still explicit and audited. Under the later Phase 12 amendment, contact values are communication data and no longer resolve sign-in accounts.

**Consequences:** The setting remains false by default. A production launch must either restore mobile uniqueness after reconciling duplicates or obtain an explicit replacement decision. Disabling the setting does not delete or merge test accounts; duplicate mobile records must be reconciled before the unique database index can be restored.

### D-028 - Four-quarter grading foundation

**Status:** Accepted by the owner on 2026-10-03.

**Decision:** Each gradable student/subject result has first-, second-, third-, and fourth-quarter grades. After all four values exist, the system calculates the final subject grade as `(Q1 + Q2 + Q3 + Q4) / 4`. A missing quarter leaves the result incomplete and is never treated as zero. The calculated final grade is server-owned, cannot be typed over directly, and is the academic input to promotion review.

Teachers may maintain only grades covered by their current section/subject assignments. Draft quarter grades may be edited; submitted/finalized grades require an authorized, reasoned, audited reopen/correction workflow. Promotion consumes only complete finalized results and continues to create a new enrollment under D-011.

**Pending boundary:** D-038 resolves numeric Grades 1–10 entry, rounding, and review authority. P-010 still requires passing/promotion criteria and Nursery/Kinder assessment treatment.

### D-029 - Registrar correction of student information

**Status:** Accepted as a restrictive engineering decision under the owner's delegated authority on 2026-10-03.

**Decision:** Registrar may correct student legal-name components, suffix, preferred name, recorded sex, birth date/place, mother tongue, addresses, structured parent-name components, applicable learner indicators/details, and previous-school facts. Every correction requires a meaningful reason and preserves actor, timestamp, before value, and after value.

Student ID and LRN use a separate uniqueness-checked identifier-correction action. Guardian identity/contact/legal-authority and relationship changes use the guardian relationship workflow. Religion remains fixed to Christianity. Enrollment state, section, requirements, support, finance, grades, and their histories remain editable only through their owning module's explicit transition/correction actions. Internal/public IDs and audit history are never generic editable fields.

### D-030 - Grading module boundary

**Status:** Accepted as an architecture decision on 2026-10-03.

**Decision:** A new Grading module owns grading periods, quarter grades, submission/finalization state, grade revisions, and calculated final subject grades. It reads authorized Academics assignments and Enrollment/People identity but writes only its own tables. Section Assignment continues to own promotion recommendations and reads finalized Grading results through an explicit application boundary.

**Consequences:** Curriculum changes cannot rewrite historical grades. Registrar is not granted grade-edit authority through student-profile correction. Reporting remains read-only and cannot bypass teacher assignment or grade-release policy.

### D-031 - Unified sign-in surface

**Status:** Accepted by the owner for the post-baseline change program and implemented in Phase 12 on 2026-10-03; supersedes D-027.

**Decision:** Guardian, Student, Admin, Registrar, Accounting, and Teacher accounts use one login form and one authentication endpoint. Ordinary login accepts only a D-036 Student ID or Staff ID plus a password; email and mobile are not login identifiers. A verified guardian with enrollment-management authority may enter a linked learner's Student ID, but the guardian's own password authenticates the guardian account. The system fails closed if one identifier/password combination matches multiple active accounts. The authorized destination is determined only afterward. Failures remain neutral and must not disclose whether an identifier exists or which role it has. Staff and portal roles remain incompatible on one account under D-005.

**Consequences:** Student ID and Staff ID are the only ordinary login identifiers. Contact details may still support communication or staff-assisted recovery but cannot authenticate. One guardian may use any eligible linked child's Student ID, and multiple guardians may use the same learner identifier with their own distinct passwords while audit activity remains attributable to the actual adult account. Authorization after sign-in remains role-, relationship-, assignment-, and record-scoped. The retired `/staff/login` page redirects to `/login`, and there is no second staff authentication endpoint.

### D-032 - Walk-in enrollment and Registrar-issued access

**Status:** Accepted by the owner and implemented in Phase 13 on 2026-10-03; supersedes the public-start portion of D-002.

**Decision:** New applicants must first appear at the school. After offline identity and duplicate review, Registrar creates or links the appropriate student/guardian records and issues expiring, one-time temporary access to the approved account owner. The account owner signs in, establishes a private password, and completes the enrollment application while authenticated. There is no unrestricted public enrollment start.

**Consequences:** Public draft creation, enrollment contact/SMS verification, and resume-token entry are retired. Registrar issues a Student ID and a three-day temporary password after in-person duplicate/identity review. The password is shown/printed once, stored only as a hash, replaceable before first use, and must be changed before application access. Nursery through Grade 6 are guardian-owned and Grades 7-10 are Student-owned. Permanent plaintext passwords are never retained or sent.

### D-033 - Development and capstone data reset

**Status:** Authorized by the owner on 2026-10-03 for the current development database and fictional Hostinger capstone demonstration data only.

**Decision:** The post-baseline schema work may use a clean database rebuild instead of spending implementation effort on compatibility backfills for disposable development/capstone records. Before a destructive rebuild, the exact database target and a readable backup must be verified, and the reviewed migrations/seed plan must recreate the intended state deterministically.

**Consequences:** This is not authorization to erase a future live production database, source files, private uploads, environment secrets, or the backup. Historical-preservation rules remain mandatory behavior for the completed production system.

### D-034 - Individual account ownership recommendation

**Status:** Accepted by the owner and implemented in Phase 13 on 2026-10-03; closes P-011.

**Decision:** Account ownership identifies the natural person responsible for the private password, recovery, and audited activity. The recommended model remains guardian-owned access for Nursery through Grade 6, Student-owned access for Grades 7-10, and individually owned staff access. One guardian account may manage multiple linked children. Under the Phase 12 identifier amendment, a child's Student ID is the public lookup identifier for eligible linked guardians but is not itself a secret or shared credential.

**Amended by D-047:** for Grades 7–10 the guardian is completed on the application, and guardian contact details are optional at the counter.

**Consequences:** The Registrar records one primary guardian during the walk-in handoff and issues the one-time credential to that natural-person owner for Nursery through Grade 6. Grades 7-10 receive Student-owned access while retaining the verified guardian relationship. Additional guardian access requires a later verified relationship/account workflow; credentials are never shared.

### D-036 - Student and Staff identifier formats

**Status:** Accepted from the owner's exact formats on 2026-10-03; closes P-009.

**Decision:** A Student ID is `JCA-YY-NNN`, for example `JCA-26-693`. `YY` is the last two digits of the start year of the learner's entry academic year. A Staff ID is `YY-JCA-NNN`, for example `26-JCA-783`. Its `YY` is the last two digits of the staff member's recorded employment-start year. `NNN` is a cryptographically random three-digit value from 100 through 999. Issuance is serialized within identifier type/year, retries collisions, and remains protected by a database unique constraint.

An already issued identifier is retained idempotently. Student IDs are issued during the Phase 13 Registrar walk-in access transaction; Staff IDs are issued when an Admin creates a staff account and records the employment-start date. Retained identifiers are not silently reformatted. An authorized, reasoned, uniqueness-checked correction is required to replace a Student ID or LRN.

**Consequences:** Phase 12 may use these identifiers in the unified resolver. Internal numeric keys and opaque public route identifiers remain distinct. With 900 suffixes per type/year, exhaustion fails closed and requires an explicit school decision rather than silently expanding the format.

### D-037 - Curriculum publication and class-program interpretation

**Status:** Implemented in Phase 14 on 2026-10-04; official 2026-2027 publication awaits Admin review under P-003.

**Decision:** The 35-page owner-supplied official class program is transcribed into a draft version for 2026-2027. A published curriculum is immutable and belongs to one effective school year. Admin alone may publish after reviewing grade-subject mappings, recurring section names, and timetable notes. Publication transactionally creates missing year-specific sections and subject offerings without deleting existing records or inferring teacher assignments. An Admin copies a published version to a new school year to carry forward stable names and subject sets, then reviews and publishes the new version. One published version per school year is allowed. Source schedule row times are used; inconsistent printed daily totals do not drive the timetable.

**Consequences:** Preschool programs are stored as section activity schedules, with no numeric graded subjects inferred before P-010. HGP, enhancement, breaks, and ceremonies are not treated as graded subjects. Subject teacher assignment is explicit, and overlapping Monday-Friday time ranges for one teacher are rejected until an authorized staff member edits the schedule. Existing year-specific history and manually created records are preserved. Real teacher matching and any source timetable discrepancy remain P-003 review items.

### D-038 - JCA four-quarter grading policy and release control

**Status:** Owner-confirmed for JCA on 2026-10-04; implemented in Phase 15 for Grades 1–10.

**Decision:** JCA retains its approved private-school four-quarter policy. Grade 1 through Grade 10 use numeric subject grades; Nursery, Junior Kinder, and Senior Kinder do not enter this numeric workflow. Quarter entries are whole numbers from 0 through 100. The server averages four complete quarter values and rounds an exact half upward to the nearest whole number; any missing quarter leaves the result incomplete. These are entered/report-card quarter values, not raw assessment-component scores. Teachers may edit drafts only for a currently authorized subject assignment and confirmed enrolled student. They submit complete results, and the Registrar or Admin approves (finalizes) them (D-043). Families and the operational grade report see finalized results only.

**Correction:** A teacher requests reopening of a submitted/finalized result with a reason; Admin approves or rejects with a review note. Approved reopening restores draft state, hides a previously finalized result, and requires a new teacher submission and Admin finalization. Quarter changes and transitions leave append-only revision and audit records. Final values cannot be typed directly. Promotion criteria and preschool assessment remain P-010 and are not inferred here.

**Production boundary:** This owner-confirmed JCA policy is not an independent legal-compliance finding. Before live grade publication, JCA should verify and retain the applicable Regional Office approval or other documentary authority for its 2026-2027 private-school adaptation (P-012).

### D-039 - Official requirement rules on every school year

**Status:** Confirmed by the owner on 2026-10-05.

**Decision:** The REQ-002 through REQ-004 minimum document sets are JCA's confirmed enrollment baseline for regular, transferee, and support-review applicants. Every school year, including a past year used for a permitted test enrollment, receives its own database rule rows. Creating or updating a school year fills missing rules in the same transaction through a Requirements-owned Action and audits the installation. The idempotent baseline seeder repairs all existing years regardless of status, while preserving any existing year-specific rule values and historical enrollment checklist snapshots. New applications snapshot the rules applicable at submission; historical applications are not silently rewritten.

**Consequence:** A newly created year can accept authenticated enrollment submissions without an extra manual seeding step. Future school-approved changes can be made to that year's data rules without changing prior-year checklists.

### D-040 - Deleting an unpublished curriculum draft

**Status:** Owner-requested on 2026-10-05.

**Decision:** Admin may permanently delete a curriculum version only while it remains an unpublished draft and has no links to actual school-year sections or subject offerings. The action requires an explicit confirmation, rechecks the state under a school-year and curriculum lock, removes only that draft's subject mappings, section templates, and timetable notes, and records the deleted version and child counts in the audit trail. Published curricula and applied academic records cannot be deleted through this workflow. The school year, grade levels, and subject catalog remain intact.

### D-041 - Reusable default class-program template

**Status:** Owner-requested on 2026-10-05.

**Decision:** The owner-supplied 2026-2027 class-program transcription is a reusable default curriculum source, independent of saved curriculum versions. The original seeder and the Admin's “Default curriculum” option read the same configured grade-subject, section, and timetable snapshot. Selecting it creates a new draft for a selected planned/open year, never publishes or overwrites a year automatically, and never assigns teachers. It remains available when the curriculum-version table is empty. Copying an existing published version remains a separate option.

**Consequence:** Later-year drafts retain JCA's established subject and section structure without depending on a historical published record. Dates, timetable discrepancies, and named teacher assignments must still be reviewed for each school year before publication under P-003.

### D-042 - Year-scoped Academics workspace, structured class times, and catalog lifecycle

**Status:** Owner-requested on 2026-10-06 after Admin feedback that the single Academics page showed every year's settings and sections at once.

**Decision:** Academics is split into three server-rendered tabs: Class schedules (default), Curriculum, and Setup. Every tab is scoped to one school year chosen from a filter that defaults to the year whose dates include today, otherwise the newest open year, otherwise the newest year. Class schedules can be narrowed to one grade level and one section and shows that section's subjects as Days / From / To / Teacher rows, modeled on the registrar block-section view the school showed. Nothing that existed before was removed: school years, grade levels, subjects, sections, subject offerings, and teaching assignments all keep add/edit, and gain archive plus delete-when-unused.

- **Class times** are stored as weekdays plus 24-hour start and end times on `section_subjects` and `curriculum_template_schedules`; `schedule_text` is kept as the derived display string so every existing screen stays unchanged. Recognized older free-text times (including single-digit hours) are parsed so the teacher-overlap check no longer silently skips them; unrecognized notes are kept as notes.
- **Delete vs archive:** only the Admin may delete or archive/restore (including deactivating grades/subjects, archiving years and sections, archiving a section's subject, and removing a teacher from a subject). A record can be deleted only when no enrollment, placement, grade, finance, curriculum, or teaching record uses it; otherwise the screen explains what uses it and offers archive. Deleting an unused school year also removes its generated requirement rules through a Requirements-owned action. The Registrar keeps add and edit.
- **Section subject archive:** a new `section_subjects.status` (`active`/`archived`). Archiving hides the offering from current schedules (Section::sectionSubjects returns active offerings only), deactivates its subject-teacher assignments, and keeps grades. Restoring reverses the status but does not reactivate teachers.
- **Flexibility after a curriculum is published:** sections may be renamed (audited with the previous name), extra sections may be added (they receive the published curriculum's grade subjects automatically), and missing curriculum subjects can be re-added to a section without removing anything. A section's school year and grade remain locked once it came from a curriculum or has students or teachers; renaming a subject used by a published curriculum stays blocked.
- **Changing a teacher** deactivates the previous active adviser or subject teacher instead of deleting it, so grade history keeps its teaching assignment.

**Consequence:** The page loads one year instead of every year's sections. Old `/academics#school-years`-style bookmarks are redirected to the Setup tab. P-003 (timetable and teacher confirmation) remains open.

### D-043 - Promotion requires Registrar-approved final grades

**Status:** Owner-requested on 2026-10-06. Resolves the promotion-eligibility part of P-010; the passing mark and preschool assessment remain pending.

**Decision:** A Grade 1-10 student is eligible for promotion only when every current (non-archived) subject of the student's confirmed section has a complete final grade (all four quarters, server-computed per D-038) that has been submitted by the teacher and approved by the Registrar, in addition to the existing next-grade-level and future-school-year checks. Approving is the existing finalization step, now available to the Registrar as well as the Admin; the Registrar also decides teacher reopen requests. Students missing an approved grade are recorded as `grades_pending` with a reason naming each subject and its state (no grade yet, incomplete, not yet submitted, waiting for Registrar approval, correction requested). Draft creation re-checks grades, so a grade reopened after the review blocks the next-year draft until eligibility is reviewed again. Nursery and Kinder have no numeric grades and keep the previous eligibility rule. A Grade 1-10 student with no confirmed section, or whose section has no subjects, is not eligible.

**Not decided here:** No passing mark is applied; an approved final grade of any value counts. The final-grade values are shown on the promotion review so staff can judge them until JCA confirms its passing and retention rule under P-010.

### D-044 - Teacher roster shows which students have support notes; the summary opens in a dialog

**Status:** Owner-requested on 2026-10-06 (teacher My teaching redesign).

**Decision:** On My teaching, a teacher's current students are one plain, alphabetical list per section. A student with a currently effective, approved teacher-safe summary (D-006 scope: the teacher's own current section only) shows a neutral "Support notes" label so the teacher knows which summaries to read; the label reveals no summary text, case status, outcome, or document. Listing does not count as reading a summary and writes no support access log. Selecting a student loads the summary into a dialog only at that moment, through the same `ViewTeacherSafeSummaryAction` (access re-checked, `support_access_logs` row and audit event written), with `Cache-Control: no-store`. The same link opens the full summary page when JavaScript is unavailable.

**Rejected:** Preloading every summary into the page (would log reads the teacher never made and put all summaries in the HTML) and hiding the indicator entirely (forces teachers to open every student to find the few with notes).

### D-045 - Deadline-week grade approval: review before approving, several classes at once

**Status:** Owner-requested on 2026-10-06.

**Decision:** The Grade approval page tracks every Grades 1-10 class of the selected year (current roster × subject) instead of listing only submitted grades, and no longer stops at 500 rows. A reviewer may approve several classes in one action, but only classes they have reviewed in the current session — by opening the class, or with “Review all on this page”, which submits the fingerprints of exactly the grades listed on screen (each class's waiting final grades are shown inline). Reviewing records, server-side in the session, a fingerprint of each waiting grade shown (values, status, timestamps). Approving then finalizes only those grades and only if each is unchanged; grades edited or submitted after the review are skipped and stay waiting, and the reviewer is told how many. Each approval still goes through `FinalizeGradeAction` (one audited finalization per grade); opening a class is audited as `grading.admin.class_reviewed`. Rows are flagged for a second look (a quarter below 60, quarters differing by 20+ points, a final of 100); flags never block approval and are not a passing mark.

**Not decided here:** A Registrar-initiated "return to teacher" and teacher reminders remain optional follow-ups; P-010 (passing mark) stays pending.

### D-046 - Accounting maintains financial configuration with the Admin

**Status:** Owner-requested on 2026-10-06. Aligns the implementation with BIL-002 ("Accounting shall maintain fee schedules").

**Decision:** The Financial configuration page and all of its actions (fee catalog, fee schedule drafts, items, publishing, archiving and deleting unused versions, payment plan options, exam periods, discount campaigns, school calendar, and print settings) are available to Admin and Accounting. Every change keeps its existing validation, history protection (published or used versions cannot be edited or deleted), and audit entry naming the acting user.

**Unchanged:** Decisions on Accounting's own requests stay Admin-only and keep the requester ≠ approver rule: payment reversals, financial adjustments, refunds, discount cancellations and slot reopening, exam-permit overrides, and permit revocation. Registrar, Teacher, Guardian, and Student remain denied.

### D-047 - Faster walk-in issuance: guardian recorded at the counter only when the guardian owns the account

**Status:** Owner-requested on 2026-10-07 to speed up the walk-in queue. Amends D-032.

**Decision:** The walk-in form shows the Parent or guardian section only for guardian-owned grades (Nursery–Grade 6), and then requires only the guardian's name and relationship: the account and temporary password are issued in that person's name, and the in-person verified guardian–student link (needed for Student ID sign-in) records who was there. Mobile and email are optional at the counter; if given and already on file for another guardian or account, issuance is refused with advice to leave them blank. For learner-owned grades (Grades 7–10) no guardian details are taken: a placeholder guardian ("Parent/Guardian" + the learner's last name) holds the verified link, `enrollments.guardian_relationship` stays null, and the learner enters the guardian's name, relationship, and contact on the application. On the application, guardian name and relationship recorded by the Registrar stay locked (corrected only at the office); placeholder details are editable and never shown as values; at least one guardian contact is required everywhere, and the application cannot be submitted until guardian details are complete. A contact matching another active guardian sets the application's duplicate review to pending for the Registrar; nothing is merged automatically. Changes are audited by field name only (`people.guardian.details_completed`).

### D-048 - Family links: who signs in for each learner, and Student accounts for elementary learners on request

**Status:** Owner-requested on 2026-10-07. Replaces the Admin "People foundation" page; amends D-032 and D-047.

**Decision:** The old Admin People page (manual guardian/learner creation and manual link requests from the OTP/link era) is retired; `/admin/people` redirects to **Family links** (`/registrar/family-links`), open to the Registrar and Admin. Each learner card shows who signs in with that Student ID (the learner's own Student account, and each parent/guardian account with its verification), plus three actions:

1. **Grant student account** (Registrar/Admin). When a parent asks in person, an elementary learner (or any learner without one) gets a separate Student account with a temporary password. It requires a recorded reason and an "asked in person" confirmation, a Student ID, and a current enrollment. The learner signs in with the same Student ID as the parent and their own password; the parent's account and password do not change. A print-only "Student account slip" is shown once (flash only), and the action is audited (`people.student.account_attached`, with the grant reason).
2. **Connect a parent who already has an account** (siblings). The Registrar enters the other child's Student ID; that child's verified, managing parent account is linked to this learner as verified and may-manage, so the parent sees both children from one account. If several parents sign in for the other child, the Registrar picks who is present.
3. **Repair tools (Admin only)**: connect an existing Student login, disconnect a wrong login, or remove a mistaken, unused learner record.

Junior High walk-in logins (D-047) are now attached to the learner record when issued. Migration `2026_10_07_000029` repairs earlier walk-ins whose login was never attached, so the student pages (grades, schedule, finance) open for them; it is irreversible (no-op down).

Because a parent and a learner share one Student ID, a password change is refused when another account signing in with the same Student ID already uses that password (otherwise sign-in would fail closed for both).

### D-049 - An unfilled walk-in application stays a draft until its owner submits it

**Status:** Owner-reported on 2026-10-07 (a Grade 7 walk-in saw "Section placement is pending" before filling in the form, and the Registrar could mark the empty draft Submitted). Amends D-047 and D-048.

**Decision:** Application completeness (learner background, addresses, 4Ps/disability answers, parent/guardian relationship and a contact, previous school for transferees) is checked inside the status transition, so a draft cannot reach Submitted from the applicant's button, the Registrar's status control, or bulk actions until the form is complete. A continuing student's promotion draft (D-043) is exempt because the Registrar prepares it from the previous year's records. In Enrollment review, an unfilled walk-in draft shows "Waiting for the application form" with what is missing, and "Submitted" is not offered. The student's My enrollment page shows the real stage: unfinished draft (with Complete application for the access owner), being prepared by a parent (for a granted Student account), with the Registrar, or placement only once approved. Whoever holds the walk-in access sees a "Finish the enrollment application" prompt on the dashboard until the draft is submitted; only that person sees Submit on the application page.



### D-050 - A new fee schedule draft can start from an existing version

**Status:** Owner-requested on 2026-10-07.

**Decision:** "Create draft schedule" has a "Start from" choice listing every fee schedule version that has fees (any school year, grade, or status), and each version card has "Use as template", which selects it in that form. Creating the draft copies the source's fee items (fee type, description, amount, required, selected-by-default) into a new draft in the chosen year and grade, numbered as the next version there. The source version is only read, never changed. Fees whose fee type has since been deactivated are left out and counted in the confirmation. The copy is an ordinary draft: amounts can be edited and fees added or removed before publishing. The audit record of the new draft names the source version and the counts.

### D-052 - Communications without SMS templates: one announcement composer and direct texts to picked people

**Status:** Owner-decided on 2026-10-07. Replaces the SMS template workflow and amends D-015.

**Decision:** The Admin-only Communications page has three parts.
1. **New announcement:** title, portal message, and "Send as" Portal only / SMS only / Portal + SMS, with its own text message (pre-filled from the title and message, live counter, maximum 3 SMS parts). The audience is Everyone or any of Parents/Guardians, Students, Teachers, Registrar, Accounting, and Admin. Before any text goes out, the Admin sees how many people will receive it per group (opted in / not opted in / no number) and the estimated credits. SMS-only announcements are texts, not portal posts.
2. **Message people:** search by name, Student ID, Staff ID, or number, tick one or more people with a number on file, and send. These texts always go out whether or not the person opted in.
3. **Sent history:** announcements and direct messages with sent / not sent yet / failed counts and "Retry failed".

Announcement texts go only to people who opted in (Data Privacy Act): parents/guardians with an active link to a learner, learners with a current enrollment, and active staff accounts per role. One text per number per send. Opt-in is recorded per number owner (guardian profile, learner record, or staff account) from SMS preferences (now open to every portal user) or from the enrollment application, which gains an optional learner mobile number with an opt-in tick for the learner and for the guardian. School-wide announcements still may not contain confidential words (payment, balance, receipt, assessment, document, medical, therapy, diagnosis); direct messages are not filtered because the Admin chose each recipient. Announcement and direct texts use Semaphore's priority queue (`SMS_MESSAGE_ROUTE`, default `priority`, 2 credits per 160-character part). Staff account codes keep the OTP route. SMS templates, template versions, and the automatic enrollment-status texts (approved / rejected / waitlisted) are removed; old template rows stay only for historical notifications.

### D-053 - Parents see the fees of the learners they enroll; examination installments never pass the permit cutoff

**Status:** Found while preparing the capstone demonstration data on 2026-10-07. Amends D-047, D-048 and P6-05.

**Decision:** A parent/guardian verified to manage a learner's enrollment (walk-in access, sibling connection on Family links, legacy identity connection) may also view that learner's financial record, so the portal's Financial records page shows the assessment, due schedule, receipts and permits. Before this, those links were created without the financial view and the page stayed empty for every family. Migration `2026_10_07_000031` grants the view to existing verified, current managing links; it is irreversible (no-op down).

A quarterly or semiannual installment tied to an examination is due 10 days before the exam, the same day as that exam's permit eligibility cutoff. When that day is a weekend or school non-business day, the due date now moves to the previous business day instead of the next one; moving it later put it after the cutoff, so the installment was not required for that exam's permit (for example, Q1 2026-2027 on August 19: due Sunday August 9 moved to Monday August 10, past the cutoff). The class-start and monthly installments still move to the next business day. Due schedules already generated keep their dates.

The restricted support case page now also shows what has been recorded so far (review outcomes with rationale and next action, restricted notes, and the teacher-safe summary teachers currently see) above the append-only forms.

### D-054 - Capstone demonstration data on the hosted site

**Status:** Owner-requested on 2026-10-07 for the Hostinger capstone deployment ("start fresh").

**Decision:** `Database\Seeders\Capstone\CapstoneDemoSeeder` builds School Year 2026-2027 on an empty database through the same actions the screens use, with the clock set back to when each step would have happened (year and curriculum in April, teachers and fees in May, walk-ins May to July, Q1 permits in August, Q1 grades late August, a few Q2 permits in early October; nothing after 2026-10-06). It creates 20 teachers (one adviser per official section, also subject teachers with conflict-free loads), the fee configuration from the school's posted fee sheet (Pre-elementary 18,088.25; Grades 1-6 19,903.25; Grades 7-10 21,850.00 required, plus books, PE uniform and graduation/moving-up fees), all four plan types per grade, the four calendar examination periods, weekday holidays as non-business days, 26 enrolled learners (14 in Grades 7-10, 12 in Nursery to Grade 6 including three support-review learners with full restricted cases and teacher-safe summaries), two applications still in progress, payments by plan and paying habit, permits where eligible, Q1 draft grades, and portal announcements.

All people are fictional; emails use the non-deliverable `gmail.test` domain and nobody has a mobile number, so live SMS and mail cannot reach anyone. Every account's password is `Password12345` (demonstration only). `php artisan capstone:snapshot-staff`, run before `migrate:fresh`, records the existing Admin, Registrar and Accounting accounts (name, email, mobile, Staff ID, roles; never passwords) in `storage/app/private/capstone/staff-snapshot.json`; the seeder recreates them from it and deletes the file. Without a snapshot it creates the hosted site's staff from the 2026-10-07 Hostinger backup: Maria Dela Cruz (Admin, 26-JCA-100), Jenny Santos (Registrar, 26-JCA-101) and Angelica Garcia (Accounting, 26-JCA-102). Accounts without a Staff ID in the 26-JCA-000 format (the hosted Admin had none; the others had HST-REG-001 / HST-ACC-001) get the role's usual number. The seeder refuses to run when any school year or enrollment exists.

### D-055 - User accounts have no Locked status

**Status:** Owner-requested on 2026-10-27 after noticing "Locked" in the User management status filter.

**Decision:** No workflow ever set an account to `locked`: sign-in throttling is a short cache-based rate limit that never changes the account. The `locked` value was removed from `AccountStatus`, the User management status filter, and the Admin dashboard account summary (which now shows Disabled). Account statuses are `invited`, `active`, and `disabled`. The `users.locked_at` column and its fail-closed checks (sign-in, setup links, reactivation, enrollment connection) stay as a dormant guard, so no migration is needed. A real lockout feature would need its own decision.

### D-056 - Nursery walk-in demonstration enrollment and preschool daily activities

**Status:** Owner-requested on 2026-10-27 for the local development database.

**Decision:** `Database\Seeders\Enrollment\NurseryWalkInSeeder` adds one fictional Nursery learner (Althea Joy Rivera Manalo, enrolled by her mother) to an existing open 2026-2027 year through the same actions the screens use, dated 21-23 October 2026: walk-in access from the Registrar, first sign-in with the temporary password and a private password, the completed and submitted application, PSA birth certificate received and verified, SF9 waived with a reason because Nursery is the learner's first school year, approval, placement in Nursery Hope, a finalized assessment on the posted Pre-elementary fee sheet with books and PE uniform, the monthly plan, and a posted cash payment of the installments already due. When Nursery Hope has no adviser it creates the roster teacher Ma. Lourdes Fernandez (18-JCA-946) and assigns her; when Nursery has no published fee schedule or plan options it creates them from `CapstoneFinance`. Existing setup is reused, a second run is rejected by the walk-in duplicate check, nobody gets a mobile number, and emails use `gmail.test`.

Preschool sections have no subject offerings; their class program is the section's activity schedule. The guardian progress page, the student progress page, the printed section sheet, and the teacher's My teaching page now show it as "Daily activities", one item per line (split at semicolons and sentence ends by `Section::activityLines()`), instead of saying that no schedule is configured. On the Admin curriculum page and the Academics section details, the same program is shown as an Activity · Time table with the class hours (`ActivityProgram::parse()`: each activity ends when the next starts), like the subject timetables of Grade 1 and up. No preschool subjects were invented.

### D-057 - Indigenous Peoples question with a group list on the application

**Status:** Owner-requested on 2026-10-27. Amends the optional Indigenous Peoples field in D-022.

**Decision:** The enrollment application asks "Is the learner a member of an Indigenous Cultural Community/Indigenous Peoples group?" as a required Yes/No, like the 4Ps and disability questions. Yes reveals a dropdown of Philippine Indigenous Peoples groups (`LearnerProfileOptions::INDIGENOUS_PEOPLES`, grouped by area) with "Other (please specify)" and a name field. The group's name is still stored in `student_profiles.ip_group`; No stores nothing, so the answer itself needs no new column and no migration. A saved name that is not on the list reappears under "Other". An application never saved shows the question unanswered. The list is reference data for convenience, not an official NCIP/DepEd code list; JCA may confirm or extend it. The Registrar's student-record correction form asks the same question through the shared `<x-people.indigenous-peoples-question>` component and `ValidatesIndigenousPeoplesAnswer` rules; a changed group is recorded in the correction history like any other field.

### D-058 - Grade 8 demonstration teachers and approved final grades

**Status:** Owner-requested on 2026-10-27 for the local development database, to see how released final grades look to a learner.

**Decision:** `Database\Seeders\Grading\GradeEightFinalGradesSeeder` works on every active Grade 8 section of the open 2026-2027 year through the screens' actions. The Admin assigns a subject teacher to each offering without one: subjects in usual pairs (English/Filipino, Mathematics/Science, Araling Panlipunan/EsP, TLE/MAPEH), the teacher who already handles them in Grade 8 first and otherwise the least-loaded one, skipping anyone the schedule check finds busy and anyone advising a preschool section (their batch hours are not recorded as subject classes). A section without an adviser gets its first subject teacher as adviser. Each subject teacher then saves all four quarter grades (repeatable values of 75-98 per learner and subject; any quarter already entered is kept) and submits them, and the Registrar approves them. Finalized grades are never changed, so a rerun adds nothing. Quarters 2-4 have not happened yet in the real school year; these are demonstration grades recorded on the day the seeder runs.

### D-059 - Audit trail: masked old/new values, append-only entries, and a learner change history

**Status:** Owner-requested on 2026-10-27 after the capstone defense recommendation to log the user, date/time, action, affected record, and old/new values of important updates ("If a student's contact number or enrollment status is changed, how can the school determine who changed it and when?"). Amends D-016 and the Phase 8 reduced audit view.

**Decision:** `AuditRecorder::recordChanges()` records an update with the names of every changed field and, for an allowlist of fields, old/new values passed through `AuditValueMask`: mobile numbers keep the network prefix and last four digits (`+63917•••4567`), emails keep the first letter and domain (`j•••@gmail.com`). Other personal fields (names, addresses, birth place) are named but their values stay out of the log; the Registrar's correction table keeps those before/after values as before. Parent/guardian details and the learner's own details saved from the enrollment application now use it (`people.guardian.details_completed`, new `people.student.application_details_saved`), so a contact-number change shows who changed it, when, and the masked old and new numbers. Enrollment status changes already store from/to in the status history and the audit entry.

Audit entries are append-only: the `AuditLog` model refuses updates and deletes. (Database-level triggers were not added because shared hosting may not grant the privilege; the application has no edit or delete path.)

Screens show only these metadata keys, through `AuditChangeSummary`: `fields`, `changes`, and `from`/`to`. Any other metadata (reasons, provider details) and the request IP and browser stay hidden. The Admin audit entry page gains a "What changed" list. The Registrar's student record gains a collapsible "Change history": the latest 50 change events for the learner profile, their enrollments, requirement checklists, section placements, family links, and connected parent/guardian profiles, each with the action, the account, and the Asia/Manila time. Reads (record views, document previews/downloads) and other modules' events (support, grading, billing) are not listed there. No migration: values fit the existing `metadata` JSON column.

### D-060 - Built-in encrypted backup and restore for the Hostinger capstone

**Status:** Owner-requested on 2026-10-27 after the client asked whether the database and uploaded documents are backed up automatically and can be restored. **Amended by D-062 (3):** one automatic daily backup now runs as well. Partially addresses P-007 for the capstone; the production schedule, retention, RPO/RTO, and offsite owner stay pending.

**Decision:** The application backs up the database and `storage/app/private` together as one encrypted file, with PHP only (PDO dump, ZipArchive, libsodium secretstream), so the same commands work locally and on Hostinger shared hosting where `mysqldump` and PHP shell access cannot be relied on. Backups are made only by hand: the Admin's "Back up now" (or `php artisan jca:backup`), plus the automatic safety backup taken just before a restore. There is no nightly or scheduled backup (owner decision of 2026-10-28); the Backups page reminds the Admin when the newest backup is more than `BACKUP_REMIND_AFTER_DAYS` (default 7) days old. The newest 14 files are kept in `storage/app/backups` (not web-served) and older ones are removed. Session, cache, and queue rows are never backed up or restored, so a restore cannot replay queued SMS. The key is `BACKUP_ENCRYPTION_KEY` or, by default, derived from `APP_KEY`; the key must be kept separately offsite. `php artisan jca:restore` verifies every checksum first, supports `--inspect` and an isolated `--drill-database` rehearsal, and restores in place only after confirmation, a pre-restore safety backup, maintenance mode, and moving the current documents aside instead of deleting them. Admins see the last successful backup, create one on demand, download files for offsite keeping (audited), and restore a file from Admin > Backups after a pop-up confirmation: ticking that every record will be replaced, typing RESTORE, and re-entering their password (owner request of 2026-10-28). Command-line and web restores share one live-restore path: verify file and key, audit the request, safety backup, maintenance mode, restore, automatic rollback to the safety backup on failure, one restore at a time, and everyone signs in again afterwards. Hostinger's hPanel backups remain an independent second layer. `BACKUP_ENCRYPTION_CONFIGURED`, `BACKUP_OFFSITE_CONFIGURED`, and `BACKUP_MONITORING_CONFIGURED` stay owner attestations for the production gate.


### D-061 - Panel UX feedback: staff-only finance, learner exits via SF10 / DepEd LIS, and trail-based Back navigation

**Status:** Owner-requested on 2026-11-03 after the evaluation panel said the system was hard to use ("Sobrang hirap gamitin ng system niyo, napakaligoy/gulo"). **Item (1) narrowed by D-064 on 2026-11-16:** families get a read-only summary of their own learners; the staff financial pages stay staff-only.

**Decision:** (1) Financial records (portal, assessment detail, statements, receipts) are restricted to admin, accounting and registrar; students and guardians no longer see or reach them. Exam permits stay printable because they carry no financial detail. This supersedes the guardian/student financial view enabled by `2026_10_07_000031_let_enrolling_guardians_view_finances` (the `may_view_financials` data is kept, no migration). (2) "My Information" is labelled "Student Information". (3) A learner leaves the school by Transfer Out or Drop. Transfer Out records the receiving school and how the SF10 (formerly Form 137) is released: sent school-to-school, mailed, or hand-carried by the student/guardian when the receiving school is far. Drop requires confirmation that the drop was already done directly in DepEd LIS. Both set `student_profiles.status` (`transferred_out` / `dropped`), archive the profile, keep enrollment, grade, section, requirement and finance history untouched, and are audited (`people.student.transferred_out`, `people.student.dropped`). Screens show Transferred Out / Dropped instead of Enrolled. No schema change: details are kept in `archive_reason` and the audit metadata. (4) Back navigation uses a per-tab trail in sessionStorage because `Referrer-Policy: no-referrer` (kept) made the referrer-based Back always fall back to the Dashboard; pages opened directly fall back to the nearest parent GET route.

### D-062 - Rules confirmed while repairing the regression suite (pre-final panel revisions)

**Status:** Owner-confirmed on 2026-11-15. The local build already behaved this way; the owner chose to keep it, and the tests and documents now follow.

**Decision:**

1. **Enrollment requirements are hard copy only** (supersedes D-025). There is no soft-copy upload or soft-copy review route. A blocking requirement clears for approval only with a recorded hard-copy receipt that the Registrar verifies, or an Admin waiver with a reason. Promotion carries forward only requirements with a recorded hard-copy receipt (D-026), so an old soft-copy-only verification is asked for again. Files uploaded before this change stay private and viewable through the protected preview and download routes.
2. **A payment may not exceed the remaining balance.** Draft, update and posting all refuse an amount above the assessment's current due (`ValidatePaymentBalance`). Student credit therefore no longer comes from overpayment; it arises only from approved corrections (credit or waiver adjustments) or reversals after payment, and the existing refund and same-student credit allocation rules still apply to it. This narrows P6-07.
3. **Backups:** besides "Back up now", one encrypted automatic backup runs daily at 02:00 Asia/Manila (`jca:backup --label=automatic-daily`). On shared hosting it runs from a direct hPanel cron entry.
4. **Sign-in IDs.** Grade 7-10 learners sign in with their Student ID. Their parent/guardian gets a separate account at the counter and signs in with a Parent ID `PAR-YY-NNN` (001-999), stored on `guardian_profiles.parent_login_id`; `users.public_id` stays an opaque ULID. A Nursery-Grade 6 parent signs in with the child's Student ID and their own password, and keeps doing so after the Registrar grants the child an account. Sign-in picks the one account whose password matches and fails closed when two do.
5. **School-year gating.** Saving or submitting an application needs the school year `open`, its enrollment switch (`enrollment_is_open`) on, and today inside the enrollment dates. A new year's switch starts off.
6. **The application requires the parent/guardian mobile and the learner's mobile.** Saving it stores the learner details, the parents' names, the learner mobile, the Indigenous Peoples group, the guardian details and contact, and the SMS opt-ins. It flags a guardian contact that already belongs to another family for the Registrar's duplicate review.

**Consequences:** P-002/P6-07 production confirmation is still pending for the overpayment rule. The panel's request for a student/parent financial summary (pre-final defense) is planned as its own change and will revisit D-061 (1).

### D-063 - Report filters, column choice, and printing selected rows

**Status:** Implemented on 2026-11-16 for the pre-final defense panel ("complete reports // filtering para mas specific siya kung ano at sino lang ang gusto mong iprint at tignan"; accounting "must add report section filter"; registrar "print button ... para ito sa specific print na gusto mo lang ma print out").

**Decision:** Every student-level report (enrollments, students, requirements, billing, payments, discounts, permits, grades) filters by school year, grade, section (the learner's current confirmed section), learner name or Student ID, and a status picked from the statuses that exist. The grades report adds final-grade bands (below 75, 75-79, 80-84, 85-89, 90-94, 95-100) and a custom range. Any listed column can sort the result. The viewer picks which allowlisted columns to show; the same choice applies to the print view and the private CSV. Rows can be ticked and printed alone. Printouts show the filters used, peso totals for money columns, and Prepared/Checked/Noted lines.

**Consequences:** Columns are still limited to each report's allowlist, and a column outside it is refused. Ticked rows are identified by public ULIDs (or the enrollment ULID plus document code for requirement rows) and are looked up inside the viewer's own scoped, filtered query, so a crafted key cannot reach another section or family. Print audit entries record the row count, whether rows were hand-picked, and the printed field names. Official signatories and form layouts remain pending under P-006.

### D-064 - Parents/guardians and students see a read-only financial summary of their own learners

**Status:** Owner-approved on 2026-11-16 from the pre-final defense panel ("financial record or summary on parent and student portal: breakdown"; "financial summary every portal student and parent to monitor their payment also"). Narrows D-061 (1).

**Decision:** A new "Financial summary" page (`/my-finances`, `billing.family.index`) shows, per finalized assessment: total fees after discounts, paid so far, remaining balance (and credit), the next payment and any overdue amount, the fee breakdown, the payment schedule with each installment's status (paid, partly paid, overdue, upcoming; payments are applied to installments in due-date order), and posted payments with their official receipt numbers. A student sees their own record; a parent/guardian sees learners linked by a verified, current link that allows the financial view (`may_view_financials`, D-053). The staff pages (`/finance`, assessment detail, statements, receipts, ledgers) stay staff-only as in D-061.

**Consequences:** Only finalized assessments appear; drafts, adjustment reasons, payer names, bank details, evidence files, permit overrides and internal notes are never shown. Each viewed assessment is audited as `billing.financial_summary.viewed` with `view: family`.

### D-065 - School insights for the owner, Admin and Accounting

**Status:** Implemented on 2026-11-16 for the pre-final defense panel ("predictive analytics reports ... mataas ang enrollment, mataas ba ang kita compared last year last month, collected and uncollected ... para maging helpful siya for the accounting to easy report to the owner"; "demographics // graphs").

**Decision:** A "School insights" page (`/reports/insights`, Admin and Accounting) compares a chosen school year with the previous one: enrolled learners, fees assessed, collected, uncollected and the collection rate (the same arithmetic as `BalanceCalculator`, aggregated in one query); collections by month against the same month last year; collections against what the payment plans scheduled; this month against last month; enrollment per grade; learners by sex per grade and by category. Two labelled end-of-year estimates are shown: "if every remaining installment is paid on time" and "at the last three months' pace", both capped at what was assessed. The page prints with the letterhead and Prepared/Checked/Noted lines for the owner. Charts are plain HTML/CSS bars (no chart library) with a legend, values at the bar tips, a table view, and screen-reader text.

**Consequences:** Only aggregate counts and amounts are shown, never learner-level rows. Views and prints are audited as `reporting.analytics.viewed`. The estimates are planning aids, not forecasts the school has approved.

### D-066 - Structured learner exits and the retention report

**Status:** Implemented on 2026-11-16 for the pre-final defense panel ("retention reports // ilan yung bumabagsak, ilan yung nag shishift, ilan yung nag drodropout/kick out, yung mga nag enrolled pero di naman bumalik / transfers must be also in detailed").

**Decision:** Learner exits are also stored as data on `student_profiles`: `exit_category` (`transferred_out`, `dropped_out`, `excluded`, `other_drop`), `dropped_at`, and `exit_receiving_school`. The Drop form asks for the kind of drop (dropped out / excluded or expelled by the school / other). Migration `2026_11_16_000001` backfills transfers and drop dates from `archive_reason` where the text allows; earlier drops have no category and appear as "Dropped (kind not recorded)". A "Retention and exits" report (Admin, Registrar) lists one row per learner and outcome for a school year: dropped out, excluded, other drop, transferred out (with the receiving school), did not return (enrolled last year, not graduated or exited, no application this year), repeating the same grade, changed section during the year ("nag-shift", from the section history), and final grade below 75. A summary gives counts per outcome and the retention rate (last year's non-graduating learners who came back). It uses the shared report filters, column choice, row selection, print and CSV (D-063). The Student directory gains "Print directory" (the Students report with the directory's year, grade and search) and links the archive to this report.

**Consequences:** "Below 75" is a plain fact from finalized grades using the DepEd numeric threshold; it is not the school's promotion or retention rule, which stays pending under P-010. "Changed section" reads "nag-shift" as a section move within the year; a change of program does not exist for Nursery-Grade 10. Exits keep their enrollment records (D-061), so they still count as enrolled for the year they left.

### D-067 - Sibling discount confirmed by the Registrar, applied by Accounting

**Status:** Owner-chosen on 2026-11-16 for the pre-final defense panel ("discounts confirm in siblings JCA sa family links"; Admin "discounts // confirm").

**Decision:** Two learners are siblings when the same parent/guardian has a verified, current link to both and both have a current, submitted enrollment in the same school year. Family links shows each learner's siblings at JCA this school year, and the Registrar or Admin confirms "Sibling discount" for an enrollment (`sibling_discount_confirmations`, one active row per enrollment through `active_key`, a snapshot of the siblings, who and when). Withdrawing needs a reason and keeps the row. Accounting sees the confirmation on the learner's financial summary and applies the school's sibling discount through the existing credit-adjustment request and Admin approval. No peso amount, percentage or eligible-child rule is coded; the value stays the school's decision (P-002).

**Consequences:** Confirmation and withdrawal are audited (`billing.sibling_discount.confirmed` / `withdrawn`). A learner without a qualifying sibling cannot be confirmed. Accounting cannot confirm; the Registrar cannot apply money.

### D-068 - Schedule conflicts view and the teacher's own weekly schedule

**Status:** Implemented on 2026-11-16 for the pre-final defense panel (Registrar and Teacher: "conflict schedules, nakikita ba? need dapat meron yon"; "also need makita ng teachers ang kanilang schedules as well").

**Decision:** A "Schedule conflicts" page (Registrar, Admin) checks every active class offering of a chosen school year and lists (1) a teacher assigned to two classes that meet at the same time, (2) two subjects of one section that meet at the same time, and (3) offerings with no schedule or a typed note that cannot be read as days and times, so they cannot be checked. Each entry links to that section in Academics to fix it. A "My schedule" page (Teacher) shows the teacher's own subject classes by weekday in time order, marks any class that overlaps another of theirs, lists classes without a set time and the sections they advise, and prints. Both reuse `ClassSchedule` (structured days/times, or a parsed schedule note) and the same overlap rule as the assignment guard.

**Consequences:** The guard that blocks a conflicting teacher assignment is unchanged; this view also catches conflicts that arrive through schedule edits, curriculum publishing or imported data. Rooms are not tracked (Phase 14), so room clashes are out of scope. A teacher sees only their own classes.

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
- Learning management, online classes, attendance, payroll, HR, library, and inventory systems. Four-quarter grading for Grades 1–10 is implemented under D-038; preschool assessment and promotion policy remain pending.
- Native mobile applications.
- Biometric login, social login, and public APIs for third parties.
- Automatic clinical or special-needs diagnosis, scoring, or placement decisions.
