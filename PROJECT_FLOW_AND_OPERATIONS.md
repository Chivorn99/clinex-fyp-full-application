# Clinex Project Flow And Operations Guide

This document explains how Clinex works end-to-end in two modes:

- Local development mode
- Production-style intranet mode

It also includes setup steps and a verification checklist to confirm production is healthy.

## 1) System Overview

Clinex is a monorepo with:

- Backend: Laravel API and queue jobs
- Frontend: Next.js web application
- OCR pipeline: Python script for document extraction
- Database: MySQL
- Intranet reverse proxy (production-style): Nginx

Main runtime components:

- User-facing UI pages in frontend
- API endpoints in backend
- Background queue worker for OCR jobs
- Private file storage in backend storage disk

## 2) Core Business Flow (End-To-End)

### A. Authentication flow

1. User opens frontend login page.
2. Frontend calls backend API login endpoint.
3. Backend validates credentials and returns token.
4. Frontend stores auth token and includes it in API requests.

Backend API routes for auth are defined in:
- backend/routes/api.php

Frontend API client is defined in:
- frontend/src/lib/api.ts

### B. Upload and batch creation flow

1. User uploads one or more PDF reports from the upload page.
2. Frontend submits multipart request to backend batch create endpoint.
3. Backend creates a report batch record.
4. Backend stores files in private disk and creates LabReport rows.
5. Backend can auto-dispatch processing job.

Key implementation files:
- backend/app/Http/Controllers/ReportBatchController.php
- backend/app/Models/ReportBatch.php
- backend/app/Models/LabReport.php

### C. OCR processing flow

1. Backend dispatches ProcessLabReportBatch job.
2. Job runs Python script against batch directory.
3. Script returns JSON extraction results.
4. Backend updates each report status and extracted data.
5. Batch status is recalculated as completed, partial, or failed.

Key implementation files:
- backend/app/Jobs/ProcessLabReportBatch.php
- backend/scripts/python/document_ocr.py

Optional single-file worker path:
- backend/app/Jobs/ProcessSingleLabReport.php

### D. Verification and persistence flow

1. Frontend verification page loads unverified processed reports.
2. User reviews and corrects extracted data.
3. Frontend submits verified payload.
4. Backend stores patient and lab result records.
5. Backend marks report as verified and increments batch counters.

Key implementation files:
- backend/app/Http/Controllers/LabReportController.php
- frontend/src/app/main/verification/page.tsx
- frontend/src/app/main/verification/monitoring/page.tsx

### E. Reporting and export flow

1. Frontend reports pages request list/detail APIs.
2. Backend serves report metadata, PDF data, and CSV export endpoints.
3. Users can review report details and export verified results.

Key implementation files:
- backend/routes/api.php
- backend/app/Http/Controllers/LabReportController.php
- backend/app/Http/Controllers/ReportBatchController.php
- frontend/src/app/main/reports/page.tsx
- frontend/src/app/main/reports/report-details/page.tsx

## 3) Local Development Setup Flow

Recommended local flow uses Docker and Makefile.

### A. Clone project

1. Clone repository.
2. Open project root.

Repository:
- https://github.com/Chivorn99/clinex-fyp-full-application.git

### B. Configure local host values

Run:

make setup HOST=127.0.0.1

This will:

- Ensure backend/.env exists
- Ensure frontend/.env.local exists
- Configure APP_URL, FRONTEND_URL, and NEXT_PUBLIC_API_URL

### C. Start containers

Run:

make docker-up

### D. Run migrations

Run:

make docker-migrate

### E. Access local app

- Frontend: http://127.0.0.1:3000
- Backend API base: http://127.0.0.1:8000/api
- Health endpoint: http://127.0.0.1:8000/api/health

## 4) Production-Style Intranet Setup Flow

Use this flow when hosting inside a private hospital network.

### A. Host/network assumptions

- One internal server runs all containers
- Access is limited by firewall to LAN or VPN only
- No public internet exposure for app endpoints

### B. Configure intranet host

Run:

make setup HOST=10.10.5.20

Replace with your actual internal IP or internal DNS.

### C. Start intranet profile (Nginx 80/443)

Run:

make docker-intranet-up

This profile:

- Hides direct frontend/backend ports
- Exposes only Nginx 80 and 443
- Proxies frontend at root path
- Proxies backend at /api path

### D. Run migrations

Run:

make docker-intranet-migrate

### E. Access intranet app

- App entrypoint: https://10.10.5.20
- API path: https://10.10.5.20/api

First run uses self-signed certificate generated under:
- ops/nginx/certs

For real hospital rollout, replace with internal CA certificate.

## 5) How To Know It Works In Production

Use this acceptance checklist after deployment.

### A. Infrastructure checks

1. Containers are up:
   - mysql, backend, queue, frontend, nginx (in intranet profile)
2. No restart loops in logs.
3. Database accepts connections.

Operational commands:

- make docker-intranet-logs
- docker compose -f docker-compose.yml -f docker-compose.intranet.yml ps

### B. API checks

1. Health endpoint responds status ok.
2. Auth endpoints respond correctly.
3. Protected endpoints reject unauthenticated requests.

Minimum checks:

- GET /api/health returns status ok
- POST /api/login works with valid credentials
- GET /api/profile requires auth token

### C. Functional smoke test

Run a real workflow with test account:

1. Login on frontend.
2. Upload at least 1 PDF report.
3. Confirm batch record appears and starts processing.
4. Confirm report reaches processed or failed state.
5. Open verification screen and verify one report.
6. Confirm report appears in reports list/detail.
7. Export verified CSV and check content format.

### D. Queue and OCR checks

1. Queue worker logs show job consumption.
2. OCR script execution has no fatal errors.
3. Failed jobs are visible and diagnosable.

Files to inspect:

- backend/storage/logs
- container logs for queue and backend

### E. Security and confidentiality checks

1. External/public access blocked by firewall.
2. Secrets are only in local env files, not in git.
3. Certificate is valid for intranet trust policy.
4. Principle of least privilege for database and service accounts.

## 6) Rollback And Recovery Basics

If deployment has issues:

1. Keep last known-good image/container set available.
2. Roll back compose stack to previous working config.
3. Restore database from latest backup if schema/data corruption occurs.
4. Verify health endpoint and smoke test again.

## 7) Operational Ownership Checklist

Before go-live, define:

- Who rotates credentials and service keys
- Who applies OS and container updates
- Who reviews logs and failed jobs daily
- Backup schedule for database and storage
- Incident escalation contact and response SLA

## 8) Useful Project References

- API routes: backend/routes/api.php
- Batch upload and process: backend/app/Http/Controllers/ReportBatchController.php
- Report verification logic: backend/app/Http/Controllers/LabReportController.php
- Batch OCR job: backend/app/Jobs/ProcessLabReportBatch.php
- Frontend API client: frontend/src/lib/api.ts
- Intranet reverse proxy: ops/nginx/intranet.conf
- Base compose stack: docker-compose.yml
- Intranet compose override: docker-compose.intranet.yml
- Automation commands: Makefile
