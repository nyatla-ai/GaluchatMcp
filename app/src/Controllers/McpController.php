<?php
namespace App\Controllers;

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;

class McpController
{
    public function manifest(Request $request, Response $response): Response
    {
        $manifest = [
            'tools' => [
                [
                    'name' => 'resolve_points',
                    'description' => 'Resolve coordinates to district code and name.',
                    'input' => [
                        'granularity (optional): admin|estat|jarl, default admin',
                        'points: array of {ref?, lat, lon, t?}'
                    ],
                    'output' => [
                        'results: array of {ref?, code, name}',
                        'failed: array of {index, ref?, code}',
                        'attribution: string'
                    ],
                    'example_input' => [
                        'granularity' => 'admin',
                        'points' => [
                            ['lat' => 35.0, 'lon' => 135.0]
                        ]
                    ],
                    'example_output' => [
                        'results' => [
                            ['code' => '00000', 'name' => 'Example']
                        ],
                        'failed' => [],
                        'attribution' => 'Data via Galuchat API'
                    ]
                ]
            ]
        ];
        $response->getBody()->write(json_encode($manifest, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        return $response->withHeader('Content-Type', 'application/json');
    }
}
