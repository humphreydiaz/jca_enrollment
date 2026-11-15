# JCA Conceptual Database Design

## 1. Status

This is the schema contract and implementation record. Phases 1-5 provide infrastructure through section assignment/promotion, Phase 6 adds Billing, Phase 7 adds Communications, and Phase 8 adds the private export lifecycle described in section 13.

Target: MySQL 8.0+ pending hosting confirmation. Use InnoDB, foreign keys, `utf8mb4`, fixed-precision financial columns, and migration-tested constraints.

## 2. Design principles

1. Separate people from login accounts: a guardian/student profile can exist before a `users` account.
2. Separate school-year enrollment from the permanent student identity.
3. Separate academic enrollment state from financial state.
4. Snapshot historical rules and amounts; never recalculate history from a changed catalog.
5. Append history for decisions, assignments, and money rather than overwriting evidence.
6. Store private files outside MySQL; store metadata and opaque keys in the database.
7. Use internal numeric primary keys and separate non-sequential public references.
8. Use nullable foreign keys only when the business lifecycle genuinely permits an absent relationship.
9. Use soft deletion sparingly; status/inactivation is preferred for referenced catalog data.
10. Treat support, identity documents, finance, and audit data as separate access domains.

## 3. Core relationship overview

```text
users --< user_roles >-- roles
  |              
  +--0..1 guardian_profiles --< guardian_student >-- student_profiles
  +--0..1 staff_profiles
  +--0..1 student_profiles (Grades 7-10 portal account by default)

student_profiles --< enrollments >-- academic_years
                         |  |  |
                         |  |  +-- grade_levels
                         |  +----- enrollment_requirements --< document_uploads
                         +-------- section_assignments >-- sections

enrollments --0..1 support_cases --< support_assessments
enrollments --< fee_assessments --< assessment_items
fee_assessments --< payment_schedules
fee_assessments --< payments --< payment_allocations
enrollments --< discount_awards >-- discount_campaigns
```

## 4. Identity and access tables

### `users`

Authentication identity only.

Key columns: `id`, `public_id`, `name`, nullable unique `email`, nullable normalized unique `mobile`, `password`, `status`, `password_changed_at`, `must_change_password`, `last_login_at`, `locked_at`, `disabled_at`, `remember_token`, timestamps.

Rules:

- At least one verified login/recovery identifier is required before activation.
- Store normalized contact values and verification timestamps separately if multiple contact points are introduced.
- `status`: `invited`, `active`, `disabled` (D-055: no workflow ever locked an account, so the `locked` status was removed). `locked_at` is kept as a fail-closed guard that no screen sets; any non-null value still blocks sign-in, setup links, and reactivation.
- Guardian/student and staff roles may not coexist on one account; enforce in the role-assignment service and tests.
- Disabling records `disabled_at`, requires an audited reason, revokes sessions and every open invitation, and preserves the user row. Admin reactivation clears `disabled_at`, returns the account to `invited`, and creates a new hashed single-use setup invitation; locked accounts are not silently reactivated.

### `roles`

Fixed application roles: `admin`, `registrar`, `accounting`, `teacher`, `guardian`, `student`. Columns: `id`, unique `code`, `name`, `is_staff`, `is_active`, timestamps.

### `user_roles`

Columns: `user_id`, `role_id`, `assigned_by`, `assigned_at`, nullable `revoked_by`, `revoked_at`. Use a uniqueness strategy that prevents duplicate active assignments while retaining assignment history.

Do not add a paid permission package. Laravel policies map stable capabilities to roles and record scope.

### `account_invitations`

Columns: `id`, `public_id`, `user_id`, `purpose`, hashed `token`, `sent_to`, `expires_at`, `consumed_at`, `revoked_at`, `created_by`, timestamps.

Only the raw one-time token leaves the server. Never store it plaintext. New account-setup tokens are grouped, unambiguous activation codes with approximately 80 bits of entropy; the hash remains the only persisted form, and legacy opaque invitation links remain redeemable until they expire.

### Framework security tables

Use Laravel-compatible `password_reset_tokens` and `sessions`. Sessions must reference `users`; indexes must support account-wide revocation and stale-session cleanup.

