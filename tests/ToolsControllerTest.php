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
            ->add(new RateLimitMiddleware(5))
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
        $this->assertCount(2, $data['results']);
        $this->assertEmpty($data['errors']);
        $this->assertSame('13101', $data['results'][0]['payload']['code']);
    }

    public function testInvalidTimeFormat(): void
    {
        $client = $this->createMock(GaluchatClient::class);
        $client->method('resolve')->willReturn([]);
        $app = $this->createApp($client);
        $payload = [
            'points' => [
                ['lat' => 35.0, 'lon' => 135.0, 't' => '2024-01-01T00:00:00']
            ]
        ];
        $request = (new ServerRequestFactory())->createServerRequest('POST', '/tools/resolve_points')
            ->withParsedBody($payload);
        $response = $app->handle($request);
        $this->assertSame(200, $response->getStatusCode());
        $data = json_decode((string)$response->getBody(), true);
        $this->assertNotEmpty($data['errors']);
        $this->assertSame('INVALID_T', $data['errors'][0]['reason']);
    }
}
