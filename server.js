import 'dotenv/config';
import express from 'express';
import { createClient } from '@supabase/supabase-js';
import { verifyGameToken } from './utils/gameAuth.js';
import { getPrice, getAllPrices, getStockSymbols, getChartData } from './services/stockPrices.js';
import { fileURLToPath } from 'url';
import { dirname, join } from 'path';

const __dirname = dirname(fileURLToPath(import.meta.url));
const PORT = process.env.PORT || 3000;
const app = express();
app.use(express.json());

const supabase = process.env.SUPABASE_URL && process.env.SUPABASE_KEY
    ? createClient(process.env.SUPABASE_URL, process.env.SUPABASE_KEY)
    : null;

app.get('/', (_req, res) => {
    res.send('Bot is alive!');
});

// 메인 메뉴
app.get('/game', (_req, res) => {
    res.sendFile(join(__dirname, 'public', 'index.html'));
});
// 코인 캐처
app.get('/game/coin', (_req, res) => {
    res.sendFile(join(__dirname, 'public', 'game.html'));
});

// 게임: 내 정보 조회 (포인트)
app.get('/game/api/me', async (req, res) => {
    const token = req.query.token;
    if (!token || !supabase) {
        return res.status(400).json({ error: 'token required or server not configured' });
    }
    const payload = verifyGameToken(token);
    if (!payload) return res.status(401).json({ error: 'invalid or expired token' });

    const [{ data: userRow, error: userErr }, { data: guildRow }] = await Promise.all([
        supabase.from('users').select('username, total_score').eq('guild_id', payload.guild_id).eq('user_id', payload.user_id).single(),
        supabase.from('guilds').select('guild_name').eq('guild_id', payload.guild_id).single(),
    ]);
    if (userErr && userErr.code !== 'PGRST116') return res.status(500).json({ error: userErr.message });
    const total_score = (userRow && userRow.total_score !== undefined) ? userRow.total_score : 0;
    return res.json({
        username: userRow?.username || payload.username,
        guild_name: guildRow?.guild_name || null,
        total_score,
    });
});

// 게임: 점수 반영 (게임 점수 → 포인트 추가, 10점당 1포인트)
app.post('/game/api/score', async (req, res) => {
    const { token, score } = req.body || {};
    if (typeof score !== 'number' || score < 0 || !token || !supabase) {
        return res.status(400).json({ error: 'invalid request' });
    }
    const payload = verifyGameToken(token);
    if (!payload) return res.status(401).json({ error: 'invalid or expired token' });

    const pointsToAdd = Math.min(Math.floor(score / 10), 100);
    if (pointsToAdd <= 0) return res.json({ added: 0, total_score: 0 });

    const { data: row, error: selectError } = await supabase
        .from('users')
        .select('total_score, username')
        .eq('guild_id', payload.guild_id)
        .eq('user_id', payload.user_id)
        .single();

    if (selectError && selectError.code !== 'PGRST116') return res.status(500).json({ error: selectError.message });
    const current = (row && row.total_score !== undefined) ? row.total_score : 0;
    const newTotal = current + pointsToAdd;

    const { error: upsertError } = await supabase
        .from('users')
        .upsert(
            {
                guild_id: payload.guild_id,
                user_id: payload.user_id,
                username: payload.username,
                total_score: newTotal,
            },
            { onConflict: 'guild_id,user_id' }
        );

    if (upsertError) return res.status(500).json({ error: upsertError.message });
    return res.json({ added: pointsToAdd, total_score: newTotal });
});

// ----- 주식 시뮬레이터 -----
app.get('/game/stock', (_req, res) => {
    res.sendFile(join(__dirname, 'public', 'stock.html'));
});

function round2(x) {
    return Math.round(Number(x) * 100) / 100;
}

async function getWalletBalance(supabase, guildId, userId) {
    const { data } = await supabase.from('stock_wallet').select('balance').eq('guild_id', guildId).eq('user_id', userId).single();
    return round2((data && data.balance != null) ? Number(data.balance) : 0);
}

app.get('/game/stock/api/me', async (req, res) => {
    const token = req.query.token;
    if (!token || !supabase) return res.status(400).json({ error: 'token required or server not configured' });
    const payload = verifyGameToken(token);
    if (!payload) return res.status(401).json({ error: 'invalid or expired token' });

    const [{ data: user }, { data: wallet }, { data: guildRow }] = await Promise.all([
        supabase.from('users').select('username, total_score').eq('guild_id', payload.guild_id).eq('user_id', payload.user_id).single(),
        supabase.from('stock_wallet').select('balance').eq('guild_id', payload.guild_id).eq('user_id', payload.user_id).single(),
        supabase.from('guilds').select('guild_name').eq('guild_id', payload.guild_id).single(),
    ]);
    const total_score = (user && user.total_score != null) ? Number(user.total_score) : 0;
    const walletBalance = round2((wallet && wallet.balance != null) ? Number(wallet.balance) : 0);
    return res.json({
        username: user?.username || payload.username,
        guild_name: guildRow?.guild_name || null,
        total_score,
        wallet_balance: walletBalance,
    });
});

