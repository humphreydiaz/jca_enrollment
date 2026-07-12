# JCA Phase 6 Financial Policy Questionnaire

## Purpose

This questionnaire collects the school policies required before JCA's offline billing, discount, payment-ledger, and exam-permit functions can be designed safely. It does **not** authorize online payments. Please answer for the first school year that will use the system and attach approved fee sheets, calendars, receipt samples, and permit samples where requested.

**Current status (2026-07-11):** P6-01 through P6-21 are **Assumed – Owner-authorized for development** and the configurable Phase 6 workflows are implemented/tested against fictional fixtures. In this questionnaire, “blocked until confirmed” now means blocked for production configuration or official output—not blocked for development. No listed value is represented as accepted JCSGO production policy.

Already settled: academic enrollment and financial standing remain separate; the system records school-collected payments only; posted payments are never silently edited or deleted; and exceptional overrides require reasons and audit history.

## A. Fees and assessments

### 1. Official fee types

- **Decision required:** List every charge JCA may assess, its official name/code, category, whether it is mandatory or optional, and whether quantity may exceed one.
- **Why it matters:** These become fee-catalog records and determine assessment line behavior and reporting.
- **Recommended default:** Separate tuition, miscellaneous, books, PE uniform, logo/tela, Bible, graduation/recognition, and any other approved charge; deactivate old types instead of deleting them.
- **Alternatives:** One combined school fee; more detailed component fees; grade-specific fee types.
- **Blocked until confirmed:** Fee-type catalog, assessment lines, and fee-based reports/printouts.

### 2. Official amounts and grade-level schedules

- **Decision required:** Supply the approved amount for every fee by school year, grade level, student category, and any other eligibility condition.
- **Why it matters:** Determines schedule tables, matching rules, exact-decimal assessments, and historical snapshots.
- **Recommended default:** One published, versioned fee schedule per school year and grade level, with explicit effective dates and PHP amounts.
- **Alternatives:** One school-wide schedule; schedules by school stage; category-specific supplements.
- **Blocked until confirmed:** Fee schedules, assessment generation, totals, and balances.

### 3. Assessment timing and revision

- **Decision required:** When is an assessment created/finalized, who may revise a draft, and how are corrections made after finalization?
- **Why it matters:** Defines assessment statuses, immutable snapshots, revision links, and permissions.
- **Recommended default:** Accounting creates a draft after academic approval; finalization freezes it; later corrections create a replacement assessment with reason and history.
- **Alternatives:** Auto-create at approval; Registrar initiates a draft; Admin-only finalized revisions.
- **Blocked until confirmed:** Assessment lifecycle, revision model, and authorization tests.

## B. Payment plans and due dates

### 4. Available payment plans

- **Decision required:** Confirm which plans are offered per grade/year: full, semi-annual, quarterly, monthly, or another approved plan.
- **Why it matters:** Determines plan catalog, schedule generation, and balance/due calculations.
- **Recommended default:** Data-driven full, semi-annual, quarterly, and monthly plans; only plans explicitly enabled for a schedule are selectable.
- **Alternatives:** Fewer plans; custom installment count; individualized Admin-approved plan.
- **Blocked until confirmed:** Payment-plan selection and installment schedules.

### 5. Installment due dates and allocation

- **Decision required:** Provide exact due dates/amounts or the rule for calculating them, including weekends/holidays and partial-payment allocation order.
- **Why it matters:** Controls due items, overdue state, payment allocation, and permit eligibility.
- **Recommended default:** Store explicit due dates and amounts per published plan; allocate to the oldest due item first unless Accounting deliberately selects allocations.
- **Alternatives:** Day-of-month rules; equal installments with final rounding adjustment; mandatory manual allocation.
- **Blocked until confirmed:** Due schedules, overdue calculations, allocations, and permit checks.

### 6. Payment-to-follow policy

- **Decision required:** Who may select `payment_to_follow`, whether approval is required, when a plan must later be chosen, and whether it affects access or permits.
- **Why it matters:** It is a financial workflow state that must not change academic enrollment.
- **Recommended default:** Registrar records the applicant's choice; Accounting confirms it; no academic-enrollment block; ordinary due and permit rules apply once assessed.
- **Alternatives:** Admin approval required; grace deadline; unrestricted until first due date.
- **Blocked until confirmed:** Payment-to-follow transition, authorization, and guardian messaging.

### 7. Late, partial, advance, and overpayments

