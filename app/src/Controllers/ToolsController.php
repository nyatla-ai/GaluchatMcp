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
        try {
            $payload = $this->resolvePointsLogic($data);
        } catch (InvalidInputException $e) {
            return $this->errorResponse($response, Errors::INVALID_INPUT, $e->getMessage(), $e->getLocation());
        } catch (\RuntimeException $e) {
            $msg = $e->getMessage();
            $code = ($msg === Errors::RATE_LIMIT || $msg === Errors::OUT_OF_COVERAGE)
                ? $msg
                : Errors::API_ERROR;
            return $this->errorResponse($response, $code, $msg);
        }

        $response->getBody()->write(json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        return $response->withHeader('Content-Type', 'application/json');
    }

    public function summarizeStays(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody();
        try {
            $payload = $this->summarizeStaysLogic($data);
        } catch (InvalidInputException $e) {
            return $this->errorResponse($response, Errors::INVALID_INPUT, $e->getMessage(), $e->getLocation());
        } catch (\RuntimeException $e) {
            $msg = $e->getMessage();
            $code = ($msg === Errors::RATE_LIMIT || $msg === Errors::OUT_OF_COVERAGE)
                ? $msg
                : Errors::API_ERROR;
            return $this->errorResponse($response, $code, $msg);
        }

        $response->getBody()->write(json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        return $response->withHeader('Content-Type', 'application/json');
    }

    public function executeResolvePoints(array $data): array
    {
        return $this->resolvePointsLogic($data);
    }

    public function executeSummarizeStays(array $data): array
    {
        return $this->summarizeStaysLogic($data);
    }

    private function resolvePointsLogic(array $data): array
    {
        $granularity = $data['granularity'] ?? 'admin';
        $valid = $this->validator->validate($data);

        $apiResults = $this->client->resolve($granularity, $valid);

        $results = [];
        foreach ($valid as $i => $pt) {
            $apiRes = $apiResults[$i] ?? null;
            if ($apiRes === null) {
                throw new \RuntimeException(Errors::OUT_OF_COVERAGE);
            }
            $code = $apiRes['code'] ?? null;
            $address = $code === null ? null : ($apiRes['address'] ?? null);
            $item = [
                'code' => $code,
                'address' => $address
            ];
            if (array_key_exists('ref', $pt)) {
                $item['ref'] = $pt['ref'];
            }
            $results[] = $item;
        }

        return [
            'granularity' => $granularity,
            'results' => $results
        ];
    }

    private function summarizeStaysLogic(array $data): array
    {
        $positions = $this->validator->validatePositions($data);
        $apiResults = $this->client->resolve('admin', $positions);

        $results = [];
        $clusterCode = null;
        $clusterAddress = null;
        $clusterStart = null;
        $clusterCount = 0;
        foreach ($positions as $i => $pos) {
            $apiRes = $apiResults[$i] ?? null;
            if ($apiRes === null) {
                throw new \RuntimeException(Errors::OUT_OF_COVERAGE);
            }
            $code = $apiRes['code'] ?? null;
            $address = $apiRes['address'] ?? null;
            if ($code === null) {
                $address = null;
            }
            if ($clusterCount === 0) {
                $clusterCode = $code;
                $clusterAddress = $address;
                $clusterStart = $pos['timestamp'];
                $clusterCount = 1;
                continue;
            }
            if ($code === $clusterCode) {
                $clusterCount++;
                continue;
            }

            $endTs = $positions[$i - 1]['timestamp'];
            $results[] = [
                'start_ts' => $clusterStart,
                'end_ts' => $endTs,
                'code' => $clusterCode,
                'address' => $clusterAddress,
                'duration_sec' => $endTs - $clusterStart,
                'count' => $clusterCount
            ];

            $clusterCode = $code;
            $clusterAddress = $address;
            $clusterStart = $pos['timestamp'];
            $clusterCount = 1;
        }
        if ($clusterCount > 0) {
            $endTs = $positions[count($positions) - 1]['timestamp'];
            $results[] = [
                'start_ts' => $clusterStart,
                'end_ts' => $endTs,
                'code' => $clusterCode,
                'address' => $clusterAddress,
                'duration_sec' => $endTs - $clusterStart,
                'count' => $clusterCount
            ];
        }

        return [
            'results' => $results
        ];
    }

    private function errorResponse(Response $response, string $code, string $message, ?array $location = null): Response
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
