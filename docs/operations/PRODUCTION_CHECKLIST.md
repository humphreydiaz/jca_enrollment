# Production readiness checklist

Every box needs an owner, date, and evidence link in the protected launch record. Environment flags are attestations, not approvals by themselves.

## School and policy gates

- [ ] P-001 retention, holds, deletion approvers, and schedule signed.
- [ ] P-002 final finance configuration and procedures signed.
- [ ] P-003 official catalog, sections, subjects, and assignments imported/approved.
- [ ] P-004 support form, vocabulary, reviewer roster, and scope signed.
- [ ] P-005 sender, consent, templates, escalation, and live-SMS decision signed; otherwise SMS remains disabled.
- [ ] P-006 reports, columns, layouts, signatories, and controls signed.
- [ ] P-007 host, MySQL version, encrypted/offsite backup, monitoring, RPO/RTO, and restore drill signed.
- [ ] P-008 role owners, UAT, training, incident contacts, support coverage, launch/rollback authority signed.

## Technical gates

- [ ] Reviewed release commit; clean dependency/security review; no secrets or development data.
- [ ] Production `.env` uses debug off, HTTPS, secure/encrypted database sessions, database queue/cache, daily redacted logs, least-privilege MySQL, and private non-serving disks.
- [ ] Original `APP_KEY` stored in approved secret custody and excluded from backups/source.
- [ ] `composer validate`, full tests, production asset build, Pint, routes, and whitespace checks pass.
- [ ] Fresh migration and rollback/reapplication pass on production-like MySQL.
- [ ] Queue supervisor, scheduler, log rotation, disk/DB capacity, alerts, and on-call ownership verified.
- [ ] Encrypted/offsite backup and isolated database/private-file restore meet approved RPO/RTO.
- [ ] `php artisan jca:production-check` passes with no development fixtures.
- [ ] Critical role, privacy, upload/download, export, CSRF, rate-limit, responsive, accessibility, concurrency, and recovery journeys pass.

## Go/no-go and aftercare

- [ ] Pre-deploy backup verified; maintenance/rollback authority present.
- [ ] Post-deploy `/up`, login, role access, private storage, queue, scheduler, and audit checks pass.
- [ ] Launch authority records GO; otherwise rollback/no-go is recorded.
- [ ] Elevated monitoring and support coverage remain active for the approved aftercare window.

Current repository status: engineering gates are implemented and locally verified; production status remains **NO-GO** until all school/hosting attestations pass.
