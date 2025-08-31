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

        $results = [];
        $errors = [];
        try {
            $apiResults = $this->client->resolve($granularity, $valid);
        } catch (\RuntimeException $e) {
            $reason = $e->getMessage();
            foreach ($valid as $pt) {
                $errors[] = [
                    'ref' => $pt['ref'] ?? null,
                    'lat' => $pt['lat'],
                    'lon' => $pt['lon'],
                    'reason' => $reason
                ];
            }
            $apiResults = [];
        }

        foreach ($invalid as $e) {
            $errors[] = [
                'ref' => $e['ref'] ?? null,
                'lat' => $e['lat'],
                'lon' => $e['lon'],
                'reason' => $e['reason']
            ];
        }

        foreach ($valid as $i => $pt) {
            $apiRes = $apiResults[$i] ?? null;
            if ($apiRes === null || empty($apiRes['code'])) {
                $errors[] = [
                    'ref' => $pt['ref'] ?? null,
                    'lat' => $pt['lat'],
                    'lon' => $pt['lon'],
                    'reason' => Errors::OUT_OF_COVERAGE
                ];
                continue;
            }
            $results[] = [
                'ref' => $pt['ref'] ?? null,
                'lat' => $pt['lat'],
                'lon' => $pt['lon'],
                'ok' => true,
                'payload' => [
                    'code' => $apiRes['code'],
                    'address' => $apiRes['address']
                ]
            ];
        }

        $payload = [
            'granularity' => $granularity,
            'results' => $results,
            'errors' => $errors
        ];
        $response->getBody()->write(json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        return $response->withHeader('Content-Type', 'application/json');
    }

    public function summarizeStays(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody();
        [$valid, $errors] = $this->validator->validateSamples($data);

        $stays = [];
        $parts = [];
        foreach ($valid as $sample) {
            $start = new \DateTime($sample['start']);
            $end = new \DateTime($sample['end']);
            $duration = (int)floor(($end->getTimestamp() - $start->getTimestamp()) / 60);
            $stays[] = [
                'ref' => $sample['ref'],
                'area' => $sample['area'],
                'start' => $sample['start'],
                'end' => $sample['end'],
                'duration_minutes' => $duration
            ];
            $parts[] = sprintf('%sで%d分滞在', $sample['area'], $duration);
        }

        $payload = [
            'summary' => implode('→', $parts),
            'stays' => $stays,
            'errors' => $errors
        ];
        $response->getBody()->write(json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        return $response->withHeader('Content-Type', 'application/json');
    }
}
