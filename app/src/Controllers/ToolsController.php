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

        $params = $data['params'] ?? [];
        $distTh = isset($params['distance_threshold_m']) ? (float)$params['distance_threshold_m'] : 50.0;
        $durTh = isset($params['duration_threshold_sec']) ? (int)$params['duration_threshold_sec'] : 120;

        $results = [];
        $cluster = [];
        foreach ($positions as $pos) {
            if (!$cluster) {
                $cluster[] = $pos;
                continue;
            }
            $prev = $cluster[count($cluster) - 1];
            $dist = $this->distance($prev['lat'], $prev['lon'], $pos['lat'], $pos['lon']);
            if ($dist <= $distTh) {
                $cluster[] = $pos;
                continue;
            }
            $this->finalizeCluster($cluster, $durTh, $results);
            $cluster = [$pos];
        }
        $this->finalizeCluster($cluster, $durTh, $results);

        $payload = [
            'results' => $results,
            'errors' => $errors
        ];
        $response->getBody()->write(json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        return $response->withHeader('Content-Type', 'application/json');
    }

    private function finalizeCluster(array $cluster, int $durTh, array &$results): void
    {
        if (!$cluster) {
            return;
        }
        $start = $cluster[0]['timestamp'];
        $end = $cluster[count($cluster) - 1]['timestamp'];
        $duration = $end - $start;
        if ($duration < $durTh) {
            return;
        }
        $latAvg = array_sum(array_column($cluster, 'lat')) / count($cluster);
        $lonAvg = array_sum(array_column($cluster, 'lon')) / count($cluster);
        $results[] = [
            'start_ts' => $start,
            'end_ts' => $end,
            'center' => [
                'lat' => round($latAvg, 6),
                'lon' => round($lonAvg, 6)
            ],
            'duration_sec' => $duration
        ];
    }

    private function distance(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $rad = M_PI / 180;
        $lat1 *= $rad;
        $lat2 *= $rad;
        $lon1 *= $rad;
        $lon2 *= $rad;
        $dlat = $lat2 - $lat1;
        $dlon = $lon2 - $lon1;
        $a = sin($dlat / 2) ** 2 + cos($lat1) * cos($lat2) * sin($dlon / 2) ** 2;
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        return 6371000 * $c; // meters
    }
}
