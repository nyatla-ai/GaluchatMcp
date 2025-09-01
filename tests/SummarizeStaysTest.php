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
use Opis\JsonSchema\Validator;

class SummarizeStaysTest extends TestCase
{
    private function createApp(GaluchatClient $client): \Slim\App
    {
        $validator = new InputValidator();
        $controller = new ToolsController($validator, $client);
        $app = AppFactory::create();
        $app->addBodyParsingMiddleware();
        $schema = __DIR__ . '/../app/resources/schema/summarize_stays.input.json';
        $app->post('/tools/summarize_stays', [$controller, 'summarizeStays'])
            ->add(new RateLimitMiddleware(100))
            ->add(new JsonSchemaMiddleware($schema));
        return $app;
    }

    public function testSummarizeStaysSuccess(): void
    {
        $client = $this->createMock(GaluchatClient::class);
        $client->method('resolve')->willReturn([
            ['code' => 'A', 'address' => 'AA'],
            ['code' => 'A', 'address' => 'AA'],
            ['code' => 'B', 'address' => 'BB'],
            ['code' => 'B', 'address' => 'BB']
        ]);
        $app = $this->createApp($client);
        $payload = [
            'positions' => [
                ['timestamp' => 0, 'lat' => 35.0, 'lon' => 135.0],
                ['timestamp' => 60, 'lat' => 35.0, 'lon' => 135.0],
                ['timestamp' => 120, 'lat' => 35.1, 'lon' => 135.1],
                ['timestamp' => 180, 'lat' => 35.1, 'lon' => 135.1]
            ]
        ];
        $request = (new ServerRequestFactory())->createServerRequest('POST', '/tools/summarize_stays')
            ->withParsedBody($payload);
        $response = $app->handle($request);
        $this->assertSame(200, $response->getStatusCode());
        $data = json_decode((string)$response->getBody(), true);
        $this->assertCount(2, $data['results']);
        $this->assertSame('A', $data['results'][0]['code']);
        $this->assertSame('AA', $data['results'][0]['address']);
        $this->assertSame(2, $data['results'][0]['count']);
        $this->assertSame(60, $data['results'][0]['duration_sec']);
        $this->assertSame('B', $data['results'][1]['code']);
        $this->assertSame('BB', $data['results'][1]['address']);
        $this->assertSame(2, $data['results'][1]['count']);
        $this->assertSame(60, $data['results'][1]['duration_sec']);

        $validator = new Validator();
        $schema = json_decode(file_get_contents(__DIR__ . '/../app/resources/schema/summarize_stays.output.json'));
        $result = $validator->validate(json_decode((string)$response->getBody()), $schema);
        $this->assertTrue($result->isValid());
    }

    public function testSummarizeStaysInvalidTime(): void
    {
        $client = $this->createMock(GaluchatClient::class);
        $app = $this->createApp($client);
        $payload = [
            'positions' => [
                ['timestamp' => 10, 'lat' => 35.0, 'lon' => 135.0],
                ['timestamp' => 5, 'lat' => 35.1, 'lon' => 135.1]
            ]
        ];
        $request = (new ServerRequestFactory())->createServerRequest('POST', '/tools/summarize_stays')
            ->withParsedBody($payload);
        $response = $app->handle($request);
        $this->assertSame(200, $response->getStatusCode());
        $data = json_decode((string)$response->getBody(), true);
        $this->assertArrayHasKey('error', $data);
        $this->assertSame('INVALID_INPUT', $data['error']['code']);
        $this->assertSame('Invalid timestamp', $data['error']['message']);
    }

    public function testSummarizeStaysNullCode(): void
    {
        $client = $this->createMock(GaluchatClient::class);
        $client->method('resolve')->willReturn([
            ['code' => 'A', 'address' => 'AA'],
            ['code' => 'A', 'address' => 'AA'],
            ['code' => null, 'address' => 'IGNORED'],
            ['code' => 'B', 'address' => 'BB'],
            ['code' => 'B', 'address' => 'BB']
        ]);
        $app = $this->createApp($client);
        $payload = [
            'positions' => [
                ['timestamp' => 0, 'lat' => 35.0, 'lon' => 135.0],
                ['timestamp' => 60, 'lat' => 35.0, 'lon' => 135.0],
                ['timestamp' => 120, 'lat' => 35.1, 'lon' => 135.1],
                ['timestamp' => 180, 'lat' => 35.2, 'lon' => 135.2],
                ['timestamp' => 240, 'lat' => 35.2, 'lon' => 135.2]
            ]
        ];
        $request = (new ServerRequestFactory())->createServerRequest('POST', '/tools/summarize_stays')
            ->withParsedBody($payload);
        $response = $app->handle($request);
        $this->assertSame(200, $response->getStatusCode());
        $data = json_decode((string)$response->getBody(), true);
        $this->assertCount(3, $data['results']);
        $this->assertSame('A', $data['results'][0]['code']);
        $this->assertSame('AA', $data['results'][0]['address']);
        $this->assertSame(2, $data['results'][0]['count']);
        $this->assertSame(60, $data['results'][0]['duration_sec']);
        $this->assertNull($data['results'][1]['code']);
        $this->assertNull($data['results'][1]['address']);
        $this->assertSame(1, $data['results'][1]['count']);
        $this->assertSame(0, $data['results'][1]['duration_sec']);
        $this->assertSame('B', $data['results'][2]['code']);
        $this->assertSame('BB', $data['results'][2]['address']);
        $this->assertSame(2, $data['results'][2]['count']);
        $this->assertSame(60, $data['results'][2]['duration_sec']);
    }
}
