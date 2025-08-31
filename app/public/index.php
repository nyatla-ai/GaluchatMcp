<?php
use Slim\Factory\AppFactory;
use App\Controllers\McpController;
use App\Controllers\ToolsController;
use App\Services\InputValidator;
use App\Services\GaluchatClient;
use App\Middleware\JsonSchemaMiddleware;
use App\Middleware\RateLimitMiddleware;

require __DIR__ . '/../vendor/autoload.php';

// load .env if exists
$envPath = __DIR__ . '/../.env';
if (file_exists($envPath)) {
    foreach (parse_ini_file($envPath, false, INI_SCANNER_RAW) as $k => $v) {
        putenv("$k=$v");
    }
}

// load config via include
$env = getenv('GALUCHAT_ENV') ?: 'dev';
$configFile = __DIR__ . "/../config/resolve_points/config.$env.php";
if (!file_exists($configFile)) {
    throw new RuntimeException("missing config file: $configFile");
}
$config = include $configFile;
foreach (['base_url', 'mapsets', 'unit'] as $k) {
    if (!isset($config['galuchat'][$k])) {
        throw new RuntimeException("config missing galuchat.$k");
    }
}

$app = AppFactory::create();
$app->addBodyParsingMiddleware();

$validator = new InputValidator();
$client = new GaluchatClient($config['galuchat']);
$tools = new ToolsController($validator, $client);
$mcp = new McpController();

$app->get('/mcp/manifest', [$mcp, 'manifest']);
$app->post('/tools/resolve_points', [$tools, 'resolvePoints'])
    ->add(new RateLimitMiddleware(5))
    ->add(new JsonSchemaMiddleware(__DIR__ . '/../resources/schema/resolve_points.input.json'));

$app->run();
