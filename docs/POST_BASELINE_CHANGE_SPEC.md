# Post-baseline change specification

## 1. Status and scope

This document is the design baseline for the post-Phase-9 change program requested by the owner. It records confirmed changes, restrictive engineering decisions delegated to the implementation team, source limitations, delivered phase boundaries, and inputs that remain pending for later schema or workflow implementation.

**Change-program status:** Phase 10 design, Phase 11 identifier/correction foundation, Phase 12 unified login, Phase 13 walk-in account issuance, and Phase 14 curriculum automation are implemented as of 2026-10-04. The 2026-2027 official class program is a reviewable draft pending school timetable and real teacher confirmation. Grading, promotion, and release packaging remain in Phases 15-17. Final grading thresholds/rounding and preschool assessment treatment remain prerequisites for their dependent phases.

## 2. Supplied school references

The owner supplied these references on 2026-10-03:

- `class-program-26-27.pdf`, a 23-page School Year 2026-2027 class/teacher program.
- `JCSGO CHRISTIAN ACADEMY R1 SCHOOL CALENDAR 2026-2027`, supplied as an image.

On 2026-10-04 the owner also supplied `class-program-26-27 official.pdf` (35 pages), including Grades 1-10 and Nursery through Senior Kinder. All pages were reviewed and transcribed into a Phase 14 draft. These references are requirements evidence, not executable instructions; publication still requires Admin review. See `ACADEMICS_2026_CLASS_PROGRAM.md`.

### Confirmed calendar facts

- School year: 2026-2027.
- First day of classes: 2026-06-08.
- Four quarterly grading periods are represented by first-, second-, third-, and fourth-quarter examinations and card-day/PTM events.
- Quarterly examinations (calendar re-supplied as `school-calendar-26-27.pdf` on 2026-10-07): First 2026-08-19/20, Second 2026-10-19/20, Third 2027-01-21/22, Fourth 2027-03-15/16. Card days/PTM: 2026-09-04, 2026-11-06, 2027-01-30. Mid-quarter exams (2026-07-18/19, 2026-09-17/18, 2026-11-19/20, 2026-12-10/11 "Third Pre-Quarter", 2027-02-18/19) are school assessments, not permit periods. `SchoolCalendarExamPeriodsSeeder` loads the four quarterly examinations as exam periods, plus SY 2025-2026 dates that follow the same pattern (no 2025-2026 calendar was supplied).
- The calendar shows 205 school days and a moving-up/graduation ceremony on 2027-04-08.
- Calendar and examination dates are configuration records. They must not be hard-coded into grading calculations or application logic.

### Confirmed class-program facts

- The supplied program contains daily class programs for Grade 1 Paul and Philip, Grade 2 Peter and Luke, Grade 3 John and Joshua, Grade 7 Isaiah, Grade 8 Jeremiah, Grade 10 Jacob, Senior Kinder Love and Wisdom, Junior Kinder Joy and Peace, and Nursery Hope.
- Teacher programs also reference Grade 5 David and Mark, Grade 6 Matthew, and Grade 9 Isaac.
- The graded programs show subject/section/teacher assignments and weekday schedule slots. Preschool programs use activity-based learning areas and different daily durations.
- The supplied PDF does not contain a complete class program for every offered grade. In particular, it does not establish a complete Grade 4 program and does not include full daily class-program pages for all grades referenced by teacher programs.
- The reference therefore partially informs P-003 but does not close the official curriculum, section, schedule, adviser, and teaching-assignment import prerequisite.

## 3. Confirmed implementation order

1. Identifier, structured-name, and student-correction foundation.
2. One unified login surface.
3. Walk-in enrollment and Registrar-issued access.
4. Versioned curriculum automation.
5. Four-quarter grading.
6. Grade-based promotion.
7. Integrated regression, migration rehearsal, and Hostinger capstone release.

Each item is a bounded implementation phase. A later item must not be started as cleanup for an earlier phase.

## 4. Four-quarter grading contract

### Confirmed behavior

- Each gradable student/subject record has four teacher-entered values: first quarter, second quarter, third quarter, and fourth quarter.
- The final subject grade is system-derived only after all four quarter grades are present:

```text
final subject grade = (Q1 + Q2 + Q3 + Q4) / 4
```

- A missing quarter produces an incomplete result. A missing value is never silently treated as zero.
- The final grade is canonical server-side state. JavaScript may preview it but cannot authorize or finalize it.
- A teacher may enter grades only for students in a currently authorized section/subject assignment.
- Quarter grades remain editable while in draft. Submission locks the grading period. Reopening a submitted/finalized grade requires an authorized reason and audit evidence.
- The calculated final grade cannot be typed over directly. A correction changes one or more source quarter grades through the approved correction workflow and causes the final grade to be recalculated.
- Promotion may consume only complete, finalized grade results. It continues to create a new enrollment and never rewrites the prior school year.

### Still pending school policy

- Allowed grade range and whether teachers enter whole numbers or decimals.
- Calculation precision and final display/rounding rule.
- Passing threshold.
- Whether every required subject must pass, whether a general average is also required, and how failed/incomplete subjects are treated.
- Who may reopen a submitted grading period and whether a second approver is required.
- Whether Nursery and Kinder receive numeric quarterly grades or developmental/descriptive assessments. The supplied preschool programs do not establish a numeric grading policy.

