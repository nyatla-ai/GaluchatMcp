<?php
namespace App\Controllers;

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use App\Domain\InvalidInputException;
use App\Domain\Errors;

class RpcController
{
    private ToolsController $tools;
    private McpController $mcp;

    public function __construct(ToolsController $tools, McpController $mcp)
    {
        $this->tools = $tools;
        $this->mcp = $mcp;
    }

    public function handle(Request $request, Response $response): Response
    {
        $body = $request->getParsedBody();
        if (!is_array($body)) {
            return $this->error($response, null, -32700, 'Parse error');
        }
        $id = $body['id'] ?? null;
        if (!array_key_exists('id', $body)) {
            return $response->withStatus(204);
        }
        $method = $body['method'] ?? null;
        $jsonrpc = $body['jsonrpc'] ?? null;
        $params = $body['params'] ?? null;
        if ($jsonrpc !== '2.0' || !is_string($method)) {
            return $this->error($response, $id, -32600, 'Invalid Request');
        }

        try {
            switch ($method) {
                case 'tools/list':
                    $result = [
                        'tools' => $this->mcp->getToolDefinitions(),
                        'nextCursor' => null,
                    ];
                    return $this->result($response, $id, $result);
                case 'tools/call':
                    if (!is_array($params) || !isset($params['name'])) {
                        return $this->error($response, $id, -32602, 'Invalid params');
                    }
                    $name = $params['name'];
                    $arguments = $params['arguments'] ?? [];
                    switch ($name) {
                        case 'resolve_points':
                            $res = $this->tools->executeResolvePoints($arguments);
                            break;
                        case 'summarize_stays':
                            $res = $this->tools->executeSummarizeStays($arguments);
                            break;
                        case 'search':
                            $res = $this->tools->executeSearch($arguments);
                            break;
                        default:
                            return $this->error($response, $id, -32601, 'Method not found');
                    }
                    return $this->result($response, $id, $res);
                default:
                    return $this->error($response, $id, -32601, 'Method not found');
            }
        } catch (InvalidInputException $e) {
            $data = ['message' => $e->getMessage(), 'location' => $e->getLocation()];
            return $this->error($response, $id, -32602, Errors::INVALID_INPUT, $data);
        } catch (\RuntimeException $e) {
            $msg = $e->getMessage();
            $codeMsg = ($msg === Errors::RATE_LIMIT || $msg === Errors::OUT_OF_COVERAGE)
                ? $msg
                : Errors::API_ERROR;
            return $this->error($response, $id, -32000, $codeMsg);
        }
    }

    private function result(Response $response, $id, $result): Response
    {
        $payload = [
            'jsonrpc' => '2.0',
            'id' => $id,
            'result' => $result
        ];
        $response->getBody()->write(json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('Access-Control-Allow-Origin', 'https://chat.openai.com');
    }

    private function error(Response $response, $id, int $code, string $message, ?array $data = null): Response
    {
        $error = ['code' => $code, 'message' => $message];
        if ($data !== null) {
            $error['data'] = $data;
        }
        $payload = [
            'jsonrpc' => '2.0',
            'id' => $id,
            'error' => $error
        ];
        $response->getBody()->write(json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('Access-Control-Allow-Origin', 'https://chat.openai.com');
    }
}
