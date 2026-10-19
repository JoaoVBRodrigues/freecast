import { defineConfig } from '@playwright/test';

const baseURL = process.env.FREECAST_BASE_URL ?? 'http://127.0.0.1:8000';

export default defineConfig({
    testDir: './tests/Browser',
    outputDir: process.env.FREECAST_TEST_OUTPUT_DIR ?? 'test-results',
    fullyParallel: false,
    retries: 0,
    workers: 1,
    reporter: 'list',
    use: {
        baseURL,
        viewport: { width: 867, height: 465 },
        trace: process.env.FREECAST_PUBLIC_PILOT ? 'off' : 'retain-on-failure',
        screenshot: 'only-on-failure',
    },
    webServer: process.env.FREECAST_BASE_URL ? undefined : {
        command: 'php artisan serve --host=127.0.0.1 --port=8000',
        url: 'http://127.0.0.1:8000/login',
        reuseExistingServer: !process.env.CI,
        timeout: 30_000,
    },
});
