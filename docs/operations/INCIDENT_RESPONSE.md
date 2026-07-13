# Incident response and failure recovery

## Ownership and evidence

P-008 must name the incident commander, technical operator, privacy contact, school leadership contact, role owners, and Semaphore escalation contact. Use request IDs, opaque record references, timestamps, audit actions, and release hashes in incident records. Never paste documents, passwords, tokens, message bodies, raw provider responses, or unnecessary child/family details.

## Immediate actions

1. Preserve logs/audit/failed-job evidence and record the reporter, time, scope, and request IDs.
2. Contain access: maintenance mode, disable affected accounts/sessions, pause workers, or keep `SMS_ENABLED=false` as appropriate.
3. Do not delete or edit historical financial, enrollment, support, communication, or audit records.
4. Assess confidentiality, integrity, availability, affected roles/families, and legal/notification obligations with the named privacy owner.
5. Recover from the last verified release/backup using the deployment and restore runbooks.
6. Validate authorization, data reconciliation, queue idempotency, private downloads, and `/up` before reopening.
7. Document root cause, corrective owner/date, notifications, and closure approval.

## Operational failures

- **Failed or backed-up queue:** stop duplicate supervisors, inspect counts without exposing payloads, resolve the dependency, retry only idempotent approved jobs, and confirm successful SMS records are never resent.
- **Semaphore failure:** disable SMS, preserve redacted status/reference, use the approved non-SMS fallback, and contact the provider only through the P-005 escalation path.
- **Private storage failure:** block uploads/downloads, preserve database metadata, restore the matching storage/database unit, and verify file hashes before reopening.
- **Database or release failure:** enter maintenance, stop workers, use an isolated restore/reconciliation, and avoid blind production migration rollback.
- **Suspected credential exposure:** revoke the affected credential and sessions. Do not casually rotate `APP_KEY`; encrypted application data requires a planned re-encryption/recovery procedure.

Monitoring must alert on `/up` failure, nonzero `jca:production-check`, queue/failed-job thresholds, scheduler silence, backup failure, storage exhaustion, repeated 5xx responses, and SMS delivery failures. Log retention and notification times remain P-007/P-008 inputs.
