<?php
namespace App\Middleware;

use Opis\JsonSchema\Validator;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Psr\Http\Server\MiddlewareInterface;
use Slim\Psr7\Response as SlimResponse;
use App\Domain\Errors;

class JsonSchemaMiddleware implements MiddlewareInterface
{
    private string $schemaPath;
    private Validator $validator;

    public function __construct(string $schemaPath)
    {
        $this->schemaPath = $schemaPath;
        $this->validator = new Validator();
    }

    public function process(Request $request, RequestHandler $handler): Response
    {
        $data = $request->getParsedBody();
        if ($data === null) {
            $response = new SlimResponse();
            $response->getBody()->write(json_encode(Errors::format(Errors::INVALID_INPUT, 'Invalid JSON')));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        $objectData = json_decode(json_encode($data));
        $schema = json_decode(file_get_contents($this->schemaPath));
        $result = $this->validator->validate($objectData, $schema);
        if (!$result->isValid()) {
            $response = new SlimResponse();
            $response->getBody()->write(json_encode(Errors::format(Errors::INVALID_INPUT, 'Request does not match schema')));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }
        return $handler->handle($request);
    }
}