## 5. People tables

### `student_profiles`

Permanent student identity. Key columns: `id`, `public_id`, nullable `user_id`, school-issued unique nullable `student_number`, nullable 12-digit `lrn`, legal name components including extension, preferred name, sex as recorded by the school, birth date/place, mother tongue, school-policy religion fixed to Christianity at enrollment, optional Indigenous Peoples group, 4Ps and learner-with-disability indicators with conditional detail, current/permanent address, parent names, status, timestamps. Age is derived from `birth_date` and is not stored.

Phase 13 walk-in issuance creates the applicant profile only after Registrar identity and duplicate review. Manual creation is restricted to verified legacy/offline records. D-024 permits audited deletion only for an unused mistaken profile with no login or historical relationship; pending duplicate-candidate pointers may be cleared with their own audit event, but any enrollment or other history blocks deletion.

Do not duplicate grade, section, or enrollment status here; those belong to school-year records.

`user_id` remains null for guardian-owned Nursery-Grade 6 access. Phase 13 records the Student owner through the enrollment while pre-approval access stays limited to that application; the existing audited post-approval connection action remains the gate for full Student-profile/section access and one-to-one profile ownership.

### `guardian_profiles`

Key columns: `id`, `public_id`, nullable `user_id`, legal name components, normalized mobile/email, address, occupation only if required, preferred contact method, status, timestamps. Registrar walk-in issuance captures the legal guardian's structured name and contact here rather than duplicating a free-form guardian name on the learner.

### `guardian_student`

Many-to-many relationship. Key columns: `id`, `guardian_id`, `student_id`, `relationship_type`, `is_primary_contact`, `may_receive_sms`, `may_view_financials`, `may_manage_enrollment`, `has_legal_authority`, nullable `verified_by`, `verified_at`, effective dates, timestamps.

Constraint: at most one active primary contact per student unless school policy later permits co-primary contacts. Changes are audited.

### `staff_profiles`

Key columns: `id`, unique `user_id`, optional employee/reference number, position title, active dates, timestamps. Staff permissions still come from roles and assignments.

### Phase 11 People changes

Phase 11 implements unique Student IDs as `JCA-YY-NNN` and Staff IDs as `YY-JCA-NNN` under D-036. `identifier_namespaces` supplies one lock row per identifier type/year so concurrent issuance is serialized before the unique profile-column constraint is reached. Internal numeric keys and opaque public IDs remain separate.

Student profiles store structured father first/middle/last/suffix and mother first/middle/maiden-last/suffix components. Guardian profiles include an optional suffix. The older free-text parent columns remain deprecated compatibility columns and receive no new application writes; a later reviewed cleanup may remove them after deployed data has been reconciled.

`student_profile_corrections` is append-only field-level evidence containing student, field, before/after values, reason, actor, and creation time. Student ID/LRN uses a separate validated action but writes the same durable history. `enrollment_corrections` is the Enrollment-owned equivalent for previous-school facts. Guardian identity and relationship changes remain in the guardian workflow. See D-029.

### Phase 13 walk-in access changes

`users.temporary_access_expires_at` marks the expiry of the Registrar-issued temporary password. `temporary_access_used_at` is claimed atomically on the first successful sign-in, making the credential single-use; both fields are cleared when the owner establishes a private password. The password column contains only Laravel's secure hash.

`enrollments.access_issued_to_user_id` records the natural-person draft owner. `walk_in_verified_by` and `walk_in_verified_at` record the Registrar/Admin verification, while `access_issued_at` records the handoff. Foreign keys protect the issued-access and verification actors. Legacy public resume/contact-verification columns remain nullable compatibility fields but receive no new writes; the routes that exposed that flow are retired.

The issuance transaction creates People and Identity facts through their owning Actions before Enrollment creates the draft. A Student ID is issued in the same transaction and is the applicant's only public login identifier. The verified guardian link has enrollment authority but SMS consent is false by default.

### Duplicate handling

Create a controlled merge workflow rather than deleting duplicate people. Preserve old public references and relationship history. Candidate matching may suggest duplicates by normalized name/birth date/contact but must not merge automatically.

## 6. Academic catalog tables

