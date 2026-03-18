<?php

declare(strict_types=1);

$projectRoot = dirname(__DIR__);
require_once $projectRoot . '/web/common.php';

$uri = $_SERVER['REQUEST_URI'] ?? '/';
$path = parse_url($uri, PHP_URL_PATH);
$path = $path === '' || $path === false ? '/' : $path;
$query = parse_url($uri, PHP_URL_QUERY) ?? '';
parse_str($query, $queryParams);
$hasToken = !empty(trim((string) ($queryParams['token'] ?? '')));

// 정적 파일
$publicDir = $projectRoot . '/web/pages';
$staticFiles = [
    '/game' => $publicDir . '/index.html',
    '/game/coin' => $publicDir . '/game.html',
    '/game/stock' => $publicDir . '/stock.html',
];
if (isset($staticFiles[$path]) && is_file($staticFiles[$path])) {
    if (!$hasToken) {
        header('Content-Type: text/html; charset=utf-8');
        echo '<script>alert("잘못된 접근입니다. 디스코드 서버에서 /game 커맨드를 통해 접근해주세요."); history.back();</script>';
        return true;
    }
    header('Content-Type: text/html; charset=utf-8');
    readfile($staticFiles[$path]);
    return true;
}

// API 라우팅
$routes = [
    'HEAD' => [
        '/' => function () { http_response_code(200); }
    ],
    'GET' => [
        '/' => function () { header('Content-Type: text/plain'); echo 'Bot is alive!'; },
        '/game/api/me' => $projectRoot . '/web/api/game/me.php',
        '/game/stock/api/me' => $projectRoot . '/web/api/stock/me.php',
        '/game/stock/api/portfolio' => $projectRoot . '/web/api/stock/portfolio.php',
        '/game/stock/api/prices' => $projectRoot . '/web/api/stock/prices.php',
        '/game/stock/api/chart' => $projectRoot . '/web/api/stock/chart.php',
    ],
    'POST' => [
        '/game/api/score' => $projectRoot . '/web/api/game/score.php',
        '/game/stock/api/deposit' => $projectRoot . '/web/api/stock/deposit.php',
        '/game/stock/api/withdraw' => $projectRoot . '/web/api/stock/withdraw.php',
        '/game/stock/api/buy' => $projectRoot . '/web/api/stock/buy.php',
        '/game/stock/api/sell' => $projectRoot . '/web/api/stock/sell.php',
    ],
];

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$handler = $routes[$method][$path] ?? null;

if ($handler === null) {
    header('HTTP/1.1 404 Not Found');
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'Not found']);
    return true;
}

if (is_callable($handler)) {
    $handler();
    return true;
}

require $handler;
return true;

function json_response(int $code, ?array $body, ?string $plain = null): void {
    header('Content-Type: application/json; charset=utf-8');
    http_response_code($code);
    if ($plain !== null) {
        echo json_encode($plain);
    } else {
        echo json_encode($body ?? []);
    }
}
