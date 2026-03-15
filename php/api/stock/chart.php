<?php

require_once __DIR__ . '/../../common.php';

$symbol = $_GET['symbol'] ?? null;
if (!$symbol) {
    json_err(400, 'symbol required');
    return;
}
$sym = strtoupper((string) $symbol);
if (!in_array($sym, get_stock_symbols(), true)) {
    json_err(400, '지원하지 않는 종목이에요.');
    return;
}
try {
    $data = get_chart_data($sym);
    if (!$data || empty($data['dates'])) {
        json_err(404, '차트 데이터 없음');
        return;
    }
    json_ok($data);
} catch (Throwable $e) {
    json_err(500, $e->getMessage() ?: '차트 조회 실패');
}
