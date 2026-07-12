# JCA Conceptual Database Design

## 1. Status

This is the schema contract and implementation record. Phases 1-5 provide infrastructure through section assignment/promotion. Phase 6 adds the implemented Billing schema described in section 11. Communication and general reporting tables remain unimplemented.

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
  +--0..1 student_profiles (Grades 7-12 portal account by default)

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
- `status`: `invited`, `active`, `locked`, `disabled`.
- Guardian/student and staff roles may not coexist on one account; enforce in the role-assignment service and tests.

### `roles`

Fixed application roles: `admin`, `registrar`, `accounting`, `teacher`, `guardian`, `student`. Columns: `id`, unique `code`, `name`, `is_staff`, `is_active`, timestamps.

### `user_roles`

Columns: `user_id`, `role_id`, `assigned_by`, `assigned_at`, nullable `revoked_by`, `revoked_at`. Use a uniqueness strategy that prevents duplicate active assignments while retaining assignment history.

Do not add a paid permission package. Laravel policies map stable capabilities to roles and record scope.

### `account_invitations`

Columns: `id`, `public_id`, `user_id`, `purpose`, hashed `token`, `sent_to`, `expires_at`, `consumed_at`, `revoked_at`, `created_by`, timestamps.

Only the raw one-time token leaves the server. Never store it plaintext.

### Framework security tables

Use Laravel-compatible `password_reset_tokens` and `sessions`. Sessions must reference `users`; indexes must support account-wide revocation and stale-session cleanup.

## 5. People tables

### `student_profiles`

Permanent student identity. Key columns: `id`, `public_id`, nullable `user_id`, school-issued unique nullable `student_number`, legal name components, preferred name, sex as recorded by the school, birth date/place, address fields, nationality/religion only if confirmed necessary, status, timestamps.

Do not duplicate grade, section, or enrollment status here; those belong to school-year records.

### `guardian_profiles`

Key columns: `id`, `public_id`, nullable `user_id`, legal name components, normalized mobile/email, address, occupation only if required, preferred contact method, status, timestamps.

### `guardian_student`

Many-to-many relationship. Key columns: `id`, `guardian_id`, `student_id`, `relationship_type`, `is_primary_contact`, `may_receive_sms`, `may_view_financials`, `may_manage_enrollment`, `has_legal_authority`, nullable `verified_by`, `verified_at`, effective dates, timestamps.

Constraint: at most one active primary contact per student unless school policy later permits co-primary contacts. Changes are audited.

### `staff_profiles`

Key columns: `id`, unique `user_id`, optional employee/reference number, position title, active dates, timestamps. Staff permissions still come from roles and assignments.

### Duplicate handling

Create a controlled merge workflow rather than deleting duplicate people. Preserve old public references and relationship history. Candidate matching may suggest duplicates by normalized name/birth date/contact but must not merge automatically.

## 6. Academic catalog tables

**Implementation status:** implemented in Phase 3 with internal numeric keys and opaque public IDs for externally routable records. Catalog records are updated or inactivated rather than deleted through application routes. The MySQL migration enforces academic-year/grade/section-name uniqueness, section-subject uniqueness, foreign-key protection, and assignment lookup indexes. No official JCA catalog records are seeded while P-003 remains pending.

### `academic_years`

Columns: `id`, opaque `public_id`, unique `code`, `name`, `starts_on`, `ends_on`, enrollment open/close dates, `status` (`planned`, `open`, `closed`, `archived`), timestamps. Only one year may be designated current by application rule.

### `grade_levels`

Columns: `id`, opaque `public_id`, unique `code`, `name`, `sequence`, `school_stage`, nullable `next_grade_level_id`, `is_active`, timestamps. Owner-authorized development baseline: Grades 1-12; production offerings remain configurable and preschool is not seeded.

### `subjects`

Columns: `id`, opaque `public_id`, unique `code`, `name`, description, `is_active`, timestamps.

### `sections`

Columns: `id`, `public_id`, `academic_year_id`, `grade_level_id`, unique name within year/grade, `capacity` default 15, sort order, status, timestamps.

Capacity is an application policy checked with a transaction/lock during confirmation, not a simple check constraint on another table's count.

### `section_subjects`

Joins a section and subject for one year. Columns include schedule text/structured meeting data when confirmed, and timestamps.

### `teaching_assignments`

Columns: `id`, opaque `public_id`, `staff_profile_id`, `section_id`, nullable `section_subject_id`, `assignment_type` (`adviser`, `subject_teacher`), effective dates, `is_active`, timestamps. Teacher authorization derives from active assignments whose effective dates include the current date.

## 7. Enrollment tables

