<?php
namespace App\Tests;

use PHPUnit\Framework\TestCase;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Response;
use App\Controllers\RpcController;
use App\Controllers\ToolsController;
use App\Controllers\McpController;
use App\Services\InputValidator;
use App\Services\GaluchatClient;

class RpcControllerTest extends TestCase
{
    public function testToolsListReturnsNextCursorAndSchemas(): void
    {
        $validator = new InputValidator();
        $client = $this->createMock(GaluchatClient::class);
        $toolsController = new ToolsController($validator, $client);
        $mcpController = new McpController();
        $controller = new RpcController($toolsController, $mcpController);

        $payload = [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'tools/list',
        ];
        $request = (new ServerRequestFactory())->createServerRequest('POST', '/rpc')
            ->withParsedBody($payload);
        $response = new Response();

        $resultResponse = $controller->handle($request, $response);
        $data = json_decode((string) $resultResponse->getBody(), true);

        $this->assertArrayHasKey('result', $data);
        $this->assertArrayHasKey('tools', $data['result']);
        $this->assertArrayHasKey('nextCursor', $data['result']);
        $this->assertNull($data['result']['nextCursor']);
        $firstTool = $data['result']['tools'][0];
        $this->assertArrayHasKey('title', $firstTool);
        $this->assertArrayHasKey('inputSchema', $firstTool);
        $this->assertArrayHasKey('outputSchema', $firstTool);
    }

    public function testNotificationWithoutIdReturns204AndEmptyBody(): void
    {
        $validator = new InputValidator();
        $client = $this->createMock(GaluchatClient::class);
        $toolsController = new ToolsController($validator, $client);
        $mcpController = new McpController();
        $controller = new RpcController($toolsController, $mcpController);

        $payload = [
            'jsonrpc' => '2.0',
            'method' => 'tools/list',
        ];
        $request = (new ServerRequestFactory())->createServerRequest('POST', '/rpc')
            ->withParsedBody($payload);
        $response = new Response();

        $resultResponse = $controller->handle($request, $response);

        $this->assertSame(204, $resultResponse->getStatusCode());
        $this->assertSame('', (string) $resultResponse->getBody());
    }
}
