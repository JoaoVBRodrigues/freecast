import { expect, test, type Page } from '@playwright/test';

async function signIn(page: Page, email: string) {
    await page.goto('/login');
    await page.getByLabel('E-mail').fill(email);
    await page.getByLabel('Senha', { exact: true }).fill('Mesa2026!');
    await page.getByRole('button', { name: 'Entrar', exact: true }).click();
    await expect(page.getByRole('heading', { name: 'Início' })).toBeVisible();
}

async function api(page: Page, url: string, body?: Record<string, unknown>) {
    return page.evaluate(async ({ url, body }) => {
        const cookie = document.cookie.split('; ').find(c => c.startsWith('XSRF-TOKEN='));
        const response = await fetch(url, { method: body ? 'POST' : 'GET', headers: {
            Accept: 'application/json', 'Content-Type': 'application/json',
            ...(cookie ? { 'X-XSRF-TOKEN': decodeURIComponent(cookie.slice(11)) } : {}),
        }, ...(body ? { body: JSON.stringify(body) } : {}) });
        return { status: response.status, body: await response.json() };
    }, { url, body });
}

test('4A: sheets, private payloads, linked token, combat and reconnect with three contexts', async ({ browser, page: master }) => {
    test.setTimeout(180_000);
    const contexts = [await browser.newContext(), await browser.newContext()];
    const player = await contexts[0].newPage();
    const third = await contexts[1].newPage();
    const title = `Parte4A ${Date.now()}`;
    try {
        await signIn(master, 'mestre@freecast.test');
        await master.getByRole('link', { name: 'Nova mesa' }).first().click();
        await master.getByLabel('Nome da mesa').fill(title);
        await master.getByLabel('Sistema').fill('Sistema próprio');
        await master.getByRole('button', { name: 'Criar mesa', exact: true }).last().click();
        await expect(master.getByRole('button', { name: 'Novo personagem' })).toBeVisible();
        const roomId = new URL(master.url()).pathname.split('/').at(-1)!;
        for (const [page, email] of [[player, 'jogador@freecast.test'], [third, 'convidada@freecast.test']] as const) {
            await signIn(page, email);
            await page.goto('/mesas');
            await page.locator('.room-card').filter({ hasText: title }).getByRole('button', { name: 'Entrar na mesa' }).click();
            await expect(page.getByRole('link', { name: `Mesa: ${title}` })).toBeVisible();
        }
        const thirdTraffic: string[] = [];
        third.on('websocket', socket => socket.on('framereceived', frame => thirdTraffic.push(String(frame.payload))));
        await third.reload();
        await master.goto(`/mesas/${roomId}/mapas`);
        await master.getByLabel('Nome do recurso').fill('Retrato sintético 4A');
        await master.getByLabel('Tipo de recurso').selectOption('token');
        await master.locator('input[type=file]').setInputFiles({ name: 'portrait.png', mimeType: 'image/png', buffer: Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aWQAAAABJRU5ErkJggg==', 'base64') });
        await master.getByRole('button', { name: 'Enviar', exact: true }).click();
        await expect(master.locator('.asset-library-card').filter({ hasText: 'Retrato sintético 4A' })).toBeVisible();
        await master.getByLabel('Nome do mapa').fill('Campo 4A');
        await master.getByRole('button', { name: 'Criar mapa', exact: true }).click();
        await expect(master.getByRole('button', { name: 'Adicionar token Retrato sintético 4A ao mapa' })).toBeVisible();
        await master.getByRole('button', { name: 'Adicionar token Retrato sintético 4A ao mapa' }).click();
        await master.locator('.map-object-list button').filter({ hasText: 'Retrato sintético 4A' }).first().click();
        await master.getByRole('button', { name: 'Novo personagem' }).click();
        await master.getByLabel('Nome do personagem').fill('Aurora 4A');
        await master.getByLabel('PV máximo').fill('20');
        await master.getByLabel('PV atual').fill('18');
        await master.getByLabel('Anotações privadas').fill('SEGREDO-PRIVADO-4A');
        await master.getByLabel('Jogador responsável').selectOption({ label: 'Theo Arantes' });
        await master.getByLabel('Retrato da biblioteca').selectOption({ label: 'Retrato sintético 4A' });
        await master.getByLabel('Nome e PV visíveis à mesa').check();
        await master.getByRole('button', { name: 'Adicionar atributo', exact: true }).click();
        await master.getByLabel('Nome do atributo').fill('Força');
        await master.getByLabel('Valor do atributo').fill('12');
        await master.getByRole('button', { name: 'Adicionar recurso', exact: true }).click();
        await master.getByLabel('Nome do recurso').fill('Mana');
        await master.getByLabel('Recurso máximo', { exact: true }).fill('5');
        await master.getByLabel('Recurso atual', { exact: true }).fill('3');
        await master.getByRole('button', { name: 'Salvar personagem' }).click();
        const hero = master.locator('.character-list article').filter({ hasText: 'Aurora 4A' });
        await expect(hero).toContainText('PV 18/20');
        await hero.getByRole('button', { name: 'Vincular token', exact: true }).click();
        await expect(hero.getByRole('button', { name: 'Desvincular token' })).toBeVisible();
        await expect(master.locator('.map-object-list')).toContainText('Aurora 4A · PV 18/20');
        await expect(player.locator('.character-list')).toContainText('Aurora 4A');
        await expect(third.locator('.character-list')).toContainText('PV 18/20');
        const snapshotUrl = `/mesas/${roomId}/personagens`;
        let snapshot = (await api(master, snapshotUrl)).body;
        const heroId = snapshot.characters[0].id;
        const summary = (await api(third, snapshotUrl)).body.characters[0];
        expect(summary).not.toHaveProperty('notes');
        expect(summary).not.toHaveProperty('attributes');
        expect(summary).not.toHaveProperty('resources');
        expect((await api(third, `/mesas/${roomId}/personagens/comandos`, { action: 'update', character_id: heroId, expected_revision: snapshot.characters[0].revision, hp_current: 1, idempotency_key: crypto.randomUUID() })).status).toBe(403);
        await player.getByRole('button', { name: 'Aurora 4A', exact: true }).click();
        await expect(player.getByLabel('Anotações privadas')).toHaveValue('SEGREDO-PRIVADO-4A');
        await player.getByLabel('PV atual').fill('11');
        await player.getByRole('button', { name: 'Salvar personagem' }).click();
        await expect(hero).toContainText('PV 11/20');
        await expect(third.locator('.character-list')).toContainText('PV 11/20');
        await expect(master.locator('.map-object-list')).toContainText('PV 11/20');
        for (const [name, visible] of [['Batedor 4A', true], ['NPC-OCULTO-4A', false]] as const) {
            await master.getByRole('button', { name: 'Novo personagem' }).click();
            await master.getByLabel('Nome do personagem').fill(name);
            if (visible) await master.getByLabel('Nome e PV visíveis à mesa').check();
            await master.getByRole('button', { name: 'Salvar personagem' }).click();
            await expect(master.locator('.character-list')).toContainText(name);
        }
        expect(JSON.stringify((await api(third, snapshotUrl)).body)).not.toContain('NPC-OCULTO-4A');
        expect(JSON.stringify((await api(third, `/mesas/${roomId}/eventos`)).body)).not.toContain('SEGREDO-PRIVADO-4A');
        await master.getByLabel('Personagem no combate').selectOption({ label: 'Aurora 4A' });
        await master.getByLabel('Iniciativa', { exact: true }).fill('10');
        await master.getByRole('button', { name: 'Adicionar ao combate' }).click();
        await expect(master.locator('.combat-list')).toContainText('Aurora 4A');
        await master.getByLabel('Personagem no combate').selectOption({ label: 'Batedor 4A' });
        await master.getByRole('button', { name: 'Adicionar ao combate' }).click();
        await expect(master.locator('.combat-list li')).toHaveCount(2);
        await master.getByRole('button', { name: 'Iniciar combate', exact: true }).click();
        await expect(master.locator('.combat-list li.active')).toContainText('Aurora 4A');
        snapshot = (await api(master, snapshotUrl)).body;
        const next = { action: 'next', expected_revision: snapshot.combat.revision, idempotency_key: crypto.randomUUID() };
        expect((await api(master, `/mesas/${roomId}/combate/comandos`, next)).status).toBe(200);
        expect((await api(master, `/mesas/${roomId}/combate/comandos`, next)).body.replayed).toBe(true);
        await expect(master.locator('.combat-list li.active')).toContainText('Batedor 4A');
        expect((await api(master, `/mesas/${roomId}/combate/comandos`, { ...next, idempotency_key: crypto.randomUUID() })).status).toBe(409);
        await master.getByRole('button', { name: 'Próximo turno', exact: true }).click();
        await expect(third.getByText('Combate · Rodada 2', { exact: true })).toBeVisible();
        await contexts[0].setOffline(true);
        snapshot = (await api(master, snapshotUrl)).body;
        expect((await api(master, `/mesas/${roomId}/personagens/comandos`, { action: 'update', character_id: heroId, expected_revision: snapshot.characters[0].revision, hp_current: 9, idempotency_key: crypto.randomUUID() })).status).toBe(200);
        await expect(third.locator('.character-list')).toContainText('PV 9/20');
        await contexts[0].setOffline(false);
        await expect(player.locator('.character-list')).toContainText('PV 9/20', { timeout: 20000 });
        await player.reload();
        await expect(player.locator('.character-list')).toContainText('PV 9/20');
        await master.reload();
        await expect(master.locator('.map-object-list')).toContainText('Aurora 4A · PV 9/20');
        await player.goto(master.url());
        await expect(player.locator('.map-object-list')).toContainText('Aurora 4A · PV 9/20');
        await player.getByRole('button', { name: 'Aurora 4A', exact: true }).click();
        await expect(player.getByLabel('Anotações privadas')).toBeVisible();
        snapshot = (await api(master, snapshotUrl)).body;
        expect((await api(master, `/mesas/${roomId}/personagens/comandos`, { action: 'update', character_id: heroId, expected_revision: snapshot.characters[0].revision, assigned_user_id: null, idempotency_key: crypto.randomUUID() })).status).toBe(200);
        await expect(player.locator('.character-form')).toHaveCount(0);
        expect((await api(player, snapshotUrl)).body.characters[0]).not.toHaveProperty('notes');
        expect(thirdTraffic.join('\n')).not.toContain('SEGREDO-PRIVADO-4A');
        expect(thirdTraffic.join('\n')).not.toContain('NPC-OCULTO-4A');
        snapshot = (await api(master, snapshotUrl)).body;
        // Empirical CSRF proof on an authenticated mutating 4A endpoint.
        const csrfCommand = { action: 'update', character_id: heroId, expected_revision: snapshot.characters[0].revision, hp_current: 8, idempotency_key: crypto.randomUUID() };
        for (const token of [null, 'invalid-csrf-token']) {
            const status = await master.evaluate(async ({ url, body, token }) => {
                const response = await fetch(url, { method: 'POST', headers: {
                    Accept: 'application/json', 'Content-Type': 'application/json',
                    ...(token ? { 'X-CSRF-TOKEN': token } : {}),
                }, body: JSON.stringify(body) });
                return response.status;
            }, { url: `/mesas/${roomId}/personagens/comandos`, body: csrfCommand, token });
            expect(status).toBe(419);
            expect((await api(master, snapshotUrl)).body).toEqual(snapshot);
        }
        expect((await api(master, `/mesas/${roomId}/personagens/comandos`, csrfCommand)).status).toBe(200);
        snapshot = (await api(master, snapshotUrl)).body;
        expect(snapshot.characters[0].hp_current).toBe(8);
        expect((await api(master, `/mesas/${roomId}/personagens/comandos`, {
            action: 'update', character_id: heroId, expected_revision: snapshot.characters[0].revision, hp_current: 9, idempotency_key: crypto.randomUUID(),
        })).status).toBe(200);
        snapshot = (await api(master, snapshotUrl)).body;
        await master.screenshot({ path: test.info().outputPath('parte4a-personagens-combate.png') });
    } finally { await Promise.all(contexts.map(c => c.close())); }
});
