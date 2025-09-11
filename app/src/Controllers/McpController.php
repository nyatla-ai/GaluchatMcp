<?php
namespace App\Controllers;

use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\Psr7\UriResolver;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;

class McpController
{
    public function manifest(Request $request, Response $response): Response
    {
        // absolute tool URLs derived from the accessed manifest URL
        $manifestUrl = $request->getUri()->withQuery('')->withFragment('');
        $resolvePointsUrl = (string) UriResolver::resolve($manifestUrl, new Uri('tools/resolve_points'));
        $summarizeStaysUrl = (string) UriResolver::resolve($manifestUrl, new Uri('tools/summarize_stays'));

        $resolvePointsInputSchema = json_decode(
            file_get_contents(__DIR__ . '/../../resources/schema/resolve_points.input.json'),
            true
        );
        $resolvePointsOutputSchema = json_decode(
            file_get_contents(__DIR__ . '/../../resources/schema/resolve_points.output.json'),
            true
        );
        $summarizeStaysInputSchema = json_decode(
            file_get_contents(__DIR__ . '/../../resources/schema/summarize_stays.input.json'),
            true
        );
        $summarizeStaysOutputSchema = json_decode(
            file_get_contents(__DIR__ . '/../../resources/schema/summarize_stays.output.json'),
            true
        );

        $manifest = [
            'name' => 'Galuchat MCP Server',
            'description' => 'Reverse geocoding utilities exposed as MCP tools.',
            'tools' => [
                [
                    'name' => 'resolve_points',
                    'endpoint' => $resolvePointsUrl,
                    'description' => 'Resolve coordinates to district code and address.',
                    'input' => [
                        'granularity (optional): admin|estat|jarl, default admin',
                        'points: array of {ref?, lat, lon}'
                    ],
                    'output' => [
                        'granularity: admin|estat|jarl',
                        'results: array of {ref?, code, address}'
                    ],
                    'input_schema' => $resolvePointsInputSchema,
                    'output_schema' => $resolvePointsOutputSchema,
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
                                'code' => '00000',
                                'address' => 'Example'
                            ]
                        ]
                    ]
                ],
                [
                    'name' => 'summarize_stays',
                    'endpoint' => $summarizeStaysUrl,
                    'description' => 'Group consecutive positions by region code.',
                    'input' => [
                        'positions: array of {timestamp, lat, lon}'
                    ],
                    'output' => [
                        'results: array of {start_ts, end_ts, code, address, duration_sec, count}'
                    ],
                    'input_schema' => $summarizeStaysInputSchema,
                    'output_schema' => $summarizeStaysOutputSchema,
                    'example_input' => [
                        'positions' => [
                            ['timestamp' => 0, 'lat' => 35.0, 'lon' => 135.0],
                            ['timestamp' => 60, 'lat' => 35.0, 'lon' => 135.0]
                        ]
                    ],
                    'example_output' => [
                        'results' => [
                            [
                                'start_ts' => 0,
                                'end_ts' => 60,
                                'code' => '00000',
                                'address' => 'Example',
                                'duration_sec' => 60,
                                'count' => 2
                            ]
                        ]
                    ]
                ]
            ]
        ];
        $response->getBody()->write(json_encode($manifest, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        return $response->withHeader('Content-Type', 'application/json');
    }
}
