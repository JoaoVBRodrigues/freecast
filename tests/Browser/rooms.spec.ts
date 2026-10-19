import { expect, test, type Page } from '@playwright/test';
import { waitForLoginResponse } from './csrf-diagnostics';

const demoPassword = 'Mesa2026!';

function passwordFor(email: string): string {
    if (email === 'mestre@freecast.test') return process.env.FREECAST_MASTER_PASSWORD ?? demoPassword;
    if (email === 'jogador@freecast.test') return process.env.FREECAST_PLAYER_PASSWORD ?? demoPassword;
    if (email === 'convidada@freecast.test') return process.env.FREECAST_GUEST_PASSWORD ?? demoPassword;

    throw new Error(`No browser-test password is configured for ${email}.`);
}

async function signIn(page: Page, email: string): Promise<void> {
    await page.goto('/login');
    await page.getByLabel('E-mail').fill(email);
    await page.getByLabel('Senha', { exact: true }).fill(passwordFor(email));
    const response = await waitForLoginResponse(page);
    expect(response.status()).toBe(302);
    if (process.env.FREECAST_BASE_URL?.startsWith('https://')) {
        expect(new URL(page.url()).origin).toBe(new URL(process.env.FREECAST_BASE_URL).origin);
    }
    await expect(page.getByRole('heading', { name: 'Início' })).toBeVisible({ timeout: 15_000 });
}

test('a registered player can sign in and see the home page', async ({ page }) => {
    await signIn(page, 'jogador@freecast.test');
    await expect(page.getByRole('heading', { name: 'Recentes' })).toBeVisible();
    await expect(page.locator('.room-card').filter({ hasText: 'A Cripta das Marés' }).first()).toBeVisible();
});

test('a master creates a room, a player joins, and an outsider is denied', async ({ browser, page }) => {
    test.setTimeout(process.env.FREECAST_PUBLIC_PILOT ? 180_000 : 60_000);

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
    const joined = player.waitForResponse(response => response.url().endsWith(`/mesas/${roomId}/entrar`) && response.request().method() === 'POST', { timeout: 30_000 });
    await card.getByRole('button', { name: 'Entrar na mesa' }).click();
    expect((await joined).status()).toBe(302);
    await expect(player.getByRole('link', { name: `Mesa: ${title}` })).toBeVisible({ timeout: 15_000 });
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
