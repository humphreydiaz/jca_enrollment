# Hostinger shared-hosting capstone deployment

## Scope

This profile is only for a low-traffic capstone demonstration with fictional records. It does not close P-001 through P-008, does not replace the production launch gate, and must not be represented as an approved live JCA deployment.

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
- keep `SMS_ENABLED=false`;
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

## Shared-hosting scheduler and queue

Create two hPanel custom cron jobs that run every minute:

```text
/usr/bin/php /absolute/path/to/public_html/artisan schedule:run
/usr/bin/php /absolute/path/to/public_html/artisan queue:work database --queue=default --stop-when-empty --tries=2 --timeout=45 --max-time=50
```

The second command drains queued work and exits because shared hosting does not provide the project's required long-running supervised worker. Enrollment verification email and report exports can therefore wait until the next cron invocation. This is a capstone compromise, not the approved production worker contract.

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

Do not enable live SMS, import real student data, or mark hosting/recovery approvals true during this rehearsal.

## Repair a missing requirement checklist

If a hosted database was created before baseline requirement rules were installed, applications can display “No requirements configured” and no upload control can be rendered. After taking a database backup and deploying the current reviewed code, run only this idempotent seeder:

```text
php artisan db:seed --class="Database\\Seeders\\Requirements\\RequirementsBaselineSeeder" --force
```

The seeder installs only missing minimum rules from REQ-002 through REQ-004 for planned/open years and years with in-flight applications. It preserves existing customized rules and backfills missing checklist rows only for submitted, under-review, incomplete, assessment, or waitlisted applications. It does not alter uploaded evidence, approved/enrolled records, or closed applications.

Then verify one affected application in the Registrar workspace and its authorized Guardian/Student portal. The checklist should show Birth Certificate and Form 138 for a regular applicant, and the portal should show a private PDF/JPG/PNG upload control. Do not use `migrate:fresh` for this repair.
