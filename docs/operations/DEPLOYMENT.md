# Deployment and rollback runbook

This runbook is hosting-neutral until P-007 names the production host and service supervisor. Do not improvise provider-specific commands or enable live SMS.

## Release prerequisites

- Signed P-001 through P-011 artifacts are stored in the launch record.
- Production secrets are installed outside the repository; `.env.production.example` is a template only.
- A least-privilege MySQL 8.0+ database/user exists, with TLS if the host supports it.
- HTTPS termination, encrypted private storage, encrypted offsite backup, monitoring, queue supervision, scheduler invocation, and log rotation are configured.
- A backup and isolated restore drill has met the approved RPO/RTO.
- `SMS_ENABLED=false` unless P-005 and live credentials are separately approved.
- When P-005 is approved, verify both Semaphore `/api/v4/messages` (routine traffic) and `/api/v4/priority` (account links and verification codes) with fictional recipients during the controlled launch check. Priority traffic costs two credits per 160-character SMS.

## Build and deploy

1. Record the release commit and database migration status.
2. Create and verify the pre-deployment database/private-storage backup.
3. Enable maintenance mode with an approved operator secret: `php artisan down --with-secret`.
4. Deploy the reviewed commit without copying `.env`, uploads, logs, caches, or development fixtures.
5. Run `composer install --no-dev --classmap-authoritative --no-interaction`.
6. Build assets in the approved build environment with `npm ci` and `npm run build`.
7. Run `php artisan migrate --force`.
8. Run `php artisan config:cache`, `php artisan route:cache`, and `php artisan view:cache`.
9. Run `php artisan jca:production-check`; a nonzero result is a no-go.
10. Restart supervised workers with `php artisan queue:restart`; confirm one scheduler invocation per minute.
11. Disable maintenance mode, then verify `/up`, login, one authorized role journey, a queued fake/non-SMS job, and private download denial.
12. Record operator, timestamps, output summaries, and go/no-go authority.

The queue worker baseline is `php artisan queue:work database --queue=default --sleep=3 --tries=2 --timeout=120 --max-time=3600`. The host supervisor must restart failures and alert on repeated exits. Run `php artisan schedule:run` every minute under a single OS scheduler entry.

## Rollback

- Stop traffic and workers, then enable maintenance mode.
- For code-only failure with no incompatible migration or post-deploy writes, redeploy the prior reviewed commit, rebuild caches, restart workers, and rerun readiness/smoke checks.
- Do not run an automatic database rollback after production writes. If schema/data compatibility is uncertain, restore the verified pre-deployment backup into an isolated database, reconcile it, and use the approved cutover plan.
- Preserve logs, failed-job records, audit data, the failed release identifier, and the original `APP_KEY` for investigation.
- Declare recovery only after the incident owner and affected role owner sign the verification record.
