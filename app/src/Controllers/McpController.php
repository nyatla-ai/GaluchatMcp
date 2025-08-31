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
                    'description' => 'Resolve coordinates to district code and address.',
                    'input' => [
                        'granularity (optional): admin|estat|jarl, default admin',
                        'points: array of {ref?, lat, lon, t?}'
                    ],
                    'output' => [
                        'granularity: admin|estat|jarl',
                        'results: array of {ref?, lat, lon, ok, payload{code, address}}',
                        'errors: array of {ref?, lat, lon, reason}'
                    ],
                    'input_schema' => 'app/resources/schema/resolve_points.input.json',
                    'output_schema' => 'app/resources/schema/resolve_points.output.json',
                    'example_input' => [
                        'granularity' => 'admin',
                        'points' => [
                            ['lat' => 35.0, 'lon' => 135.0]
                        ]
                    ],
                    'example_output' => [
                        'granularity' => 'admin',
                        'results' => [
                            [
                                'lat' => 35.0,
                                'lon' => 135.0,
                                'ok' => true,
                                'payload' => [
                                    'code' => '00000',
                                    'address' => 'Example'
                                ]
                            ]
                        ],
                        'errors' => []
                    ]
                ]
            ]
        ];
        $response->getBody()->write(json_encode($manifest, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        return $response->withHeader('Content-Type', 'application/json');
    }
}
