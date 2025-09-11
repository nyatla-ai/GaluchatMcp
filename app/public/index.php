<?php
use Slim\Factory\AppFactory;
use App\Controllers\McpController;
use App\Controllers\ToolsController;
use App\Controllers\RpcController;
use App\Services\InputValidator;
use App\Services\GaluchatClient;
use App\Middleware\JsonSchemaMiddleware;
use App\Middleware\RateLimitMiddleware;

require __DIR__ . '/../../vendor/autoload.php';


// load config via include
$env = getenv('GALUCHAT_ENV') ?: 'dev';
$configFile = __DIR__ . "/../../config/config.$env.php";
if (!file_exists($configFile)) {
    throw new RuntimeException("missing config file: $configFile");
}
$config = include $configFile;
foreach (['api_url_prefix', 'mapsets'] as $k) {
    if (!isset($config['galuchat'][$k])) {
        throw new RuntimeException("config missing galuchat.$k");
    }
}

$urlPrefix = rtrim($config['app']['url_prefix'] ?? (getenv('MCP_URL_PREFIX') ?: '/mcp'), '/');
$app = AppFactory::create();
if ($urlPrefix !== '' && $urlPrefix !== '/') {
    $app->setBasePath($urlPrefix);
}
$app->addBodyParsingMiddleware();
$app->addRoutingMiddleware();
$app->addErrorMiddleware(true, true, true);
$maxPoints = $config['resolve_points']['max_points'] ?? 10000;
$validator = new InputValidator($maxPoints);
$client = new GaluchatClient($config['galuchat']);
$tools = new ToolsController($validator, $client);
$mcp = new McpController();
$rpc = new RpcController($tools, $mcp);

$manifestPath = ($urlPrefix === '' ? '' : $urlPrefix) . '/manifest.json';

$optionsHandler = function ($request, $response) {
    return $response
        ->withHeader('Access-Control-Allow-Origin', 'https://chat.openai.com')
        ->withHeader('Access-Control-Allow-Methods', 'GET, POST, OPTIONS')
        ->withHeader('Access-Control-Allow-Headers', 'Content-Type');
};

$app->options('/manifest.json', $optionsHandler);
$app->get('/manifest.json', [$mcp, 'manifest']);

$app->options('/rpc', $optionsHandler);
$app->post('/rpc', [$rpc, 'handle']);

$app->options('/tools/resolve_points', $optionsHandler);
$app->post('/tools/resolve_points', [$tools, 'resolvePoints'])
    ->add(new RateLimitMiddleware(5))
    ->add(new JsonSchemaMiddleware(__DIR__ . '/../resources/schema/resolve_points.input.json'));

$app->options('/tools/summarize_stays', $optionsHandler);
$app->post('/tools/summarize_stays', [$tools, 'summarizeStays'])
    ->add(new RateLimitMiddleware(5))
    ->add(new JsonSchemaMiddleware(__DIR__ . '/../resources/schema/summarize_stays.input.json'));

$app->get('/', function ($request, $response) use ($manifestPath) {
    return $response->withHeader('Location', $manifestPath)->withStatus(302);
});

// Removed catch-all route so Slim's default 404/405 handlers are used

$app->run();
