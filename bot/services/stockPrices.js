/**
 * 주식 시세 조회 (yahoo-finance2) + 메모리 캐시 (5분 TTL)
 * 실시간에 가깝게 반영하려면 캐시 시간을 줄이면 됨 (API 호출 제한 고려)
 */
const CACHE_MS = 5 * 60 * 1000; // 5분
const priceCache = new Map();

// 고가 + 저가 종목 혼합 (포인트가 적어도 1주 이상 매수 가능한 종목 포함)
const STOCK_SYMBOLS = [
    'AAPL', 'MSFT', 'GOOGL', 'AMZN', 'META', 'NVDA', 'TSLA',
    'BRK-B', 'JPM', 'V', 'JNJ', 'WMT', 'PG', 'MA', 'HD',
    'F', 'INTC', 'AMD', 'GE', 'BAC', 'C', 'AAL', 'UAL',
    'NIO', 'XPEV', 'PLTR', 'SOFI', 'RIVN', 'LCID', 'SNAP'
];

async function fetchQuote(symbol) {
    try {
        const YahooFinance = (await import('yahoo-finance2')).default;
        const yf = new YahooFinance({ suppressNotices: ['yahooSurvey'] });
        const summary = await yf.quoteSummary(symbol, { modules: ['price'] });
        const priceMod = summary?.price;
        if (!priceMod) return { price: null, name: null, dayHigh: null, dayLow: null, changePercent: null };
        const price = priceMod.regularMarketPrice ?? priceMod.regularMarketOpen ?? null;
        const rawName = priceMod.shortName ?? priceMod.longName ?? '';
        const name = (typeof rawName === 'string' ? rawName : String(rawName)).trim() || null;
        const dayHigh = priceMod.regularMarketDayHigh != null ? Number(priceMod.regularMarketDayHigh) : null;
        const dayLow = priceMod.regularMarketDayLow != null ? Number(priceMod.regularMarketDayLow) : null;
        const changePercent = priceMod.regularMarketChangePercent != null ? Number(priceMod.regularMarketChangePercent) : null;
        return {
            price: price != null ? Number(price) : null,
            name: name || null,
            dayHigh: dayHigh,
            dayLow: dayLow,
            changePercent: changePercent,
        };
    } catch (e) {
        console.warn(`[stock] quote ${symbol} failed:`, e?.message || e);
        return { price: null, name: null, dayHigh: null, dayLow: null, changePercent: null };
    }
}

export async function getPrice(symbol) {
    const key = String(symbol).toUpperCase();
    const cached = priceCache.get(key);
    if (cached && Date.now() - cached.at < CACHE_MS) return cached.price;
    const data = await fetchQuote(key);
    if (data.price != null) priceCache.set(key, { ...data, at: Date.now() });
    return data.price;
}

export async function getAllPrices() {
    const prices = {};
    const names = {};
    const dayHighs = {};
    const dayLows = {};
    const changePercents = {};
    await Promise.all(
        STOCK_SYMBOLS.map(async (sym) => {
            const key = String(sym).toUpperCase();
            const cached = priceCache.get(key);
            if (cached && Date.now() - cached.at < CACHE_MS) {
                if (cached.price != null) prices[key] = cached.price;
                if (cached.name) names[key] = cached.name;
                if (cached.dayHigh != null) dayHighs[key] = cached.dayHigh;
                if (cached.dayLow != null) dayLows[key] = cached.dayLow;
                if (cached.changePercent != null) changePercents[key] = cached.changePercent;
                return;
            }
            const data = await fetchQuote(key);
            if (data.price != null) {
                priceCache.set(key, { ...data, at: Date.now() });
                prices[key] = data.price;
                if (data.name) names[key] = data.name;
                if (data.dayHigh != null) dayHighs[key] = data.dayHigh;
                if (data.dayLow != null) dayLows[key] = data.dayLow;
                if (data.changePercent != null) changePercents[key] = data.changePercent;
            }
        })
    );
    return { prices, names, dayHighs, dayLows, changePercents };
}

export function getStockSymbols() {
    return [...STOCK_SYMBOLS];
}

const CHART_CACHE_MS = 10 * 60 * 1000; // 10분
const chartCache = new Map();

/** 종목별 과거 시세 (차트용). 반환: { dates: string[], prices: number[] } */
export async function getChartData(symbol) {
    const key = String(symbol).toUpperCase();
    const cached = chartCache.get(key);
    if (cached && Date.now() - cached.at < CHART_CACHE_MS) return cached.data;

    try {
        const YahooFinance = (await import('yahoo-finance2')).default;
        const yf = new YahooFinance({ suppressNotices: ['yahooSurvey'] });
        const end = new Date();
        const start = new Date();
        start.setDate(start.getDate() - 90); // 최근 약 3개월

        const result = await yf.chart(key, {
            period1: start,
            period2: end,
            interval: '1d',
        });

        const quotes = result?.quotes || [];
        const dates = quotes.map((q) => (q.date ? new Date(q.date).toLocaleDateString('ko-KR', { month: 'short', day: 'numeric' }) : ''));
        const prices = quotes.map((q) => (q.close != null ? Number(q.close) : null));
        const data = { dates: dates.slice(-60), prices: prices.slice(-60) };
        chartCache.set(key, { data, at: Date.now() });
        return data;
    } catch (e) {
        console.warn(`[stock] chart ${key} failed:`, e?.message || e);
        return null;
    }
}
