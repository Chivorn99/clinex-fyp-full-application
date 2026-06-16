SHELL := /bin/sh

REPO_URL ?= https://github.com/Chivorn99/clinex-fyp-full-application.git
CLONE_DIR ?= clinex-fyp-full-application

HOST ?= 127.0.0.1
BACKEND_PORT ?= 8000
FRONTEND_PORT ?= 3000

COMPOSE ?= docker compose
COMPOSE_INTRAnet ?= docker compose -f docker-compose.yml -f docker-compose.intranet.yml
INTRANET_CERT_DIR := ops/nginx/certs
INTRANET_CERT_CRT := $(INTRANET_CERT_DIR)/clinex-intranet.crt
INTRANET_CERT_KEY := $(INTRANET_CERT_DIR)/clinex-intranet.key
INTRANET_CN ?= kvh.local

BACKEND_DIR := backend
FRONTEND_DIR := frontend

BACKEND_URL := http://$(HOST):$(BACKEND_PORT)
API_URL := $(BACKEND_URL)/api
FRONTEND_URL := http://$(HOST):$(FRONTEND_PORT)

.PHONY: help clone setup env backend-env frontend-env intranet-config install backend-install frontend-install dev-backend dev-frontend status system-design-html docker-up docker-down docker-restart docker-logs docker-build docker-migrate docker-shell-backend docker-shell-frontend docker-intranet-cert docker-intranet-up docker-intranet-down docker-intranet-logs docker-intranet-migrate

help:
	@echo "Clinex local/intranet commands"
	@echo ""
	@echo "  make clone                      Clone the project from the private repository"
	@echo "  make setup HOST=10.10.5.20      Create local env files and install dependencies"
	@echo "  make intranet-config HOST=10.10.5.20  Update URLs for the hospital LAN host"
	@echo "  make install                    Install backend and frontend dependencies"
	@echo "  make system-design-html         Regenerate clinex-system-design.html from clinex_system_design.md"
	@echo "  make dev-backend                Run Laravel on 0.0.0.0:$(BACKEND_PORT)"
	@echo "  make dev-frontend               Run Next.js on 0.0.0.0:$(FRONTEND_PORT)"
	@echo "  make docker-up                  Start full stack with Docker"
	@echo "  make docker-down                Stop Docker stack"
	@echo "  make docker-migrate             Run Laravel migrations in container"
	@echo "  make docker-logs                Follow all service logs"
	@echo "  make docker-intranet-up         Start intranet profile (single 80/443 entrypoint)"
	@echo "  make docker-intranet-down       Stop intranet profile"
	@echo "  make docker-intranet-migrate    Run migrations in intranet profile"
	@echo "  make docker-intranet-logs       Follow intranet profile logs"
	@echo "  make status                     Print the current intranet URLs"

clone:
	@git clone $(REPO_URL) $(CLONE_DIR)

setup: env intranet-config install

env: backend-env frontend-env

backend-env:
	@php -r "if (!file_exists('$(BACKEND_DIR)/.env')) { copy('$(BACKEND_DIR)/.env.example', '$(BACKEND_DIR)/.env'); echo 'Created $(BACKEND_DIR)/.env' . PHP_EOL; } else { echo '$(BACKEND_DIR)/.env already exists' . PHP_EOL; }"

frontend-env:
	@HOST='$(HOST)' BACKEND_PORT='$(BACKEND_PORT)' php -r "$$path='$(FRONTEND_DIR)/.env.local'; if (!file_exists($$path)) { file_put_contents($$path, 'NEXT_PUBLIC_API_URL=http://' . getenv('HOST') . ':' . getenv('BACKEND_PORT') . '/api' . PHP_EOL); echo 'Created ' . $$path . PHP_EOL; } else { echo $$path . ' already exists' . PHP_EOL; }"

