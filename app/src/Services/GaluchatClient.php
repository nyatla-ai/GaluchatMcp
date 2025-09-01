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
        if (!isset($config['base_url']) || !is_string($config['base_url']) || $config['base_url'] === '') {
            throw new \RuntimeException('INVALID_CONFIG');
        }
        if (!isset($config['timeout_ms']) || !is_int($config['timeout_ms'])) {
            throw new \RuntimeException('INVALID_CONFIG');
        }
        if (!isset($config['mapsets']) || !is_array($config['mapsets'])) {
            throw new \RuntimeException('INVALID_CONFIG');
        }
        foreach (['admin', 'estat', 'jarl'] as $key) {
            if (!isset($config['mapsets'][$key]) || !is_string($config['mapsets'][$key]) || $config['mapsets'][$key] === '') {
                throw new \RuntimeException('INVALID_CONFIG');
            }
        }
        if (!isset($config['unit']) || !is_numeric($config['unit'])) {
            throw new \RuntimeException('INVALID_CONFIG');
        }
        $this->mapsets = $config['mapsets'];
        $this->unit = (float)$config['unit'];
        $baseUrl = $config['base_url'];
        $timeout = $config['timeout_ms'] / 1000;
        $this->http = new Client([
            'base_uri' => rtrim($baseUrl, '/'),
            'timeout' => $timeout,
            'http_errors' => false
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
        $codesKey = $granularity === 'estat' ? 'scodes' : 'aacodes';
        if (!is_array($data) || !isset($data[$codesKey]) || !is_array($data[$codesKey])
            || !isset($data['addresses']) || !is_array($data['addresses'])) {
            throw new \RuntimeException(Errors::OUT_OF_COVERAGE);
        }
        $codes = $data[$codesKey];
        $addresses = $data['addresses'];
        if (count($codes) !== count($points)) {
            throw new \RuntimeException(Errors::OUT_OF_COVERAGE);
        }
        $results = [];
        foreach ($codes as $i => $rawCode) {
            if ($rawCode === null) {
                $results[] = ['code' => null, 'address' => null];
                continue;
            }
            $key = (string)$rawCode;
            $addr = $addresses[$key] ?? null;
            if (!is_array($addr)) {
                throw new \RuntimeException(Errors::OUT_OF_COVERAGE);
            }
            $resCode = $addr['code'] ?? $key;
            if (isset($addr['code'])) {
                unset($addr['code']);
            }
            $addressStr = implode('', $addr);
            $results[] = [
                'code' => (string)$resCode,
                'address' => $addressStr
            ];
        }
        return $results;
    }
}