**Implementation status:** implemented in Phase 3 with internal numeric keys and opaque public IDs for externally routable records. Catalog records are updated or inactivated rather than deleted through application routes. The MySQL migration enforces academic-year/grade/section-name uniqueness, section-subject uniqueness, foreign-key protection, and assignment lookup indexes. The confirmed grade progression is seeded; official school years, sections, subjects, and teaching assignments remain pending.

### `academic_years`

Columns: `id`, opaque `public_id`, unique `code`, `name`, `starts_on`, `ends_on`, enrollment open/close dates, `status` (`planned`, `open`, `closed`, `archived`), timestamps. Only one year may be designated current by application rule.

### `grade_levels`

Columns: `id`, opaque `public_id`, unique `code`, `name`, `sequence`, `school_stage`, nullable `next_grade_level_id`, `is_active`, timestamps. The seeded progression is Nursery, Junior Kinder, Senior Kinder, Grades 1-6, and Grades 7-10; Grade 10 is the last grade and has a null `next_grade_level_id`. JCA does not offer Senior High School, so Grades 11-12 are not seeded. Offerings remain editable and may be inactivated without deleting history.

### `subjects`

Columns: `id`, opaque `public_id`, unique `code`, `name`, description, `is_active`, timestamps.

### `sections`

Columns: `id`, `public_id`, `academic_year_id`, `grade_level_id`, unique name within year/grade, `capacity` default 15, sort order, status, timestamps.

Capacity is an application policy checked with a transaction/lock during confirmation, not a simple check constraint on another table's count.

### `section_subjects`

Joins a section and subject for one year. Columns: `section_id`, `subject_id`, nullable `curriculum_id` provenance (null means added by hand), `status` (`active`/`archived`, indexed), structured meeting data `days` (comma-separated ISO weekdays 1-6), `starts_at` and `ends_at` (TIME), the derived display `schedule_text`, and timestamps. Archived offerings stay for grade history and are excluded from current schedules. Migration `2026_10_06_000028` added `status` and the structured columns and backfilled them from recognized `schedule_text`; rolling back drops only the added columns.

### `teaching_assignments`

Columns: `id`, opaque `public_id`, `staff_profile_id`, `section_id`, nullable `section_subject_id`, `assignment_type` (`adviser`, `subject_teacher`), effective dates, `is_active`, timestamps. Teacher authorization derives from active assignments whose effective dates include the current date.

### Phase 14 versioned curriculum tables

`curricula` stores an opaque public reference, effective `academic_year_id`, unique version within the year, name, draft/published state, source note, and publishing actor/time. A year lock serializes draft version creation and publication. Only one version may be published for a school year; a published version is immutable in the application.

`curriculum_grade_subjects` normalizes each grade-to-subject mapping. `curriculum_section_templates` stores stable grade/section names, capacity, order, and optional preschool/co-curricular activity notes. `curriculum_template_schedules` stores a subject's reviewed class time for a section template as `days`, `starts_at`, `ends_at`, and the derived `schedule_text`. The unique keys prevent duplicate mappings and names; foreign keys restrict removal while referenced.

An Admin-confirmed deletion may remove an unpublished draft and its three draft-only child tables in one audited transaction, provided no actual `sections` or `section_subjects` link to it. Published versions and applied records remain immutable.

The owner-supplied 2026-2027 program is also held as a reusable configured default template, separate from `curricula`. The seeder and Admin draft creation both materialize its grade-subject mappings, section templates, and timetable notes into new year-specific draft rows; deleting all draft versions does not remove this default option. No actual sections or teacher assignments are created until separate authorized steps.

Publication creates or links year-specific `sections` and `section_subjects` in one transaction. Both keep a nullable `curriculum_id` provenance foreign key; their schedule/activity values are copied so later version changes cannot recalculate older years. Existing manually entered schedule values are retained. Teaching assignments remain explicit and year-specific. Structured class times (and recognized older text such as `Mon-Fri 8:20-9:00`) are checked for a teacher's overlapping active subject assignments on shared weekdays; unrecognized free-text notes are displayed for manual review.

