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
        [$valid, $failed] = $this->validator->validate($data);
        $granularity = $data['granularity'] ?? 'admin';

        try {
            $apiResults = $this->client->resolve($granularity, $valid);
        } catch (\Throwable $e) {
            $response->getBody()->write(json_encode(Errors::format(Errors::INTERNAL, 'Backend error')));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }

        $results = [];
        $validIdx = 0;
        foreach ($data['points'] as $i => $pt) {
            $isFailed = false;
            foreach ($failed as $f) {
                if ($f['index'] === $i) { $isFailed = true; break; }
            }
            if ($isFailed) { continue; }
            $apiRes = $apiResults[$validIdx++] ?? ['code' => '', 'name' => ''];
            $entry = [
                'code' => $apiRes['code'],
                'name' => $apiRes['name']
            ];
            if (isset($pt['ref'])) {
                $entry['ref'] = $pt['ref'];
            }
            $results[] = $entry;
        }

        $payload = [
            'results' => $results,
            'failed' => $failed,
            'attribution' => 'Data via Galuchat API'
        ];
        $response->getBody()->write(json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        return $response->withHeader('Content-Type', 'application/json');
    }
}
