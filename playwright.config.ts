import { defineConfig } from 'playwright/test';
import os from 'node:os';
import path from 'node:path';

const databasePath =
    process.env.OPENKOS_E2E_DATABASE ??
    path.join(os.tmpdir(), 'openkos-ope-220-playwright.sqlite');
const fixturePath = path.join(os.tmpdir(), 'openkos-ope-220-playwright.json');

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
            QUEUE_CONNECTION: 'sync',
            SESSION_DRIVER: 'file',
        },
    },
});
