<?php

namespace App\Modules\Auth\Infrastructure\OpenApi;

use ApiPlatform\OpenApi\Factory\OpenApiFactoryInterface;
use ApiPlatform\OpenApi\Model\MediaType;
use ApiPlatform\OpenApi\Model\Operation;
use ApiPlatform\OpenApi\Model\PathItem;
use ApiPlatform\OpenApi\Model\RequestBody;
use ApiPlatform\OpenApi\OpenApi;
use Symfony\Component\DependencyInjection\Attribute\AsDecorator;

#[AsDecorator('api_platform.openapi.factory', priority: -10)]
class AuthOpenApiDecorator implements OpenApiFactoryInterface
{
    public function __construct(
        private readonly OpenApiFactoryInterface $decorated
    ) {
    }

    public function __invoke(array $context = []): OpenApi
    {
        // 1. Obtener la especificación OpenAPI generada por API Platform y LexikJWT
        $openApi = ($this->decorated)($context);

        // 2. Documentar el endpoint POST /api/auth/register
        $openApi->getPaths()->addPath('/api/auth/register', (new PathItem())->withPost(
            (new Operation())
                ->withOperationId('auth_register')
                ->withTags(['Auth'])
                ->withSummary('Registra un nuevo usuario')
                ->withDescription('Crea un nuevo usuario en la base de datos con contraseña cifrada.')
                ->withRequestBody(
                    (new RequestBody())
                        ->withDescription('Datos para el registro de usuario')
                        ->withRequired(true)
                        ->withContent(new \ArrayObject([
                            'application/json' => new MediaType(new \ArrayObject([
                                'type' => 'object',
                                'properties' => [
                                    'email' => [
                                        'type' => 'string',
                                        'example' => 'dev@example.com',
                                    ],
                                    'password' => [
                                        'type' => 'string',
                                        'example' => 'password123',
                                    ],
                                ],
                                'required' => ['email', 'password'],
                            ]))
                        ]))
                )
                ->withResponses([
                    '201' => [
                        'description' => 'Usuario registrado exitosamente',
                        'content' => [
                            'application/json' => [
                                'schema' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'message' => ['type' => 'string'],
                                        'user' => [
                                            'type' => 'object',
                                            'properties' => [
                                                'id' => ['type' => 'integer'],
                                                'email' => ['type' => 'string'],
                                                'roles' => [
                                                    'type' => 'array',
                                                    'items' => ['type' => 'string'],
                                                ],
                                                'created_at' => ['type' => 'string', 'format' => 'date-time'],
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                    '400' => [
                        'description' => 'Campos obligatorios faltantes',
                    ],
                    '409' => [
                        'description' => 'El correo electrónico ya existe',
                    ],
                ])
        ));

        // 3. Documentar el endpoint GET /api/auth/me (con candado JWT)
        $openApi->getPaths()->addPath('/api/auth/me', (new PathItem())->withGet(
            (new Operation())
                ->withOperationId('auth_me')
                ->withTags(['Auth'])
                ->withSummary('Obtiene el perfil del usuario autenticado')
                ->withDescription('Devuelve los datos del usuario conectado utilizando el Bearer Token JWT.')
                ->withSecurity([['JWT' => []]]) // Asocia el esquema JWT que configuró Lexik
                ->withResponses([
                    '200' => [
                        'description' => 'Perfil del usuario obtenido con éxito',
                        'content' => [
                            'application/json' => [
                                'schema' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'id' => ['type' => 'integer'],
                                        'email' => ['type' => 'string'],
                                        'roles' => [
                                            'type' => 'array',
                                            'items' => ['type' => 'string'],
                                        ],
                                        'created_at' => ['type' => 'string', 'format' => 'date-time'],
                                    ],
                                ],
                            ],
                        ],
                    ],
                    '401' => [
                        'description' => 'Token JWT ausente o inválido',
                    ],
                ])
        ));

        return $openApi;
    }
}
