<?php
namespace App\Tests;

use PHPUnit\Framework\TestCase;
use Slim\Factory\AppFactory;
use Slim\Psr7\Factory\ServerRequestFactory;
use App\Services\InputValidator;
use App\Controllers\ToolsController;
use App\Middleware\JsonSchemaMiddleware;
use App\Middleware\RateLimitMiddleware;
use App\Services\GaluchatClient;
use App\Domain\Errors;

class ToolsControllerTest extends TestCase
{
    private function createApp($client): \Slim\App
    {
        $validator = new InputValidator();
        $controller = new ToolsController($validator, $client);
        $app = AppFactory::create();
        $app->addBodyParsingMiddleware();
        $schema = __DIR__ . '/../app/resources/schema/resolve_points.input.json';
        $app->post('/tools/resolve_points', [$controller, 'resolvePoints'])
            ->add(new RateLimitMiddleware(100))
            ->add(new JsonSchemaMiddleware($schema));
        return $app;
    }

    public function testResolvePointsSuccess(): void
    {
        $client = $this->createMock(GaluchatClient::class);
        $client->method('resolve')->willReturn([
            ['code' => '13101', 'address' => 'A'],
            ['code' => '13102', 'address' => 'B']
        ]);
        $app = $this->createApp($client);
        $payload = [
            'granularity' => 'admin',
            'points' => [
                ['ref' => 'r1', 'lat' => 35.0, 'lon' => 135.0],
                ['ref' => 'r2', 'lat' => 36.0, 'lon' => 136.0]
            ]
        ];
        $request = (new ServerRequestFactory())->createServerRequest('POST', '/tools/resolve_points')
            ->withParsedBody($payload);
        $response = $app->handle($request);
        $this->assertSame(200, $response->getStatusCode());
        $data = json_decode((string)$response->getBody(), true);
        $this->assertArrayNotHasKey('error', $data);
        $this->assertCount(2, $data['results']);
        $this->assertSame('r1', $data['results'][0]['ref']);
        $this->assertSame('13101', $data['results'][0]['code']);
        $this->assertSame('A', $data['results'][0]['address']);
    }

    public function testResolvePointsNullCode(): void
    {
        $client = $this->createMock(GaluchatClient::class);
        $client->method('resolve')->willReturn([
            ['code' => null, 'address' => 'IGNORED']
        ]);
        $app = $this->createApp($client);
        $payload = [
            'points' => [
                ['ref' => 'r1', 'lat' => 35.0, 'lon' => 135.0]
            ]
        ];
        $request = (new ServerRequestFactory())->createServerRequest('POST', '/tools/resolve_points')
            ->withParsedBody($payload);
        $response = $app->handle($request);
        $data = json_decode((string)$response->getBody(), true);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertArrayNotHasKey('error', $data);
        $this->assertNull($data['results'][0]['code']);
        $this->assertNull($data['results'][0]['address']);
    }

    public function testResolvePointsAllowsEmptyAndNullRef(): void
    {
        $client = $this->createMock(GaluchatClient::class);
        $client->method('resolve')->willReturn([
            ['code' => 'A', 'address' => 'X'],
            ['code' => 'B', 'address' => 'Y'],
            ['code' => 'C', 'address' => 'Z']
        ]);
        $app = $this->createApp($client);
        $payload = [
            'points' => [
                ['ref' => '', 'lat' => 35.0, 'lon' => 135.0],
                ['lat' => 36.0, 'lon' => 136.0],
                ['ref' => null, 'lat' => 37.0, 'lon' => 137.0]
            ]
        ];
        $request = (new ServerRequestFactory())->createServerRequest('POST', '/tools/resolve_points')
            ->withParsedBody($payload);
        $response = $app->handle($request);
        $data = json_decode((string)$response->getBody(), true);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertArrayNotHasKey('error', $data);
        $this->assertCount(3, $data['results']);
        $this->assertSame('', $data['results'][0]['ref']);
        $this->assertNull($data['results'][1]['ref']);
        $this->assertNull($data['results'][2]['ref']);
    }

    public function testResolvePointsRateLimit(): void
    {
        $client = $this->createMock(GaluchatClient::class);
        $client->method('resolve')->willThrowException(new \RuntimeException(Errors::RATE_LIMIT));
        $app = $this->createApp($client);
        $payload = [
            'points' => [
                ['ref' => 'r1', 'lat' => 35.0, 'lon' => 135.0]
            ]
        ];
        $request = (new ServerRequestFactory())->createServerRequest('POST', '/tools/resolve_points')
            ->withParsedBody($payload);
        $response = $app->handle($request);
        $this->assertSame(200, $response->getStatusCode());
        $data = json_decode((string)$response->getBody(), true);
        $this->assertSame('RATE_LIMIT', $data['error']['code']);
        $this->assertSame('RATE_LIMIT', $data['error']['message']);
    }

    public function testResolvePointsOutOfCoverage(): void
    {
        $client = $this->createMock(GaluchatClient::class);
        $client->method('resolve')->willThrowException(new \RuntimeException(Errors::OUT_OF_COVERAGE));
        $app = $this->createApp($client);
        $payload = [
            'points' => [
                ['ref' => 'r1', 'lat' => 35.0, 'lon' => 135.0]
            ]
        ];
        $request = (new ServerRequestFactory())->createServerRequest('POST', '/tools/resolve_points')
            ->withParsedBody($payload);
        $response = $app->handle($request);
        $this->assertSame(200, $response->getStatusCode());
        $data = json_decode((string)$response->getBody(), true);
        $this->assertSame('OUT_OF_COVERAGE', $data['error']['code']);
        $this->assertSame('OUT_OF_COVERAGE', $data['error']['message']);
    }
}
