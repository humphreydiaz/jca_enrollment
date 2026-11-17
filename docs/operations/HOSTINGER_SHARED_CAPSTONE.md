# Hostinger shared-hosting capstone deployment

## Scope

This profile is only for a low-traffic capstone demonstration with fictional records. It does not close P-001 through P-011, does not replace the production launch gate, and must not be represented as an approved live JCA deployment.

Verified hosting facts for the rehearsal:

- Hostinger Web Hosting Unlimited in Singapore.
- PHP 8.3 selected for the website.
- MariaDB 11.8.9 supplied by Hostinger; application compatibility remains subject to a clean migration and workflow rehearsal.
- SSH and hPanel cron jobs are available.
- The fixed website root is `public_html`.

## Package safety

Run `npm run build`, then create the upload archive with:

```powershell
powershell -ExecutionPolicy Bypass -File scripts/build-hostinger-package.ps1
```

The package uses an explicit allowlist. It includes application code and compiled public assets but excludes `.env`, Git history, dependencies, database dumps, tests, logs, cached sessions/views, test uploads, and private documents.

## Web layout

Extract the package directly into `public_html`. The package root `.htaccess` blocks application-internal paths and forwards public requests to Laravel's `public` directory. Laravel's existing `public/.htaccess` handles application routes.

Never place a populated local `.env`, SQL dump, `storage` contents, or student documents in the upload archive.

## Server preparation

From SSH, change to the website's `public_html` path and run Composer using PHP 8.3. Create `.env` on the server from `.env.production.example`, then enter secrets locally in hPanel or the SSH editor. Never paste secrets into chat, source control, screenshots, or command output.

For this public capstone test:

- keep `APP_DEBUG=false`;
- use the HTTPS temporary domain for `APP_URL`;
- retain database-backed sessions, cache, and queues;
- live SMS is the owner's choice (D-070): with `SMS_ENABLED=true`, `SMS_DRIVER=semaphore` and the owner's `SEMAPHORE_API_KEY` in the server `.env`, texts go out through the `queue:work` cron job; confirm with Communications > SMS status and test;
- keep every unsigned JCA approval flag false;
- use only fictional people, documents, payments, and contact destinations.

Run the clean MariaDB rehearsal before creating accounts:

