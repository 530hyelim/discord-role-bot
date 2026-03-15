<?php

declare(strict_types=1);

$offset = isset($_GET['offset']) ? (int) $_GET['offset'] : null;
$limit = isset($_GET['limit']) ? (int) $_GET['limit'] : null;
if ($offset !== null && $offset < 0) $offset = 0;
if ($limit !== null && $limit < 1) $limit = null;
try {
    $all = get_all_prices($offset, $limit);
    $symbols = get_stock_symbols();
    json_ok([
        'prices' => $all['prices'],
        'symbols' => $symbols,
        'names' => $all['names'] ?? [],
        'dayHighs' => $all['dayHighs'] ?? [],
        'dayLows' => $all['dayLows'] ?? [],
        'changePercents' => $all['changePercents'] ?? [],
    ]);
} catch (Throwable $e) {
    json_err(500, $e->getMessage() ?: '가격 조회 실패');
}
