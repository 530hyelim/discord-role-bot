import 'dotenv/config';
import { spawn } from 'child_process';
import { fileURLToPath } from 'url';
import { dirname } from 'path';

const __dirname = dirname(fileURLToPath(import.meta.url));
const PORT = process.env.PORT || 3000;

const php = spawn('php', ['-S', `0.0.0.0:${PORT}`, 'web/router.php'], {
    cwd: __dirname,
    stdio: ['ignore', 'inherit', 'inherit'],
    env: { ...process.env },
});

php.on('error', (err) => {
    console.error('PHP server failed:', err);
    process.exit(1);
});

php.on('exit', (code, signal) => {
    if (code !== null && code !== 0) {
        console.error(`PHP exited with code ${code}`);
        process.exit(code);
    }
});

process.on('SIGTERM', () => {
    php.kill('SIGTERM');
});

process.on('SIGINT', () => {
    php.kill('SIGINT');
});

// PHP가 포트 바인딩할 시간 확보
await new Promise((r) => setTimeout(r, 1500));

console.log(`PHP web server listening on port ${PORT}`);

// Discord 봇 로드
await import('./bot/index.js');
