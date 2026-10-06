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
                'status' => ['type' => 'string', 'enum' => ['PENDING', 'PROCESSING', 'CONFIRMED', 'FAILED'], 'example' => 'PENDING'],
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

        return $openApi;
    }
}
