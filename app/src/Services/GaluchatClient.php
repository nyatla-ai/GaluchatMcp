<?php
namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

class GaluchatClient
{
    private Client $http;
    private array $paths = [
        'admin' => '/raacs',
        'estat' => '/resareas',
        'jarl'  => '/rjccs'
    ];

    public function __construct(string $baseUrl, int $timeoutMs = 3000)
    {
        $this->http = new Client([
            'base_uri' => rtrim($baseUrl, '/'),
            'timeout' => $timeoutMs / 1000
        ]);
    }

    /**
     * @param string $granularity
     * @param array $points array of ['lat'=>, 'lon'=>]
     * @return array
     * @throws GuzzleException
     */
    public function resolve(string $granularity, array $points): array
    {
        if (empty($points)) {
            return [];
        }
        $path = $this->paths[$granularity] ?? $this->paths['admin'];
        $resp = $this->http->post($path, [
            'json' => ['points' => array_map(fn($p) => ['lat' => $p['lat'], 'lon' => $p['lon']], $points)]
        ]);
        $data = json_decode($resp->getBody()->getContents(), true);
        return $data['results'] ?? [];
    }
}
