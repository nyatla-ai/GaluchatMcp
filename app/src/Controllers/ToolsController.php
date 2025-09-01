<?php
namespace App\Controllers;

use App\Services\InputValidator;
use App\Services\GaluchatClient;
use App\Domain\Errors;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;

class ToolsController
{
    private InputValidator $validator;
    private GaluchatClient $client;

    public function __construct(InputValidator $validator, GaluchatClient $client)
    {
        $this->validator = $validator;
        $this->client = $client;
    }

    public function resolvePoints(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody();
        $granularity = $data['granularity'] ?? 'admin';
        [$valid, $invalid] = $this->validator->validate($data);

        $total = count($data['points'] ?? []);
        $results = array_fill(0, $total, null);

        foreach ($invalid as $e) {
            $results[$e['index']] = $this->errorResult($e['ref'] ?? null, $e['reason']);
        }

        if ($valid) {
            try {
                $apiResults = $this->client->resolve($granularity, $valid);
                foreach ($valid as $i => $pt) {
                    $apiRes = $apiResults[$i] ?? null;
                    if ($apiRes === null || empty($apiRes['code'])) {
                        $results[$pt['index']] = $this->errorResult($pt['ref'] ?? null, Errors::OUT_OF_COVERAGE);
                        continue;
                    }
                    $results[$pt['index']] = [
                        'ref' => $pt['ref'] ?? null,
                        'success' => true,
                        'payload' => [
                            'code' => $apiRes['code'],
                            'address' => $apiRes['address']
                        ]
                    ];
                }
            } catch (\RuntimeException $e) {
                $reason = $e->getMessage();
                foreach ($valid as $pt) {
                    $results[$pt['index']] = $this->errorResult($pt['ref'] ?? null, $reason);
                }
            }
        }

        $payload = [
            'granularity' => $granularity,
            'results' => array_values($results)
        ];
        $response->getBody()->write(json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        return $response->withHeader('Content-Type', 'application/json');
    }

    private function errorResult(?string $ref, string $code): array
    {
        return [
            'ref' => $ref,
            'success' => false,
            'error' => [
                'code' => $code,
                'message' => $code
            ]
        ];
    }

    public function summarizeStays(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody();
        [$positions, $errors] = $this->validator->validatePositions($data);

        $results = [];

        if ($positions) {
            try {
                $apiResults = $this->client->resolve('admin', $positions);
                $clusterCode = null;
                $clusterStart = null;
                $clusterCount = 0;
                foreach ($positions as $i => $pos) {
                    $code = $apiResults[$i]['code'] ?? null;
                    if (!$code) {
                        if ($clusterCount >= 2) {
                            $endTs = $positions[$i - 1]['timestamp'];
                            $results[] = [
                                'start_ts' => $clusterStart,
                                'end_ts' => $endTs,
                                'code' => $clusterCode,
                                'duration_sec' => $endTs - $clusterStart
                            ];
                        }
                        $clusterCode = null;
                        $clusterStart = null;
                        $clusterCount = 0;
                        $errors[] = ['index' => $i, 'reason' => Errors::OUT_OF_COVERAGE];
                        continue;
                    }
                    if ($clusterCode === null) {
                        $clusterCode = $code;
                        $clusterStart = $pos['timestamp'];
                        $clusterCount = 1;
                        continue;
                    }
                    if ($code === $clusterCode) {
                        $clusterCount++;
                        continue;
                    }
                    if ($clusterCount >= 2) {
                        $endTs = $positions[$i - 1]['timestamp'];
                        $results[] = [
                            'start_ts' => $clusterStart,
                            'end_ts' => $endTs,
                            'code' => $clusterCode,
                            'duration_sec' => $endTs - $clusterStart
                        ];
                    }
                    $clusterCode = $code;
                    $clusterStart = $pos['timestamp'];
                    $clusterCount = 1;
                }
                if ($clusterCount >= 2) {
                    $endTs = $positions[count($positions) - 1]['timestamp'];
                    $results[] = [
                        'start_ts' => $clusterStart,
                        'end_ts' => $endTs,
                        'code' => $clusterCode,
                        'duration_sec' => $endTs - $clusterStart
                    ];
                }
            } catch (\RuntimeException $e) {
                $reason = $e->getMessage();
                foreach ($positions as $i => $_) {
                    $errors[] = ['index' => $i, 'reason' => $reason];
                }
            }
        }

        $payload = [
            'results' => $results,
            'errors' => $errors
        ];
        $response->getBody()->write(json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        return $response->withHeader('Content-Type', 'application/json');
    }
}