```text
php artisan migrate:fresh --seed --force
php artisan identity:bootstrap-admin
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

`migrate:fresh` is authorized only while the new hosted database is confirmed empty. Never use it after demonstration data has been entered.

## Moving to a new site with an imported database

When a hosted database is imported from another environment (for example the local database), the server `.env` must use that environment's `APP_KEY`. Direct messages, notification bodies, SMS recipient numbers, sessions and key-derived backups are encrypted with it, so do not run `key:generate`. Copy the matching `storage/app/private/documents` files so the imported `document_uploads` rows resolve. Then run `migrate --force` (expect "Nothing to migrate") and `cache:clear`, and never `migrate:fresh`. See `deploy/hostinger-new-site-20261104/README.md`.

## Shared-hosting scheduler and queue

Create two hPanel custom cron jobs that run every minute:

```text
/usr/bin/php /absolute/path/to/public_html/artisan schedule:run
/usr/bin/php /absolute/path/to/public_html/artisan queue:work database --queue=default --stop-when-empty --tries=2 --timeout=45 --max-time=50
```

If the host disables `proc_open` (Composer then reports "proc_open is disabled" and `package:discover` fails at the end of `composer install`), run `php artisan package:discover --ansi` by hand. `schedule:run` cannot start its tasks in that case, so replace the first cron job with direct jobs in server time (UTC): every hour `artisan reports:prune-exports`; daily at 18:00 UTC (02:00 Manila) `artisan auth:clear-resets`, `artisan queue:prune-failed --hours=720`, `artisan queue:prune-batches --hours=168 --unfinished=168 --cancelled=168` and `artisan jca:backup --label=automatic-daily`; daily at 19:00 UTC (03:00 Manila) `artisan app:graduate-eligible-grade10-students`. Keep these in step with `routes/console.php`. `queue:work` does not need `proc_open`.

The second command drains queued work and exits because shared hosting does not provide the project's required long-running supervised worker. Enrollment verification email and report exports can therefore wait until the next cron invocation. This is a capstone compromise, not the approved production worker contract.

## Backups and restore (D-060)

Backups: the Admin clicks Back up now in Admin > Backups, and one automatic backup runs daily at 02:00 Manila from the cron job above (D-062); the newest 14 are kept in `storage/app/backups`. Make one before every hotfix and at least weekly. Before relying on it, check from SSH in `public_html`:

```text
php -m | grep -Ei '^(sodium|zip)$'
php artisan jca:backup --label=first-check
php artisan jca:restore jca-backup-<name>.jcabak --inspect
```

If `sodium` or `zip` is missing, enable it in hPanel > Advanced > PHP Configuration for PHP 8.3. Keep a copy of the server's `APP_KEY` (and `BACKUP_ENCRYPTION_KEY`, if set) somewhere safe outside Hostinger; backups cannot be restored without it. Download the newest backup from Admin > Backups at least weekly. Keep Hostinger's own hPanel backups enabled as a second layer.

To restore after a failure or accidental deletion, the Admin uses Admin > Backups > Restore (pop-up confirmation with RESTORE and their password), or from SSH (both replace the live records after taking a safety backup, and roll back automatically if the restore fails):

```text
php artisan jca:restore jca-backup-<name>.jcabak
php artisan migrate --force
```

A rehearsal without touching the live site needs a second, empty database created in hPanel and assigned to the site's database user: `php artisan jca:restore jca-backup-<name>.jcabak --drill-database=<that database>`. See `docs/operations/BACKUP_RESTORE.md`.

## Required rehearsal

Before connecting a permanent domain, verify:

1. `/up` returns a successful response over HTTPS.
2. Public enrollment can start with a fictional email address and the verification message is processed by cron.
3. Staff and portal sign-in separation still works.
4. A fictional document upload cannot be downloaded without authorization.
5. A report export moves from queued to ready.
6. Scheduler and cron output show no repeated failures.
7. `jobs` and `failed_jobs` remain within the configured thresholds.
8. `APP_DEBUG` remains false and sensitive paths return 403 or 404.

Do not import real student data or mark hosting/recovery approvals true during this rehearsal. Live SMS uses real credits; turn it on only with the owner's key (D-070).

## Repair a missing requirement checklist

If a hosted database was created before baseline requirement rules were installed, applications can display “No requirements configured” and no upload control can be rendered. After taking a database backup and deploying the current reviewed code, run only this idempotent seeder:

```text
php artisan db:seed --class="Database\\Seeders\\Requirements\\RequirementsBaselineSeeder" --force
```

The seeder installs only missing minimum rules from REQ-002 through REQ-004 for planned/open years and years with in-flight applications. It preserves existing customized rules and backfills missing checklist rows only for submitted, under-review, incomplete, assessment, or waitlisted applications. It does not alter uploaded evidence, approved/enrolled records, or closed applications.

Then verify one affected application in the Registrar workspace and its authorized Guardian/Student portal. The checklist should show Birth Certificate and School Form 9 (SF9) for a regular applicant, and the portal should show a private PDF/JPG/PNG upload control. Do not use `migrate:fresh` for this repair.

## Rebuild the capstone demonstration data (D-054)

Owner-approved on 2026-10-07: the hosted database may be wiped and rebuilt with the capstone demonstration school year. Back up the database and code first (see `deploy/hostinger-fixes/capstone-update-20261007/README.md`), then from `public_html`:

```text
php artisan optimize:clear
php artisan capstone:snapshot-staff
php artisan migrate:fresh --force
php artisan db:seed --class="Database\Seeders\Capstone\CapstoneDemoSeeder" --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan queue:restart
```

`capstone:snapshot-staff` records the current Admin, Registrar and Accounting accounts (name, email, mobile, Staff ID, roles; never passwords) so the seeder recreates the same people; the seeder deletes the snapshot after use. Every seeded account signs in with `Password12345`. The seeder refuses to run on a database that already has a school year or enrollment, so it cannot be stacked on real records. The demonstration people are fictional, with `@gmail.test` emails and no mobile numbers, so live SMS cannot reach anyone.
