# Clinex Application

Monorepo for Clinex backend (Laravel) and frontend (Next.js).

Detailed operations guide:
- PROJECT_FLOW_AND_OPERATIONS.md

Hospital handover SOP (one page):
- HOSPITAL_HANDOVER_SOP.md

## Hospital Intranet Model

This project is best hosted on a private hospital network instead of the public internet.

In practice, one trusted server inside the Hospital LAN runs all containers, and users access a single internal entrypoint through Nginx on `80/443`.

Staff devices on the same network open the app using the server's internal IP or internal DNS name, for example `https://10.10.5.20`.

The important rule is simple: do not expose these ports to the public internet. Keep access limited to the hospital network or a VPN.

## Project Structure
- `backend`: Laravel API, queue jobs, OCR integration
- `frontend`: Next.js web app

## 1. Clone
```bash
git clone https://github.com/Chivorn99/clinex-fyp-full-application.git
cd "clinex-fyp-full-application"
```

## 2. Preferred Setup (Docker + Makefile)

This is the recommended path for confidential intranet deployment and for developer onboarding.

### 2.1 Requirements
- Docker Desktop (or Docker Engine + Docker Compose plugin)
- GNU Make (optional convenience wrapper)

### 2.2 Configure host IP for intranet
Pick your intranet host IP or internal DNS, then run:

```bash
make setup HOST=10.10.5.20
```

This will:
- Create `backend/.env` if missing
- Create `frontend/.env.local` if missing
- Set `APP_URL`, `FRONTEND_URL`, and `NEXT_PUBLIC_API_URL` for your intranet host

### 2.3 Start all services in containers (intranet profile)
```bash
make docker-intranet-up
```

### 2.4 Run database migrations
```bash
make docker-intranet-migrate
```

### 2.5 Check logs
```bash
make docker-intranet-logs
```

### 2.6 Stop services
```bash
make docker-intranet-down
```

After startup:
- App entrypoint: `https://10.10.5.20`
- API path (proxied): `https://10.10.5.20/api`

On first run, the Makefile generates a self-signed TLS certificate for intranet use under `ops/nginx/certs/`. For production hospital use, replace it with a certificate issued by your internal CA.

## 3. Docker Services Included
- `mysql`: MySQL 8.4 database
- `backend`: Laravel API container
- `queue`: Laravel queue worker container
- `frontend`: Next.js app container
- `nginx` (in intranet profile): reverse proxy and single 80/443 entrypoint

## 5. OCR Setup (Google Document AI)

### 5.1 Place service-account credentials
Put your Google service-account JSON at:
- `backend/storage/app/google/<your-key-file>.json`

### 5.2 Configure backend env
In `backend/.env`, set:
- `GOOGLE_APPLICATION_CREDENTIALS=app/google/<your-key-file>.json`
- `GOOGLE_CLOUD_PROJECT_ID=<your-project-id>`
- `GOOGLE_CLOUD_LOCATION=<processor-region>`
- `GOOGLE_CLOUD_DOCUMENT_AI_PROCESSOR_ID=<processor-id>`

Current OCR flow supports PDF and image files (JPG, JPEG, PNG, TIFF, GIF, BMP, WEBP) with auto mime detection.

### 5.3 Optional Python CLI environment for OCR script
From `backend/scripts/python`:
```powershell
./setup_ocr_env.ps1
```
This creates a local virtual environment and installs required packages from `requirements.txt`.

## 6. Non-Docker Local Development (Optional)
If you prefer to run directly on your machine:

1. Install prerequisites manually: PHP 8.2+, Composer 2+, Node.js 20+, MySQL 8+, Python 3.10+
2. Backend:
```bash
cd backend
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan serve --host=0.0.0.0 --port=8000
```
3. Queue worker (new terminal):
```bash
cd backend
php artisan queue:work
```
4. Frontend:
```bash
cd frontend
npm install
npm run dev -- --hostname 0.0.0.0 --port 3000
```

## 7. Credentials Safety Before Pushing

### Important
Never commit:
- `.env` files
- Service-account keys
- Private key files (`.pem`, `.p12`, `.key`)

The repo now ignores Google key files under:
- `backend/storage/app/google/`

### Pre-push safety checklist
From repo root:
```bash
git status --short
```
Verify no `.env` or key files are listed.

```powershell
git ls-files | Where-Object { $_ -match '(^|/)\.env$|\.pem$|\.p12$|\.key$|google.*\.json|credentials' }
```
Expected output: empty.

If you use Git Bash:
```bash
git ls-files | grep -E "(\.env$|\.pem$|\.p12$|\.key$|google.*\.json|credentials)"
```

If a secret was ever committed, rotate it immediately (Google key, mail/app secrets, etc.) and rewrite git history if needed.

## 8. Troubleshooting
- If `php artisan cache:clear` fails with DB connection errors, ensure MySQL is running or temporarily use file cache locally.
- If Python script says `ModuleNotFoundError: google.cloud`, run the Python setup script in `backend/scripts/python`.
- If Document AI returns `NOT_FOUND` or permission errors, verify project id, region, processor id, and service-account IAM role.
- If `make` is not available on Windows, run equivalent commands directly with `docker compose`.
