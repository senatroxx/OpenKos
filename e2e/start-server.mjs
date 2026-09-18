import { execFileSync, spawn } from 'node:child_process';
import { readFileSync } from 'node:fs';
import process from 'node:process';

const environment = { ...process.env };

const seed = process.env.OPENKOS_E2E_SEED ?? 'e2e/seed-ope-220.php';

if (seed === 'e2e/seed-ope-220.php') {
    execFileSync('php', ['artisan', 'migrate:fresh', '--seed'], {
        cwd: process.cwd(),
        env: environment,
        stdio: 'inherit',
    });
} else {
    execFileSync('php', ['artisan', 'migrate:fresh'], {
        cwd: process.cwd(),
        env: environment,
        stdio: 'inherit',
    });

    for (const seeder of [
        'RoleAndPermissionSeeder',
        'SettingSeeder',
        'RegionAndCitySeeder',
        'OwnerSeeder',
    ]) {
        execFileSync('php', ['artisan', 'db:seed', `--class=Database\\Seeders\\${seeder}`], {
            cwd: process.cwd(),
            env: environment,
            stdio: 'inherit',
        });
    }
}

execFileSync(
    'php',
    [
        'artisan',
        'tinker',
        '--execute',
        readFileSync(seed, 'utf8'),
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
