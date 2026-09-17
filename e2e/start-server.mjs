import { execFileSync, spawn } from 'node:child_process';
import { readFileSync } from 'node:fs';

const environment = { ...process.env };

execFileSync('php', ['artisan', 'migrate:fresh', '--seed'], {
    cwd: process.cwd(),
    env: environment,
    stdio: 'inherit',
});

execFileSync(
    'php',
    [
        'artisan',
        'tinker',
        '--execute',
        readFileSync('e2e/seed-ope-220.php', 'utf8'),
    ],
    {
        cwd: process.cwd(),
        env: environment,
        stdio: 'inherit',
    },
);

const server = spawn(
    'php',
    ['artisan', 'serve', '--host=127.0.0.1', '--port=4173'],
    {
        cwd: process.cwd(),
        env: environment,
        stdio: 'inherit',
    },
);

for (const signal of ['SIGINT', 'SIGTERM']) {
    process.on(signal, () => server.kill(signal));
}

server.on('exit', (code, signal) => {
    process.exit(code ?? (signal ? 1 : 0));
});
