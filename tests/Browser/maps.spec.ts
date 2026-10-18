import { expect, test, type Page } from '@playwright/test';
import { deflateSync } from 'node:zlib';
import { waitForLoginResponse } from './csrf-diagnostics';

const demoPassword = 'Mesa2026!';

function passwordFor(email: string): string {
    if (email === 'mestre@freecast.test') return process.env.FREECAST_MASTER_PASSWORD ?? demoPassword;
    if (email === 'jogador@freecast.test') return process.env.FREECAST_PLAYER_PASSWORD ?? demoPassword;
    if (email === 'convidada@freecast.test') return process.env.FREECAST_GUEST_PASSWORD ?? demoPassword;

    throw new Error(`No browser-test password is configured for ${email}.`);
}

function crc32(buffer: Buffer): number {
    let crc = 0xffffffff;
    for (const byte of buffer) {
        crc ^= byte;
        for (let bit = 0; bit < 8; bit += 1) {
            crc = (crc >>> 1) ^ ((crc & 1) ? 0xedb88320 : 0);
        }
    }
    return (crc ^ 0xffffffff) >>> 0;
}

function pngChunk(type: string, data: Buffer): Buffer {
    const name = Buffer.from(type);
    const content = Buffer.concat([name, data]);
    const length = Buffer.alloc(4);
    const checksum = Buffer.alloc(4);
    length.writeUInt32BE(data.length);
    checksum.writeUInt32BE(crc32(content));
    return Buffer.concat([length, content, checksum]);
}

