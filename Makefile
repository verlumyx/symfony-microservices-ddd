# ==============================================================================
# Makefile - Symfony Microservices Stack
# ==============================================================================

.DEFAULT_GOAL := help
DC := docker compose

# Colores ANSI para la salida en consola
GREEN  := \033[32m
YELLOW := \033[33m
RESET  := \033[0m

##@ ℹ️ Ayuda
.PHONY: help
help: ## Muestra este menú de ayuda
	@echo -e ''
	@echo -e '$(YELLOW)Uso:$(RESET)'
	@echo -e '  make $(GREEN)<comando>$(RESET)'
	@echo -e ''
	@echo -e '$(YELLOW)Comandos disponibles:$(RESET)'
	@awk 'BEGIN {FS = ":.*##"; printf ""} /^[a-zA-Z_0-9-]+:.*?##/ { printf "  \033[32m%-20s\033[0m %s\n", $$1, $$2 } /^##@/ { printf "\n\033[33m%s\033[0m\n", substr($$0, 5) } ' $(MAKEFILE_LIST)
	@echo -e ''

##@ 🐳 Gestión de Contenedores Docker
.PHONY: up down start stop restart build ps logs logs-php clean

up: ## Inicia todos los contenedores en segundo plano
	$(DC) up -d

down: ## Detiene y destruye los contenedores
	$(DC) down

start: ## Inicia contenedores existentes
	$(DC) start

stop: ## Detiene contenedores en ejecución
	$(DC) stop

restart: ## Reinicia todos los contenedores
	$(DC) restart

build: ## Reconstruye las imágenes de Docker
	$(DC) build

ps: ## Lista el estado de los contenedores
	$(DC) ps

logs: ## Muestra los logs en tiempo real de todos los servicios
	$(DC) logs -f

logs-php: ## Muestra los logs del contenedor PHP
	$(DC) logs -f php

clean: ## Detiene contenedores y elimina volúmenes y redes huérfanas (⚠️ borra datos)
	$(DC) down -v --remove-orphans

##@ 🐚 Terminal y Acceso a Contenedores
.PHONY: sh sh-root sh-db sh-redis perms

perms: ## Corrige los permisos de todos los archivos para el usuario anfitrión (UID 1000)
	$(DC) exec -u root php chown -R 1000:1000 /var/www/html
	$(DC) exec -u root php chmod -R u+rwX /var/www/html

sh: ## Abre una terminal interactiva (sh/bash) dentro del contenedor PHP
	$(DC) exec php bash || $(DC) exec php sh

sh-root: ## Abre una terminal con usuario root en el contenedor PHP
	$(DC) exec -u root php bash || $(DC) exec -u root php sh

sh-db: ## Entra a la consola PostgreSQL (psql)
	$(DC) exec database psql -U app -d app

sh-redis: ## Entra a la consola interactiva de Redis (redis-cli)
	$(DC) exec redis redis-cli

##@ 📦 Composer y Dependencias
.PHONY: composer-install composer-update

composer-install: ## Ejecuta composer install dentro del contenedor
	$(DC) exec php composer install

composer-update: ## Ejecuta composer update dentro del contenedor
	$(DC) exec php composer update

##@ ⚡ Symfony Console
.PHONY: sf cc routes

sf: ## Ejecuta comandos de bin/console (ej: make sf cmd="cache:clear")
	$(DC) exec php php bin/console $(cmd)

cc: ## Limpia la cache de Symfony
	$(DC) exec php php bin/console cache:clear

routes: ## Muestra las rutas registradas en la aplicación
	$(DC) exec php php bin/console debug:router

##@ 📨 Symfony Messenger
.PHONY: messenger-setup consume consume-fast messenger-failed messenger-retry

messenger-setup: ## Inicializa colas, exchanges y transportes en RabbitMQ y Redis
	$(DC) exec php php bin/console messenger:setup-transports

consume: ## Inicia el consumidor de pedidos (worker RabbitMQ en tiempo real)
	$(DC) exec php php bin/console messenger:consume async_orders -vv

consume-fast: ## Inicia el consumidor rápido (worker Redis en tiempo real)
	$(DC) exec php php bin/console messenger:consume async_fast -vv

messenger-failed: ## Muestra los mensajes fallidos en la Dead Letter Queue
	$(DC) exec php php bin/console messenger:failed:show

messenger-retry: ## Reintenta los mensajes fallidos de la Dead Letter Queue
	$(DC) exec php php bin/console messenger:failed:retry

##@ 🗄️ Base de Datos y Doctrine
.PHONY: db-create db-migrate db-diff db-check-vector

db-create: ## Crea la base de datos si no existe
	$(DC) exec php php bin/console doctrine:database:create --if-not-exists

db-diff: ## Genera una migración con las diferencias de las entidades
	$(DC) exec php php bin/console doctrine:migrations:diff

db-migrate: ## Aplica las migraciones pendientes
	$(DC) exec php php bin/console doctrine:migrations:migrate --no-interaction

db-check-vector: ## Verifica la extensión pgvector en PostgreSQL
	$(DC) exec database psql -U app -d app -c "SELECT extname, extversion FROM pg_extension WHERE extname = 'vector';"
