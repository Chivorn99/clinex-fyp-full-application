# Clinex Application

Monorepo for Clinex backend (Laravel) and frontend (Next.js).

## Project Structure
- `backend`: Laravel API, queue jobs, OCR integration
- `frontend`: Next.js web app

## 1. Clone
```bash
git clone <your-repo-url>
cd "Clinex Application"
```

## 2. Prerequisites
- PHP 8.2+
- Composer 2+
- Node.js 20+
- MySQL 8+
- Python 3.10+

Optional but recommended:
- Redis (for queue/cache in non-local setups)

## 3. Backend Setup (Laravel)
```bash
cd backend
composer install
npm install
```

Create your local environment file:
```bash
cp .env.example .env
```
If `.env.example` does not exist in your clone, create `.env` manually.

Generate app key (if needed):
```bash
php artisan key:generate
```

Set database values in `.env`, then run:
```bash
php artisan migrate
```

Build assets (if needed):
```bash
npm run build
```

Run backend locally:
```bash
php artisan serve
```

### Queue worker
In another terminal:
```bash
php artisan queue:work
```

## 4. Frontend Setup (Next.js)
```bash
cd frontend
npm install
npm run dev
```

Frontend default URL is typically `http://localhost:3000`.

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

## 6. Run Locally
Recommended order:
1. Start MySQL
2. Start backend (`php artisan serve`)
3. Start queue worker (`php artisan queue:work`)
4. Start frontend (`npm run dev`)

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
