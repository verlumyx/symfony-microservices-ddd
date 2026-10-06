# Hoja de Ruta de Implementación (Paso a Paso)

Este documento describe las fases para construir la aplicación desde cero de forma reproducible mediante Docker.

---

## Fase 1: Configuración del Entorno Docker

Dado que en el host no se cuenta con PHP ni Composer instalados directamente, todo el stack se ejecuta en contenedores.

### Servicios de `compose.yaml`:
1. **`php`:**
   * Imagen base: `php:8.3-fpm-alpine` o `php:8.3-fpm`.
   * Extensiones requeridas:
     * `amqp` (librería `librabbitmq-dev` + extensión PECL `amqp`)
     * `redis` (extensión PECL `redis`)
     * `pdo_pgsql` / `pgsql` (soporte de base de datos relacional)
     * `intl`, `zip`, `opcache`
   * Instalación de Composer dentro de la imagen.
2. **`web` (Nginx):**
   * Configuración de vhost apuntando a `/app/public/index.php`.
   * Expone el puerto `8080` o `80`.
3. **`database` (PostgreSQL 16 + pgvector):**
   * Build personalizado basado en `postgres:16-alpine` compilando `pgvector` (`v0.8.1` con `with_llvm=no`).
   * Script de inicialización montado en `/docker-entrypoint-initdb.d/` para ejecutar `CREATE EXTENSION IF NOT EXISTS vector;`.
   * Persistencia mediante volumen Docker.
4. **`redis`:**
   * Imagen `redis:7-alpine`.
   * Expone el puerto `6379`.
5. **`rabbitmq`:**
   * Imagen `rabbitmq:3-management-alpine`.
   * Puerto AMQP: `5672`.
   * Puerto UI de gestión: `15672` (usuario/clave configurables).

---

## Fase 2: Inicialización del Proyecto Symfony con API Platform

1. **Creación del esqueleto Symfony:**
   * Ejecutar Composer desde el contenedor PHP:
     ```bash
     composer create-project symfony/skeleton:"7.1.*" .
     ```
2. **Instalación de paquetes requeridos:**
   * **API Platform:** `composer require api-platform/core`
   * **ORM / Doctrine:** `composer require symfony/orm-pack`
   * **Symfony Messenger:** `composer require symfony/messenger`
   * **Soporte AMQP para Messenger:** `composer require symfony/amqp-pack`
   * **Soporte Redis / Cache / Lock:** `composer require symfony/redis-pack symfony/lock`
   * **Maker Bundle (Dev):** `composer require --dev symfony/maker-bundle`

---

## Fase 3: Configuración de Symfony Messenger

Edición de `config/packages/messenger.yaml`:

```yaml
framework:
    messenger:
        failure_transport: failed

        transports:
            # RabbitMQ AMQP Transport
            async_orders:
                dsn: '%env(MESSENGER_AMQP_DSN)%'
                options:
                    exchange:
                        name: orders_exchange
                        type: topic
                    queues:
                        order_processing:
                            binding_keys: ['order.created']
                retry_strategy:
                    max_retries: 3
                    delay: 1000
                    multiplier: 2

            # Redis Transport (para tareas ultrarrápidas o de prioridad alta)
            async_fast:
                dsn: '%env(MESSENGER_REDIS_DSN)%'

            # Dead Letter Queue
            failed: 'doctrine://default?queue_name=failed'

        routing:
            'App\Message\CreateOrderMessage': async_orders
            'App\Message\SendNotificationMessage': async_orders
```

---

## Fase 4: Implementación del Dominio y Caso de Uso

1. **Entidad `Order`:**
   * Atributos: `id` (UUID), `customerEmail`, `totalAmount`, `status` (`PENDING`, `PROCESSING`, `CONFIRMED`, `FAILED`), `createdAt`.
   * Anotaciones/Atributos de API Platform: `#[ApiResource]`.
2. **DTO y Custom State Processor:**
   * `OrderInputDto`: Valida el payload de entrada.
   * `CreateOrderProcessor`:
     * Verifica la cabecera `X-Idempotency-Key` en Redis.
     * Guarda la entidad `Order` con estado `PENDING`.
     * Despacha `CreateOrderMessage` al bus de Messenger.
     * Retorna inmediatamente respuesta HTTP `202 Accepted` o `201 Created`.
3. **Consumidor / Message Handler:**
   * `CreateOrderHandler`:
     * Escucha la cola `order_processing`.
     * Simula proceso de validación / cobro.
     * Actualiza la orden en PostgreSQL a `CONFIRMED`.
     * Actualiza el cache de Redis.

---

## Fase 5: Ejecución y Verificación

1. **Consumo de mensajes en segundo plano:**
   ```bash
   php bin/console messenger:consume async_orders -vv
   ```
2. **Pruebas en la interfaz Swagger:**
   * Ingresar a `http://localhost:8080/api/docs`.
   * Ejecutar `POST /api/orders`.
3. **Verificación en RabbitMQ:**
   * Abrir `http://localhost:15672` y comprobar la tasa de mensajes entrando y saliendo del exchange y de la cola.
4. **Verificación en Redis:**
   * Comprobar llaves de idempotencia y locks mediante `redis-cli monitor`.
