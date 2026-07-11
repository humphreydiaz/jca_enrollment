# JCA System Architecture

## 1. Architecture goal

Build a maintainable school system that remains simple to deploy: one Laravel application, one MySQL database, private file storage, a database-backed queue, and Semaphore SMS. The code is organized as a modular monolith so each business area can evolve independently without network services or duplicated data.

## 2. Current baseline

- Laravel Framework 13.19.0; `composer.json` requires `^13.8`.
- PHP requirement `^8.3`; inspected CLI is PHP 8.3.16.
- Tailwind CSS 4 and Vite 8 are present.
- Alpine.js 3.14.9 is installed as a direct development dependency and loaded once from the Vite entry point.
- The foundation includes default Laravel migrations plus an audit-log migration, database-backed cache/jobs/sessions, and MySQL-only configuration.
- `.env.example` and `.env.testing.example` target MySQL; the application configuration has no SQLite, PostgreSQL, or Redis connection.
- Shared module routing, a non-business smoke route, reusable Blade layouts/components, private-document disk configuration, and log-context redaction are available.
- Phase 2 adds closed login, rate limiting, one-time invitations, staff-assisted recovery invitations, password-change enforcement, role history, people profiles, and guardian/student relationships.
- Phase 3 adds the Academics module: data-driven school years, grade levels, subjects, sections, schedule notes, and active teaching assignments. Admin/Registrar maintain catalog data; teacher visibility is derived only from active assignments. No official JCA catalog data was seeded.
- Phase 4 adds Enrollment, Requirements & Documents, and Student Support delivery: rate-limited public drafts, hashed resume/contact-verification secrets, email code delivery with staff verification fallback, MySQL identity locks, duplicate review, guardian invitation connection, explicit enrollment transitions/history/decisions, checklist snapshots, versioned private uploads, restricted reviewer queues/events, and guardian-safe progress.
- Phase 5 adds the Section Assignment module: deterministic available-capacity recommendation, transactionally locked confirmation/transfer, Admin-only overrides, append-only history, final academic `enrolled` transition, promotion recommendations/drafts, portal-safe section/schedule views, and assignment-scoped Teacher summaries.
- Billing, communication beyond enrollment verification email, reporting, grades, and attendance remain unimplemented.

## 3. System context

```text
Guardian / Student / Staff browser
                |
                v
     Laravel web application
       |        |         |
       |        |         +--> Semaphore SMS API
       |        +------------> Private document storage
       +---------------------> MySQL
                                  |
                                  +--> database queue, sessions, cache
```

There is no separate API frontend, Redis service, payment gateway, container platform, or microservice.

## 4. Architectural style

### 4.1 Laravel MVC at the delivery edge

- **Routes** identify the module and action.
- **Controllers** remain thin: authorize, validate with Form Requests, invoke an application action, and return a Blade view or redirect.
- **Blade views** render server-owned state.
- **Alpine.js** handles local disclosure, tabs, dialogs, previews, and small progressive interactions only.
- **Tailwind CSS** provides design tokens and components.
- **Vite** compiles first-party CSS and JavaScript.

Business rules do not live in controllers, Blade templates, Alpine state, or model observers with hidden side effects.

### 4.2 Module internals

Each module may contain:

```text
app/Modules/<Module>/
  Actions/          one use case per class
  Data/             typed input/result objects when useful
  Enums/            persisted state vocabulary
  Events/           completed domain events
  Exceptions/       module-specific failures
  Http/
    Controllers/
    Requests/
  Jobs/             queued work
  Models/           Eloquent models owned by the module
  Policies/         authorization for owned resources
  Queries/          reusable read/query objects
  Services/         cohesive domain services, not generic helpers
  Support/          module-private utilities
```

Views use `resources/views/<module>/`; routes are split under `routes/modules/` and registered by module providers. Shared, Identity, Academics, Enrollment, Student Support, and Section Assignment providers currently register their bounded web routes.

### 4.3 Transaction boundary

An application Action owns each transaction. Controllers must not coordinate partial writes. The following operations require explicit database transactions and locking/idempotency as applicable:

- verifying a submission and issuing an invitation;
- enrollment transition and history creation;
- assigning an early-enrollee discount rank;
- confirming or transferring a section assignment;
- creating an assessment snapshot;
- posting/reversing a payment and updating allocations;
- granting an exam-permit override;
- recording document deletion;
- reserving and dispatching an SMS notification.

## 5. Module map

