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
            'samples' => [
                ['ref' => 'r1', 'area' => 'Area1', 'start' => '2024-01-01T00:00:00Z', 'end' => '2024-01-01T01:00:00Z'],
                ['ref' => 'r2', 'area' => 'Area2', 'start' => '2024-01-01T01:00:00Z', 'end' => '2024-01-01T02:30:00Z']
            ]
        ];
        $request = (new ServerRequestFactory())->createServerRequest('POST', '/tools/summarize_stays')
            ->withParsedBody($payload);
        $response = $app->handle($request);
        $this->assertSame(200, $response->getStatusCode());
        $data = json_decode((string)$response->getBody(), true);
        $this->assertSame('Area1で60分滞在→Area2で90分滞在', $data['summary']);
        $this->assertCount(2, $data['stays']);
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
            'samples' => [
                ['ref' => 'r1', 'area' => 'Area1', 'start' => '2024-01-01T02:00:00Z', 'end' => '2024-01-01T01:00:00Z']
            ]
        ];
        $request = (new ServerRequestFactory())->createServerRequest('POST', '/tools/summarize_stays')
            ->withParsedBody($payload);
        $response = $app->handle($request);
        $this->assertSame(200, $response->getStatusCode());
        $data = json_decode((string)$response->getBody(), true);
        $this->assertEmpty($data['stays']);
        $this->assertCount(1, $data['errors']);
        $this->assertSame('INVALID_TIME_RANGE', $data['errors'][0]['reason']);
    }
}