app.post('/game/stock/api/deposit', async (req, res) => {
    const { token, amount } = req.body || {};
    const num = Math.floor(Number(amount));
    if (!token || !supabase || num <= 0) return res.status(400).json({ error: 'invalid request' });
    const payload = verifyGameToken(token);
    if (!payload) return res.status(401).json({ error: 'invalid or expired token' });

    const { data: user, error: uErr } = await supabase.from('users').select('total_score, username').eq('guild_id', payload.guild_id).eq('user_id', payload.user_id).single();
    if (uErr && uErr.code !== 'PGRST116') return res.status(500).json({ error: uErr.message });
    const points = (user && user.total_score != null) ? Number(user.total_score) : 0;
    if (points < num) return res.status(400).json({ error: '포인트가 부족해요.' });

    const walletBalance = await getWalletBalance(supabase, payload.guild_id, payload.user_id);
    const newPoints = points - num;
    const newWallet = round2(walletBalance + num);

    await supabase.from('users').upsert({ guild_id: payload.guild_id, user_id: payload.user_id, username: payload.username, total_score: newPoints }, { onConflict: 'guild_id,user_id' });
    await supabase.from('stock_wallet').upsert({ guild_id: payload.guild_id, user_id: payload.user_id, balance: newWallet, updated_at: new Date().toISOString() }, { onConflict: 'guild_id,user_id' });
    return res.json({ total_score: newPoints, wallet_balance: newWallet });
});

app.post('/game/stock/api/withdraw', async (req, res) => {
    const { token, amount } = req.body || {};
    const payload = verifyGameToken(token);
    if (!token || !supabase) return res.status(400).json({ error: 'invalid request' });
    if (!payload) return res.status(401).json({ error: 'invalid or expired token' });

    const walletBalance = await getWalletBalance(supabase, payload.guild_id, payload.user_id);
    // total_score가 정수라서 출금은 정수만 이체. 소수점은 게임 잔액에 남김 (3.5 → 3 출금, 0.5 잔액)
    const maxWithdrawInt = Math.floor(walletBalance);
    const withdrawAmount = amount != null
        ? Math.min(Math.floor(Number(amount)), maxWithdrawInt)
        : maxWithdrawInt;
    if (withdrawAmount <= 0) return res.status(400).json({ error: '출금할 잔액이 없어요.' });

    const { data: user } = await supabase.from('users').select('total_score, username').eq('guild_id', payload.guild_id).eq('user_id', payload.user_id).single();
    const points = (user && user.total_score != null) ? Number(user.total_score) : 0;
    const newWallet = round2(Math.max(0, walletBalance - withdrawAmount));
    const newPoints = points + withdrawAmount;

    await supabase.from('users').upsert({ guild_id: payload.guild_id, user_id: payload.user_id, username: payload.username, total_score: newPoints }, { onConflict: 'guild_id,user_id' });
    await supabase.from('stock_wallet').upsert({ guild_id: payload.guild_id, user_id: payload.user_id, balance: newWallet, updated_at: new Date().toISOString() }, { onConflict: 'guild_id,user_id' });
    return res.json({ total_score: newPoints, wallet_balance: newWallet });
});

app.get('/game/stock/api/portfolio', async (req, res) => {
    const token = req.query.token;
    if (!token || !supabase) return res.status(400).json({ error: 'invalid request' });
    const payload = verifyGameToken(token);
    if (!payload) return res.status(401).json({ error: 'invalid or expired token' });

    const { data: rows } = await supabase.from('stock_portfolio').select('symbol, shares, avg_cost').eq('guild_id', payload.guild_id).eq('user_id', payload.user_id);
    const list = (rows || []).filter((r) => r.shares > 0).map((r) => ({ symbol: r.symbol, shares: r.shares, avg_cost: Number(r.avg_cost) }));
    return res.json({ portfolio: list });
});

app.get('/game/stock/api/prices', async (_req, res) => {
    try {
        const { prices, names, dayHighs, dayLows, changePercents } = await getAllPrices();
        const symbols = getStockSymbols();
        return res.json({ prices, symbols, names: names || {}, dayHighs: dayHighs || {}, dayLows: dayLows || {}, changePercents: changePercents || {} });
    } catch (e) {
        return res.status(500).json({ error: e?.message || '가격 조회 실패' });
    }
});