| Module | Owns | May depend on |
|---|---|---|
| Identity & Access | users, invitations, roles, authentication, session/security events | Shared/Audit |
| People | student, guardian, staff profiles and relationships | Identity, Shared/Audit |
| Academics | school years, grade levels, subjects, sections, teaching assignments | People, Shared/Audit |
| Enrollment | applications, enrollment state, decisions, promotion | People, Academics, Requirements, Support, Shared/Audit |
| Requirements & Documents | requirement rules, checklists, uploads, verification | Enrollment identity references, Shared/Audit |
| Student Support | restricted assessment, observations, approved teacher summary | People, Enrollment, Academics, Shared/Audit |
| Section Assignment | recommendations, confirmations, transfers, capacity overrides | Enrollment, Academics, Student Support, Shared/Audit |
| Billing | fees, assessments, discounts, due schedules, payment ledger, permits | Enrollment, People, Shared/Audit |
| Communications | announcements, templates, notifications, Semaphore delivery logs | Identity, People, Enrollment events, Shared/Audit |
| Reporting | authorized read models, CSV/print exports | read-only access to module data |
| Shared/Audit | reference generation, audit records, common value objects | none |

### Boundary rules

1. A module writes only tables it owns.
2. Cross-module work is coordinated by an Action in the initiating module and completed events after commit.
3. Events contain identifiers and non-sensitive facts, not document bodies or private notes.
4. Reporting may read across modules but does not become a back door for writes or authorization bypass.
5. Reusable code is moved to Shared only after at least two modules need the same stable concept.

## 6. Key workflows

### 6.1 Applicant to guardian account

1. Public form accepts only the minimum information required to create a draft and verify a contact channel.
2. Rate limiting, validation, duplicate detection, and bot/spam controls protect the endpoint.
3. Applicant receives an opaque application reference.
4. Registrar validates the submission as legitimate.
5. Identity creates an expiring, single-use guardian invitation tied to the existing guardian profile.
6. Guardian sets a password; the invitation is consumed and existing application relationships determine portal access.

The system never sends a permanent plaintext password by SMS.

### 6.2 Enrollment approval

1. Enrollment Action validates the requested transition.
2. Requirements module confirms all blocking requirements are verified or validly waived.
3. Student Support confirms an approved outcome when required.
4. In one transaction, Enrollment changes state and appends status and decision history. Phase 6 will add discount allocation.
5. Phase 5 will consume approved enrollment state for section recommendation/finalization; later phases add assessment preparation and notification events.

### 6.3 Section assignment

**Implementation status:** complete in Phase 5. Recommendation is read-only; confirmation and transfer lock the enrollment/current assignment and destination section before capacity is re-counted. A nullable unique active key prevents two current assignments. Support-review placement records explicit suitability, while Teacher summary access follows only active teaching assignments in the current confirmed section.

1. Query eligible sections for the enrollment's school year and grade.
2. Exclude inactive/full sections from automatic recommendation.
3. Sort by configured order and current confirmed count.
4. Registrar confirms a recommendation; support cases require suitability confirmation.
5. Admin over-capacity confirmation requires explicit override permission and reason.
6. Transfers close the current assignment and add a new assignment; no history is deleted.

### 6.4 Payment posting

1. Accounting selects an assessed enrollment and enters receipt/payment details.
2. Server validates receipt uniqueness, amount, allocation limits, and posting permission.
3. Transaction creates the payment and allocations as posted ledger entries.
4. Balance and due status are calculated from the ledger, not manually typed.
5. A correction creates a reversal/adjustment referencing the original; Admin approval is recorded.
6. Permit eligibility is recalculated from configured rules; overrides remain separate and audited.

### 6.5 SMS

1. A committed business event requests a notification with an idempotency key.
2. Communications resolves authorized/consented recipients and renders a minimal template.
3. Database queue dispatches Semaphore outside the original web transaction.
4. Provider reference and delivery result are recorded.
5. Retry policy must not duplicate an already accepted provider request.

## 7. Authorization model

Use a single session guard and user provider. Roles narrow the set of possible actions; policies then check relationships and record scope.

| Actor | Scope summary |
|---|---|
| Admin | Configuration, oversight, exceptional approval; sensitive support access only when specifically authorized and audited |
| Registrar | Student identity, enrollment, requirements, academic records, support workflow review, sections, reports |
| Accounting | Fee assessment, payment ledger, financial reports; minimum student identity; no raw support documents |
| Teacher | Current assigned advisory/subject students and approved instructional support summary |
| Guardian | Students connected by an active guardian relationship and allowed visibility flags |
| Student | Own current and historical portal-safe records |

