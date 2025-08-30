<?php
namespace App\Middleware;

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Psr\Http\Server\MiddlewareInterface;
use Slim\Psr7\Response as SlimResponse;
use App\Domain\Errors;

class RateLimitMiddleware implements MiddlewareInterface
{
    private int $limit;
    private static array $requests = [];

    public function __construct(int $limit = 5)
    {
        $this->limit = $limit;
    }

    public function process(Request $request, RequestHandler $handler): Response
    {
        $ip = $request->getServerParams()['REMOTE_ADDR'] ?? 'unknown';
        $now = microtime(true);
        self::$requests[$ip] = array_filter(self::$requests[$ip] ?? [], fn($t) => $now - $t < 1);
        if (count(self::$requests[$ip]) >= $this->limit) {
            $response = new SlimResponse();
            $response->getBody()->write(json_encode(Errors::format(Errors::RATE_LIMITED, 'Too many requests')));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(429);
        }
        self::$requests[$ip][] = $now;
        return $handler->handle($request);
    }
}
