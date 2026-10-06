# Documentación del Proyecto: Arquitectura de Microservicios con Symfony

Bienvenido a la documentación técnica del proyecto. Este repositorio implementa una base para arquitecturas orientadas a microservicios y sistemas dirigidos por eventos (**Event-Driven Architecture**) utilizando el ecosistema de **Symfony**.

---

## 📚 Índice de Documentación

1. [Arquitectura y Caso Práctico](file:///home/verlumyx/Documentos/dev/symfony-practica/docs/arquitectura.md)
   - Explicación del caso de uso (**Sistema de Procesamiento de Pedidos**).
   - Diagrama de flujo y arquitectura técnica.
   - Responsabilidades de cada componente (**API Platform**, **Symfony Messenger**, **RabbitMQ**, **Redis**, **PostgreSQL**).
2. [Hoja de Ruta de Implementación](file:///home/verlumyx/Documentos/dev/symfony-practica/docs/roadmap.md)
   - Fases paso a paso para levantar el entorno Docker, inicializar el proyecto y configurar los servicios.

---

## 🛠️ Stack Tecnológico

| Componente | Tecnología | Propósito |
| :--- | :--- | :--- |
| **Framework Base** | Symfony 7.x (PHP 8.3) | Núcleo de la aplicación y servicios backend |
| **Capa API** | API Platform | Generación de endpoints REST/JSON-LD y OpenAPI/Swagger |
| **Bus de Mensajes** | Symfony Messenger | Implementación de CQRS, despacho de comandos y eventos |
| **Message Broker** | RabbitMQ (AMQP) | Distribución asíncrona de mensajes, colas de trabajo y DLQ |
| **Cache y Bloqueos** | Redis | Control de idempotencia, locks distribuidos y cache de estado |
| **Persistencia & Vectores** | PostgreSQL 16 + pgvector | Almacenamiento relacional transaccional y búsqueda vectorial (embeddings) |
| **Entorno de Ejecución** | Docker & Docker Compose | Contenerización completa y reproducible sin dependencias locales |