intranet-config:
	@HOST='$(HOST)' BACKEND_PORT='$(BACKEND_PORT)' FRONTEND_PORT='$(FRONTEND_PORT)' php -r "$$backendPath='$(BACKEND_DIR)/.env'; $$frontendPath='$(FRONTEND_DIR)/.env.local'; $$backendLines=file_exists($$backendPath) ? file($$backendPath, FILE_IGNORE_NEW_LINES) : file('$(BACKEND_DIR)/.env.example', FILE_IGNORE_NEW_LINES); $$set=function(&$$lines, $$key, $$value) { $$found=false; foreach ($$lines as &$$line) { if (str_starts_with($$line, $$key . '=')) { $$line = $$key . '=' . $$value; $$found=true; break; } } unset($$line); if (!$$found) { $$lines[] = $$key . '=' . $$value; } }; $$host=getenv('HOST'); $$backendPort=getenv('BACKEND_PORT'); $$frontendPort=getenv('FRONTEND_PORT'); $$set($$backendLines, 'APP_URL', 'http://' . $$host . ':' . $$backendPort); $$set($$backendLines, 'FRONTEND_URL', 'http://' . $$host . ':' . $$frontendPort); file_put_contents($$backendPath, implode(PHP_EOL, $$backendLines) . PHP_EOL); file_put_contents($$frontendPath, 'NEXT_PUBLIC_API_URL=http://' . $$host . ':' . $$backendPort . '/api' . PHP_EOL); echo 'Configured intranet URLs for ' . $$host . PHP_EOL;"

install: backend-install frontend-install

backend-install:
	@cd $(BACKEND_DIR) && composer install

frontend-install:
	@cd $(FRONTEND_DIR) && npm install

dev-backend:
	@cd $(BACKEND_DIR) && php artisan serve --host=0.0.0.0 --port=$(BACKEND_PORT)

dev-frontend:
	@cd $(FRONTEND_DIR) && npm run dev -- --hostname 0.0.0.0 --port $(FRONTEND_PORT)

status:
	@echo "Backend:  $(BACKEND_URL)"
	@echo "API:      $(API_URL)"
	@echo "Frontend: $(FRONTEND_URL)"

system-design-html:
	@node tools/generate-system-design-html.mjs

docker-build:
	@$(COMPOSE) build

docker-up:
	@$(COMPOSE) up -d --build

docker-down:
	@$(COMPOSE) down

docker-restart:
	@$(COMPOSE) restart

docker-logs:
	@$(COMPOSE) logs -f

docker-migrate:
	@$(COMPOSE) exec backend php artisan migrate

docker-shell-backend:
	@$(COMPOSE) exec backend sh

docker-shell-frontend:
	@$(COMPOSE) exec frontend sh

docker-intranet-cert:
	@mkdir -p $(INTRANET_CERT_DIR)
	@if [ ! -f "$(INTRANET_CERT_CRT)" ] || [ ! -f "$(INTRANET_CERT_KEY)" ]; then \
		echo "Generating self-signed intranet certificate..."; \
		docker run --rm -v "$(CURDIR)/$(INTRANET_CERT_DIR):/certs" alpine:3.20 sh -lc "apk add --no-cache openssl >/dev/null && openssl req -x509 -nodes -days 825 -newkey rsa:2048 -keyout /certs/clinex-intranet.key -out /certs/clinex-intranet.crt -subj '/CN=$(INTRANET_CN)'"; \
	else \
		echo "Intranet certificate already exists"; \
	fi

docker-intranet-up: docker-intranet-cert
	@$(COMPOSE_INTRAnet) up -d --build

docker-intranet-down:
	@$(COMPOSE_INTRAnet) down

docker-intranet-logs:
	@$(COMPOSE_INTRAnet) logs -f

docker-intranet-migrate:
	@$(COMPOSE_INTRAnet) exec backend php artisan migrate

# ── Cloud: Droplet 1 — Web + Database (clinex.live) ──
COMPOSE_WEB = docker compose -f docker-compose.cloud-web.yml

docker-web-up:
	@make docker-intranet-cert
	@$(COMPOSE_WEB) up -d --build

docker-web-down:
	@$(COMPOSE_WEB) down

docker-web-logs:
	@$(COMPOSE_WEB) logs -f

docker-web-migrate:
	@$(COMPOSE_WEB) exec backend php artisan migrate --force

docker-web-seed:
	@$(COMPOSE_WEB) exec backend php artisan db:seed --force

docker-web-shell:
	@$(COMPOSE_WEB) exec backend sh

docker-web-restart:
	@$(COMPOSE_WEB) restart

# ── Cloud: Droplet 2 — AI Engine + Queue Worker ──
COMPOSE_AI = docker compose -f docker-compose.cloud-ai.yml

docker-ai-up:
	@$(COMPOSE_AI) up -d --build

docker-ai-down:
	@$(COMPOSE_AI) down

docker-ai-logs:
	@$(COMPOSE_AI) logs -f

docker-ai-pull-model:
	@$(COMPOSE_AI) exec ollama ollama pull phi3:mini

docker-ai-shell:
	@$(COMPOSE_AI) exec queue sh

docker-ai-restart:
	@$(COMPOSE_AI) restart
