<?php

declare(strict_types=1);

/** 주식 시세 조회 (Yahoo Finance HTTP API) + 메모리 캐시 (5분 TTL) */
const CACHE_SEC = 300; // 5분
const CHART_CACHE_SEC = 600; // 10분

$GLOBALS['_stock_price_cache'] = [];
$GLOBALS['_stock_chart_cache'] = [];

const STOCK_SYMBOLS = [
    'AAPL', 'MSFT', 'GOOGL', 'AMZN', 'META', 'NVDA', 'TSLA',
    'BRK-B', 'JPM', 'V', 'JNJ', 'WMT', 'PG', 'MA', 'HD',
    'F', 'INTC', 'AMD', 'GE', 'BAC', 'C', 'AAL', 'UAL',
    'NIO', 'XPEV', 'PLTR', 'SOFI', 'RIVN', 'LCID', 'SNAP',
];

function _yahoo_http(string $url): ?array {
    $ctx = stream_context_create([
        'http' => [
            'header' => "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36\r\nAccept: application/json",
            'timeout' => 5,
        ],
    ]);
    $raw = @file_get_contents($url, false, $ctx);
    if ($raw === false) return null;
    $data = json_decode($raw, true);
    return is_array($data) ? $data : null;
}

/** 차트 API 응답에서 최근 일봉 데이터 추출 (현재가, 고가, 저가, 변동률) */
function _parse_chart_quote(array $json): ?array {
    $result = $json['chart']['result'][0] ?? null;
    if (!$result) return null;
    $meta = $result['meta'] ?? [];
    $q = $result['indicators']['quote'][0] ?? [];
    $closes = $q['close'] ?? [];
    $highs = $q['high'] ?? [];
    $lows = $q['low'] ?? [];
    $n = count($closes);
    $lastIdx = null;
    for ($i = $n - 1; $i >= 0; $i--) {
        if (isset($closes[$i]) && $closes[$i] !== null) {
            $lastIdx = $i;
            break;
        }
    }
    if ($lastIdx === null) return null;
    $lastClose = (float) $closes[$lastIdx];
    $price = isset($meta['regularMarketPrice']) && $meta['regularMarketPrice'] !== null
        ? (float) $meta['regularMarketPrice']
        : $lastClose;
    $dayHigh = isset($highs[$lastIdx]) && $highs[$lastIdx] !== null ? (float) $highs[$lastIdx] : null;
    $dayLow = isset($lows[$lastIdx]) && $lows[$lastIdx] !== null ? (float) $lows[$lastIdx] : null;
    $prevClose = null;
    for ($i = $lastIdx - 1; $i >= 0; $i--) {
        if (isset($closes[$i]) && $closes[$i] !== null) {
            $c = (float) $closes[$i];
            $prevClose = $c;
            if (abs($c - $lastClose) > 0.0001) break;
        }
    }
    $changePercent = null;
    if ($prevClose !== null && $prevClose > 0) {
        $changePercent = (($price - $prevClose) / $prevClose) * 100;
    }
    return ['price' => $price, 'dayHigh' => $dayHigh, 'dayLow' => $dayLow, 'changePercent' => $changePercent];
}

/** 여러 종목 차트를 병렬로 한 번에 요청 (현재가, 고가, 저가, 변동률) */
function _fetch_chart_quotes_batch(array $symbols): array {
    $out = [];
    if (!function_exists('curl_init')) return $out;
    $end = time();
    $start = $end - (7 * 86400);
    $mh = curl_multi_init();
    $handles = [];
    $ua = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36';
    foreach ($symbols as $sym) {
        $key = strtoupper($sym);
        $url = 'https://query1.finance.yahoo.com/v8/finance/chart/' . urlencode($key) . '?interval=1d&period1=' . $start . '&period2=' . $end;
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_HTTPHEADER => ["User-Agent: $ua", 'Accept: application/json'],
        ]);
        $handles[$key] = $ch;
        curl_multi_add_handle($mh, $ch);
    }
    do {
        $status = curl_multi_exec($mh, $running);
        if ($running) curl_multi_select($mh, 0.05);
    } while ($running && $status === CURLM_OK);
    foreach ($handles as $key => $ch) {
        $raw = curl_multi_getcontent($ch);
        curl_multi_remove_handle($mh, $ch);
        curl_close($ch);
        if ($raw === false) continue;
        $data = json_decode($raw, true);
        if (is_array($data)) {
            $parsed = _parse_chart_quote($data);
            if ($parsed !== null) $out[$key] = $parsed;
        }
    }
    curl_multi_close($mh);
    return $out;
}

/** 차트 API에서 마지막 종가를 현재가로 사용 (단일 종목, get_price용) */
function _price_from_chart(string $symbol): ?float {
    $chart = get_chart_data($symbol);
    if ($chart === null || empty($chart['prices'])) return null;
    $prices = $chart['prices'];
    for ($i = count($prices) - 1; $i >= 0; $i--) {
        if (isset($prices[$i]) && $prices[$i] !== null) return (float) $prices[$i];
    }
    return null;
}

