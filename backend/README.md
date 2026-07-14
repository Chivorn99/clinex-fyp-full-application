# Clinex Backend

The REST API and AI processing engine for the Clinex clinical lab report management system, built with **Laravel 12**.

## Tech Stack

- **Framework**: Laravel 12 (PHP 8.2)
- **Auth**: Laravel Sanctum (token-based)
- **Queue**: Laravel Horizon + Redis
- **Database**: MySQL 8.0
- **OCR**: Tesseract (via Python)
- **AI/LLM**: Phi-3 via Ollama (local inference)
- **Testing**: Pest PHP v3

## API Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| `POST` | `/api/login` | Authenticate user |
| `POST` | `/api/register` | Register new user |
| `POST` | `/api/logout` | Logout |
| `GET` | `/api/user` | Current user |
| `GET` | `/api/health` | Health check |
| `GET/POST` | `/api/batches` | Batch management |
| `GET` | `/api/lab-reports` | Lab reports |
| `POST` | `/api/lab-reports/{id}/verify` | Verify report |
| `GET/POST` | `/api/patients` | Patient management |
| `GET` | `/api/analytics` | Analytics data |
| `GET` | `/api/admin/*` | Admin operations |

## Testing

```bash
# Run all 118 tests
./vendor/bin/pest

# Run specific test suite
./vendor/bin/pest tests/Unit
./vendor/bin/pest tests/Feature
```

**Test Coverage**: 118 tests, 282 assertions, 100% pass rate

## AI Pipeline

```
PDF → Tesseract OCR → Text Extraction → Phi-3 LLM → Structured JSON → Human Verification
```

The AI pipeline runs locally — no patient data leaves the network.

## Sensitive Files

Never commit these files:
- `.env` — Application secrets
- `storage/app/google/*.json` — GCP service account keys
- `storage/*.key` — Private keys
