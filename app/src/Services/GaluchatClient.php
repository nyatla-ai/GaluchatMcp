<?php
namespace App\Services;

use App\Domain\Errors;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

class GaluchatClient
{
    private Client $http;
    private array $mapsets;
    private float $unit;
    private array $paths = [
        'admin' => '/raacs',
        'estat' => '/resareas',
        'jarl'  => '/rjccs'
    ];

    public function __construct(array $config)
    {
        $this->mapsets = $config['mapsets'] ?? [];
        $this->unit = $config['unit'] ?? 1.0;
        $baseUrl = $config['base_url'] ?? '';
        $timeout = ($config['timeout_ms'] ?? 10000) / 1000;
        $this->http = new Client([
            'base_uri' => rtrim($baseUrl, '/'),
            'timeout' => $timeout
        ]);
    }

    /**
     * @param string $granularity
     * @param array $points array of ['lat'=>, 'lon'=>, 'ref'=>]
     * @return array array of ['code'=>?, 'address'=>?]
     */
    public function resolve(string $granularity, array $points): array
    {
        if (empty($points)) {
            return [];
        }
        $path = $this->paths[$granularity] ?? $this->paths['admin'];
        $mapset = $this->mapsets[$granularity] ?? '';
        $body = [
            'unit' => $this->unit,
            'points' => array_map(function ($p) {
                $lon = (int)round($p['lon'] / $this->unit);
                $lat = (int)round($p['lat'] / $this->unit);
                return [$lon, $lat];
            }, $points)
        ];
        try {
            $resp = $this->http->post($path, [
                'query' => ['mapset' => $mapset],
                'json' => $body
            ]);
        } catch (GuzzleException $e) {
            throw new \RuntimeException(Errors::API_ERROR);
        }
        $status = $resp->getStatusCode();
        if ($status == 429) {
            throw new \RuntimeException(Errors::RATE_LIMIT);
        }
        if ($status >= 400) {
            throw new \RuntimeException(Errors::API_ERROR);
        }
        $data = json_decode((string)$resp->getBody(), true);
        $codes = $data['codes'] ?? [];
        $addresses = $data['addresses'] ?? [];
        $results = [];
        foreach ($codes as $i => $code) {
            $results[] = [
                'code' => $code,
                'address' => $addresses[$i] ?? null
            ];
        }
        return $results;
    }
}
