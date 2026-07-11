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

**Decision:** Do not allow unrestricted public account registration. A guardian may begin a minimal enrollment application without a login. After the submission is verified as legitimate, the school sends an invitation for an individual guardian account so the guardian can track progress before final enrollment. Student accounts are provisioned only after enrollment approval.

**Consequences:** Applicant records and user accounts are separate. An application can exist before a `users` row is linked. Invitations expire, are single-use, and must never contain a reusable plaintext password.

### D-003 - Guardian and student relationships

**Decision:** Every person has an individual account; credentials are never shared as a household account. A guardian can be linked to multiple students and a student can be linked to multiple guardians. Each link records relationship type, contact permissions, portal visibility, pickup/legal authority where applicable, and whether the guardian is the primary contact.

### D-004 - Student portal eligibility

**Decision:** Provision student accounts for Grades 7-10 after enrollment approval. Nursery through Grade 6 use guardian access by default. Admin may provision an exception with a recorded reason.

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

**Decision:** Plan the catalog for Nursery, Junior Kindergarten, Senior Kindergarten, and Grades 1-10. Store grade levels as configurable records and do not encode this list in authorization or workflow code.

**Status:** Assumed school offering based on the outline's enrollment banner and Elementary-to-Junior-High wording; must be confirmed before production data import.

### D-013 - Confidential document storage

**Decision:** Uploaded requirements and support documents are private files outside the public web root. The database stores metadata and opaque storage keys, not binary documents. Download is always mediated by authorization, logged, and returned with safe content headers.

### D-014 - Retention and deletion

**Decision:** Retention rules are configurable by document category. Until JCA approves a retention schedule, production deletion automation stays disabled and the absence of an approved schedule is a release blocker. The design must support legal/administrative holds, archival state, approved deletion, and deletion audit evidence.

**Consequences:** Development data may be disposable, but production documents must not be silently retained forever or automatically destroyed under an invented policy.

### D-015 - SMS delivery

**Decision:** Semaphore is the SMS provider. SMS is sent asynchronously through the database queue. Store templates, recipient, purpose, provider reference, delivery state, timestamps, and a redacted failure response. Do not store API keys or full sensitive document details in message bodies or logs.

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
| P-002 | Final fee schedules, installment due dates, discount values, and exam-permit thresholds | Before Billing phase acceptance | Data-driven rules; no hard-coded amounts |
| P-003 | Official section names, subjects, teaching assignments, and confirmed grade offerings | Before Academics phase production import | Seed only clearly labeled development fixtures |
| P-004 | Exact support-assessment form, decision labels, and authorized reviewer roster | Before official production support-form activation | Phase 4 uses restrictive access, reviewer assignment, neutral generic outcomes, private observations, history, and approved teacher-safe summaries only |
| P-005 | Official SMS sender name, approved templates, consent wording, and escalation contacts | Before enabling live SMS | SMS disabled by default; log driver/test fake |
| P-006 | Required official reports and signatories | Before Reporting phase acceptance | Build reusable filters and exports from normalized data |
| P-007 | Hosting, backup, recovery objectives, and production MySQL version | Before deployment readiness | Target MySQL 8.0+ and document assumptions |

## Pending Phase 6 questions

The following questions are **pending**. They expand P-002 for implementation planning only; none is confirmed. Detailed client wording, recommendations, alternatives, and blocked work are in [PHASE6_DECISION_QUESTIONNAIRE.md](PHASE6_DECISION_QUESTIONNAIRE.md).

