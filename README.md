# 🚀 Symfony Microservices & Event-Driven API (DDD / Hexagonal)

[![Symfony 7.4](https://img.shields.io/badge/Symfony-7.4-blue.svg?logo=symfony)](https://symfony.com)
[![PHP 8.3](https://img.shields.io/badge/PHP-8.3-777BB4.svg?logo=php)](https://www.php.net)
[![PostgreSQL 16](https://img.shields.io/badge/PostgreSQL-16%20(pgvector)-336791.svg?logo=postgresql)](https://www.postgresql.org)
[![RabbitMQ 3.13](https://img.shields.io/badge/RabbitMQ-3.13-FF6600.svg?logo=rabbitmq)](https://www.rabbitmq.com)
[![Redis 7](https://img.shields.io/badge/Redis-7-DC382D.svg?logo=redis)](https://redis.io)
[![Docker](https://img.shields.io/badge/Docker-Compose-2496ED.svg?logo=docker)](https://www.docker.com)

API RESTful empresarial de alto rendimiento desarrollada con **Symfony 7.4** y **PHP 8.3 FPM**, aplicando **Arquitectura Hexagonal (Puertos y Adaptadores)** y **Domain-Driven Design (DDD)**. Implementa procesamiento asíncrono basado en eventos (**Event-Driven Architecture**) con **RabbitMQ**, control de idempotencia y locks distribuidos con **Redis**, y soporte para búsqueda vectorial con **PostgreSQL + pgvector**.

---

## 📑 Tabla de Contenidos

- [🛠️ Stack Tecnológico](#️-stack-tecnológico)
- [🏛️ Arquitectura del Sistema](#️-arquitectura-del-sistema)
- [🔍 Herramientas y Paneles de Inspección](#-herramientas-y-paneles-de-inspección)
- [⚙️ Requisitos Previos](#️-requisitos-previos)
- [🚀 Instalación y Puesta en Marcha](#-instalación-y-puesta-en-marcha)
- [🔄 ¿Cómo Funciona el Proyecto?](#-cómo-funciona-el-proyecto)
- [📡 Endpoints de la API](#-endpoints-de-la-api)
- [⌨️ Comandos Útiles (`Makefile`)](#️-comandos-útiles-makefile)
- [📂 Estructura de Directorios](#-estructura-de-directorios)

---

## 🛠️ Stack Tecnológico

| Componente | Versión / Imagen | Rol / Propósito |
| :--- | :--- | :--- |
| **PHP Runtime** | `php:8.3-fpm-alpine` | PHP 8.3 FPM con extensiones `amqp`, `redis`, `pdo_pgsql`, `opcache`, `intl` y `bcmath` |
| **Servidor Web** | `nginx:1.27-alpine` | Proxy inverso HTTP / FastCGI sirviendo en el puerto `8080` |
| **Base de Datos** | `postgres:16-alpine` | PostgreSQL 16 con extensión `pgvector` compilada para búsqueda semántica |
| **Message Broker** | `rabbitmq:3.13-management` | AMQP 0-9-1 para colas asíncronas, exchanges tipo topic y DLQ |
| **Caché y Locks** | `redis:7-alpine` | Idempotencia, semáforos/locks distribuidos y lecturas ultrarrápidas de estado |
| **Worker en Background** | Contenedor dedicado | Ejecuta `messenger:consume` permanentemente en segundo plano con autorecuperación |
| **Inspección Redis** | `rediscommander` | Interfaz web para explorar llaves y memoria en Redis (`http://localhost:8081`) |

---

## 🏛️ Arquitectura del Sistema

El proyecto sigue una separación estricta en tres capas concéntricas:

$$\text{Infrastructure (HTTP / Persistencia / Mensajería)} \longrightarrow \text{Application (Casos de Uso / Puertos / DTOs)} \longrightarrow \text{Domain (Entidades / Reglas / Interfaces)}$$

1. **`Domain` (Núcleo Puro):**
   - Entidades ricas con métodos expresivos de negocio (`Order::create()`, `markAsProcessing()`, `markAsConfirmed()`).
   - Contratos agnósticos de persistencia (`OrderRepositoryInterface`, `UserRepositoryInterface`).
   - Excepciones tipadas de dominio (`OrderNotFoundException`, `OrderLockException`, `InvalidOrderException`).

2. **`Application` (Casos de Uso & Puertos):**
   - Orquestación pura de la lógica de aplicación sin acoplamiento a frameworks (`CreateOrderUseCase`, `GetOrderStatusUseCase`, etc.).
   - Puertos (Interfaces de salida): `OrderStatusCacheInterface`, `IdempotencyManagerInterface`, `OrderEventPublisherInterface`.
   - DTOs y comandos inmutables (`CreateOrderCommand`, `OrderResponseDto`, `OrderStatusDto`).

3. **`Infrastructure` (Adaptadores Concretos):**
   - **Controladores delgados:** Únicamente extraen parámetros HTTP, invocan el caso de uso y devuelven respuestas JSON.
   - **Adaptadores:** Implementaciones de los puertos utilizando Symfony Messenger, Doctrine ORM y Redis Cache/Locks.
   - **OpenAPI Decorator:** Documentación Swagger autogenerada mediante decoradores de `api_platform.openapi.factory`.

> 📊 **Diagrama visual:** Puedes ver el diagrama gráfico interactivo abriendo [`docs/diagrama_arquitectura.html`](docs/diagrama_arquitectura.html) en tu navegador (`xdg-open docs/diagrama_arquitectura.html`).

---

## 🔍 Herramientas y Paneles de Inspección

Con los contenedores en marcha, dispones de acceso inmediato a:

| Servicio | URL | Credenciales |
| :--- | :--- | :--- |
| **Swagger UI (OpenAPI Docs)** | [http://localhost:8080/api/docs](http://localhost:8080/api/docs) | N/A (Público) |
| **RabbitMQ Management** | [http://localhost:15672](http://localhost:15672) | Usuario: `app` • Clave: `app_secret` |
| **Redis Commander** | [http://localhost:8081](http://localhost:8081) | N/A (Sin contraseña) |
| **API Base URL** | [http://localhost:8080/api](http://localhost:8080/api) | Autenticación Bearer JWT |

---

## ⚙️ Requisitos Previos

- **Docker** $\ge$ 24.0
- **Docker Compose** $\ge$ 2.20
- **Make** *(opcional, pero agiliza los comandos)*
- **Git**

> ℹ️ *No necesitas tener PHP, Composer, PostgreSQL, RabbitMQ ni Redis instalados en tu máquina anfitriona; todo corre 100% dentro de contenedores Docker.*

---

## 🚀 Instalación y Puesta en Marcha

### 1. Clonar el repositorio
```bash
git clone <URL_DEL_REPOSITORIO>
cd symfony-practica
```

### 2. Iniciar todos los contenedores Docker
```bash
make up
# O manualmente: docker compose up -d
```

### 3. Instalar dependencias de Composer
```bash
make composer-install
```

### 4. Generar claves criptográficas para JWT
```bash
make sf cmd="lexik:jwt:generate-keypair --skip-if-exists"
```

### 5. Aplicar migraciones en PostgreSQL
```bash
make db-migrate
```

### 6. Configurar colas y exchanges en RabbitMQ
```bash
make messenger-setup
```

### 7. Verificar el estado de los contenedores
```bash
make ps
```
Deberías ver 7 contenedores activos: `symfony_php`, `symfony_web`, `symfony_database`, `symfony_redis`, `symfony_rabbitmq`, `symfony_redis_commander` y `symfony_worker`.

---

## 🔄 ¿Cómo Funciona el Proyecto?

### 1. Autenticación con JWT (Módulo `Auth`)
- El usuario se registra en `POST /api/auth/register`.
- Se autentica en `POST /api/auth/login` recibiendo un token JWT firmado (RS256).
- Los endpoints protegidos requieren la cabecera `Authorization: Bearer <TOKEN>`.

### 2. Creación Asíncrona de Pedidos con Idempotencia (Módulo `Order`)
- El cliente envía `POST /api/orders` con la cabecera opcional `X-Idempotency-Key: <UUID>`.
- [`RedisIdempotencyManager`](src/Modules/Order/Infrastructure/Idempotency/RedisIdempotencyManager.php) adquiere un **lock distribuido** en Redis para prevenir peticiones simultáneas idénticas.
- Si la orden ya se procesó, devuelve inmediatamente la orden existente (`202 Accepted`).
- Si es nueva:
  1. Guarda la orden en PostgreSQL con estado `PENDING`.
  2. Precalienta el estado `PENDING` en Redis (`order_status_{id}`).
  3. Publica `CreateOrderMessage` en el exchange `orders_exchange` de RabbitMQ.
  4. Devuelve `202 Accepted` al cliente en menos de **15 ms**.

### 3. Procesamiento en Segundo Plano (Worker)
- El contenedor `symfony_worker` escucha permanentemente la cola `order_processing`.
- Al recibir el mensaje:
  1. Cambia el estado a `PROCESSING` y actualiza Redis en tiempo real.
  2. Ejecuta la lógica pesada (simulación de cobro bancario y validación de inventario).
  3. Cambia el estado a `CONFIRMED` y actualiza Redis.
  4. Publica `SendNotificationMessage` a RabbitMQ, el cual es consumido para notificar al comprador.

### 4. Consulta Ultrarrápida de Estado (`GET /api/orders/{id}/status`)
- **Cache HIT:** Consulta Redis directamente y responde en **$< 1$ ms** con la cabecera `X-Cache: HIT`.
- **Cache MISS:** Si la llave expiró en Redis, consulta PostgreSQL, repuebla automáticamente Redis para futuras consultas y responde con `X-Cache: MISS`.

---

## 📡 Endpoints de la API

### Módulo `Auth`

| Método | Ruta | Descripción | Auth |
| :--- | :--- | :--- | :--- |
| `POST` | `/api/auth/register` | Registro de nuevo usuario | Pública |
| `POST` | `/api/auth/login` | Login y obtención de Token JWT | Pública |
| `GET` | `/api/auth/me` | Perfil del usuario autenticado | Bearer JWT |

### Módulo `Order`

| Método | Ruta | Descripción | Auth |
| :--- | :--- | :--- | :--- |
| `POST` | `/api/orders` | Creación asíncrona de pedido (soporta `X-Idempotency-Key`) | Bearer JWT |
| `GET` | `/api/orders/{id}/status` | **Consulta ultrarrápida de estado en Redis (`X-Cache: HIT/MISS`)** | Bearer JWT |
| `GET` | `/api/orders/{id}` | Consulta detallada del pedido en PostgreSQL | Bearer JWT |
| `GET` | `/api/orders` | Listado de pedidos registrados | Bearer JWT |

#### 📝 Ejemplo de creación de pedido:
```bash
# 1. Obtener Token
TOKEN=$(curl -s -X POST http://localhost:8080/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email": "dev@example.com", "password": "password123"}' | grep -o '"token":"[^"]*' | cut -d'"' -f4)

# 2. Crear Pedido
curl -X POST http://localhost:8080/api/orders \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer $TOKEN" \
  -H "X-Idempotency-Key: orden-001" \
  -d '{
    "customerEmail": "cliente@example.com",
    "totalAmount": 149.99
  }'

# 3. Consultar Estado en Redis (Instantáneo)
curl -i -X GET http://localhost:8080/api/orders/<ORDER_ID>/status \
  -H "Authorization: Bearer $TOKEN"
```

---

## ⌨️ Comandos Útiles (`Makefile`)

El proyecto incluye un `Makefile` completo para no memorizar comandos largos de Docker:

```bash
# Gestión de Contenedores
make up               # Levanta todos los contenedores en segundo plano
make down             # Detiene y destruye los contenedores
make ps               # Muestra el estado de salud de los servicios
make logs             # Logs en tiempo real de todos los servicios
make logs-worker      # Logs del contenedor Worker de mensajería

# Symfony Console & Cache
make sf cmd="ruta"    # Ejecuta comandos de Symfony (ej: make sf cmd="debug:router")
make cc               # Limpia la caché de Symfony
make routes           # Lista todas las rutas registradas

# Mensajería & Workers
make messenger-setup  # Inicializa colas y exchanges en RabbitMQ
make messenger-stop   # Detiene y reinicia gracefully los workers para recargar código
make messenger-failed # Muestra mensajes en la Dead Letter Queue (DLQ)
make messenger-retry  # Reintenta procesar mensajes fallidos

# Base de Datos
make db-migrate       # Ejecuta migraciones pendientes de Doctrine
make db-diff          # Genera una migración basada en los cambios de entidades
make db-check-vector  # Verifica que la extensión pgvector esté activa en PostgreSQL

# Terminales
make sh               # Entra a la consola bash del contenedor PHP
make sh-db            # Entra a psql en la base de datos
make sh-redis         # Entra a la consola interactiva redis-cli
```

---

## 📂 Estructura de Directorios

```
symfony-practica/
├── compose.yaml                    # Orquestación de servicios Docker
├── Makefile                        # Atajos para gestión del stack
├── config/                         # Configuración de paquetes Symfony
│   └── packages/
│       ├── messenger.yaml          # AMQP, Redis transports y retry strategy
│       └── security.yaml           # Firewalls y autenticación JWT
├── docker/                         # Dockerfiles y configs (PHP, Nginx, PostgreSQL)
├── docs/                           # Documentación técnica y diagramas interactivos
│   └── diagrama_arquitectura.html  # Visualizador gráfico HTML de arquitectura
├── src/
│   ├── Modules/
│   │   ├── Auth/                   # Módulo de Autenticación
│   │   │   ├── Domain/             # User entity, UserRepositoryInterface
│   │   │   ├── Application/        # UseCases (Register, Profile), DTOs
│   │   │   └── Infrastructure/     # Controllers, Doctrine UserRepository
│   │   └── Order/                  # Módulo de Pedidos
│   │       ├── Domain/             # Order aggregate, OrderStatus, Exceptions
│   │       ├── Application/        # UseCases, Ports (Cache, Idempotency, Bus), DTOs
│   │       └── Infrastructure/     # Controllers, Redis Adapters, RabbitMQ Publisher
│   └── Kernel.php
└── public/
    └── index.php
```

---

## 📄 Licencia

Distribuido bajo la licencia MIT. Siéntete libre de utilizarlo como referencia o base para tus proyectos.