Catalog rows are deleted only when unused (D-042); the application counts referencing enrollments, sections, placements, grades, curricula, finance configuration, and teaching assignments before deleting, and MySQL `restrict` foreign keys remain the final guard.

The owner-supplied 35-page 2026-2027 program is imported as a draft only. Preschool activity programs stay distinct from numeric subject curricula until P-010 is resolved. P-003 remains open for timetable and teacher confirmation. See `ACADEMICS_2026_CLASS_PROGRAM.md`.

## 6.1 Grading tables (Phase 15)

Implemented for JCA-approved numeric Grades 1–10. Quarter and final values are whole-number 0–100; the final value is null until all four are present and is the nearest whole-number average (half upward). Passing/promotion thresholds and preschool assessment remain P-010.

### `grading_periods`

Four ordered quarter records per academic year are created when grading begins. Dates are nullable data pending JCA's timetable review and never embedded in calculation code. Submission/release controls live on each subject-grade result.

### `student_subject_grades`

One enrollment/subject result contains four nullable quarter values, server-derived nullable final value, draft/submitted/finalized state, offering and original teaching-assignment provenance, immutable subject name/code snapshot, and timestamps. Enrollment plus subject is unique so a section transfer cannot create a second grade for the same school year. MySQL checks guard quarter ranges and final arithmetic.

### `grade_revisions`

Append-only quarter and transition evidence includes affected quarter, old/new values, actor, optional reason, and timestamp. `grade_correction_requests` records teacher reason, prior state, Admin decision/note, and timestamps. Approval returns the grade to draft and hides it from family/report views until refinalization. No revision edits the calculated final value independently of its quarter inputs.

## 7. Enrollment tables

**Implementation status:** implemented in Phase 4. Public drafts use opaque public IDs plus hashed resume tokens and hashed expiring verification codes. Exact public identity starts are serialized with a MySQL named lock; potential existing student matches require staff review. `active_enrollment_key` provides a MySQL unique guard for one active enrollment per linked student/year and is released only by terminal rejection, withdrawal, or cancellation.

### `enrollments`

One student application/enrollment per academic year and intended grade.

Key columns: `id`, `public_id`, unique `application_number`, `student_id`, `academic_year_id`, `grade_level_id`, `applicant_guardian_id`, nullable `student_applicant_user_id`, `student_category`, guardian relationship, nullable previous school name/address, `status`, contact verification timestamps, `submitted_at`, `approved_at`, `enrolled_at`, `rejected_at`, `withdrawn_at`, decision actor/reason fields where appropriate, `previous_enrollment_id`, lock version or equivalent concurrency control, timestamps.

Unique constraints: one non-terminal active enrollment per `student_id` and `academic_year_id`, plus one non-terminal application per signed-in Student account and academic year. Both use nullable MySQL-compatible active keys and transactional locks; rejected, withdrawn, or cancelled records retain history but release their active key.

`student_category` starts with `regular`, `transferee`, and `support_review`; categories remain configurable or enum-controlled according to the final rules.

### `enrollment_status_history`

Columns: `id`, `enrollment_id`, `from_status`, `to_status`, `reason`, `changed_by`, `changed_at`, metadata JSON limited to non-sensitive workflow context.

### `enrollment_notes`

General operational notes only, with `visibility_scope` and author. Raw support notes must use the Student Support module, not this table.

### `enrollment_decisions`

Captures approval/rejection/waitlist/override evidence when more than a status-history row is needed: `decision_type`, `outcome`, `reason`, `decided_by`, `decided_at`.

### `promotion_recommendations`

**Implementation status:** implemented in Phase 5 and updated by D-026. One recommendation belongs to one enrolled source record. It records the configured future year/next grade or a safe ineligible reason. Eligible confirmation creates one new draft linked by unique `previous_enrollment_id`, generates the target checklist, and carries accepted hard-copy evidence with provenance; it never mutates the source year, grade, status, assignment, or history.

Columns: source enrollment, target academic year/grade, eligibility result, reason, generated/confirmed actors and timestamps, and nullable created enrollment. Confirmation creates a new draft enrollment; it never updates the source.

## 8. Requirements and document tables