function fetch_quote(string $symbol): array {
    $sym = strtoupper($symbol);
    $url = 'https://query2.finance.yahoo.com/v10/finance/quoteSummary/' . urlencode($sym) . '?modules=price';
    $json = _yahoo_http($url);
    if ($json === null) return ['price' => null, 'name' => null, 'dayHigh' => null, 'dayLow' => null, 'changePercent' => null];
    $priceMod = $json['quoteSummary']['result'][0]['price'] ?? null;
    if (!$priceMod) return ['price' => null, 'name' => null, 'dayHigh' => null, 'dayLow' => null, 'changePercent' => null];
    $price = $priceMod['regularMarketPrice'] ?? $priceMod['regularMarketOpen'] ?? null;
    $name = trim((string) ($priceMod['shortName'] ?? $priceMod['longName'] ?? '')) ?: null;
    $dayHigh = isset($priceMod['regularMarketDayHigh']) ? (float) $priceMod['regularMarketDayHigh'] : null;
    $dayLow = isset($priceMod['regularMarketDayLow']) ? (float) $priceMod['regularMarketDayLow'] : null;
    $changePercent = isset($priceMod['regularMarketChangePercent']) ? (float) $priceMod['regularMarketChangePercent'] : null;
    return ['price' => $price !== null ? (float) $price : null, 'name' => $name, 'dayHigh' => $dayHigh, 'dayLow' => $dayLow, 'changePercent' => $changePercent];
}

function get_price(string $symbol): ?float {
    $key = strtoupper($symbol);
    $cache = &$GLOBALS['_stock_price_cache'];
    if (isset($cache[$key]) && (time() - $cache[$key]['at']) < CACHE_SEC) {
        return $cache[$key]['price'];
    }
    // 차트 API 1회로 시세 조회 (quoteSummary 403 회피, 이중 요청 방지)
    $quotes = _fetch_chart_quotes_batch([$key]);
    if (isset($quotes[$key])) {
        $q = $quotes[$key];
        $cache[$key] = [
            'price' => $q['price'],
            'name' => null,
            'dayHigh' => $q['dayHigh'],
            'dayLow' => $q['dayLow'],
            'changePercent' => $q['changePercent'],
            'at' => time(),
        ];
        return $q['price'];
    }
    $data = fetch_quote($key);
    if ($data['price'] === null) {
        $data['price'] = _price_from_chart($key);
        if ($data['price'] !== null) {
            $data['dayHigh'] = $data['dayLow'] = $data['changePercent'] = null;
        }
    }
    if ($data['price'] !== null) {
        $cache[$key] = $data + ['at' => time()];
    }
    return $data['price'];
}

/** @param int|null $offset 시작 인덱스 (페이지 단위 로딩용)
 *  @param int|null $limit 개수 (null이면 전부) */
function get_all_prices(?int $offset = null, ?int $limit = null): array {
    $cache = &$GLOBALS['_stock_price_cache'];
    $prices = $names = $dayHighs = $dayLows = $changePercents = [];
    $toFetch = [];
    if ($offset !== null && $limit !== null) {
        $symbolsToConsider = array_slice(STOCK_SYMBOLS, $offset, $limit);
    } else {
        $symbolsToConsider = STOCK_SYMBOLS;
    }
    foreach ($symbolsToConsider as $sym) {
        $key = strtoupper($sym);
        if (isset($cache[$key]) && (time() - $cache[$key]['at']) < CACHE_SEC) {
            $c = $cache[$key];
            if ($c['price'] !== null) $prices[$key] = $c['price'];
            if (!empty($c['name'])) $names[$key] = $c['name'];
            if ($c['dayHigh'] !== null) $dayHighs[$key] = $c['dayHigh'];
            if ($c['dayLow'] !== null) $dayLows[$key] = $c['dayLow'];
            if ($c['changePercent'] !== null) $changePercents[$key] = $c['changePercent'];
            continue;
        }
        $toFetch[] = $key;
    }
    if ($toFetch !== []) {
        $now = time();
        $quotes = _fetch_chart_quotes_batch($toFetch);
        foreach ($quotes as $key => $q) {
            $prices[$key] = $q['price'];
            if ($q['dayHigh'] !== null) $dayHighs[$key] = $q['dayHigh'];
            if ($q['dayLow'] !== null) $dayLows[$key] = $q['dayLow'];
            if ($q['changePercent'] !== null) $changePercents[$key] = $q['changePercent'];
            $cache[$key] = [
                'price' => $q['price'],
                'name' => null,
                'dayHigh' => $q['dayHigh'],
                'dayLow' => $q['dayLow'],
                'changePercent' => $q['changePercent'],
                'at' => $now,
            ];
        }
    }
    return ['prices' => $prices, 'names' => $names, 'dayHighs' => $dayHighs, 'dayLows' => $dayLows, 'changePercents' => $changePercents];
}

function get_stock_symbols(): array {
    return STOCK_SYMBOLS;
}

function get_chart_data(string $symbol): ?array {
    $key = strtoupper($symbol);
    $cache = &$GLOBALS['_stock_chart_cache'];
    if (isset($cache[$key]) && (time() - $cache[$key]['at']) < CHART_CACHE_SEC) {
        return $cache[$key]['data'];
    }
    $end = time();
    $start = $end - (90 * 86400);
    $url = 'https://query1.finance.yahoo.com/v8/finance/chart/' . urlencode($key) . '?interval=1d&period1=' . $start . '&period2=' . $end;
    $json = _yahoo_http($url);
    if ($json === null) return null;
    $result = $json['chart']['result'][0] ?? null;
    if (!$result) return null;
    $quotes = $result['indicators']['quote'][0] ?? [];
    $timestamps = $result['timestamp'] ?? [];
    $closes = $quotes['close'] ?? [];
    $slice = max(0, count($timestamps) - 60);
    $timestamps = array_slice($timestamps, $slice);
    $closes = array_slice($closes, $slice);
    $dates = [];
    $prices = [];
    foreach ($timestamps as $i => $ts) {
        $dates[] = date('M j', (int) $ts);
        $prices[] = isset($closes[$i]) && $closes[$i] !== null ? (float) $closes[$i] : null;
    }
    $data = ['dates' => $dates, 'prices' => $prices];
    $cache[$key] = ['data' => $data, 'at' => time()];
    return $data;
}
