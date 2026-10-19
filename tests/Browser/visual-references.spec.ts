import { test, expect } from '@playwright/test';
import { waitForLoginResponse } from './csrf-diagnostics';

test('4D: synthetic home and room at the original reference image dimensions', async ({ page }) => {
    await page.goto('/login');
    await page.getByLabel('E-mail').fill('mestre@freecast.test');
    await page.getByLabel('Senha', { exact: true }).fill('Mesa2026!');
    expect((await waitForLoginResponse(page)).status()).toBe(302);
    await expect(page.getByRole('heading', { name: 'Início' })).toBeVisible({ timeout: 15_000 });
    await page.setViewportSize({ width: 864, height: 465 });
    await page.screenshot({ path: test.info().outputPath('inicio-referencia-864.png') });

    // Prepared synthetic fixture, independent of the order of gameplay tests.
    await page.goto('/mesas');
    await page.locator('.room-card').filter({ hasText: 'A Cripta das Marés' }).first()
        .getByRole('link', { name: 'Abrir mesa', exact: true }).click();
    await page.setViewportSize({ width: 879, height: 465 });
    for (const selector of ['.room-left-column', '.chat-panel', '.participants-panel']) {
        await expect(page.locator(selector)).toBeVisible({ timeout: 15_000 });
    }
    await page.screenshot({ path: test.info().outputPath('mesa-referencia-879.png') });
});