- **Decision required:** Confirm treatment of partial payments, advance payments, overpayments/credits, late payments, and penalties.
- **Why it matters:** Changes ledger states, allocation rules, credit handling, and balance calculations.
- **Recommended default:** Accept partial/advance payments; keep unapplied excess as a visible credit; add no penalty unless JCA supplies an approved fee rule.
- **Alternatives:** Reject overpayments; auto-apply credit to future dues; approved late-fee schedule.
- **Blocked until confirmed:** Edge-case posting and exact balance behavior.

## C. Discounts

### 8. Discount campaign definitions

- **Decision required:** For each campaign, provide school year, name/code, value (fixed amount or percentage), eligible fees, dates, slot limit, and eligibility conditions.
- **Why it matters:** Determines campaign/award schema, calculations, date checks, and snapshots.
- **Recommended default:** Versioned school-year campaigns with explicit eligible assessment items and no retroactive catalog edits.
- **Alternatives:** Assessment-wide percentage; fixed tuition-only amount; manually awarded discounts without slots.
- **Blocked until confirmed:** Discount catalog, award calculation, and assessment application.

### 9. Early-enrollee rank details

- **Decision required:** Confirm the slot limit (currently assumed 100), whether ranking resets each school year, tie handling, cancellation/recycling rules, and whether prior students may qualify.
- **Why it matters:** Controls unique rank allocation, locking, and historical awards.
- **Recommended default:** Rank at the first transition to `approved`, reset per campaign/school year, break ties by committed approval time then enrollment ID, and never recycle a cancelled award without an audited Admin action.
- **Alternatives:** First payment; completed requirements time; automatic slot recycling; separate limits per grade.
- **Blocked until confirmed:** Concurrency-safe allocation and campaign-rank constraints.

### 10. Discount combination and priority

- **Decision required:** May discounts stack? If so, in what order, with what cap, and which combinations are prohibited?
- **Why it matters:** Order changes monetary results and requires reproducible assessment snapshots.
- **Recommended default:** No stacking unless a campaign explicitly permits named combinations; total discounts cannot reduce an eligible item below zero.
- **Alternatives:** Best single discount; fixed-before-percentage; percentage-before-fixed; global maximum percentage.
- **Blocked until confirmed:** Multi-discount calculations and validation.

### 11. Discount cancellation and adjustment

- **Decision required:** Who may cancel/correct an award, required reasons, and whether a replacement award is permitted.
- **Why it matters:** Awards must retain rank/history and cannot be silently deleted.
- **Recommended default:** Admin-only cancellation with reason; retain original award; create an explicit replacement/adjustment if approved.
- **Alternatives:** Accounting requests and Admin approves; automatic cancellation on enrollment withdrawal.
- **Blocked until confirmed:** Award reversal events and assessment adjustments.

## D. Receipts and payment ledger

### 12. Receipt/reference rules

- **Decision required:** Provide receipt type(s), official number format, uniqueness scope, manual vs system numbering, required fields, reprint markings, and a sample receipt.
- **Why it matters:** Determines unique keys, public references, validation, and printable content.
- **Recommended default:** Accounting enters the official school receipt number; it is unique school-wide; the system also creates an immutable internal public payment reference; reprints are marked as reprints.
- **Alternatives:** Separate series by school year/cashier; system-generated receipt number; multiple receipt types.
- **Blocked until confirmed:** Payment posting, receipt uniqueness, and receipt printing.

### 13. Payment methods and evidence

- **Decision required:** Confirm allowed methods and method-specific references/evidence. No bank/card credentials should be stored.
- **Why it matters:** Determines method values, required validation, and safe metadata.
- **Recommended default:** Cash only for the first release unless JCA explicitly approves other offline methods and their safe reference fields.
- **Alternatives:** Cash, bank deposit, transfer, cheque, or approved offline channels.
- **Blocked until confirmed:** Payment-entry fields and method validation.

### 14. Posting permissions and separation of duties

- **Decision required:** Who may create drafts, post payments, collect funds, view ledgers, and reopen a draft? May one person collect and post?
- **Why it matters:** Defines policies, actor fields, audit events, and session-level authorization.
- **Recommended default:** Accounting creates/posts; Admin may view and perform exceptional approvals; Registrar views summary only; collecting and posting actors are separately recorded even if the same authorized person performs both.
- **Alternatives:** Admin may post; two-person posting approval; cashier-specific role added only if formally approved.
- **Blocked until confirmed:** Payment authorization matrix and posting workflow.

### 15. Reversals, voids, refunds, and adjustments

