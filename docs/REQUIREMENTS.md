# JCA Enrollment and Student Management System Requirements

## 1. Purpose and scope

The system centralizes enrollment, student records, requirement verification, support assessment, sections, payment tracking, announcements, SMS notifications, parent/student portals, and operational reports for J.C.S.G.O. Christian Academy R1 Inc. (JCA), Lingayen, Pangasinan.

This remains the requirements baseline; the short phase-status note below prevents completed behavior from being mistaken for future scope. Read [DECISIONS.md](DECISIONS.md) for selected defaults and [PLAN.md](../PLAN.md) for detailed delivery evidence.

## 2. Requirement status

- **Confirmed:** directly stated in the outline or imposed by the project owner.
- **Decided:** ambiguity resolved in [DECISIONS.md](DECISIONS.md).
- **Assumed:** working interpretation that must be validated before production.
- **Pending:** information JCA still needs to provide.
- **Out of scope:** intentionally excluded from this product.

**Implementation status (2026-07-11):** Phases 1-6 are complete for development. Billing provides configurable offline assessment, discount, ledger, correction, permit, portal, evidence, and print workflows under owner-authorized development assumptions. Live SMS, general reporting, grades, and attendance remain later phases. Official Phase 6 production policies, catalog import, retention, and support-policy inputs remain pending.

## 3. Confirmed requirements

### 3.1 Product and technology

| ID | Requirement |
|---|---|
| PR-001 | The product shall be a browser-based enrollment and student management system. |
| PR-002 | The application shall use Laravel MVC with Blade, Tailwind CSS, Alpine.js, MySQL, and Vite. |
| PR-003 | The application shall be a modular monolith with one deployable Laravel application and one primary MySQL database. |
| PR-004 | The solution shall not use React, Vue, Inertia, Livewire, Tabler, Bootstrap, PostgreSQL, Redis, Docker, microservices, or paid packages. |
| PR-005 | Semaphore shall be the only paid external integration. |
| PR-006 | Interfaces shall follow the school logo, use a lively light theme, and not copy the outdated visual styling of the PSU reference. |

### 3.2 Users and access

| ID | Requirement |
|---|---|
| IAM-001 | The system shall support Admin, Registrar, Accounting, Teacher, Guardian/Parent, and Student access. |
| IAM-002 | Every authenticated action shall be authorized by role, relationship, current assignment, and record sensitivity as applicable. |
| IAM-003 | Temporary or invited users shall set a private password before normal portal access. |
| IAM-004 | The system shall support password change, secure recovery, account disable/lock, and session invalidation. |
| IAM-005 | Teachers shall have limited access to assigned advisory/subject students only. |
| IAM-006 | Therapy/support information, uploaded documents, personal information, and payment data shall be restricted to authorized users. |

### 3.3 Enrollment

| ID | Requirement |
|---|---|
| ENR-001 | A guardian shall be able to enroll a child; a high-school student may participate directly while retaining a linked guardian. |
| ENR-002 | The system shall collect a validated online enrollment form and required documents. |
| ENR-003 | Registrar shall be able to verify, reject, waive when authorized, or request replacement of individual requirements. |
| ENR-004 | The system shall track enrollment state and its history rather than overwrite the latest outcome without evidence. |
| ENR-005 | Final academic enrollment shall require complete/approved requirements and any required support assessment. |
| ENR-006 | Payment may be marked as `payment to follow`; financial standing shall not erase academic enrollment state. |
| ENR-007 | Guardians and eligible students shall see current status, missing requirements, section information, and relevant announcements. |
| ENR-008 | Status changes shall support targeted SMS notifications and notification history. |
| ENR-009 | The system shall prevent duplicate active enrollment for the same student, school year, and grade level. |
| ENR-010 | Enrollment actions and overrides shall be auditable. |

### 3.4 Requirement rules

| ID | Requirement |
|---|---|
| REQ-001 | Requirement rules shall be data-driven by student/enrollment category and school year. |
| REQ-002 | Non-therapy students require at least a Birth Certificate and Form 138/Report Card, subject to the active school policy. |
| REQ-003 | Transferees require at least a Birth Certificate, Report Card, and Form 137/school-to-school requirement. |
| REQ-004 | Students requiring therapy/special-support review require at least a Birth Certificate and therapist Certificate of LOV, plus school observation/review. |
| REQ-005 | Documents shall be stored privately and downloads shall require authorization. |
| REQ-006 | The system shall retain document version, uploader, verification result, reviewer, timestamps, and rejection/waiver reason. |

### 3.5 Support assessment

| ID | Requirement |
|---|---|
| SUP-001 | JCA shall review students who require therapy/special-support assessment before acceptance and placement. |
| SUP-002 | The workflow shall record observation/review state, authorized reviewer, decision, rationale, and date. |
| SUP-003 | Section suitability shall be manually confirmed for a student with an active support assessment. |
| SUP-004 | Teachers shall receive only an approved educational support summary necessary to support the student. |
| SUP-005 | The system shall not automatically diagnose, reject, or place a student based on uploaded information. |

### 3.6 Academics, sections, and promotion

