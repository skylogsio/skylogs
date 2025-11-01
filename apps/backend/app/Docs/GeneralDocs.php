<?php

namespace App\Docs;

use OpenApi\Attributes as OA;



#[OA\Info(
    version: '1.0.0',
    description: 'Skylogs backend API documentation',
    title: 'Skylogs API'
)]
#[OA\Server(
    url: swaggerHost,
    description: 'Skylogs local server'
)]
#[OA\Components(
    securitySchemes: [
        new OA\SecurityScheme(
            securityScheme: 'bearerAuth',
            type: 'http',
            description: 'Enter JWT token (without “Bearer” prefix)',
            bearerFormat: 'JWT',
            scheme: 'bearer'
        ),
    ]
)]
#[OA\SecurityRequirement(name: 'bearerAuth')]
class GeneralDocs {}
