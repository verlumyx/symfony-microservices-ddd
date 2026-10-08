<?php

declare(strict_types=1);

namespace App\Modules\Order\Infrastructure\OpenApi;

use ApiPlatform\OpenApi\Factory\OpenApiFactoryInterface;
use ApiPlatform\OpenApi\Model\MediaType;
use ApiPlatform\OpenApi\Model\Operation;
use ApiPlatform\OpenApi\Model\Parameter;
use ApiPlatform\OpenApi\Model\PathItem;
use ApiPlatform\OpenApi\Model\RequestBody;
use ApiPlatform\OpenApi\OpenApi;
use Symfony\Component\DependencyInjection\Attribute\AsDecorator;

#[AsDecorator('api_platform.openapi.factory', priority: -20)]
class OrderOpenApiDecorator implements OpenApiFactoryInterface
{
    public function __construct(
        private readonly OpenApiFactoryInterface $decorated
    ) {
    }

    public function __invoke(array $context = []): OpenApi
    {
        $openApi = ($this->decorated)($context);

        // Esquema común para un objeto de pedido
        $orderSchema = [
            'type' => 'object',
            'properties' => [
                'id' => ['type' => 'string', 'format' => 'uuid', 'example' => '91c8363d-d3be-4ff7-ad9d-eaf48b3199d8'],
                'customerEmail' => ['type' => 'string', 'example' => 'cliente@example.com'],
                'totalAmount' => ['type' => 'number', 'format' => 'float', 'example' => 125.75],
                'status' => ['type' => 'string', 'enum' => ['PENDING', 'PROCESSING', 'CONFIRMED', 'FAILED', 'CANCELLED'], 'example' => 'PENDING'],
                'createdAt' => ['type' => 'string', 'format' => 'date-time'],
                'updatedAt' => ['type' => 'string', 'format' => 'date-time', 'nullable' => true],
            ],
        ];

        // 1. Documentar POST /api/orders y GET /api/orders
        $ordersPathItem = new PathItem();

        // Operación POST /api/orders
        $ordersPathItem = $ordersPathItem->withPost(
            (new Operation())
                ->withOperationId('order_create')
                ->withTags(['Orders'])
                ->withSummary('Crea un nuevo pedido de forma asíncrona')
                ->withDescription('Crea un pedido en estado PENDING y publica un evento en RabbitMQ para procesamiento en background. Soporta la cabecera X-Idempotency-Key para garantizar idempotencia mediante Redis.')
                ->withSecurity([['JWT' => []]])
                ->withParameters([
                    new Parameter(
                        name: 'X-Idempotency-Key',
                        in: 'header',
                        description: 'Clave única de idempotencia (ej: UUID o hash) para prevenir pedidos duplicados',
                        required: false,
                        schema: ['type' => 'string']
                    ),
                ])
                ->withRequestBody(
                    (new RequestBody())
                        ->withDescription('Datos para la creación del pedido')
                        ->withRequired(true)
                        ->withContent(new \ArrayObject([
                            'application/json' => new MediaType(new \ArrayObject([
                                'type' => 'object',
                                'properties' => [
                                    'customerEmail' => [
                                        'type' => 'string',
                                        'example' => 'cliente@example.com',
                                    ],
                                    'totalAmount' => [
                                        'type' => 'number',
                                        'example' => 125.75,
                                    ],
                                ],
                                'required' => ['customerEmail', 'totalAmount'],
                            ]))
                        ]))
                )
                ->withResponses([
                    '202' => [
                        'description' => 'Pedido aceptado y encolado para procesamiento en RabbitMQ',
                        'content' => [
                            'application/json' => [
                                'schema' => $orderSchema,
                            ],
                        ],
                    ],
                    '400' => [
                        'description' => 'Datos de validación inválidos',
                    ],
                    '401' => [
                        'description' => 'Token JWT ausente o inválido',
                    ],
                    '409' => [
                        'description' => 'Solicitud en curso con la misma clave de idempotencia',
                    ],
                ])
        );

        // Operación GET /api/orders
        $ordersPathItem = $ordersPathItem->withGet(
            (new Operation())
                ->withOperationId('orders_list')
                ->withTags(['Orders'])
                ->withSummary('Lista todos los pedidos')
                ->withDescription('Retorna todos los pedidos registrados ordenados por fecha de creación.')
                ->withSecurity([['JWT' => []]])
                ->withResponses([
                    '200' => [
                        'description' => 'Lista de pedidos obtenida exitosamente',
                        'content' => [
                            'application/json' => [
                                'schema' => [
                                    'type' => 'array',
                                    'items' => $orderSchema,
                                ],
                            ],
                        ],
                    ],
                    '401' => [
                        'description' => 'Token JWT ausente o inválido',
                    ],
                ])
        );

        $openApi->getPaths()->addPath('/api/orders', $ordersPathItem);

        // 2. Documentar GET /api/orders/{id}
        $openApi->getPaths()->addPath('/api/orders/{id}', (new PathItem())->withGet(
            (new Operation())
                ->withOperationId('orders_get_item')
                ->withTags(['Orders'])
                ->withSummary('Obtiene un pedido por su identificador UUID')
                ->withDescription('Retorna la información y el estado actualizado de un pedido específico.')
                ->withSecurity([['JWT' => []]])
                ->withParameters([
                    new Parameter(
                        name: 'id',
                        in: 'path',
                        description: 'UUID del pedido',
                        required: true,
                        schema: ['type' => 'string', 'format' => 'uuid']
                    ),
                ])
                ->withResponses([
                    '200' => [
                        'description' => 'Detalle del pedido obtenido con éxito',
                        'content' => [
                            'application/json' => [
                                'schema' => $orderSchema,
                            ],
                        ],
                    ],
                    '400' => [
                        'description' => 'UUID con formato inválido',
                    ],
                    '401' => [
                        'description' => 'Token JWT ausente o inválido',
                    ],
                    '404' => [
                        'description' => 'Pedido no encontrado',
                    ],
                ])
        ));

        // 3. Documentar GET /api/orders/{id}/status (Ultra-rápido vía Redis)
        $orderStatusSchema = [
            'type' => 'object',
            'properties' => [
                'orderId' => ['type' => 'string', 'format' => 'uuid', 'example' => '91c8363d-d3be-4ff7-ad9d-eaf48b3199d8'],
                'status' => ['type' => 'string', 'enum' => ['PENDING', 'PROCESSING', 'CONFIRMED', 'FAILED'], 'example' => 'CONFIRMED'],
                'customerEmail' => ['type' => 'string', 'example' => 'cliente@example.com'],
                'totalAmount' => ['type' => 'number', 'format' => 'float', 'example' => 125.75],
                'updatedAt' => ['type' => 'string', 'format' => 'date-time'],
            ],
            'required' => ['orderId', 'status', 'customerEmail', 'totalAmount', 'updatedAt'],
        ];

        $openApi->getPaths()->addPath('/api/orders/{id}/status', (new PathItem())->withGet(
            (new Operation())
                ->withOperationId('orders_get_status')
                ->withTags(['Orders'])
                ->withSummary('Consulta ultrarrápida del estado de un pedido (Redis Cache)')
                ->withDescription('Consulta el estado de la orden en Redis con latencia de sub-milisegundo. Si no está en caché (MISS), consulta la base de datos PostgreSQL y repuebla Redis de forma transparente. Devuelve la cabecera X-Cache (HIT/MISS).')
                ->withSecurity([['JWT' => []]])
                ->withParameters([
                    new Parameter(
                        name: 'id',
                        in: 'path',
                        description: 'UUID del pedido',
                        required: true,
                        schema: ['type' => 'string', 'format' => 'uuid']
                    ),
                ])
                ->withResponses([
                    '200' => [
                        'description' => 'Estado del pedido obtenido exitosamente (con cabecera X-Cache: HIT o MISS)',
                        'headers' => [
                            'X-Cache' => [
                                'description' => 'Indica si la respuesta provino de Redis (HIT) o de la base de datos (MISS)',
                                'schema' => ['type' => 'string', 'enum' => ['HIT', 'MISS']],
                            ],
                        ],
                        'content' => [
                            'application/json' => [
                                'schema' => $orderStatusSchema,
                            ],
                        ],
                    ],
                    '400' => [
                        'description' => 'UUID con formato inválido',
                    ],
                    '401' => [
                        'description' => 'Token JWT ausente o inválido',
                    ],
                    '404' => [
                        'description' => 'Pedido no encontrado',
                    ],
                ])
        ));

        // 4. Documentar POST /api/orders/{id}/cancel
        $openApi->getPaths()->addPath('/api/orders/{id}/cancel', (new PathItem())->withPost(
            (new Operation())
                ->withOperationId('orders_cancel')
                ->withTags(['Orders'])
                ->withSummary('Cancela un pedido y genera evento asíncrono')
                ->withDescription('Actualiza el estado de la orden a CANCELLED y publica un OrderCancelledMessage en RabbitMQ para ejecutar compensaciones (reembolsos, liberación de stock).')
                ->withSecurity([['JWT' => []]])
                ->withParameters([
                    new Parameter(
                        name: 'id',
                        in: 'path',
                        description: 'UUID del pedido a cancelar',
                        required: true,
                        schema: ['type' => 'string', 'format' => 'uuid']
                    ),
                ])
                ->withRequestBody(
                    (new RequestBody())
                        ->withDescription('Motivo opcional de cancelación')
                        ->withRequired(false)
                        ->withContent(new \ArrayObject([
                            'application/json' => new MediaType(new \ArrayObject([
                                'type' => 'object',
                                'properties' => [
                                    'reason' => [
                                        'type' => 'string',
                                        'example' => 'El cliente solicitó reembolso por duplicidad',
                                    ],
                                ],
                            ]))
                        ]))
                )
                ->withResponses([
                    '200' => [
                        'description' => 'Pedido cancelado con éxito y evento publicado',
                        'content' => [
                            'application/json' => [
                                'schema' => $orderSchema,
                            ],
                        ],
                    ],
                    '400' => [
                        'description' => 'UUID inválido o la orden ya estaba cancelada',
                    ],
                    '401' => [
                        'description' => 'Token JWT ausente o inválido',
                    ],
                    '404' => [
                        'description' => 'Pedido no encontrado',
                    ],
                ])
        ));

        return $openApi;
    }
}
