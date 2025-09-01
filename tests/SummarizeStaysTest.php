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
    private function createApp(): \Slim\App
    {
        $validator = new InputValidator();
        $client = $this->createMock(GaluchatClient::class);
        $controller = new ToolsController($validator, $client);
        $app = AppFactory::create();
        $app->addBodyParsingMiddleware();
        $schema = __DIR__ . '/../app/resources/schema/summarize_stays.input.json';
        $app->post('/tools/summarize_stays', [$controller, 'summarizeStays'])
            ->add(new RateLimitMiddleware(5))
            ->add(new JsonSchemaMiddleware($schema));
        return $app;
    }

    public function testSummarizeStaysSuccess(): void
    {
        $app = $this->createApp();
        $payload = [
            'positions' => [
                ['timestamp' => 0, 'lat' => 35.0, 'lon' => 135.0],
                ['timestamp' => 60, 'lat' => 35.0, 'lon' => 135.0005],
                ['timestamp' => 120, 'lat' => 35.0, 'lon' => 135.0],
                ['timestamp' => 5000, 'lat' => 35.1, 'lon' => 135.1],
                ['timestamp' => 5120, 'lat' => 35.1002, 'lon' => 135.1002]
            ],
            'params' => [
                'distance_threshold_m' => 100,
                'duration_threshold_sec' => 60
            ]
        ];
        $request = (new ServerRequestFactory())->createServerRequest('POST', '/tools/summarize_stays')
            ->withParsedBody($payload);
        $response = $app->handle($request);
        $this->assertSame(200, $response->getStatusCode());
        $data = json_decode((string)$response->getBody(), true);
        $this->assertCount(2, $data['results']);
        $this->assertEmpty($data['errors']);

        $validator = new Validator();
        $schema = json_decode(file_get_contents(__DIR__ . '/../app/resources/schema/summarize_stays.output.json'));
        $result = $validator->validate(json_decode((string)$response->getBody()), $schema);
        $this->assertTrue($result->isValid());
    }

    public function testSummarizeStaysInvalidTime(): void
    {
        $app = $this->createApp();
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
        $this->assertEmpty($data['results']);
        $this->assertCount(1, $data['errors']);
        $this->assertSame('INVALID_TIMESTAMP', $data['errors'][0]['reason']);
    }
}
