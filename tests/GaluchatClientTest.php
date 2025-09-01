<?php
namespace App\Tests;

use App\Services\GaluchatClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;

class GaluchatClientTest extends TestCase
{
    private function createClient(array $responses, string $granularity = 'admin'): GaluchatClient
    {
        $config = [
            'base_url' => 'http://example.com',
            'timeout_ms' => 1000,
            'mapsets' => [
                'admin' => 'a',
                'estat' => 'e',
                'jarl' => 'j'
            ]
        ];
        $client = new GaluchatClient($config);
        $mock = new MockHandler($responses);
        $handlerStack = HandlerStack::create($mock);
        $guzzle = new \GuzzleHttp\Client(['handler' => $handlerStack, 'http_errors' => false]);
        $prop = new \ReflectionProperty(GaluchatClient::class, 'http');
        $prop->setAccessible(true);
        $prop->setValue($client, $guzzle);
        return $client;
    }

    public function testConstructorAcceptsValidConfig(): void
    {
        $config = [
            'base_url' => 'http://example.com',
            'timeout_ms' => 1000,
            'mapsets' => [
                'admin' => 'a',
                'estat' => 'e',
                'jarl' => 'j'
            ]
        ];
        $client = new GaluchatClient($config);
        $this->assertInstanceOf(GaluchatClient::class, $client);
    }

    /**
     * @dataProvider invalidConfigProvider
     */
    public function testConstructorThrowsOnInvalidConfig(array $config): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('INVALID_CONFIG');
        new GaluchatClient($config);
    }

    public static function invalidConfigProvider(): array
    {
        $base = [
            'base_url' => 'http://example.com',
            'timeout_ms' => 1000,
            'mapsets' => [
                'admin' => 'a',
                'estat' => 'e',
                'jarl' => 'j'
            ]
        ];
        return [
            'missing base_url' => [array_diff_key($base, ['base_url' => true])],
            'base_url not string' => [array_merge($base, ['base_url' => 123])],
            'missing timeout_ms' => [array_diff_key($base, ['timeout_ms' => true])],
            'timeout_ms not int' => [array_merge($base, ['timeout_ms' => 'abc'])],
            'missing mapsets' => [array_diff_key($base, ['mapsets' => true])],
            'mapsets missing admin' => [array_merge($base, ['mapsets' => ['estat' => 'e', 'jarl' => 'j']])],
            'mapsets missing estat' => [array_merge($base, ['mapsets' => ['admin' => 'a', 'jarl' => 'j']])],
            'mapsets missing jarl' => [array_merge($base, ['mapsets' => ['admin' => 'a', 'estat' => 'e']])],
            'mapsets admin not string' => [array_merge($base, ['mapsets' => ['admin' => 1, 'estat' => 'e', 'jarl' => 'j']])],
        ];
    }

    public function testResolveAdmin(): void
    {
        $body = [
            'addresses' => [
                '1' => ['prefecture' => 'A', 'city' => 'B'],
                '2' => ['prefecture' => 'C', 'city' => 'D']
            ],
            'aacodes' => [1, null, 2]
        ];
        $client = $this->createClient([new Response(200, [], json_encode($body))]);
        $points = [['lat' => 0, 'lon' => 0], ['lat' => 0, 'lon' => 0], ['lat' => 0, 'lon' => 0]];
        $res = $client->resolve('admin', $points);
        $this->assertSame('1', $res[0]['code']);
        $this->assertSame('AB', $res[0]['address']);
        $this->assertNull($res[1]['code']);
        $this->assertNull($res[1]['address']);
        $this->assertSame('2', $res[2]['code']);
        $this->assertSame('CD', $res[2]['address']);
    }

    public function testResolveUsesAddressCodeField(): void
    {
        $body = [
            'addresses' => [
                '10' => ['code' => 'J1', 'prefecture' => 'X', 'city' => 'Y']
            ],
            'aacodes' => [10]
        ];
        $client = $this->createClient([new Response(200, [], json_encode($body))], 'jarl');
        $points = [['lat' => 0, 'lon' => 0]];
        $res = $client->resolve('jarl', $points);
        $this->assertSame('J1', $res[0]['code']);
        $this->assertSame('XY', $res[0]['address']);
    }

    public function testResolveThrowsOutOfCoverageOnMismatch(): void
    {
        $body = [
            'addresses' => [],
            'aacodes' => [1]
        ];
        $client = $this->createClient([new Response(200, [], json_encode($body))]);
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('OUT_OF_COVERAGE');
        $client->resolve('admin', [['lat' => 0, 'lon' => 0]]);
    }
}
