<?php
namespace App\Controllers;

use App\Services\InputValidator;
use App\Services\GaluchatClient;
use App\Domain\Errors;
use App\Domain\InvalidInputException;
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
        try {
            $valid = $this->validator->validate($data);
        } catch (InvalidInputException $e) {
            return $this->errorResponse($response, Errors::INVALID_INPUT, $e->getMessage(), $e->getLocation());
        }

        try {
            $apiResults = $this->client->resolve($granularity, $valid);
        } catch (\RuntimeException $e) {
            return $this->errorResponse($response, Errors::API_ERROR, $e->getMessage());
        }

        $results = [];
        foreach ($valid as $i => $pt) {
            $apiRes = $apiResults[$i] ?? null;
            if ($apiRes === null) {
                return $this->errorResponse($response, Errors::OUT_OF_COVERAGE, Errors::OUT_OF_COVERAGE, [
                    'index' => $pt['index'],
                    'ref' => $pt['ref'] ?? null
                ]);
            }
            $results[] = [
                'ref' => $pt['ref'] ?? null,
                'code' => $apiRes['code'] ?? null,
                'address' => $apiRes['address'] ?? null
            ];
        }

        $payload = [
            'granularity' => $granularity,
            'results' => $results
        ];
        $response->getBody()->write(json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        return $response->withHeader('Content-Type', 'application/json');
    }

    public function summarizeStays(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody();
        try {
            $positions = $this->validator->validatePositions($data);
        } catch (InvalidInputException $e) {
            return $this->errorResponse($response, Errors::INVALID_INPUT, $e->getMessage(), $e->getLocation());
        }

        try {
            $apiResults = $this->client->resolve('admin', $positions);
        } catch (\RuntimeException $e) {
            return $this->errorResponse($response, Errors::API_ERROR, $e->getMessage());
        }

        $results = [];
        $clusterCode = null;
        $clusterStart = null;
        $clusterCount = 0;
        foreach ($positions as $i => $pos) {
            $apiRes = $apiResults[$i] ?? null;
            if ($apiRes === null) {
                return $this->errorResponse($response, Errors::OUT_OF_COVERAGE, Errors::OUT_OF_COVERAGE, ['index' => $i]);
            }
            $code = $apiRes['code'] ?? null;
            if ($code === null) {
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

        $payload = [
            'results' => $results
        ];
        $response->getBody()->write(json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        return $response->withHeader('Content-Type', 'application/json');
    }

    private function errorResponse(Response $response, string $code, string $message, array $location = null): Response
    {
        $error = [
            'code' => $code,
            'message' => $message
        ];
        if ($location !== null) {
            $error['location'] = $location;
        }
        $response->getBody()->write(json_encode(['error' => $error], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        return $response->withHeader('Content-Type', 'application/json');
    }
}
