# Phase 6 implemented development policy

## Status

P-002 and P6-01 through P6-21 are **Assumed – Owner-authorized for development**. They satisfy the development and automated-testing gate only. Official JCSGO approval is still required before production configuration is activated.

## Implemented contracts

- Fee types and versioned grade/year schedules are database configuration. Finalized assessment lines snapshot identity, quantity, exact amounts, discounts, and permit-critical status.
- Assessments support idempotent editable drafts plus finalized, superseded, and cancelled history. A no-payment revision points to its predecessor; posted history is corrected only by append-only Admin-approved entries. Published fee schedules and finalized snapshots cannot be rewritten.
- Payment plan options are unique by school year, grade scope, and plan type. Admin deactivates an option with an audited reason instead of deleting it; deactivation affects future selections and preserves historical assessments and payments. A plan controls due timing only—a matching published fee schedule and finalized student assessment are still required before collection.
- Full, semiannual, quarterly, and monthly due schedules use integer centavos. One-time charges enter the first installment, the final split absorbs rounding, and configured non-business days move forward.
- Payment-to-follow requires a selected plan. Accounting may approve the 15-day baseline; an independent Admin and reason are required for a longer extension. It never grants permit eligibility.
- The development early-enrollee campaign is tuition-only, one campaign per enrollment, ranked under a campaign row lock at first approval, capped by configured slots, and not automatically recycled.
- Editable offline payment drafts support cash, cheque, bank transfer/deposit, e-wallet, and other configured descriptions. Sensitive credential fields are prohibited. Posting assigns locked yearly payment/receipt references. Default allocations prioritize permit-critical obligations; explicitly allocated same-student/same-year credit is append-only and cross-student transfer is denied.
- Posted payments and allocations are immutable. Partial reversal allocations preserve exactly which obligations were reversed. Debit, credit, waiver, and refund decisions retain request/decision actors and reasons.
- Exam eligibility compares permit-critical due obligations at the configured cutoff with net posted critical allocations. Each calculation is stored. Configurable issue windows apply. Restricted approved overrides can authorize issuance without exposing their explanation; permits and overrides retain revocation/replacement history.
- Financial views are server-scoped for Admin, Accounting, Registrar, assigned Teacher, linked Guardian, and self Student. Private evidence uses opaque generated storage names on the private documents disk; generic Laravel local-storage serving routes are disabled.
- Statement, receipt, exam-permit, and internal-ledger print routes have explicit allowlists and audit entries. Receipt and permit print histories increment without replacing the original reference.

## Development fixtures

`Database\Seeders\Billing\BillingDevelopmentSeeder` is opt-in and is not called by `DatabaseSeeder`. It labels its school year, schedules, exam dates, and campaign as development-only; it provides Grades 1-12, the seven development fee types, four plan options, four fictional exam periods, and the inactive fictional First-100 campaign. It must not be used as production policy.

`Database\Seeders\Billing\CurrentSchoolYearJuniorHighBillingTestSeeder` is also opt-in. It populates only the active Junior High School grade levels already configured for School Year 2026-2027, uses the same fictional JHS amounts and plan behavior, leaves the development discount campaign inactive, adds fictional exam/non-business dates, and records Admin-attributed audit evidence. It never creates a missing grade offering and must be replaced with approved JCA fee, calendar, discount, and print inputs before production.

## Production release blockers

- actual fee amounts and enabled fee types;
- actual class, examination, holiday, and business calendars;
- official discount amount, dates, eligibility, and activation;
- official receipt/invoice series and tax wording;
- enabled offline methods and evidence procedure;
- financial signatory names/titles and final print wording/layouts;
- promissory-note and refund/release procedures;
- statutory/disadvantaged-student verification procedure;
- final exam-permit wording and exception authority roster.

Online collection, payment gateways, bank/card credential storage, inventory, SMS, and general reporting are not part of Phase 6.
