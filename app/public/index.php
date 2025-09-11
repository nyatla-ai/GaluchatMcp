<?php
use Slim\Factory\AppFactory;
use App\Controllers\McpController;
use App\Controllers\ToolsController;
use App\Services\InputValidator;
use App\Services\GaluchatClient;
use App\Middleware\JsonSchemaMiddleware;
use App\Middleware\RateLimitMiddleware;

require __DIR__ . '/../../vendor/autoload.php';

// load .env if exists
$envPath = __DIR__ . '/../.env';
if (file_exists($envPath)) {
    foreach (parse_ini_file($envPath, false, INI_SCANNER_RAW) as $k => $v) {
        putenv("$k=$v");
    }
}

// load config via include
$env = getenv('GALUCHAT_ENV') ?: 'dev';
$configFile = __DIR__ . "/../../config/config.$env.php";
if (!file_exists($configFile)) {
    throw new RuntimeException("missing config file: $configFile");
}
$config = include $configFile;
foreach (['base_url', 'mapsets'] as $k) {
    if (!isset($config['galuchat'][$k])) {
        throw new RuntimeException("config missing galuchat.$k");
    }
}

$basePath = rtrim(getenv('MCP_BASE_PATH') ?: '/mcp', '/');
$app = AppFactory::create();
if ($basePath !== '' && $basePath !== '/') {
    $app->setBasePath($basePath);
}
$app->addBodyParsingMiddleware();
$maxPoints = $config['resolve_points']['max_points'] ?? 10000;
$validator = new InputValidator($maxPoints);
$client = new GaluchatClient($config['galuchat']);
$tools = new ToolsController($validator, $client);
$mcp = new McpController();

$manifestPath = ($basePath === '' ? '' : $basePath) . '/manifest.json';

$app->get('/manifest.json', [$mcp, 'manifest']);
$app->post('/tools/resolve_points', [$tools, 'resolvePoints'])
    ->add(new RateLimitMiddleware(5))
    ->add(new JsonSchemaMiddleware(__DIR__ . '/../resources/schema/resolve_points.input.json'));
$app->post('/tools/summarize_stays', [$tools, 'summarizeStays'])
    ->add(new RateLimitMiddleware(5))
    ->add(new JsonSchemaMiddleware(__DIR__ . '/../resources/schema/summarize_stays.input.json'));

$app->get('/', function ($request, $response) use ($manifestPath) {
    return $response->withHeader('Location', $manifestPath)->withStatus(302);
});
$app->map(['GET', 'POST', 'PUT', 'DELETE', 'PATCH', 'OPTIONS'], '/{routes:.+}', function ($request, $response) use ($manifestPath) {
    return $response->withHeader('Location', $manifestPath)->withStatus(302);
});

$app->run();