**Implementation status:** implemented in Phase 4. Public drafts use opaque public IDs plus hashed resume tokens and hashed expiring verification codes. Exact public identity starts are serialized with a MySQL named lock; potential existing student matches require staff review. `active_enrollment_key` provides a MySQL unique guard for one active enrollment per linked student/year and is released only by terminal rejection, withdrawal, or cancellation.

### `enrollments`

One student application/enrollment per academic year and intended grade.

Key columns: `id`, `public_id`, unique `application_number`, `student_id`, `academic_year_id`, `grade_level_id`, `applicant_guardian_id`, `student_category`, `status`, contact verification timestamps, `submitted_at`, `approved_at`, `enrolled_at`, `rejected_at`, `withdrawn_at`, decision actor/reason fields where appropriate, `previous_enrollment_id`, lock version or equivalent concurrency control, timestamps.

Unique constraint: one non-cancelled active enrollment per `student_id` and `academic_year_id`, implemented in a MySQL-compatible migration/application strategy and protected by transactions.

`student_category` starts with `regular`, `transferee`, and `support_review`; categories remain configurable or enum-controlled according to the final rules.

### `enrollment_status_history`

Columns: `id`, `enrollment_id`, `from_status`, `to_status`, `reason`, `changed_by`, `changed_at`, metadata JSON limited to non-sensitive workflow context.

### `enrollment_notes`

General operational notes only, with `visibility_scope` and author. Raw support notes must use the Student Support module, not this table.

### `enrollment_decisions`

Captures approval/rejection/waitlist/override evidence when more than a status-history row is needed: `decision_type`, `outcome`, `reason`, `decided_by`, `decided_at`.

### `promotion_recommendations`

**Implementation status:** implemented in Phase 5. One recommendation belongs to one enrolled source record. It records the configured future year/next grade or a safe ineligible reason. Eligible confirmation creates one new draft linked by unique `previous_enrollment_id`; it never mutates the source year, grade, status, assignment, or history.

Columns: source enrollment, target academic year/grade, eligibility result, reason, generated/confirmed actors and timestamps, and nullable created enrollment. Confirmation creates a new draft enrollment; it never updates the source.

## 8. Requirements and document tables

**Implementation status:** implemented in Phase 4 through `document_types`, effective-dated `requirement_rules`, enrollment checklist snapshots, versioned `document_uploads`, and append-only `document_access_logs`. Files remain outside the public root. Replacement archives metadata/history; no permanent or automated retention deletion exists while P-001 is open.

### `document_types`

Catalog such as Birth Certificate, Report Card/Form 138, Form 137, Certificate of LOV. Columns: code, name, sensitivity class, allowed formats/size policy reference, retention policy key, active flag.

### `requirement_rules`

Columns: `id`, `academic_year_id`, `student_category`, `document_type_id`, `is_required`, `is_blocking`, validity/notes, effective dates. Freeze the applicable rules into enrollment checklist rows when an enrollment is submitted.

### `enrollment_requirements`

Snapshot/checklist row. Columns: `enrollment_id`, `document_type_id`, `rule_snapshot` JSON or explicit snapshot columns, `status`, current upload reference, reviewer, reviewed timestamp, rejection/waiver reason, waived_by, timestamps.

Unique constraint on enrollment and document type for the active checklist row unless policy permits multiples.

### `document_uploads`

Columns: `id`, `public_id`, `enrollment_requirement_id`, opaque `disk` and `path`, original filename, stored filename, MIME, extension, byte size, checksum, uploaded_by/user or applicant reference, scan/validation status, version, archived/deleted/hold timestamps and actors, timestamps.

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

### `announcements`

Title, body, audience definition, publish window, status, author, timestamps. Sensitive support/financial details are prohibited in school-wide content.

### `notification_templates`

Code, channel, purpose, locale, content, version, active flag. Templates use a restricted set of placeholders.

### `notifications`

Application-level intent: template/purpose, related subject type/id, recipient user/profile, status, idempotency key, scheduled/sent timestamps.

### `sms_messages`

Notification, normalized recipient, provider, provider reference, rendered body or safely retained representation according to policy, status, attempt count, accepted/delivered/failed timestamps, redacted error code/response.

Unique idempotency key prevents duplicate sends for the same business event and recipient.

### `sms_consents`

Guardian/contact, purpose, consent state/source, effective and withdrawn timestamps. Transactional messages and optional announcements must remain distinguishable if JCA's approved policy requires it.

## 13. Reporting and audit tables

### `audit_logs`

Append-oriented: `id`, actor user, action code, subject type/public identifier, outcome, reason, request ID, IP/user agent subject to policy, safe before/after metadata, occurred_at. Never store passwords, invitation/reset tokens, raw documents, or unnecessary support notes.

### `export_jobs`

Requester, report code, safe filter snapshot, status, private file key, generated/expires/deleted timestamps, row count, failure code. Download is authorized again at access time.

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
