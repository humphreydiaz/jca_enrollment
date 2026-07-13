# Backup and restore runbook

The database and `storage/app/private` form one recovery unit. A database-only backup is incomplete because it omits requirement documents and payment evidence. Ephemeral reports may be included but remain subject to expiry.

## Backup contract

- Use a restricted MySQL client defaults file outside the repository; do not put passwords on the command line.
- Run `ops/backup.ps1` to create a consistent transactional MySQL dump, private-storage archive, SHA-256 manifest, and non-secret metadata.
- Write the bundle directly to an approved encrypted volume. The script does not encrypt by itself.
- Transfer the bundle to approved encrypted offsite storage, restrict access, monitor job completion, and apply the retention schedule approved under P-001/P-007.
- Never include `.env`, application keys, runtime logs, public build assets, or cache/session/queue tables in an unprotected support ticket.

Example:

```powershell
powershell.exe -NoProfile -ExecutionPolicy Bypass -File ops\backup.ps1 `
  -Database jca_enrollment_production `
  -OutputDirectory E:\EncryptedJcaBackups `
  -PrivateStoragePath C:\path\to\storage\app\private `
  -MySqlDumpPath C:\path\to\mysqldump.exe `
  -DefaultsFile C:\Protected\jca-backup.cnf
```

## Restore drill

1. Obtain incident/change approval and an isolated, empty MySQL database plus empty private-storage directory.
2. Verify the encrypted/offsite retrieval record and bundle checksum.
3. Run `ops/restore.ps1` with `-ConfirmIsolatedEmptyTarget`; the script refuses non-empty targets.
4. Install the matching application release and original application key in the protected drill environment.
5. Run `php artisan migrate:status`, `php artisan jca:production-check`, and the critical smoke/UAT subset.
6. Reconcile migration count, role/user/enrollment/payment totals, recent audit timestamps, and a sample of private-file SHA-256 values against the backup manifest/source record.
7. Record start/end time, achieved RPO/RTO, discrepancies, corrective owner/date, and approval. Destroy the isolated drill copy under the approved retention procedure.

## Verified development rehearsal

On 2026-07-13, the scripts restored the dedicated fictional MySQL test database with all 16 migration rows and 6 fixed roles. A fictional private file matched its source SHA-256 exactly. This proves script mechanics only; it is not production-host, encryption/offsite, scale, or agreed-RTO evidence.