- **Decision required:** Define allowed correction types, requester/approver roles, required evidence/reasons, full vs partial reversal, refund handling, and replacement receipt rules.
- **Why it matters:** Determines append-only ledger entries, approval states, links, and balance effects.
- **Recommended default:** Accounting requests; Admin approves/posts; original remains immutable; partial/full reversal and adjustment are separate records; refunds are not implemented unless JCA defines them.
- **Alternatives:** Admin directly reverses; second Admin approval; correction by compensating adjustment only.
- **Blocked until confirmed:** Reversal/adjustment schema, permissions, and recalculation.

## E. Exam permits

### 16. Exam periods

- **Decision required:** List exam periods per school year, their dates, issue window, official label, and whether permits have unique references.
- **Why it matters:** Defines exam-period records, permit uniqueness, issue timing, and print layout.
- **Recommended default:** Configurable school-year periods with explicit eligibility cutoff and issue dates; one permit record per enrollment/period.
- **Alternatives:** Grade-specific periods; multiple assessments within one period; no stored permit reference.
- **Blocked until confirmed:** Exam-period catalog and permit issuance.

### 17. Permit eligibility threshold

- **Decision required:** State the exact financial rule for each period: required paid amount/percentage, due items that must be settled, grace treatment, credits, and cutoff time.
- **Why it matters:** Permit results must be reproducible from ledger facts, not manually inferred.
- **Recommended default:** Eligibility means all installments due on or before the period cutoff are fully settled by posted payments/approved credits; future installments do not block.
- **Alternatives:** Percentage of net assessment; fixed minimum balance; named required fees; Admin/manual determination.
- **Blocked until confirmed:** Eligibility calculation and permit status.

### 18. Permit overrides

- **Decision required:** Who may override ineligible status, required reason/evidence, expiry, and whether a second approval is required.
- **Why it matters:** Determines override fields, authorization, audit history, and visible print indicators.
- **Recommended default:** Admin only, mandatory reason, actor/time recorded, applies to one enrollment and exam period, and is visibly marked in staff audit views.
- **Alternatives:** Admin plus Accounting approval; Registrar request/Admin approval; time-limited override.
- **Blocked until confirmed:** Override workflow and audit/print behavior.

## F. Visibility and outputs

### 19. Guardian and student financial visibility

- **Decision required:** What may Guardians and Students see: assessment items, discounts, plan, due dates, payments, receipt references, balance, overdue state, permit eligibility, or downloadable documents? Confirm relationship permissions.
- **Why it matters:** Defines row/field-level policies and prevents cross-family disclosure.
- **Recommended default:** Guardians with active `may_view_financials` see linked-child summaries, due items, posted payments, balance, and receipt references; Students see no financial data by default; neither sees staff notes, reversal rationale, or other families.
- **Alternatives:** Student visibility for Grades 7–10; balance-only guardian view; downloadable receipts enabled.
- **Blocked until confirmed:** Portal financial queries, policy tests, and displayed fields.

### 20. Required financial printouts

- **Decision required:** List required documents and approved layouts: statement of account, assessment, payment schedule, receipt, exam permit, ledger, collection report, or others. Provide titles, fields, signatories, copy labels, paper size, and numbering rules.
- **Why it matters:** Printouts require explicit data allowlists, authorization, references, and stable snapshots.
- **Recommended default:** Start with assessment/statement of account, payment schedule, official receipt copy, and exam permit; omit signatories until JCA supplies approved names/titles or signature rules.
- **Alternatives:** Combined assessment/SOA; cashier collection summary; no guardian-downloadable printouts.
- **Blocked until confirmed:** Print routes, templates, content tests, and signatory/reference fields.

## G. Implementation prerequisite

### 21. Eligible enrollment prerequisite

- **Decision required:** Confirm which enrollment status allows assessment and whether section confirmation is required first.
- **Why it matters:** The inspected branch does not contain a Section Assignment module, and Billing must not guess its prerequisite or assess rejected/cancelled applications.
- **Recommended default:** Create assessments only for academically `approved` or `enrolled` records; do not require payment or section confirmation to create an assessment.
- **Alternatives:** Assessment at submitted/under-review state; require finalized enrollment; require section confirmation.
- **Blocked until confirmed:** Safe linkage between Enrollment and Billing and the assessment queue.

## Requested attachments

Please return this questionnaire with:

- approved fee sheets per school year/grade;
- installment calendar;
- discount memo/campaign rules;
- blank official receipt sample;
- exam calendar and permit sample;
- approved financial forms/printouts;
- role/approval matrix for Accounting and Admin.

All answers remain pending until recorded as accepted decisions in `docs/DECISIONS.md`.
