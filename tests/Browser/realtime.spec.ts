import { expect, test, type BrowserContext, type Page } from '@playwright/test';
import { spawn, type ChildProcess } from 'node:child_process';
import { deflateSync } from 'node:zlib';
import { waitForLoginResponse } from './csrf-diagnostics';

const demoPassword = 'Mesa2026!';
const baseURL = process.env.FREECAST_BASE_URL ?? 'http://127.0.0.1:8000';

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
    const content = Buffer.concat([Buffer.from(type), data]);
    const length = Buffer.alloc(4);
    const checksum = Buffer.alloc(4);
    length.writeUInt32BE(data.length);
    checksum.writeUInt32BE(crc32(content));
    return Buffer.concat([length, content, checksum]);
}

function syntheticPng(color: [number, number, number]): Buffer {
    const size = 64;
    const pixels = Buffer.alloc((size * 4 + 1) * size);
    for (let y = 0; y < size; y += 1) {
        const row = y * (size * 4 + 1);
        for (let x = 0; x < size; x += 1) {
            const offset = row + 1 + x * 4;
            const inside = (x - 31.5) ** 2 + (y - 31.5) ** 2 <= 29 ** 2;
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
    if (baseURL.startsWith('https://')) {
        expect(new URL(page.url()).origin).toBe(new URL(baseURL).origin);
    }
    await expect(page.getByRole('heading', { name: 'Início' })).toBeVisible({ timeout: 15_000 });
}

async function joinRoom(page: Page, roomTitle: string): Promise<void> {
    await page.goto('/mesas');
    const roomCard = page.locator('.room-card').filter({ hasText: roomTitle });
    await expect(roomCard).toBeVisible();
    await roomCard.getByRole('button', { name: 'Entrar na mesa' }).click();
    await expect(page.getByRole('link', { name: `Mesa: ${roomTitle}` })).toBeVisible();
}

async function uploadToken(page: Page, name: string, color: [number, number, number]): Promise<void> {
    await page.getByLabel('Imagem de mapa ou token').setInputFiles({
        name: `${name}.png`,
        mimeType: 'image/png',
        buffer: syntheticPng(color),
    });
    await page.getByLabel('Nome do recurso').fill(name);
    await page.getByLabel('Tipo de recurso').selectOption('token');
    const response = page.waitForResponse((item) =>
        item.request().method() === 'POST'
        && item.url().includes('/biblioteca/recursos')
        && item.status() === 302,
    );
    await page.getByRole('button', { name: 'Enviar', exact: true }).click();
    await response;
    await expect(page.locator('.asset-library-card').filter({ hasText: name })).toBeVisible();
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

async function dragWorldPoint(page: Page, from: { x: number; y: number }, to: { x: number; y: number }): Promise<void> {
    await page.mouse.move(from.x, from.y);
    await page.mouse.down();
    await page.mouse.move(to.x, to.y, { steps: 8 });
    await page.mouse.up();
}

async function sendJsonFromPage(
    page: Page,
    method: 'DELETE' | 'PATCH' | 'POST',
    url: string,
    payload: Record<string, unknown>,
): Promise<{ status: number; body: string }> {
    return page.evaluate(async ({ method, url, payload }) => {
        const cookie = document.cookie.split('; ').find((item) => item.startsWith('XSRF-TOKEN='));
        const csrf = cookie ? decodeURIComponent(cookie.slice('XSRF-TOKEN='.length)) : null;
        const response = await fetch(url, {
            method,
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                ...(csrf ? { 'X-XSRF-TOKEN': csrf } : {}),
            },
            body: JSON.stringify(payload),
        });
        return { status: response.status, body: await response.text() };
    }, { method, url, payload });
}

async function readNumericPageProp(page: Page, key: string): Promise<number> {
    return page.locator('#app').evaluate((element, property) => {
        const pageData = JSON.parse(element.getAttribute('data-page') ?? '{}') as {
            props?: Record<string, unknown>;
        };
        return Number(pageData.props?.[property]);
    }, key);
}

async function readTokenAssetId(page: Page, name: string): Promise<number> {
    return page.locator('#app').evaluate((element, assetName) => {
        const pageData = JSON.parse(element.getAttribute('data-page') ?? '{}') as {
            props?: { assets?: Array<{ id: number; name: string }> };
        };
        return pageData.props?.assets?.find((asset) => asset.name === assetName)?.id ?? 0;
    }, name);
}

function startQueueWorker(): ChildProcess {
    return spawn('php', ['artisan', 'queue:work', '--tries=5', '--timeout=20', '--sleep=1'], {
        cwd: process.cwd(),
        windowsHide: true,
        stdio: 'ignore',
    });
}

async function stopQueueWorker(worker: ChildProcess): Promise<void> {
    if (worker.exitCode !== null || worker.killed) return;
    const exited = new Promise<void>((resolve) => worker.once('exit', () => resolve()));
    worker.kill();
    await Promise.race([exited, new Promise<void>((resolve) => setTimeout(resolve, 5_000))]);
    if (worker.exitCode === null) {
        worker.kill('SIGKILL');
        await exited;
    }
}

function trackTraffic(
    page: Page,
    traffic: string[],
    onSocketClose?: () => void,
    socketTraffic?: string[],
    socketUrls?: string[],
): void {
    page.on('websocket', (socket) => {
        socketUrls?.push(socket.url());
        socket.on('framereceived', (frame) => {
            const payload = typeof frame.payload === 'string' ? frame.payload : frame.payload.toString();
            traffic.push(payload);
            socketTraffic?.push(payload);
        });
        if (onSocketClose) socket.on('close', onSocketClose);
    });
    page.on('response', (response) => {
        const path = new URL(response.url()).pathname;
        if (!path.endsWith('/eventos') && !path.endsWith('/historico') && !path.endsWith('/mensagens')) return;
        void response.text().then((body) => traffic.push(body));
    });
}

test('three participants synchronize maps, chat, rolls, private messages, and reconnect recovery', async ({ browser, page }) => {
    test.setTimeout(240_000);

    const contexts: BrowserContext[] = [];
    let queueWorker: ChildProcess | null = startQueueWorker();
    const privateMarker = `PRIVADO-${Date.now()}`;
    const roomTitle = `Mesa em tempo real ${Date.now()}`;

    try {
        await page.waitForTimeout(1_000);
        expect(queueWorker.exitCode).toBeNull();
        await signIn(page, 'mestre@freecast.test');
        await page.getByRole('link', { name: 'Nova mesa' }).first().click();
        await page.getByLabel('Nome da mesa').fill(roomTitle);
        await page.getByLabel('Sistema').fill('Sistema Próprio');
        const roomResponse = page.waitForResponse((response) =>
            response.request().method() === 'POST' && new URL(response.url()).pathname === '/mesas',
        );
        await page.getByRole('button', { name: 'Criar mesa', exact: true }).last().click();
        expect((await roomResponse).status()).toBeLessThan(400);
        await expect(page).toHaveURL(/\/mesas\/\d+$/, { timeout: 15_000 });
        const roomId = new URL(page.url()).pathname.split('/').at(-1);
        if (!roomId) throw new Error('The new room URL did not contain a room id.');

        const firstContext = await browser.newContext({ viewport: { width: 867, height: 465 } });
        const secondContext = await browser.newContext({ viewport: { width: 867, height: 465 } });
        contexts.push(firstContext, secondContext);
        const firstLogin = await firstContext.newPage();
        const secondLogin = await secondContext.newPage();
        await Promise.all([
            signIn(firstLogin, 'jogador@freecast.test'),
            signIn(secondLogin, 'convidada@freecast.test'),
        ]);
        await Promise.all([joinRoom(firstLogin, roomTitle), joinRoom(secondLogin, roomTitle)]);

        const masterMap = page;
        const mapLibraryResponse = await masterMap.goto(`/mesas/${roomId}/mapas`);
        expect(mapLibraryResponse?.status()).toBe(200);
        const mapTitle = `Mapa compartilhado ${Date.now()}`;
        await masterMap.getByLabel('Nome do mapa').fill(mapTitle);
        await masterMap.getByLabel('Largura do mapa').fill('1000');
        await masterMap.getByLabel('Altura do mapa').fill('700');
        await masterMap.getByLabel('Tamanho da célula').fill('50');
        const mapCreateResponsePromise = masterMap.waitForResponse((response) =>
            response.request().method() === 'POST'
            && new URL(response.url()).pathname === `/mesas/${roomId}/mapas`,
        );
        await masterMap.getByRole('button', { name: 'Criar mapa', exact: true }).click();
        const mapCreateResponse = await mapCreateResponsePromise;
        expect(mapCreateResponse.status()).toBeLessThan(400);
        await expect(masterMap).toHaveURL(new RegExp(`/mesas/${roomId}/mapas/\\d+$`), { timeout: 15_000 });
        await expect(masterMap.locator('.map-toolbar-title strong')).toHaveText(mapTitle, { timeout: 15_000 });
        const mapId = new URL(masterMap.url()).pathname.split('/').at(-1);
        if (!mapId) throw new Error('The new map URL did not contain a map id.');

        await masterMap.goto(`/mesas/${roomId}/mapas`);
        await uploadToken(masterMap, 'Token azul', [70, 145, 235]);
        await uploadToken(masterMap, 'Token verde', [77, 185, 120]);
        await uploadToken(masterMap, 'Token dourado', [224, 172, 68]);
        await masterMap.goto(`/mesas/${roomId}/mapas/${mapId}`);

        for (const tokenName of ['Token azul', 'Token verde', 'Token dourado']) {
            await masterMap.getByRole('button', { name: `Adicionar token ${tokenName}` }).click();
            await expect(masterMap.locator('.map-object-list button')).toHaveCount(
                ['Token azul', 'Token verde', 'Token dourado'].indexOf(tokenName) + 1,
            );
        }

        const blueToken = masterMap.locator('.map-object-list button').filter({ hasText: 'Token azul' });
        const blueTokenId = Number((await blueToken.getAttribute('data-testid'))?.replace('map-object-select-', ''));
        await blueToken.click();
        await masterMap.locator('.map-inspector').evaluate((element) => {
            element.scrollTop = element.scrollHeight;
        });
        await masterMap.getByLabel('Jogador autorizado a mover o token').selectOption({ label: 'Theo Arantes' });

        const greenToken = masterMap.locator('.map-object-list button').filter({ hasText: 'Token verde' });
        const greenTokenId = Number((await greenToken.getAttribute('data-testid'))?.replace('map-object-select-', ''));
        const gmLayerId = await masterMap.locator('.map-layer-list li')
            .filter({ hasText: 'Somente mestre' })
            .getAttribute('data-layer-id');
        if (!blueTokenId || !greenTokenId || !gmLayerId) throw new Error('Map layers or tokens were not created.');
        const gmUpdateStatus = await sendJsonFromPage(masterMap, 'PATCH', `/mesas/${roomId}/mapas/${mapId}/objetos/${greenTokenId}`, {
            name: 'Segredo do mestre',
            layer_id: Number(gmLayerId),
            expected_revision: 1,
            idempotency_key: `hide-${Date.now()}-token`,
        });
        expect(gmUpdateStatus.status, gmUpdateStatus.body).toBe(200);
        const activateStatus = await sendJsonFromPage(
            masterMap,
            'POST',
            `/mesas/${roomId}/mapas/${mapId}/ativar`,
            { idempotency_key: `activate-${Date.now()}-map` },
        );
        expect(activateStatus.status, activateStatus.body).toBe(200);
        await masterMap.screenshot({ path: 'test-results/realtime-map-three-contexts.png' });

        const masterChat = await page.context().newPage();
        const firstMap = firstLogin;
        const secondMap = secondLogin;
        const firstChat = await firstContext.newPage();
        const secondChat = await secondContext.newPage();
        const secondTraffic: string[] = [];
        const observedWebSocketUrls: string[] = [];
        trackTraffic(secondChat, secondTraffic, undefined, undefined, observedWebSocketUrls);
        await Promise.all([
            firstChat.goto(`/mesas/${roomId}`),
            secondChat.goto(`/mesas/${roomId}`),
            masterChat.goto(`/mesas/${roomId}`),
        ]);
        const browserBaseUrl = new URL(baseURL);
        const expectedWebSocketScheme = browserBaseUrl.protocol === 'https:' ? 'wss:' : 'ws:';
        await expect.poll(() => observedWebSocketUrls.some((socketUrl) => {
            const parsed = new URL(socketUrl);
            return parsed.protocol === expectedWebSocketScheme
                && parsed.host === browserBaseUrl.host
                && parsed.pathname.startsWith('/app/');
        }), { timeout: 15_000 }).toBe(true);
        if (browserBaseUrl.protocol === 'https:') {
            expect(await secondChat.evaluate(() => window.location.protocol)).toBe('https:');
            const insecureResources = await secondChat.evaluate(() =>
                performance.getEntriesByType('resource')
                    .map((entry) => entry.name)
                    .filter((url) => url.startsWith('http://')),
            );
            expect(insecureResources).toEqual([]);
            const sessionCookie = (await secondChat.context().cookies()).find((cookie) =>
                cookie.name === 'freecast_pilot_session',
            );
            expect(sessionCookie?.secure).toBe(true);
        }
        await firstMap.goto(`/mesas/${roomId}/mapas/${mapId}`);
        const secondMapNavigation = secondMap.waitForResponse((response) =>
            response.request().resourceType() === 'document'
            && new URL(response.url()).pathname === `/mesas/${roomId}/mapas/${mapId}`,
        );
        await secondMap.goto(`/mesas/${roomId}/mapas/${mapId}`);
        const secondMapDocument = await (await secondMapNavigation).text();
        expect(secondMapDocument).not.toContain('Segredo do mestre');
        await expect(secondMap.locator('.map-layer-list li').filter({ hasText: 'Somente mestre' })).toHaveCount(0);
        await expect(secondMap.getByTestId(`map-object-select-${greenTokenId}`)).toHaveCount(0);
        await expect(firstMap.getByTestId(`map-object-select-${blueTokenId}`)).toContainText('0, 0');
        await expect(firstMap.getByText('Você pode mover este token.')).toHaveCount(0);
        await firstMap.getByTestId(`map-object-select-${blueTokenId}`).click();
        await expect(firstMap.getByText('Você pode mover este token.')).toBeVisible();

        const forbiddenMove = await sendJsonFromPage(secondMap, 'PATCH', `/mesas/${roomId}/mapas/${mapId}/objetos/${blueTokenId}`, {
            x: 900,
            y: 650,
            expected_revision: 1,
            idempotency_key: `denied-${Date.now()}-move`,
        });
        expect(forbiddenMove.status, forbiddenMove.body).toBe(403);
        const forbiddenMapUpdate = await sendJsonFromPage(secondMap, 'PATCH', `/mesas/${roomId}/mapas/${mapId}`, {
            width: 1500,
            expected_revision: 1,
            idempotency_key: `denied-${Date.now()}-map`,
        });
        expect(forbiddenMapUpdate.status, forbiddenMapUpdate.body).toBe(403);

        const tokenStart = await canvasPoint(firstMap, 32, 32);
        const tokenDrop = await canvasPoint(firstMap, 182, 132);
        await dragWorldPoint(firstMap, tokenStart, tokenDrop);
        await expect(firstMap.getByTestId(`map-object-select-${blueTokenId}`)).toContainText('150, 100', { timeout: 15_000 });
        await expect(secondMap.getByTestId(`map-object-select-${blueTokenId}`)).toContainText('150, 100', { timeout: 15_000 });
        await expect(masterMap.getByTestId(`map-object-select-${blueTokenId}`)).toContainText('150, 100', { timeout: 15_000 });

        const firstMessage = `P1-SIM-${Date.now()}`;
        const secondMessage = `P2-SIM-${Date.now()}`;
        await Promise.all([
            firstChat.getByLabel('Digite uma mensagem').fill(firstMessage),
            secondChat.getByLabel('Digite uma mensagem').fill(secondMessage),
        ]);
        const sendBoth = (chat: Page) => chat.getByRole('button', { name: 'Enviar mensagem' }).click();
        await Promise.all([sendBoth(firstChat), sendBoth(secondChat)]);
        for (const chat of [masterChat, firstChat, secondChat]) {
            await expect(chat.locator('.chat-message').filter({ hasText: firstMessage })).toHaveCount(1);
            await expect(chat.locator('.chat-message').filter({ hasText: secondMessage })).toHaveCount(1);
        }

        const formattedBody = '**Forte** *ênfase* __sublinhado__ <img src=x onerror=alert(1)>';
        const messageEditor = firstChat.getByLabel('Digite uma mensagem');
        await messageEditor.fill('Forte');
        await messageEditor.press('Control+A');
        await firstChat.getByRole('button', { name: 'Negrito' }).click();
        await expect(messageEditor).toHaveValue('**Forte**');
        await messageEditor.fill(formattedBody);
        await firstChat.getByRole('button', { name: 'Enviar mensagem' }).click();
        const formattedParagraph = firstChat.locator('.chat-message-content p').filter({ hasText: 'Forte' });
        await expect(formattedParagraph.locator('strong')).toHaveText('Forte');
        await expect(formattedParagraph.locator('em')).toHaveText('ênfase');
        await expect(formattedParagraph.locator('u')).toHaveText('sublinhado');
        await expect(formattedParagraph.locator('img')).toHaveCount(0);
        await expect(formattedParagraph).toContainText('<img src=x onerror=alert(1)>');

        await firstChat.getByLabel('Digite uma mensagem').fill('/r 1d20 + 5');
        await firstChat.getByRole('button', { name: 'Enviar mensagem' }).click();
        const firstRoll = firstChat.locator('.chat-message-roll');
        await expect(firstRoll).toHaveCount(1);
        await expect(masterChat.locator('.chat-message-roll')).toHaveCount(1);
        await expect(secondChat.locator('.chat-message-roll')).toHaveCount(1);
        const rollText = (await firstRoll.innerText()).replace(/\s+/g, ' ').trim();
        expect((await masterChat.locator('.chat-message-roll').innerText()).replace(/\s+/g, ' ').trim()).toBe(rollText);
        expect((await secondChat.locator('.chat-message-roll').innerText()).replace(/\s+/g, ' ').trim()).toBe(rollText);

        await masterChat.getByLabel('Tipo da mensagem').selectOption('private');
        await masterChat.getByLabel('Destinatário da mensagem privada').selectOption({ label: 'Theo Arantes' });
        await masterChat.getByLabel('Digite uma mensagem').fill(privateMarker);
        await masterChat.getByRole('button', { name: 'Enviar mensagem' }).click();
        await expect(firstChat.locator('.chat-message').filter({ hasText: privateMarker })).toHaveCount(1);
        await expect(masterChat.locator('.chat-message').filter({ hasText: privateMarker })).toHaveCount(1);
        await expect(secondChat.locator('.chat-message').filter({ hasText: privateMarker })).toHaveCount(0);
        await expect.poll(() => secondTraffic.some((payload) => payload.includes(privateMarker))).toBe(false);

        await Promise.all([secondMap.close(), secondChat.close()]);
        const reconnectMessage = `RECUPERADO-${Date.now()}`;
        await firstChat.getByLabel('Digite uma mensagem').fill(reconnectMessage);
        await firstChat.getByRole('button', { name: 'Enviar mensagem' }).click();
        const nextTokenStart = await canvasPoint(firstMap, 182, 132);
        const nextTokenDrop = await canvasPoint(firstMap, 282, 232);
        await dragWorldPoint(firstMap, nextTokenStart, nextTokenDrop);
        await expect(firstMap.getByTestId(`map-object-select-${blueTokenId}`)).toContainText('250, 200', { timeout: 15_000 });

        const reconnectedChat = await secondContext.newPage();
        const reconnectedMap = await secondContext.newPage();
        let revokedSocketCount = 0;
        const reconnectedSocketTraffic: string[] = [];
        trackTraffic(reconnectedChat, secondTraffic, () => { revokedSocketCount += 1; }, reconnectedSocketTraffic);
        trackTraffic(reconnectedMap, secondTraffic, () => { revokedSocketCount += 1; });
        await Promise.all([
            reconnectedChat.goto(`/mesas/${roomId}`),
            reconnectedMap.goto(`/mesas/${roomId}/mapas/${mapId}`),
        ]);
        await expect(reconnectedChat.locator('.chat-message').filter({ hasText: reconnectMessage })).toHaveCount(1);
        await expect(reconnectedMap.getByTestId(`map-object-select-${blueTokenId}`)).toContainText('250, 200');

        await stopQueueWorker(queueWorker);
        queueWorker = null;
        const pendingMessage = `APOS-REINICIO-${Date.now()}`;
        await masterChat.getByLabel('Tipo da mensagem').selectOption('public');
        await masterChat.getByLabel('Digite uma mensagem').fill(pendingMessage);
        await masterChat.getByRole('button', { name: 'Enviar mensagem' }).click();
        await expect(masterChat.locator('.chat-message').filter({ hasText: pendingMessage })).toHaveCount(1);
        await page.waitForTimeout(1_200);
        expect(reconnectedSocketTraffic.some((frame) => frame.includes(pendingMessage))).toBe(false);

        queueWorker = startQueueWorker();
        await page.waitForTimeout(1_000);
        expect(queueWorker.exitCode).toBeNull();
        await expect(reconnectedChat.locator('.chat-message').filter({ hasText: pendingMessage })).toHaveCount(1, { timeout: 15_000 });
        await expect.poll(() => reconnectedSocketTraffic.some((frame) => frame.includes(pendingMessage)), { timeout: 15_000 }).toBe(true);

        const removedUserId = await readNumericPageProp(reconnectedChat, 'user_id');
        const blueAssetId = await readTokenAssetId(reconnectedMap, 'Token azul');
        expect(removedUserId).toBeGreaterThan(0);
        expect(blueAssetId).toBeGreaterThan(0);
        const xsrfToken = await masterMap.evaluate(() => {
            const cookie = document.cookie.split('; ').find((item) => item.startsWith('XSRF-TOKEN='));
            return cookie ? decodeURIComponent(cookie.slice('XSRF-TOKEN='.length)) : null;
        });
        const revokeResponse = await masterMap.context().request.delete(
            new URL(`/mesas/${roomId}/participantes/${removedUserId}`, baseURL).toString(),
            {
                maxRedirects: 0,
                headers: {
                    Accept: 'application/json',
                    ...(xsrfToken ? { 'X-XSRF-TOKEN': xsrfToken } : {}),
                },
            },
        );
        expect(revokeResponse.status()).toBe(302);
        await expect.poll(() => revokedSocketCount, { timeout: 15_000 }).toBeGreaterThan(0);
        const revokedAssetStatus = await reconnectedMap.evaluate(async (assetUrl) => (await fetch(assetUrl)).status,
            `/mesas/${roomId}/recursos/${blueAssetId}`);
        expect(revokedAssetStatus).toBe(403);
        const revokedHistoryStatus = await reconnectedChat.evaluate(async (historyUrl) =>
            (await fetch(historyUrl, { headers: { Accept: 'application/json' } })).status,
        `/mesas/${roomId}/eventos`);
        expect(revokedHistoryStatus).toBe(403);
    } finally {
        if (queueWorker) await stopQueueWorker(queueWorker);
        await Promise.all(contexts.map((context) => context.close()));
    }
});
