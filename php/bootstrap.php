<?php

declare(strict_types=1);

$projectRoot = dirname(__DIR__);
if (file_exists($projectRoot . '/.env')) {
    $dotenv = \Dotenv\Dotenv::createImmutable($projectRoot);
    $dotenv->safeLoad();
}

function env(string $key, ?string $default = null): ?string {
    $v = getenv($key);
    return $v !== false ? $v : $default;
}