**Implementation status:** implemented in Phase 4 and updated by D-025. `document_types`, effective-dated `requirement_rules`, and enrollment checklist snapshots remain current. Each checklist item has separate soft-copy review and hard-copy receipt/review state. Versioned uploads and append-only access logs protect both current and historical files. No permanent or automated retention deletion exists while P-001 is open.

### `document_types`

Catalog such as Birth Certificate, School Form 9 (SF9), Form 137, Certificate of LOV. Columns: code, name, sensitivity class, allowed formats/size policy reference, retention policy key, active flag.

### `requirement_rules`

Columns: `id`, `academic_year_id`, `student_category`, `document_type_id`, `is_required`, `is_blocking`, validity/notes, effective dates. Freeze the applicable rules into enrollment checklist rows when an enrollment is submitted.

Under D-039, the confirmed minimum rule rows are installed when an academic year is saved and are backfilled idempotently for all existing years, including closed/archived years. Existing per-year values are never overwritten, and historical enrollment checklist snapshots are not recalculated.

### `enrollment_requirements`

Snapshot/checklist row. Columns: `enrollment_id`, `document_type_id`, nullable self-referencing `carried_from_requirement_id`, nullable `carried_over_by` and `carried_over_at`, `rule_snapshot`, hard-copy `status`, `received_by`, `received_at`, `receipt_notes`, hard-copy reviewer/timestamp/reason/waiver, `soft_copy_status`, soft-copy reviewer/timestamp/reason, and timestamps.

Unique constraint on enrollment and document type for the active checklist row unless policy permits multiples.

### `document_uploads`

Stores current and historical soft-copy versions. A replacement archives the previous active version without deleting it. Columns: `id`, `public_id`, `enrollment_requirement_id`, opaque `disk` and `path`, original filename, stored filename, MIME, extension, byte size, checksum, uploaded_by user, validation status, version, archived/deleted/hold timestamps and actors, timestamps.

Files are private and never referenced by a direct public URL.

### `document_access_logs`

Append download/preview/export events for sensitive documents: document, actor, purpose, request context, occurred_at. Do not record the file contents.

### `retention_policies` and `retention_actions`

Configurable category, retention trigger/duration, approval requirements, version/effective dates. Actions record archive, hold, release, deletion approval, completion, actor, reason, and evidence. Automated production deletion remains disabled until policy approval.

## 9. Student Support tables

**Implementation status:** implemented in Phase 4 as a generic restricted workflow. Cases have Admin-controlled reviewer assignment, append-only case events, observations, assessment versions, neutral outcomes, and versioned approved teacher-safe summaries. Raw successful reads create both support-access and general audit evidence. P-004 remains open for the official JCA form, vocabulary, and roster; no diagnoses, medical scoring, or printable official form is encoded.

These tables require stricter policies and must be excluded from general search/reporting.

### `support_cases`

Columns: `id`, `public_id`, `enrollment_id`, `status`, `opened_by/at`, `closed_by/at`, final outcome, review-required flag, timestamps.

### `support_assessments`

Columns: case, assessment type, scheduled/completed dates, authorized reviewer, structured outcome, confidential rationale, next action, timestamps. Version or append rather than silently overwrite completed assessments.

### `support_observations`

Private observation notes with author, time, category, and access classification.

### `approved_support_summaries`

Teacher-safe summary version: case, summary text/structured accommodations, approved_by/at, effective dates, superseded_by. It must not embed raw therapist documents or unnecessary diagnosis details.

### `support_access_logs`

Append access to cases, assessments, observations, and raw support documents, including actor, reason/purpose, and timestamp.

## 10. Section assignment tables

**Implementation status:** implemented in Phase 5. Current confirmation uses a nullable unique `active_assignment_key`; ended rows retain section, dates, actor, and reason. Capacity lookup and current-assignment indexes support row-locked confirmation/transfer. Override and suitability actors/reasons are explicit columns, and `section_assignment_events` is append-only.

### `section_assignments`

Columns: `id`, `enrollment_id`, `section_id`, `status` (`recommended`, `confirmed`, `ended`), recommendation order/basis, suitability confirmation, confirmed_by/at, effective dates, ended reason/actor, `is_capacity_override`, override reason/actor, timestamps.