| ID | Requirement |
|---|---|
| ACA-001 | Admin/Registrar shall manage school years, grade levels, subjects, sections, advisers, and teaching assignments. |
| ACA-002 | A section shall belong to one school year and one grade level. |
| ACA-003 | Normal section capacity shall be 15 students. |
| ACA-004 | The system shall recommend sections in first-qualified/first-approved order while allowing authorized staff to balance groups. |
| ACA-005 | Over-capacity assignment shall require Admin authorization and an audit reason. |
| ACA-006 | Section transfer history shall be retained. |
| ACA-007 | Promotion shall create a new next-school-year enrollment and shall not alter historical enrollment records. |
| ACA-008 | Subjects shall be configurable rather than embedded in application code. |

### 3.7 Payment tracking and permits

| ID | Requirement |
|---|---|
| BIL-001 | The product shall track school-collected payments only; it shall not process online payments. |
| BIL-002 | Accounting shall maintain fee schedules for tuition, miscellaneous fees, books, PE uniform, logo/tela, Bible, graduation/recognition, and future configured fee types. |
| BIL-003 | Each enrollment assessment shall snapshot the applicable fees and discounts so later fee changes do not rewrite history. |
| BIL-004 | The system shall support cash, full, semi-annual, quarterly, and monthly tracking plans where configured. |
| BIL-005 | The system shall record payment date, amount, receipt/reference, collector, allocations, and posting status. |
| BIL-006 | Posted payments shall not be edited or deleted; correction shall use an approved reversal or adjustment. |
| BIL-007 | The system shall calculate balance and due status from posted ledger entries. |
| BIL-008 | Exam-permit eligibility shall be determined from configured due rules and shall allow an audited Admin override. |
| BIL-009 | Discount campaigns shall support a school-year limit, eligibility rule, dates, value, and awarded rank. |

### 3.8 Portals, announcements, SMS, and reports

| ID | Requirement |
|---|---|
| COM-001 | Guardian Portal shall show linked children only. |
| COM-002 | Student Portal shall show the authenticated student's records only. |
| COM-003 | Portals shall show enrollment progress, requirements, section/schedule, announcements, and permitted financial summaries. |
| COM-004 | Admin shall send school-wide or targeted announcements and SMS messages. |
| COM-005 | Automated SMS shall support missing-requirement reminders, concerns, and enrollment status changes. |
| COM-006 | SMS delivery attempts shall be logged with provider status without logging confidential content unnecessarily. |
| REP-001 | Admin, Registrar, and Accounting shall have searchable/filterable lists within their authorized scope. |
| REP-002 | Reports shall support school year, grade, section, enrollment state, incomplete requirements, financial standing, and SMS history where authorized. |
| REP-003 | Required academic and administrative outputs shall be printable/downloadable with access control and generation audit. |

## 4. Decided requirements

The following resolve ambiguities in the outline:

| ID | Decision-backed requirement | Decision |
|---|---|---|
| DR-001 | Guardian accounts are individual and use many-to-many guardian/student links. | D-003 |
| DR-002 | Public account registration is closed; a verified applicant receives a school invitation. | D-002 |
| DR-003 | Student accounts are defaulted to Grades 7-12 after approval; Grades 1-6 normally use Guardian access, with an audited Admin exception. | D-004 |
| DR-004 | Staff may have multiple staff roles, but staff and portal identities are not combined. | D-005 |
| DR-005 | Teacher access excludes raw support, document, and payment records. | D-006 |
| DR-006 | Academic and financial states are independent. | D-007 |
| DR-007 | Payment tracking is ledger-based and has no gateway. | D-008 |
| DR-008 | Early-enrollee rank is assigned on first approval, not initial submission. | D-009 |
| DR-009 | Section placement is system-recommended and staff-confirmed. | D-010 |
| DR-010 | Sensitive retention is configurable and requires school approval before production automation. | D-014 |
| DR-011 | Account recovery is staff-assisted through an audited, expiring recovery invitation after offline identity verification. | D-018 |

## 5. Workflow definitions

### 5.1 Standard enrollment

1. Guardian or eligible student starts an unauthenticated minimal application and verifies the designated contact channel.
2. System creates a draft enrollment reference without creating an active login.
3. Applicant completes the form and uploads requirements.
4. Registrar validates identity/duplicates and sends the guardian invitation when the submission is legitimate.
5. Registrar reviews requirements; incomplete/rejected items return to the applicant.
6. If support assessment is required, authorized staff complete the assessment and record the decision.
7. Registrar approves or rejects the application. Approval may atomically award a discount campaign rank.
8. System recommends a section; Registrar/Admin confirms it. Support cases require manual suitability confirmation.
9. Accounting creates/verifies the fee assessment and records payment plan or `payment to follow`.
10. Authorized staff finalize academic enrollment. Guardian/student receives a status notification.

### 5.2 Enrollment states

Allowed baseline states are `draft`, `submitted`, `under_review`, `incomplete`, `assessment_required`, `assessment_pending`, `approved`, `waitlisted`, `rejected`, `enrolled`, `withdrawn`, and `cancelled`. State changes must use an explicit transition service and create history.