function syntheticPng(color: [number, number, number], circle = false): Buffer {
    const size = 64;
    const pixels = Buffer.alloc((size * 4 + 1) * size);

    for (let y = 0; y < size; y += 1) {
        const row = y * (size * 4 + 1);
        pixels[row] = 0;
        for (let x = 0; x < size; x += 1) {
            const offset = row + 1 + x * 4;
            const inside = !circle || ((x - 31.5) ** 2 + (y - 31.5) ** 2 <= 29 ** 2);
            if (!inside) continue;
            pixels[offset] = color[0];
            pixels[offset + 1] = color[1];
            pixels[offset + 2] = color[2];
            pixels[offset + 3] = 255;
        }
    }

    const header = Buffer.alloc(13);
    header.writeUInt32BE(size, 0);
    header.writeUInt32BE(size, 4);
    header[8] = 8;
    header[9] = 6;

    return Buffer.concat([
        Buffer.from([137, 80, 78, 71, 13, 10, 26, 10]),
        pngChunk('IHDR', header),
        pngChunk('IDAT', deflateSync(pixels)),
        pngChunk('IEND', Buffer.alloc(0)),
    ]);
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

async function uploadImage(page: Page, name: string, kind: 'background' | 'token', color: [number, number, number]): Promise<void> {
    await page.getByLabel('Imagem de mapa ou token').setInputFiles({
        name: `${name}.png`,
        mimeType: 'image/png',
        buffer: syntheticPng(color, kind === 'token'),
    });
    await page.getByLabel('Nome do recurso').fill(name);
    await page.getByLabel('Tipo de recurso').selectOption(kind);
    const uploadResponse = page.waitForResponse((response) =>
        response.request().method() === 'POST'
        && response.url().includes('/biblioteca/recursos')
        && response.status() === 302,
    );
    await page.getByRole('button', { name: 'Enviar', exact: true }).click();
    await uploadResponse;
    await expect(page.locator('.asset-library-card').filter({ hasText: name })).toBeVisible();
    await expect(page.getByRole('button', { name: 'Enviar', exact: true })).toBeEnabled();
}

async function canvasPoint(page: Page, worldX: number, worldY: number): Promise<{ x: number; y: number }> {
    const host = await page.locator('.map-canvas-host').boundingBox();
    if (!host) throw new Error('Map canvas host is not visible.');
    const camera = await page.locator('.map-editor-page').evaluate((element) => ({
        x: Number(element.getAttribute('data-camera-x')),
        y: Number(element.getAttribute('data-camera-y')),
        scale: Number(element.getAttribute('data-camera-scale')),
    }));

    return {
        x: host.x + camera.x + worldX * camera.scale,
        y: host.y + camera.y + worldY * camera.scale,
    };
}

test('a master persists an uploaded map and three tokens; a player can move only their assigned token', async ({ browser, page }) => {
    test.setTimeout(180_000);

    const roomTitle = `Mesa de mapas ${Date.now()}`;
    const mapTitle = `Mapa persistente ${Date.now()}`;
    await signIn(page, 'mestre@freecast.test');
    await page.getByRole('link', { name: 'Nova mesa' }).first().click();
    await page.getByLabel('Nome da mesa').fill(roomTitle);
    await page.getByLabel('Sistema').fill('Sistema Próprio');
    const createRoomResponse = page.waitForResponse((response) =>
        response.request().method() === 'POST' && new URL(response.url()).pathname === '/mesas',
    );
    await page.getByRole('button', { name: 'Criar mesa', exact: true }).last().click();
    expect((await createRoomResponse).status()).toBeLessThan(400);
    await expect(page.getByRole('link', { name: `Mesa: ${roomTitle}` })).toBeVisible({ timeout: 15_000 });
    const roomId = new URL(page.url()).pathname.split('/').at(-1);

    const player = await browser.newPage({ viewport: { width: 867, height: 465 } });
    await signIn(player, 'jogador@freecast.test');
    await player.goto('/mesas');
    const roomCard = player.locator('.room-card').filter({ hasText: roomTitle });
    await expect(roomCard).toBeVisible();
    await roomCard.getByRole('button', { name: 'Entrar na mesa' }).click();
    await expect(player.getByRole('link', { name: `Mesa: ${roomTitle}` })).toBeVisible();
    await expect(player.getByText('Jogador', { exact: true })).toBeVisible();

    const mapLibraryResponse = await page.goto(`/mesas/${roomId}/mapas`);
    expect(mapLibraryResponse?.status()).toBe(200);
    await page.getByLabel('Nome do mapa').fill(mapTitle);
    await page.getByLabel('Largura do mapa').fill('1000');
    await page.getByLabel('Altura do mapa').fill('700');
    await page.getByLabel('Tamanho da célula').fill('50');
    await page.getByRole('button', { name: 'Criar mapa', exact: true }).click();
    await expect(page.locator('.map-toolbar-title strong')).toHaveText(mapTitle, { timeout: 15_000 });
    const mapId = new URL(page.url()).pathname.split('/').at(-1);

    const resourceLibraryResponse = await page.goto(`/mesas/${roomId}/mapas`);
    expect(resourceLibraryResponse?.status()).toBe(200);
    await uploadImage(page, 'Fundo sintético', 'background', [47, 52, 62]);
    await uploadImage(page, 'Token azul', 'token', [70, 145, 235]);
    await uploadImage(page, 'Token verde', 'token', [77, 185, 120]);
    await uploadImage(page, 'Token dourado', 'token', [224, 172, 68]);

    await page.goto(`/mesas/${roomId}/mapas/${mapId}`);
    const backgroundResponse = page.waitForResponse((response) =>
        response.request().method() === 'PATCH'
        && new URL(response.url()).pathname === `/mesas/${roomId}/mapas/${mapId}`,
    );
    await page.getByLabel('Imagem de fundo').selectOption({ label: 'Fundo sintético' });
    expect((await backgroundResponse).status()).toBeLessThan(400);
    for (const tokenName of ['Token azul', 'Token verde', 'Token dourado']) {
        const objectResponse = page.waitForResponse((response) =>
            response.request().method() === 'POST'
            && new URL(response.url()).pathname === `/mesas/${roomId}/mapas/${mapId}/objetos`,
        );
        await page.getByRole('button', { name: `Adicionar token ${tokenName}` }).click();
        expect((await objectResponse).status()).toBeLessThan(400);
        await expect(page.locator('.map-object-list button')).toHaveCount(
            ['Token azul', 'Token verde', 'Token dourado'].indexOf(tokenName) + 1,
            { timeout: 15_000 },
        );
    }

    await expect(page.getByLabel('Canvas do mapa. Tokens selecionáveis; segure Espaço para deslocar a câmera e use a roda do mouse para zoom.')).toBeVisible();
    await page.screenshot({ path: 'test-results/map-editor-three-tokens.png' });
    const tokenButton = page.locator('.map-object-list button').filter({ hasText: 'Token azul' });
    const tokenObjectId = Number((await tokenButton.getAttribute('data-testid'))?.replace('map-object-select-', ''));
    await tokenButton.click();
    await page.locator('.map-inspector').evaluate((element) => {
        element.scrollTop = element.scrollHeight;
    });
    await expect(page.getByLabel('Jogador autorizado a mover o token')).toBeVisible();
    const assignmentResponse = page.waitForResponse((response) =>
        response.request().method() === 'PATCH'
        && new URL(response.url()).pathname === `/mesas/${roomId}/mapas/${mapId}/objetos/${tokenObjectId}`,
    );
    await page.getByLabel('Jogador autorizado a mover o token').selectOption({ label: 'Theo Arantes' });
    expect((await assignmentResponse).status()).toBeLessThan(400);

    await player.goto(`/mesas/${roomId}/mapas/${mapId}`);
    const playerToken = player.getByTestId(`map-object-select-${tokenObjectId}`);
    await expect(playerToken).toContainText('Token azul');
    await expect(playerToken).toContainText('0, 0');
    await playerToken.click();
    await expect(player.getByText('Você pode mover este token.')).toBeVisible();
    const otherToken = player.locator('.map-object-list button').filter({ hasText: 'Token verde' });
    await otherToken.click();
    await expect(player.getByText('Este token não foi atribuído a você.')).toBeVisible();
    await playerToken.click();
    await expect(player.getByLabel('Imagem de fundo')).toHaveCount(0);
    await expect(player.getByLabel('Largura do mundo')).toHaveCount(0);
    await expect(player.locator('.layer-control input:not(:disabled)')).toHaveCount(0);

    const start = await canvasPoint(player, 32, 32);
    await player.mouse.move(start.x, start.y);
    await player.mouse.wheel(0, -300);
    await expect.poll(async () => Number(await player.locator('.map-editor-page').getAttribute('data-camera-scale'))).toBeGreaterThan(0.5);
    const cameraBeforePan = await player.locator('.map-editor-page').evaluate((element) => ({
        x: Number(element.getAttribute('data-camera-x')),
        y: Number(element.getAttribute('data-camera-y')),
    }));
    await player.locator('.map-canvas-host').focus();
    await player.keyboard.down('Space');
    const host = await player.locator('.map-canvas-host').boundingBox();
    if (!host) throw new Error('Map canvas host is not visible.');
    const panStart = { x: host.x + host.width - 14, y: host.y + host.height - 14 };
    await player.mouse.move(panStart.x, panStart.y);
    await player.mouse.down();
    await player.mouse.move(panStart.x - 26, panStart.y - 20, { steps: 5 });
    await player.mouse.up();
    await player.keyboard.up('Space');
    const cameraAfterPan = await player.locator('.map-editor-page').evaluate((element) => ({
        x: Number(element.getAttribute('data-camera-x')),
        y: Number(element.getAttribute('data-camera-y')),
    }));
    expect(cameraAfterPan.x).not.toBe(cameraBeforePan.x);
    expect(cameraAfterPan.y).not.toBe(cameraBeforePan.y);
    const before = await canvasPoint(player, 32, 32);
    await player.mouse.move(before.x, before.y);
    await player.mouse.down();
    const after = await canvasPoint(player, 182, 132);
    await player.mouse.move(after.x, after.y, { steps: 8 });
    await player.mouse.up();
    await expect(playerToken).toContainText('150, 100', { timeout: 10_000 });
    const otherTokenStart = await canvasPoint(player, 82, 32);
    await player.mouse.move(otherTokenStart.x, otherTokenStart.y);
    await player.mouse.down();
    const otherTokenDrop = await canvasPoint(player, 232, 132);
    await player.mouse.move(otherTokenDrop.x, otherTokenDrop.y, { steps: 8 });
    await player.mouse.up();
    await expect(otherToken).toContainText('50, 0');
    const cameraBeforeReload = await player.locator('.map-editor-page').evaluate((element) => ({
        x: Number(element.getAttribute('data-camera-x')),
        y: Number(element.getAttribute('data-camera-y')),
        scale: Number(element.getAttribute('data-camera-scale')),
    }));
    await player.reload();
    await expect(player.getByTestId(`map-object-select-${tokenObjectId}`)).toContainText('150, 100');
    await expect(player.getByLabel('Imagem de fundo')).toHaveCount(0);
    const cameraAfterReload = await player.locator('.map-editor-page').evaluate((element) => ({
        x: Number(element.getAttribute('data-camera-x')),
        y: Number(element.getAttribute('data-camera-y')),
        scale: Number(element.getAttribute('data-camera-scale')),
    }));
    expect(cameraAfterReload.x).toBeCloseTo(cameraBeforeReload.x, 1);
    expect(cameraAfterReload.y).toBeCloseTo(cameraBeforeReload.y, 1);
    expect(cameraAfterReload.scale).toBeCloseTo(cameraBeforeReload.scale, 2);

    await page.reload();
    await expect(page.getByTestId(`map-object-select-${tokenObjectId}`)).toContainText('150, 100');
    await expect(page.getByLabel('Imagem de fundo')).toHaveValue(/.+/);
    await expect(page.locator('.map-object-list button')).toHaveCount(3);
    await expect(page.locator('.map-canvas-host canvas')).toBeVisible();
});
