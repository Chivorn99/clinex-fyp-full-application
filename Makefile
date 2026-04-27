SHELL := /bin/sh

REPO_URL ?= https://github.com/Chivorn99/clinex-fyp-full-application.git
CLONE_DIR ?= clinex-fyp-full-application

HOST ?= 127.0.0.1
BACKEND_PORT ?= 8000
FRONTEND_PORT ?= 3000

COMPOSE ?= docker compose

BACKEND_DIR := backend
FRONTEND_DIR := frontend

BACKEND_URL := http://$(HOST):$(BACKEND_PORT)
API_URL := $(BACKEND_URL)/api
FRONTEND_URL := http://$(HOST):$(FRONTEND_PORT)

.PHONY: help clone setup env backend-env frontend-env intranet-config install backend-install frontend-install dev-backend dev-frontend status docker-up docker-down docker-restart docker-logs docker-build docker-migrate docker-shell-backend docker-shell-frontend

help:
	@echo "Clinex local/intranet commands"
	@echo ""
	@echo "  make clone                      Clone the project from the private repository"
	@echo "  make setup HOST=10.10.5.20      Create local env files and install dependencies"
	@echo "  make intranet-config HOST=10.10.5.20  Update URLs for the hospital LAN host"
	@echo "  make install                    Install backend and frontend dependencies"
	@echo "  make dev-backend                Run Laravel on 0.0.0.0:$(BACKEND_PORT)"
	@echo "  make dev-frontend               Run Next.js on 0.0.0.0:$(FRONTEND_PORT)"
	@echo "  make docker-up                  Start full stack with Docker"
	@echo "  make docker-down                Stop Docker stack"
	@echo "  make docker-migrate             Run Laravel migrations in container"
	@echo "  make docker-logs                Follow all service logs"
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