Only one active confirmed assignment per enrollment. Count confirmed active assignments under a section lock before confirmation.

### `section_assignment_events`

Optional append history if assignment rows alone do not fully express recommendation, rejection, transfer, and override events.

## 11. Billing tables

**Implementation status:** implemented in Phase 6 by `2026_07_11_000013_create_billing_and_exam_permit_tables.php`. All money columns use `DECIMAL(12,2)`; application calculations convert decimal strings to integer centavos. Financial history uses restrictive foreign keys, opaque public identifiers for sensitive routes, unique current/revision/reference keys, and append-only application guards.

### Catalog and immutable assessment

`fee_types`, `fee_schedules`, and `fee_schedule_items` hold configurable versioned catalogs. `fee_assessments` records enrollment, schedule, predecessor revision, revision number/reason, immutable totals, finalization actors/times, and a unique current-finalized key. `assessment_items` snapshot code, name, category, quantity, unit/gross/discount/net amounts, and permit-critical status, so catalog edits cannot rewrite history.

### Plans and calendar

`payment_plan_options` configures offered plan codes by year/grade. `payment_plans` records the selected plan per assessment, and `payment_schedule_items` stores exact installment, due date, total, and permit-critical amount. `school_non_business_days` moves generated due dates forward. `payment_to_follow_approvals` retains ordinary and extended approvals, dates, reasons, actors, and the resulting first-due-date extension.

### Discounts

`discount_campaigns` owns school-year limits, dates, value, active/development flags. `discount_awards` uniquely protects campaign rank and enrollment; cancelled ranks remain. `discount_award_events` retains cancellation requests/decisions and explicit slot reopening. Campaign rows are locked before rank allocation.

### Offline ledger and private evidence

`payments` separates editable drafts and posted records and assigns unique `PAY-YYYY-######` and `JCA-REC-YYYY-######` values only on posting. `financial_sequences` is locked per type/year. `payment_allocations` records item/installment application; `credit_allocations` records append-only same-student/same-year application of available credit. `payment_reversals` retains independent approval and `payment_reversal_allocations` reproduces the affected allocations. `financial_adjustments` stores debit, credit, or waiver requests/decisions, optionally targeted to an assessment item. `refunds` records approved accounting entries and an offline release reference/date. Original posted rows are not deleted or edited.

`payment_evidence` stores only private-disk opaque paths, protected original-name metadata, MIME, size, checksum, and uploader. It has no public URL or deletion route. `receipt_prints` increments a print number without changing the receipt reference.

### Exam permits and print settings

`exam_periods` stores school-year exam dates, eligibility cutoff, and issue window. `exam_permit_calculations` is an append-oriented reproducibility record containing required/satisfied amounts, ordinary/override result, timestamp, and actor. `exam_permit_overrides` retains restricted category/explanation, offline evidence reference, verifier, scope, effective dates, request/approval/revocation history. `exam_permits` retains the active opaque key, issued reference, replacement predecessor, calculation facts, and revocation history; `exam_permit_prints` records each access. `financial_print_settings` keeps configurable role labels/footer text without inventing employee signatories.

### Key integrity and lookup rules

- unique schedule version per academic year/grade;
- unique assessment revision and one non-null current-finalized key per enrollment;
- unique campaign rank and campaign/enrollment award;
- unique payment, receipt, and permit references;
- unique non-null method reference within method;
- one non-null active permit key per enrollment/exam period while revoked/replaced history remains;
- indexes for enrollment/status/date, due cutoff, campaign rank, portal lookup, and permit calculations;
- restrictive deletes for finalized/posted/history relationships.

## 12. Communications tables

**Implementation status:** implemented in Phase 7. The module uses opaque public IDs for public routes, explicit audience rows instead of a free-form contact export, encrypted recipient/body fields, a unique notification idempotency key, and indexed delivery state. Templates are versioned and deactivated rather than overwritten. P-005 keeps live Semaphore delivery disabled by default.

### `announcements`

Title, body, `delivery` (`portal`, `sms`, `portal_sms`), `sms_body`, publish window, status, author, timestamps. The related `announcement_audiences` rows hold `all` or role targeting (D-052; older rows may hold section, student, or guardian targeting). Sensitive support/financial details are prohibited in school-wide content.

