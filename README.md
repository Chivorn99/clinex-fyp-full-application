<div align="center">

# 🏥 Clinex

**Clinical Lab Report Management System**

*Multi-engine OCR + LLM pipeline for digitizing Cambodian lab reports*

[![Live Demo](https://img.shields.io/badge/Live-clinex.live-0ea5e9?style=for-the-badge&logo=vercel)](https://clinex.live)
[![Laravel](https://img.shields.io/badge/Laravel-11-FF2D20?style=flat-square&logo=laravel&logoColor=white)](https://laravel.com)
[![Next.js](https://img.shields.io/badge/Next.js-14-000000?style=flat-square&logo=next.js&logoColor=white)](https://nextjs.org)
[![Docker](https://img.shields.io/badge/Docker-Compose-2496ED?style=flat-square&logo=docker&logoColor=white)](https://docs.docker.com/compose/)
[![License](https://img.shields.io/badge/License-MIT-green?style=flat-square)](LICENSE)

</div>

---

## 📋 Overview

Clinex is a full-stack web application that digitizes **handwritten and printed clinical lab reports** from Cambodian hospitals. It uses a multi-engine OCR pipeline fused with an LLM to extract structured test results (Biochemistry, Hematology, Serology/Immunology, Urine Analysis) and presents them through a modern analytics dashboard.

Built as a **Final Year Project (FYP)**, Clinex is designed for two deployment models:
- **☁️ Cloud** — Two-droplet architecture on DigitalOcean with SSL
- **🏥 Hospital Intranet** — Single-server air-gapped deployment behind Nginx

---

## ✨ Key Features

| Feature | Description |
|---------|-------------|
| 🔍 **Multi-Engine OCR** | PaddleOCR (English/numeric) + KiriOCR (Khmer) with dual-engine fusion and Google Document AI fallback |
| 🤖 **LLM Extraction** | Ollama phi3:mini structures raw OCR text into validated JSON with test names, values, units & ranges |
| 📄 **Batch Upload** | Upload multiple PDF/image lab reports at once with real-time processing progress |
| ✅ **Verification Workflow** | Human-in-the-loop review and correction of extracted data before finalization |
| 📊 **Analytics Dashboard** | Visual insights — reports per day/week, processing stats, verification rates |
| 🛡️ **RBAC** | Role-based access control (Admin, Lab Technician) with granular permissions |
| 🖥️ **System Health Monitor** | Real-time status of Database, AI Engines, Queue Worker, and Disk — with stale job auto-cleanup |
| 📤 **CSV Export** | Export verified structured data for integration with hospital LIS/EMR systems |
| 🏥 **Hospital SOP** | One-page operational guide for hospital IT handover |

---

## 🏗️ Architecture

### Cloud Deployment (2-Droplet)

```
                    ┌─────────────────────────────────────────────┐
                    │        Droplet 1 — Web + Database           │
                    │           (2 vCPU / 4 GB RAM)               │
                    │                                             │
  Users ──HTTPS──▶  │  Nginx (:443)                               │
                    │    ├── Next.js Frontend (:3000)              │
                    │    └── Laravel API (:8000)                   │
                    │  MySQL (:3306)  ·  Redis (:6379)            │
                    │  Laravel Scheduler (hourly cleanup)         │
                    └──────────────────┬──────────────────────────┘
                                       │ VPC (10.104.0.x)
                    ┌──────────────────┴──────────────────────────┐
                    │        Droplet 2 — AI Engine                │
                    │           (4 vCPU / 8 GB RAM)               │
                    │                                             │
                    │  Ollama LLM (phi3:mini)                     │
                    │  Laravel Horizon (Queue Worker)             │
                    │  PaddleOCR · KiriOCR · Python Pipeline      │
                    └─────────────────────────────────────────────┘
```

### OCR Processing Pipeline

```
Upload PDF ──▶ Laravel Queue (Redis)
                 │
                 ▼
         ProcessLabReportBatch Job
                 │
                 ▼
    ┌────────────────────────────┐
    │  Python document_ocr.py    │
    │                            │
    │  1. PaddleOCR (English)  ──┤──▶ Dual-Engine
    │  2. KiriOCR (Khmer)      ──┤    Fusion
    │                            │       │
    │  3. Google Document AI   ◀─┤── (if confidence < 0.85)
    │                            │
    │  4. Ollama phi3:mini     ──┤──▶ Structured JSON
    │     (LLM extraction)       │    {testResults, patientInfo}
    └────────────────────────────┘
                 │
                 ▼
    Extracted data saved to DB ──▶ Verification UI
```

---

## 🛠️ Tech Stack

| Layer | Technology |
|-------|------------|
| **Backend** | Laravel 11, PHP 8.2, Sanctum Auth |
| **Frontend** | Next.js 14, React, Tailwind CSS |
| **Database** | MySQL 8.4 |
| **Cache / Queue** | Redis, Laravel Horizon |
| **OCR Engine 1** | PaddleOCR v3.5 — English & numeric extraction |
| **OCR Engine 2** | KiriOCR — Khmer text recognition |
| **OCR Fallback** | Google Cloud Document AI |
| **LLM** | Ollama (phi3:mini) — structured JSON extraction |
| **OCR Runtime** | Python 3.10+, PyMuPDF |
| **Reverse Proxy** | Nginx 1.27 with SSL/TLS |
| **Containers** | Docker + Docker Compose |
| **Build** | GNU Make |
| **SSL** | Let's Encrypt (cloud), self-signed (intranet) |

---

## 📁 Project Structure

```
clinex/
├── backend/                   # Laravel 11 API
│   ├── app/
│   │   ├── Console/Commands/  # Artisan commands (stale job cleanup)
│   │   ├── Http/Controllers/  # API controllers (Admin, Auth, Reports, Batches)
│   │   ├── Jobs/              # Queue jobs (ProcessLabReportBatch, ProcessSingleLabReport)
│   │   └── Models/            # Eloquent models (User, LabReport, ReportBatch, Patient)
│   ├── config/clinex.php      # App-specific config (stale job threshold)
│   └── scripts/python/        # OCR pipeline (document_ocr.py)
├── frontend/                  # Next.js 14 App
│   └── src/app/
│       ├── admin/             # Admin panel (Users, Reports, Templates, System Health)
│       ├── dashboard/         # Main dashboard with analytics
│       └── reports/           # Report upload, processing, verification
├── docs/                      # Documentation
│   ├── HOSPITAL_HANDOVER_SOP.md
│   └── PROJECT_FLOW_AND_OPERATIONS.md
├── ops/nginx/                 # Nginx configs & SSL certs
├── tools/                     # Build utilities
├── docker-compose.yml         # Local development (full stack)
├── docker-compose.cloud-web.yml   # Production: Droplet 1 (Web + DB)
├── docker-compose.cloud-ai.yml    # Production: Droplet 2 (AI + Queue)
├── docker-compose.intranet.yml    # Hospital intranet overlay
├── docker-compose.gpu.yml         # GPU override
└── Makefile                   # All build/deploy commands
```

---

## 🚀 Quick Start

### Prerequisites

- Docker & Docker Compose
- Git
- GNU Make (optional but recommended)

### 1. Clone

```bash
git clone https://github.com/Chivorn99/clinex-fyp-full-application.git
cd clinex-fyp-full-application
```

### 2. Setup Environment

```bash
make setup HOST=127.0.0.1
# Creates .env files and installs dependencies
```

### 3. Start (Docker)

```bash
make docker-up
# Starts: MySQL, Redis, Backend, Frontend, Ollama, Queue Worker, phpMyAdmin
```

### 4. Initialize Database

```bash
make docker-migrate
```

### 5. Access

| Service | URL |
|---------|-----|
| Frontend | http://localhost:3000 |
| Backend API | http://localhost:8000/api |
| phpMyAdmin | http://localhost:8080 |

---

## 🏥 Hospital Intranet Deployment

For air-gapped hospital networks with no internet access:

```bash
# Configure for hospital LAN IP
make setup HOST=10.10.5.20

# Start with Nginx + self-signed TLS
make docker-intranet-up

# Run migrations
make docker-intranet-migrate
```

Staff access the app at `https://10.10.5.20`. See [`docs/HOSPITAL_HANDOVER_SOP.md`](docs/HOSPITAL_HANDOVER_SOP.md) for operational procedures.

---

## ☁️ Cloud Deployment

### Droplet 1 — Web Server

```bash
make docker-web-up          # Build & start
make docker-web-migrate     # Run migrations
make docker-web-seed        # Seed demo data
make docker-web-logs        # Follow logs
```

### Droplet 2 — AI Engine

```bash
make docker-ai-up           # Build & start
make docker-ai-pull-model   # Download phi3:mini into Ollama
make docker-ai-logs         # Follow logs
```

### Updating Production

```bash
# On your local machine
git push origin sit

# SSH into the droplet
cd /opt/clinex
git fetch origin sit
git reset --hard origin/sit
docker compose -f docker-compose.cloud-web.yml up -d --force-recreate --build
```

---

## ⚙️ Environment Variables

### OCR / AI Configuration

| Variable | Default | Description |
|----------|---------|-------------|
| `PADDLE_OCR_ENABLED` | `false` | Enable PaddleOCR engine |
| `PADDLE_OCR_LANGUAGE` | `en` | OCR language model |
| `PADDLE_OCR_DEVICE` | `auto` | `cpu` or `auto` (GPU) |
| `KIRI_OCR_ENABLED` | `false` | Enable KiriOCR (Khmer) |
| `KIRI_OCR_DECODE_METHOD` | `accurate` | Decoding strategy |
| `KIRI_OCR_DEVICE` | `auto` | `cpu` or `auto` (GPU) |
| `GOOGLE_CLOUD_PROJECT_ID` | — | GCP project for Document AI fallback |
| `GOOGLE_CLOUD_DOCUMENT_AI_PROCESSOR_ID` | — | Document AI processor ID |
| `GOOGLE_APPLICATION_CREDENTIALS` | — | Path to GCP service account JSON |
| `OLLAMA_ENABLED` | `false` | Enable LLM post-processing |
| `OLLAMA_HOST` | `http://ollama:11434` | Ollama server URL |
| `OLLAMA_MODEL` | `phi3:mini` | LLM model for extraction |
| `OLLAMA_TIMEOUT` | `600` | Timeout in seconds |

### Application

| Variable | Default | Description |
|----------|---------|-------------|
| `CLINEX_STALE_JOB_THRESHOLD_MINUTES` | `120` | Auto-reset stuck processing jobs after N minutes |

---

## 🧰 Make Targets Reference

<details>
<summary><strong>Click to expand all targets</strong></summary>

### Local Development
```
make setup HOST=<ip>      # Full setup (env + install)
make docker-up            # Start full stack
make docker-down          # Stop stack
make docker-logs          # Follow logs
make docker-migrate       # Run migrations
make docker-shell-backend # Shell into backend
```

### Hospital Intranet
```
make docker-intranet-up   # Start with Nginx + TLS
make docker-intranet-down # Stop
make docker-intranet-logs # Follow logs
```

### Cloud — Droplet 1 (Web)
```
make docker-web-up        # Build & start
make docker-web-down      # Stop
make docker-web-migrate   # Migrate (--force)
make docker-web-seed      # Seed (--force)
make docker-web-shell     # Shell into backend
make docker-web-logs      # Follow logs
make docker-web-restart   # Restart
```

### Cloud — Droplet 2 (AI)
```
make docker-ai-up         # Build & start
make docker-ai-down       # Stop
make docker-ai-pull-model # Pull phi3:mini
make docker-ai-shell      # Shell into queue
make docker-ai-logs       # Follow logs
make docker-ai-restart    # Restart
```

</details>

---

## 📚 Documentation

| Document | Description |
|----------|-------------|
| [`docs/PROJECT_FLOW_AND_OPERATIONS.md`](docs/PROJECT_FLOW_AND_OPERATIONS.md) | End-to-end project flow for local dev and intranet |
| [`docs/HOSPITAL_HANDOVER_SOP.md`](docs/HOSPITAL_HANDOVER_SOP.md) | One-page SOP for hospital IT handover |

---

## 👤 Author

**Nhoung Chivorn** — Final Year Project, 2026

---

<div align="center">
  <sub>Built with ❤️ for Cambodian healthcare</sub>
</div>
