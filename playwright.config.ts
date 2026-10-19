import { defineConfig } from '@playwright/test';

// Never start a default server against the development .env after shell setup fails.
if (process.env.FREECAST_TEST_RUNTIME !== '1' || process.env.DB_DATABASE !== 'freecast_test' || !process.env.FREECAST_BASE_URL) {
    throw new Error('Playwright requires the explicit isolated freecast_test environment. Use scripts/Run-Part4ATests.ps1.');
}
const baseURL = process.env.FREECAST_BASE_URL;

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
});
