# User acceptance and role-owner sign-off

Use fictional records in a protected production-like UAT environment until privacy/retention approval permits otherwise. Record pass/fail, tester, date, evidence reference, defect severity, owner, and retest result for every journey.

## Shared journeys

- Sign in, forced password setup, sign out, staff-assisted recovery, timeout/session revocation, validation errors, keyboard-only navigation, mobile layout, and safe forbidden/not-found behavior.
- Confirm no role can infer unrelated records through URLs, filters, counts, exports, printouts, downloads, or error messages.

## Role journeys

- **Admin:** users/roles, catalog oversight, support assignment, billing exceptions, communications, all approved reports, audit review, and incident controls.
- **Registrar:** enrollment intake/transitions, requirements, approved support scope, section assignment/promotion, permitted financial summary, and academic reports.
- **Accounting:** assessment, offline payment posting, immutable correction approval path, private evidence, permits, and finance reports; confirm support denial.
- **Teacher:** only active assigned classes/students, approved teacher-safe summary, permitted exam status, and announcements; confirm raw support/document/finance denial.
- **Guardian:** only actively linked children, progress/requirements/section/approved finance, announcements, and communication preferences; confirm cross-family denial.
- **Student:** own records only, appropriate progress/section/approved finance/announcements, and no restricted staff/support data.

## Recovery and operations

- Queue failure/retry without duplicate SMS, SMS-disabled fallback, expired export denial/pruning, backup alert, isolated restore/reconciliation, rollback decision, and incident escalation.

## Sign-off

Required signers under P-008: Admin owner, Registrar owner, Accounting owner, Teacher representative, Guardian representative, Student representative where age-appropriate, privacy/records owner, technical operator, and final launch authority. No names or signatures are invented in this repository.
