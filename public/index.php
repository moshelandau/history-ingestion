<?php

declare(strict_types=1);

require __DIR__.'/../vendor/autoload.php';

use HistoryIngestion\Dashboard\DashboardHandler;

$projectRoot = dirname(__DIR__);
$specPath = $projectRoot.'/output/site-spec.json';
$sitePath = $projectRoot.'/output/site/index.html';

$handler = new DashboardHandler($specPath, $sitePath);

$method = $_SERVER['REQUEST_METHOD'];
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$body = file_get_contents('php://input') ?: '';

[$status, $headers, $body] = $handler->handle($method, $path, $body);

http_response_code($status);
foreach ($headers as $name => $value) {
    header("{$name}: {$value}");
}
echo $body;
