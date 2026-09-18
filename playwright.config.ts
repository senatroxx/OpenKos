import os from 'node:os';
import path from 'node:path';
import { defineConfig } from 'playwright/test';

const scenario = process.env.OPENKOS_E2E_NAME ?? 'ope-220';
const databasePath =
    process.env.OPENKOS_E2E_DATABASE ??
    path.join(os.tmpdir(), `openkos-${scenario}-playwright.sqlite`);
const fixturePath = path.join(os.tmpdir(), `openkos-${scenario}-playwright.json`);

export default defineConfig({
    testDir: './e2e',
    testMatch: '**/*.spec.ts',
    timeout: 60_000,
    expect: {
        timeout: 10_000,
    },
    fullyParallel: false,
    workers: 1,
    reporter: 'list',
    use: {
        baseURL: 'http://127.0.0.1:4173',
        trace: 'retain-on-failure',
        screenshot: 'only-on-failure',
        video: 'retain-on-failure',
    },
    webServer: {
        command: 'node e2e/start-server.mjs',
        url: 'http://127.0.0.1:4173/login',
        reuseExistingServer: false,
        timeout: 120_000,
        env: {
            ...process.env,
            APP_ENV: 'testing',
            APP_URL: 'http://127.0.0.1:4173',
            BCRYPT_ROUNDS: '4',
            CACHE_STORE: 'array',
            DB_CONNECTION: 'sqlite',
            DB_DATABASE: databasePath,
            DB_URL: '',
            MAIL_MAILER: 'array',
            OPENKOS_E2E_FIXTURE_PATH: fixturePath,
            OPENKOS_E2E_SEED: `e2e/seed-${scenario}.php`,
            QUEUE_CONNECTION: 'sync',
            SESSION_DRIVER: 'file',
        },
    },
});
