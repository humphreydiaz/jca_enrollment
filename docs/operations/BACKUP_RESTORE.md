# Backup and restore runbook

The database and `storage/app/private` form one recovery unit. A database-only backup is incomplete because it omits requirement documents and payment evidence. Ephemeral reports may be included but remain subject to expiry.

## Built-in encrypted backups (D-060)

The application backs itself up with PHP only, so it works on Windows and on Hostinger shared hosting without `mysqldump`, shell access from PHP, or extra packages. It needs the PHP `sodium` and `zip` extensions (`php -m`).

- `php artisan jca:backup [--label=before-hotfix]` writes one encrypted file, `jca-backup-YYYYMMDD-HHMMSS[-label].jcabak`, to `BACKUP_PATH` (default `storage/app/backups`, which the root `.htaccess` never serves; the folder also gets its own deny-all `.htaccess`). Inside: `database.sql` (every table from one consistent snapshot; session, cache, and queue rows are left out), every file under `storage/app/private` (requirement documents and payment evidence), and `manifest.json` with row counts, migration count, and a SHA-256 checksum for each part.
- Encryption: libsodium secretstream (XChaCha20-Poly1305). The key is `BACKUP_ENCRYPTION_KEY` when set, otherwise derived from `APP_KEY`. Keep a copy of the key outside the server and away from the backup files; without it a backup cannot be restored, and encrypted database fields need the same `APP_KEY` anyway.
- Backups are manual only (owner decision of 2026-10-28): an Admin clicks "Back up now" in Admin > Backups, or someone runs `php artisan jca:backup` over SSH. Nothing is scheduled. A restore also takes its own safety backup first. The newest `BACKUP_KEEP` files (default 14) are kept and older ones are removed. The result is written to `status.json` beside the backups.
- Admin > Backups shows the last successful backup (with a reminder when none exists or the newest is more than `BACKUP_REMIND_AFTER_DAYS`, default 7, days old), lists the files, offers "Back up now", downloads a file for offsite keeping (audited), and restores a file. Restore opens a pop-up that lists what will happen and requires ticking "I understand", typing RESTORE, and the Admin's own password (a no-JavaScript confirmation page asks the same). The same live-restore steps as the command run, then everyone is signed out and the sign-in page names the backup time and the safety backup.
- `php artisan jca:restore <file> --inspect` decrypts and checks every checksum without changing anything.
- `php artisan jca:restore <file> --drill-database=<empty database> [--drill-files=<empty folder>]` rehearses a restore into a separate empty database and folder; the live system is not touched. On Hostinger, create the drill database in hPanel first and give the site's database user access to it.
- `php artisan jca:restore <file>` restores in place after typing `RESTORE` (or `--force`): it verifies the file and key, records the request, takes a `-pre-restore` safety backup, puts the site in maintenance mode, moves the current documents to `storage/app/restore-previous-<time>`, replaces every table, unpacks the backed-up documents, compares row counts, clears caches, and brings the site back. If anything fails part-way, the safety backup is restored automatically and the site comes back as it was; only if that also fails does the site stay in maintenance mode with the exact SSH command to finish. Only one restore can run at a time. A backup made with a different `APP_KEY` is refused unless `--allow-different-app-key` is passed. After restoring an older backup onto newer code, run `php artisan migrate --force`. Everyone is signed out because sessions are not part of a backup.

Offsite copy: at least weekly, an Admin downloads the newest backup from Admin > Backups (or over SFTP) to the school's encrypted storage. Hostinger's own hPanel backups remain a second, independent layer.

### Verified local rehearsal (2026-10-27)

`jca:backup` on the development database produced a 28 MB file (89 tables, 2,517 rows, 29 documents). A drill restore into a separate database matched every table's row count and the CHECKSUM of `student_profiles`, `enrollments`, `payments`, and `student_subject_grades`; all 29 documents matched their SHA-256. (`audit_logs` differed by the single `operations.backup.created` entry written after the snapshot, as designed.) An in-place restore of the same file then completed with a safety backup, matching row counts, maintenance mode off, and the previous documents kept aside. This is development evidence; repeat the drill on the Hostinger host after deployment.

## PowerShell bundle (original Phase 9 tooling)

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
