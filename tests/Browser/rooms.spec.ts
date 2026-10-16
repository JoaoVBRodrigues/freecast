import { expect, test, type Page } from '@playwright/test';

const demoPassword = 'Mesa2026!';

async function signIn(page: Page, email: string): Promise<void> {
    await page.goto('/login');
    await page.getByLabel('E-mail').fill(email);
    await page.getByLabel('Senha', { exact: true }).fill(demoPassword);
    await page.getByRole('button', { name: 'Entrar', exact: true }).click();
    await expect(page.getByRole('heading', { name: 'Início' })).toBeVisible({ timeout: 15_000 });
}

test('a registered player can sign in and see the home page', async ({ page }) => {
    await signIn(page, 'jogador@freecast.test');
    await expect(page.getByRole('heading', { name: 'Recentes' })).toBeVisible();
    await expect(page.locator('.room-card').filter({ hasText: 'A Cripta das Marés' }).first()).toBeVisible();
});

test('a master creates a room, a player joins, and an outsider is denied', async ({ browser, page }) => {
    test.setTimeout(60_000);

    const title = `Mesa de teste ${Date.now()}`;
    await signIn(page, 'mestre@freecast.test');

    await page.getByRole('link', { name: 'Nova mesa' }).first().click();
    await page.getByLabel('Nome da mesa').fill(title);
    await page.getByLabel('Sistema').fill('Sistema Próprio');
    await page.getByRole('button', { name: 'Criar mesa', exact: true }).last().click();
    await expect(page.getByRole('link', { name: `Mesa: ${title}` })).toBeVisible();
    const roomUrl = new URL(page.url());
    const roomId = roomUrl.pathname.split('/').at(-1);

    const player = await browser.newPage();
    await signIn(player, 'jogador@freecast.test');
    await player.goto('/mesas');
    const card = player.locator('.room-card').filter({ hasText: title });
    await expect(card).toBeVisible();
    await card.getByRole('button', { name: 'Entrar na mesa' }).click();
    await expect(player.getByRole('link', { name: `Mesa: ${title}` })).toBeVisible();
    await expect(player.getByText('Jogador', { exact: true })).toBeVisible();

    const liveMessage = 'A maré está subindo.';
    await page.getByRole('textbox', { name: 'Digite uma mensagem' }).fill(liveMessage);
    await page.getByRole('button', { name: 'Enviar mensagem' }).click();
    await expect(player.getByText(liveMessage, { exact: true })).toBeVisible({ timeout: 10_000 });

    const outsider = await browser.newPage();
    await signIn(outsider, 'convidada@freecast.test');
    const denied = await outsider.goto(`/mesas/${roomId}`, { waitUntil: 'commit' });
    expect(denied?.status()).toBe(403);
});
