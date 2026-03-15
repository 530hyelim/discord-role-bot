<?php

declare(strict_types=1);

/** @var PDO|null */
$GLOBALS['_db_connection'] = null;

/** @var string|null 'not_set' | 'parse_error' | 'connection_failed' */
$GLOBALS['_db_error'] = null;

/** @var string|null Last PDO error message (for DEBUG_DB) */
$GLOBALS['_db_last_error'] = null;

function get_db(): ?PDO {
    if (isset($GLOBALS['_db_connection'])) {
        return $GLOBALS['_db_connection'];
    }
    $url = getenv('DATABASE_URL');
    if ($url === false || $url === '') {
        $GLOBALS['_db_error'] = 'not_set';
        return null;
    }
    $params = parse_database_url($url);
    if ($params === null) {
        $GLOBALS['_db_error'] = 'parse_error';
        return null;
    }
    try {
        $dsn = sprintf(
            'pgsql:host=%s;port=%s;dbname=%s;sslmode=%s',
            $params['host'],
            $params['port'],
            $params['dbname'],
            $params['sslmode']
        );
        $pdo = new PDO($dsn, $params['user'], $params['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);
        $GLOBALS['_db_connection'] = $pdo;
        $GLOBALS['_db_error'] = null;
        $GLOBALS['_db_last_error'] = null;
        return $pdo;
    } catch (Throwable $e) {
        $GLOBALS['_db_error'] = 'connection_failed';
        $GLOBALS['_db_last_error'] = $e->getMessage();
        return null;
    }
}

/**
 * @return array{host:string,port:string,dbname:string,user:string,password:string,sslmode:string}|null
 */
function parse_database_url(string $url): ?array {
    $parsed = parse_url($url);
    if ($parsed === false) {
        return null;
    }
    $scheme = $parsed['scheme'] ?? '';
    if ($scheme === 'postgres') {
        $parsed['scheme'] = 'postgresql';
    }
    $host = $parsed['host'] ?? 'localhost';
    $port = isset($parsed['port']) ? (string) $parsed['port'] : '5432';
    $path = $parsed['path'] ?? '/';
    $dbname = ltrim($path, '/');
    if ($dbname === '') {
        return null;
    }
    $user = $parsed['user'] ?? '';
    $password = $parsed['pass'] ?? '';
    parse_str($parsed['query'] ?? '', $query);
    $sslmode = $query['sslmode'] ?? 'prefer';
    return [
        'host' => $host,
        'port' => $port,
        'dbname' => $dbname,
        'user' => $user,
        'password' => $password,
        'sslmode' => $sslmode,
    ];
}

function get_db_error(): ?string {
    get_db();
    return $GLOBALS['_db_error'] ?? null;
}

function get_db_last_error(): ?string {
    return $GLOBALS['_db_last_error'] ?? null;
}

/**
 * @param array<string, mixed> $params
 * @return array<string, mixed>|null
 */
function fetch_one(string $sql, array $params = []): ?array {
    $db = get_db();
    if (!$db) {
        return null;
    }
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row !== false ? $row : null;
}

/**
 * @param array<string, mixed> $params
 * @return list<array<string, mixed>>
 */
function fetch_all(string $sql, array $params = []): array {
    $db = get_db();
    if (!$db) {
        return [];
    }
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    return $rows !== false ? $rows : [];
}

/**
 * @param array<string, mixed> $params
 */
function execute_sql(string $sql, array $params = []): void {
    $db = get_db();
    if (!$db) {
        return;
    }
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
}