### `notification_templates`

Retired by D-052: no longer created or used. Existing rows remain only so historical notifications keep their reference.

### `notifications`

Application-level intent: purpose (`announcement` or `direct`), related subject (announcement or direct message), recipient guardian, learner (`recipient_student_id`), or user, encrypted rendered body, status, idempotency key (one per number per send), scheduled/sent timestamps.

### `direct_messages`

D-052: one Admin "Message people" send: encrypted body, recipient count, sender, timestamps. Its notifications are the per-person texts.

### `sms_messages`

Notification, encrypted normalized recipient, provider, provider reference, recipient hash/last four, status, attempt count, accepted/delivered/failed timestamps, and redacted error code. Do not log the message body or raw provider response.

Unique idempotency key prevents duplicate sends for the same business event and recipient.

### `sms_consents`

Exactly one owner (guardian profile, learner record `student_id`, or staff account `user_id`), purpose, consent state/source (`portal_preferences` or `enrollment_application`), effective and withdrawn timestamps. Announcement texts require a granted `announcement` consent; Admin direct messages do not (D-052). `student_profiles.mobile` holds the optional learner number from the application.

## 13. Reporting and audit tables

**Implementation status:** implemented in Phase 8. Operational reports query normalized source tables through fixed role scopes and field allowlists; they do not persist report-row caches. P-006 remains pending, so print views are marked as development operational layouts and do not invent official signatories or control references.

### `audit_logs`

Append-only (the model refuses updates and deletes, D-059): `id`, actor user, action code, subject type/public identifier, outcome, reason, request ID, IP/user agent subject to policy, safe before/after metadata, occurred_at. Change events store `metadata.fields` (changed field names) and `metadata.changes` (`{field: {old, new}}` for allowlisted fields only; mobile numbers and emails masked by `AuditValueMask`). Never store passwords, invitation/reset tokens, raw documents, full contact values, or unnecessary support notes.

### `export_jobs`

Opaque public ID, requester, report code, safe filter and field snapshots, status, private file key/disk/MIME/checksum, generated/expires/downloaded/deleted timestamps, row count, and safe failure code. Download is authorized again at access time, and generated files use random names on a non-serving private disk.

No generic report cache may copy confidential data into a less-protected table.

## 14. Index and constraint checklist

Future migrations must explicitly test:

- unique public IDs, application numbers, student numbers, and active receipt/reference identifiers;
- normalized email/mobile uniqueness where present;
- guardian/student relationship uniqueness for active links;
- enrollment uniqueness per student/year;
- section uniqueness per year/grade/name;
- one active confirmed section assignment per enrollment;
- role and teaching-assignment lookup indexes;
- requirement lookup by enrollment/status;
- payment/assessment indexes by enrollment, status, date, and reference;
- discount campaign rank uniqueness and slot allocation locking;
- SMS idempotency and provider-reference indexes;
- audit indexes by actor, subject, action, and time;
- foreign-key delete behavior that protects historical/financial records.

## 15. Deletion behavior

- **Restrict:** academic years, grade levels, students with enrollments, finalized assessments, posted payments, discount awards, and audit subjects.
- **Deactivate/close:** users, roles, catalog values, sections, assignments, campaigns, and templates.
- **Retention workflow:** uploaded documents and exports.
- **Cascade only when safe:** unconsumed draft-only child records that have no audit, financial, or historical value, and framework cleanup tables where Laravel expects it.

Never cascade-delete a student and thereby erase enrollment, payment, or support decision history.

## 16. Migration implementation sequence

1. Identity/security and framework infrastructure.
2. People and guardian relationships.
3. Academic catalog and teaching assignments.
4. Enrollment and status history.
5. Requirements/documents/retention metadata.
6. Restricted support records.
7. Section assignments.
8. Fee catalog, assessment snapshots, discounts, ledger, and permits.
9. Communications/SMS.
10. Audit/export enhancements and final indexes.

Each step requires rollback review, MySQL feature tests, factories, minimal development seed data, and an authorization review before the next dependent phase.