app.get('/game/stock/api/chart', async (req, res) => {
    const symbol = req.query.symbol;
    if (!symbol) return res.status(400).json({ error: 'symbol required' });
    const sym = String(symbol).toUpperCase();
    if (!getStockSymbols().includes(sym)) return res.status(400).json({ error: '지원하지 않는 종목이에요.' });
    try {
        const data = await getChartData(sym);
        if (!data || !data.dates?.length) return res.status(404).json({ error: '차트 데이터 없음' });
        return res.json(data);
    } catch (e) {
        return res.status(500).json({ error: e?.message || '차트 조회 실패' });
    }
});

app.post('/game/stock/api/buy', async (req, res) => {
    const { token, symbol, shares } = req.body || {};
    const numShares = Math.floor(Number(shares));
    if (!token || !supabase || !symbol || numShares <= 0) return res.status(400).json({ error: 'invalid request' });
    const payload = verifyGameToken(token);
    if (!payload) return res.status(401).json({ error: 'invalid or expired token' });

    const sym = String(symbol).toUpperCase();
    if (!getStockSymbols().includes(sym)) return res.status(400).json({ error: '지원하지 않는 종목이에요.' });
    const price = await getPrice(sym);
    if (price == null) return res.status(502).json({ error: '시세 조회 실패' });
    const cost = Math.round(price * numShares * 100) / 100;

    const walletBalance = await getWalletBalance(supabase, payload.guild_id, payload.user_id);
    if (walletBalance < cost) return res.status(400).json({ error: '잔액이 부족해요.' });

    const { data: row } = await supabase.from('stock_portfolio').select('shares, avg_cost').eq('guild_id', payload.guild_id).eq('user_id', payload.user_id).eq('symbol', sym).single();
    const prevShares = (row && row.shares) ? Number(row.shares) : 0;
    const prevCost = (row && row.avg_cost) ? Number(row.avg_cost) : 0;
    const newShares = prevShares + numShares;
    const newAvgCost = prevShares + numShares === 0 ? 0 : (prevShares * prevCost + numShares * price) / newShares;
    const newBalance = round2(walletBalance - cost);

    await supabase.from('stock_wallet').upsert({ guild_id: payload.guild_id, user_id: payload.user_id, balance: newBalance, updated_at: new Date().toISOString() }, { onConflict: 'guild_id,user_id' });
    await supabase.from('stock_portfolio').upsert({ guild_id: payload.guild_id, user_id: payload.user_id, symbol: sym, shares: newShares, avg_cost: newAvgCost, updated_at: new Date().toISOString() }, { onConflict: 'guild_id,user_id,symbol' });
    return res.json({ wallet_balance: newBalance, portfolio: [{ symbol: sym, shares: newShares, avg_cost: newAvgCost }] });
});

app.post('/game/stock/api/sell', async (req, res) => {
    const { token, symbol, shares } = req.body || {};
    const numShares = Math.floor(Number(shares));
    if (!token || !supabase || !symbol || numShares <= 0) return res.status(400).json({ error: 'invalid request' });
    const payload = verifyGameToken(token);
    if (!payload) return res.status(401).json({ error: 'invalid or expired token' });

    const sym = String(symbol).toUpperCase();
    const { data: row } = await supabase.from('stock_portfolio').select('shares, avg_cost').eq('guild_id', payload.guild_id).eq('user_id', payload.user_id).eq('symbol', sym).single();
    if (!row || Number(row.shares) < numShares) return res.status(400).json({ error: '보유 수량이 부족해요.' });

    const price = await getPrice(sym);
    if (price == null) return res.status(502).json({ error: '시세 조회 실패' });
    const proceeds = round2(price * numShares);
    const newShares = Number(row.shares) - numShares;
    const walletBalance = await getWalletBalance(supabase, payload.guild_id, payload.user_id);
    const newBalance = round2(walletBalance + proceeds);

    await supabase.from('stock_wallet').upsert({ guild_id: payload.guild_id, user_id: payload.user_id, balance: newBalance, updated_at: new Date().toISOString() }, { onConflict: 'guild_id,user_id' });
    await supabase.from('stock_portfolio').upsert({ guild_id: payload.guild_id, user_id: payload.user_id, symbol: sym, shares: newShares, avg_cost: Number(row.avg_cost), updated_at: new Date().toISOString() }, { onConflict: 'guild_id,user_id,symbol' });
    return res.json({ wallet_balance: newBalance, sold: numShares, proceeds });
});

app.listen(PORT, '0.0.0.0', () => {
    console.log(`Web server running! (PORT: ${PORT})`);
});

import('./index.js').catch((err) => {
    console.error('Bot load failed:', err);
    process.exit(1);
});
