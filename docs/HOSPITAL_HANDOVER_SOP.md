# Clinex Hospital Handover SOP (One Page)

Use this SOP for day-to-day operation of Clinex in a hospital intranet environment.

## 1) Purpose

Ensure Clinex is:

- Available to hospital staff
- Secure inside internal network
- Monitored with clear incident response steps

## 2) Who Uses This SOP

- IT support staff
- System supervisor
- Department lead (for escalation)

## 3) System Access

- Staff App URL: `https://<hospital-internal-host>`
- API Health Check: `https://<hospital-internal-host>/api/health`
- Access policy: internal LAN/VPN only, no public internet exposure

## 4) Daily Start-Of-Day Check (5 minutes)

1. Confirm services are running.
2. Open app URL and verify login page loads.
3. Check health endpoint returns status OK.
4. Login with test account and open dashboard.
5. Confirm yesterday's queue/jobs show no unresolved critical failures.

Pass criteria:

- App loads
- Login works
- Health check is OK
- No critical service outage

## 5) Functional Smoke Check (10 minutes)

1. Upload one sample PDF report.
2. Confirm batch appears in monitoring.
3. Confirm report reaches `processed` or `verified` flow correctly.
4. Open report details.
5. Export verified CSV successfully.

Pass criteria:

- Upload, processing, and export all work end-to-end

## 6) Incident Response

When app is down or unstable:

1. Record incident time and visible error.
2. Check container/service status and logs.
3. If temporary failure, restart affected service.
4. Re-run health check and smoke check.
5. If unresolved in 15 minutes, escalate.

Escalation contacts (fill in):

- Primary IT Engineer: `<name> / <phone>`
- Backup Engineer: `<name> / <phone>`
- Clinical Operations Contact: `<name> / <phone>`

## 7) Security Rules (Mandatory)

1. Never expose Clinex services directly to the public internet.
2. Keep credentials in local environment files only.
3. Do not share admin credentials in chat/email.
4. Rotate compromised credentials immediately.
5. Use hospital-approved TLS certificate for production trust.

## 8) Backup And Recovery Basics

1. Database backup: daily (automated).
2. Backup retention: per hospital policy.
3. Test restore: at least monthly.
4. Keep last known-good deployment configuration for rollback.

## 9) Weekly Supervisor Checklist

1. Confirm backup completed for all days.
2. Confirm no unresolved high-priority incidents.
3. Confirm user access list is up to date.
4. Confirm security patches are scheduled/applied.

## 10) Go-Live Acceptance Criteria

Deployment is considered healthy when all are true:

1. Health endpoint is OK.
2. Login and dashboard access are stable.
3. Upload to verification workflow works end-to-end.
4. Export works.
5. No critical errors in service logs.

---

Reference documents:

- `PROJECT_FLOW_AND_OPERATIONS.md`
- `README.md`