| ID | Pending decision | Blocks |
|---|---|---|
| P6-01 | Official fee types, codes, categories, mandatory/optional behavior, and quantities | Fee catalog and assessment items |
| P6-02 | Approved fee amounts and schedules by school year, grade, and category | Assessment generation and totals |
| P6-03 | Assessment creation, finalization, revision, and replacement rules | Immutable assessment lifecycle |
| P6-04 | Offered payment plans by grade/schedule | Payment-plan catalog and selection |
| P6-05 | Exact due dates, installment amounts, calendar handling, and allocation order | Due schedule, overdue state, allocations |
| P6-06 | Payment-to-follow authority, approval, deadline, and downstream effects | Payment-to-follow workflow |
| P6-07 | Partial, advance, late, overpayment, credit, and penalty treatment | Ledger edge cases and balances |
| P6-08 | Discount campaigns, eligibility, value, applicable fees, dates, and limits | Campaign and award calculation |
| P6-09 | Early-enrollee limit, annual reset, tie-break, cancellation, and slot-recycling rules | Concurrency-safe rank allocation |
| P6-10 | Discount stacking, calculation order, exclusions, and caps | Combined discount calculation |
| P6-11 | Discount cancellation/correction authority and replacement rules | Award history and adjustments |
| P6-12 | Official receipt types, number format, uniqueness scope, issuance, and reprint rules | Payment posting and receipt printing |
| P6-13 | Allowed offline payment methods and safe evidence/reference fields | Payment-entry validation |
| P6-14 | Draft, collection, posting, ledger-view, and reopening permissions | Financial authorization matrix |
| P6-15 | Reversal, void, refund, and adjustment types, approval, evidence, and balance effect | Immutable correction workflow |
| P6-16 | Exam periods, issue windows, labels, dates, and permit references | Exam-period and permit records |
| P6-17 | Exact permit eligibility threshold, cutoff, dues, credits, and grace policy | Reproducible eligibility calculation |
| P6-18 | Permit override authority, evidence, duration, approval, and visibility | Override workflow and audit |
| P6-19 | Guardian/Student financial visibility and relationship permissions | Portal policies and field allowlists |
| P6-20 | Required financial printouts, approved fields, layouts, numbering, paper, and signatories | Print routes/templates/tests |
| P6-21 | Enrollment status and section prerequisite for assessment creation | Enrollment-to-Billing boundary |

## Pending Phase 6 questions

The following questions are **pending**. They expand P-002 for implementation planning only; none is confirmed. Detailed client wording, recommendations, alternatives, and blocked work are in [PHASE6_DECISION_QUESTIONNAIRE.md](PHASE6_DECISION_QUESTIONNAIRE.md).

| ID | Pending decision | Blocks |
|---|---|---|
| P6-01 | Official fee types, codes, categories, mandatory/optional behavior, and quantities | Fee catalog and assessment items |
| P6-02 | Approved fee amounts and schedules by school year, grade, and category | Assessment generation and totals |
| P6-03 | Assessment creation, finalization, revision, and replacement rules | Immutable assessment lifecycle |
| P6-04 | Offered payment plans by grade/schedule | Payment-plan catalog and selection |
| P6-05 | Exact due dates, installment amounts, calendar handling, and allocation order | Due schedule, overdue state, allocations |
| P6-06 | Payment-to-follow authority, approval, deadline, and downstream effects | Payment-to-follow workflow |
| P6-07 | Partial, advance, late, overpayment, credit, and penalty treatment | Ledger edge cases and balances |
| P6-08 | Discount campaigns, eligibility, value, applicable fees, dates, and limits | Campaign and award calculation |
| P6-09 | Early-enrollee limit, annual reset, tie-break, cancellation, and slot-recycling rules | Concurrency-safe rank allocation |
| P6-10 | Discount stacking, calculation order, exclusions, and caps | Combined discount calculation |
| P6-11 | Discount cancellation/correction authority and replacement rules | Award history and adjustments |
| P6-12 | Official receipt types, number format, uniqueness scope, issuance, and reprint rules | Payment posting and receipt printing |
| P6-13 | Allowed offline payment methods and safe evidence/reference fields | Payment-entry validation |
| P6-14 | Draft, collection, posting, ledger-view, and reopening permissions | Financial authorization matrix |
| P6-15 | Reversal, void, refund, and adjustment types, approval, evidence, and balance effect | Immutable correction workflow |
| P6-16 | Exam periods, issue windows, labels, dates, and permit references | Exam-period and permit records |
| P6-17 | Exact permit eligibility threshold, cutoff, dues, credits, and grace policy | Reproducible eligibility calculation |
| P6-18 | Permit override authority, evidence, duration, approval, and visibility | Override workflow and audit |
| P6-19 | Guardian/Student financial visibility and relationship permissions | Portal policies and field allowlists |
| P6-20 | Required financial printouts, approved fields, layouts, numbering, paper, and signatories | Print routes/templates/tests |
| P6-21 | Enrollment status and section prerequisite for assessment creation | Enrollment-to-Billing boundary |

## Out-of-scope decisions

- Online payments and payment gateways.
- Learning management, online classes, grading, attendance, payroll, HR, library, and inventory systems.
- Native mobile applications.
- Biometric login, social login, and public APIs for third parties.
- Automatic clinical or special-needs diagnosis, scoring, or placement decisions.