`enrolled` means academically admitted for the school year; it does not mean fully paid.

### 5.3 Financial states

Allowed summary states are `not_assessed`, `unpaid`, `partial`, `paid`, `overdue`, and `waived`. The summary is derived from assessment, due items, posted payments, approved discounts, reversals, and waivers.

### 5.4 Requirement states

Allowed states are `missing`, `submitted`, `under_review`, `verified`, `rejected`, and `waived`. Waiver requires permission, reason, and audit evidence.

## 6. Non-functional requirements

### 6.1 Security and privacy

| ID | Requirement |
|---|---|
| NFR-SEC-001 | Use Laravel password hashing, CSRF protection, session regeneration, validation, authorization policies, and rate limiting. |
| NFR-SEC-002 | Force password setup/change after invitation before ordinary portal use. |
| NFR-SEC-003 | Use database sessions; allow staff to revoke sessions after account or role changes. |
| NFR-SEC-004 | Prevent insecure direct object references by authorizing every record lookup and download. |
| NFR-SEC-005 | Keep production secrets only in environment configuration and never in source, logs, exports, or SMS content. |
| NFR-SEC-006 | Audit sensitive reads and material state changes with actor, action, subject, time, request context, and before/after metadata where safe. |
| NFR-SEC-007 | Exports shall be scoped, time-limited where stored, and protected like their source data. |
| NFR-SEC-008 | Sensitive support notes shall not appear in general search, notifications, or standard reports. |

### 6.2 Data integrity

| ID | Requirement |
|---|---|
| NFR-DAT-001 | Use foreign keys, unique constraints, transactions, and idempotency controls for enrollment approval, discount allocation, payment posting, and SMS dispatch. |
| NFR-DAT-002 | Financial amounts shall use fixed-precision decimal columns; never floating point. |
| NFR-DAT-003 | Historical assessments, enrollments, assignments, and posted payments shall not be retroactively rewritten by catalog changes. |
| NFR-DAT-004 | Times shall be displayed in Asia/Manila and stored consistently according to the architecture standard. |
| NFR-DAT-005 | Database changes shall be delivered through reviewed Laravel migrations once implementation begins. |

### 6.3 Usability and accessibility

| ID | Requirement |
|---|---|
| NFR-UI-001 | Interfaces shall be responsive and usable on common mobile and desktop browsers. |
| NFR-UI-002 | Forms shall preserve entered data, show field-specific errors, explain next actions, and support keyboard operation. |
| NFR-UI-003 | Status shall never be conveyed by color alone. |
| NFR-UI-004 | Use accessible contrast, visible focus states, semantic HTML, labels, and reduced-motion support. |
| NFR-UI-005 | Print/download views shall exclude controls and unnecessary confidential information. |

### 6.4 Operations

| ID | Requirement |
|---|---|
| NFR-OPS-001 | Use the database queue; Redis is prohibited. |
| NFR-OPS-002 | Failed SMS jobs shall be retryable and visible to authorized staff without duplicating successful sends. |
| NFR-OPS-003 | Production shall have documented backups, restore testing, scheduler operation, queue supervision, and log rotation before launch. |
| NFR-OPS-004 | Health checks must not expose secrets or personal data. |

## 7. Assumptions requiring validation

- The owner-authorized development baseline covers Grades 1-12; the official production catalog is still pending, and preschool is excluded unless confirmed later.
- MySQL 8.0 or newer is available in the eventual hosting environment.
- One Registrar, one Accounting staff member, and at least one Admin operate enrollment, while staff-role multiplicity supports absences and future staffing.
- Mobile number is the primary invitation/SMS contact; email may be optional until JCA confirms recovery policy.
- A fee assessment can be created before payment and academic enrollment may proceed with `payment to follow`.
- Section capacity 15 is a normal policy limit, with exceptional Admin override rather than an impossible database constraint.

## 8. Pending requirements

- Final school-approved retention schedule and deletion approvers.
- Current school-year catalog: grade offerings, sections, subjects, schedules, advisers, and capacities.
- Production-approved fee amounts, class/exam/holiday dates, discount campaign, receipt/invoice series, methods, financial signatories, exception procedures, permit wording, and thresholds. Phase 6 development assumptions remain configurable and are not official fees.
- Official support-assessment form, decision vocabulary, and authorized reviewer roster. The generic restricted Phase 4 workflow is complete and does not treat these pending inputs as school policy.
- Official Semaphore sender name, SMS templates, consent language, and live credentials.
- Final report list, layouts, signatories, and whether any report needs a control/reference number.
- Hosting, backup retention, recovery objectives, and production MySQL version.

## 9. Out of scope

- Online payment collection or gateway integration.
- Grades, attendance, learning management, online examinations, library, payroll, HR, inventory, clinic/medical records, and therapy case management.
- Automatic diagnosis or algorithmic acceptance/placement decisions.
- Native Android/iOS applications.
- Public third-party API, social login, biometric authentication, chat, or real-time WebSocket features.
- Multi-school tenancy and microservices.