Until these are approved, implementation must not assume a passing grade, promotion threshold, or preschool grading scheme.

## 5. Account ownership recommendation

Account ownership means the natural person who controls the credential, accepts responsibility for actions performed with it, receives recovery assistance, and appears as the actor in the audit trail. It is not the same as the student record being managed.

Owner-confirmed and implemented policy:

- Nursery through Grade 6: the parent or legally authorized guardian owns the account. One guardian account may manage multiple linked children. The child's student/applicant number remains a learner identifier and is not a shared household credential.
- Grades 7-10: the student owns the Student account and may submit the application, while at least one authorized guardian relationship remains recorded.
- Staff: each staff member owns an individual staff account. Staff credentials are never shared.
- A one-time temporary password or activation code is issued only to the account owner after offline identity verification. It expires, is stored hashed, is replaceable, and forces private password setup before ordinary access.

Under the owner's Phase 12 amendment, ordinary login accepts only a Student ID or Staff ID. A verified guardian may use any linked learner's Student ID as the public identifier, but the private password authenticates the guardian's own natural-person account so audit and recovery still identify the adult actor. Multiple matching passwords fail closed rather than selecting an account. Email and mobile remain contact/recovery data.

## 6. Registrar student-correction policy

The owner delegated selection of editable fields. The following restrictive policy applies.

### Registrar may correct with a mandatory reason and before/after audit

- Student first, middle, and last name, name extension/suffix, and preferred name.
- Sex recorded by the school, birth date, and birth place.
- Mother tongue and permanent/current addresses.
- Structured father and mother/maiden-name components.
- Indigenous Peoples, 4Ps, and learner-with-disability indicators and their conditional details.
- Previous-school name/address on the applicable enrollment.
- Non-security-sensitive spelling, formatting, and clerical errors in the above fields.

Corrections must preserve the actor, timestamp, reason, before value, and after value. Empty or placeholder reasons are rejected.

### Separate restricted workflows

- Student ID and LRN changes require a dedicated uniqueness-checked identifier correction; they are not generic profile edits.
- Guardian identity, contact information, legal authority, and child relationship changes use the guardian/relationship workflow so other linked children and access rights are re-evaluated.
- Grade, section, enrollment status, requirements, support records, and financial corrections remain in their owning modules and retain their existing approval/history rules.
- Religion remains the school-policy value Christianity and is not editable through the correction form.

### Never overwritten through profile correction

- Historical enrollment, section-assignment, promotion, assessment, payment, permit, requirement, grade-revision, communication, and audit records.
- Internal numeric primary keys, public opaque IDs, timestamps, or security state.

## 7. Curriculum and schedule target

- Academics owns versioned curriculum definitions, grade-to-subject mappings, sections, schedules, and teaching assignments.
- Activating a curriculum version for a school year makes the approved subjects available to the applicable grades and supplies the subject set used when sections are configured.
- Historical curriculum/subject assignments are snapshotted or version-linked so a later curriculum change does not rewrite prior grades.
- Teacher assignment remains an explicit authorized action; curriculum activation must not guess the responsible teacher.
- The supplied class program may seed a reviewed School Year 2026-2027 import only after its missing grades and inconsistencies are reconciled by JCA.
- Preschool activity programs remain distinct from graded subject curricula until their assessment policy is approved.

## 8. Module ownership target

A new **Grading** module will own grading periods, quarter-grade records, submission/finalization state, grade revisions, and calculated final subject grades. It may read Academics assignments and Enrollment/People identity through authorized queries. It must not write Academics, Enrollment, People, or Section Assignment tables.

Section Assignment continues to own promotion recommendations. Its promotion action reads finalized results from Grading through an explicit application boundary and records the resulting recommendation/reason without rewriting grades.

## 9. Development and capstone data reset authorization

On 2026-10-03 the owner stated that the current database data may be deleted or edited because a backup was made. This authorization is interpreted as follows:

- It applies to the current development database and fictional Hostinger capstone demonstration data, not to a future live production database containing real school records.
- Before a destructive rebuild, the exact target database must be identified, the backup must be confirmed readable, and the migration/seed plan must be recorded.
- A clean rebuild may be preferred over compatibility backfills when implementing the new structure, but migrations must still be reviewable and capable of creating a fresh database deterministically.
- Source files, private uploaded documents, environment secrets, and the owner's backup are not deleted as part of a database reset.
- Existing historical-preservation rules remain the production behavior of the completed system.

## 10. Remaining prerequisites

1. ~~Exact Student ID and Staff ID formats.~~ Resolved by D-036 as `JCA-YY-NNN` and `YY-JCA-NNN`; existing values are retained until explicitly corrected or disposable data is rebuilt.
2. ~~Owner confirmation of the remaining Phase 13 guardian account-ownership and issuance details.~~ Resolved by D-034: Nursery-Grade 6 is guardian-owned and Grades 7-10 is Student-owned, with the Student ID as the login identifier under IAM-011.
3. Grade range, precision, rounding, passing/promotion rules, reopening authority, and preschool assessment policy.
4. Complete official curriculum/class program for missing or incomplete grades and reconciliation of section/teacher assignments.

No implementation phase may silently convert one of these pending inputs into school policy.