Policy checks must be applied to index queries as well as individual records. A user who cannot view a record must not infer its existence through counts, validation errors, URLs, exports, notifications, or search.

## 8. Security architecture

- Laravel session authentication, CSRF, password hashing, regeneration after login, and rate limiting.
- Secure invitation and recovery tokens stored hashed; short expiry and single use.
- Password setup required before invited account becomes active.
- Database sessions permit centralized revocation.
- Sensitive files stored on a private disk with randomized keys; original name is metadata only.
- Validate file size, MIME type, extension, and signature where practical; never execute uploads.
- Stream authorized downloads through a controller or short-lived signed mechanism that rechecks access.
- Encrypt transport with HTTPS in production. Secure, HTTP-only, same-site cookies are mandatory in production.
- Do not put personal or support details in route keys, SMS bodies, logs, job names, exception messages, or analytics.
- Audit events are append-oriented and protected from ordinary operational deletion.
- Use least-privilege MySQL and filesystem credentials.

## 9. Data and storage

- MySQL is the system of record.
- Database cache, queue, and sessions satisfy the no-Redis constraint.
- Financial values use `DECIMAL`, explicit currency, and immutable snapshots.
- Private uploads store opaque paths in database metadata; no blobs in MySQL.
- Public identifiers are non-sequential references; internal foreign keys may use numeric IDs.
- Catalog records become inactive instead of being deleted when historical records reference them.
- Application timestamps are displayed in `Asia/Manila`; storage conventions must be consistent and tested.

See [DATABASE.md](DATABASE.md) for the conceptual schema.

## 10. User interface architecture

- One authenticated shell with role-appropriate navigation assembled server-side.
- Blade components for fields, errors, status badges, tables, empty states, dialogs, pagination, and printable headers.
- Alpine components stay local and receive no authorization responsibility.
- Prefer ordinary links/forms that work without JavaScript for critical workflows.
- Server-rendered filtering/pagination is the baseline for large student and financial lists.
- Print views use dedicated Blade layouts and minimize confidential fields.

See [UI_GUIDE.md](UI_GUIDE.md).

## 11. External integration boundary

Semaphore code is isolated behind an application-owned `SmsGateway` contract. Production uses a Semaphore adapter; tests use a fake; local development defaults to disabled/log behavior. Provider request/response shapes must not leak into Enrollment or People models.

No application module may call Semaphore directly from a controller or model.

## 12. Deployment shape

Expected production processes:

1. Web server/PHP runtime serving the Laravel application.
2. MySQL 8.0+ database, pending hosting validation.
3. Database queue worker with controlled retries.
4. Laravel scheduler invocation.
5. Private durable file storage and backups.

Docker is not part of the project. Deployment documentation must use the actual host environment chosen by JCA.

## 13. Testing strategy

- **Unit tests:** state transitions, fee/discount calculations, capacity policy, authorization helpers, reference generation.
- **Feature tests:** routes, validation, policies, scoped lists, account invitation, each workflow transition.
- **Integration tests:** MySQL constraints/transactions, private storage, queue behavior, Semaphore adapter with HTTP fakes.
- **Security tests:** cross-guardian access, teacher scope, accounting/support separation, ID enumeration, invitation replay, download authorization.
- **Browser smoke tests:** critical role journeys and responsive navigation using the project's approved browser test approach, without adding paid tooling.
- **Print/export tests:** authorization, correct filters, safe field selection, and stable output structure.

Tests should use MySQL for behaviors that depend on locking, indexes, generated SQL, or transaction semantics; SQLite is not an acceptable substitute for those acceptance tests.

## 14. Observability and recovery

- Structured application logs without personal document content.
- Job failure visibility and retry runbook.
- SMS delivery dashboard for authorized staff.
- Audit search by actor, subject, action, and time.
- Backup schedule, restore test, recovery objective, and document-storage recovery must be approved before launch.
- Health endpoint reports availability only and never configuration or personal data.

## 15. Architecture fitness checks

Before accepting any implementation phase, verify:

- no banned framework/package/service was added;
- controllers remain orchestration-only;
- policies cover every new read/write/export/download;
- the owning module controls writes to its tables;
- sensitive operations generate audit evidence;
- application features work with MySQL and database queue/cache/session;
- tests cover success, validation failure, forbidden access, and concurrency-sensitive behavior;
- documentation reflects changed contracts and decisions.
