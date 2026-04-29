# Clinex Backend (Laravel)

This folder contains the Laravel API used by the Clinex intranet application.

## Recommended Workflow

Use the root-level Docker + Makefile workflow described in the main repository README.

From the repository root:

```bash
make setup HOST=10.10.5.20
make docker-up
make docker-migrate
```

## Backend Responsibilities

- Authentication and user management
- Patient and lab report APIs
- OCR processing jobs and queue workers
- Verified report exports

## Sensitive Files

Never commit:

- `.env`
- `storage/app/google/*.json`
- private key files

