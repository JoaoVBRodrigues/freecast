import {test, expect, type Page} from '@playwright/test';
import path from 'node:path';
import {waitForLoginResponse} from './csrf-diagnostics';

async function login(p: Page, email: string) {
    await p.goto('/login');
    await p.getByLabel('E-mail').fill(email);
    await p.getByLabel('Senha', {exact: true}).fill('Mesa2026!');
    expect((await waitForLoginResponse(p)).status()).toBe(302);
    await expect(p.getByRole('heading', {name: 'Início'})).toBeVisible({timeout: 15000});
}
async function api(p: Page, url: string, body?: Record<string, unknown>, method = body ? 'POST' : 'GET', csrf = 'valid') {
    return p.evaluate(async ({url, body, method, csrf}) => {
        const c = document.cookie.split('; ').find(c => c.startsWith('XSRF-TOKEN='));
        const inertia = /\/mapas\/\d+$/.test(url) && method === 'GET';
        const r = await fetch(url, {method, headers: {
            Accept: 'application/json', 'Content-Type': 'application/json',
            ...(inertia ? {'X-Inertia': 'true', 'X-Requested-With': 'XMLHttpRequest', 'X-Inertia-Version': JSON.parse(document.getElementById('app')!.getAttribute('data-page')!).version} : {}),
            ...(csrf === 'valid' && c ? {'X-XSRF-TOKEN': decodeURIComponent(c.slice(11))} : csrf === 'invalid' ? {'X-CSRF-TOKEN': 'invalid'} : {}),
        }, ...(body ? {body: JSON.stringify(body)} : {})});
        const text = await r.text();
        return {status: r.status, body: text.startsWith('{') ? JSON.parse(text) : text};
    }, {url, body, method, csrf});
}
async function gesture(p: Page, name: string, a: number[], b: number[]) {
    await p.getByRole('button', {name, exact: true}).click();
    await drag(p, a, b);
}
async function drag(p: Page, a: number[], b: number[]) {
    const host = await p.locator('.map-canvas-host').boundingBox();
    if (!host) throw new Error('Canvas missing');
    const root = p.locator('.map-editor-page');
    const x = Number(await root.getAttribute('data-camera-x')), y = Number(await root.getAttribute('data-camera-y')), scale = Number(await root.getAttribute('data-camera-scale'));
    await p.mouse.move(host.x + 1 + x + a[0]*scale, host.y + 1 + y + a[1]*scale);
    await p.mouse.down();
    await p.mouse.move(host.x + 1 + x + b[0]*scale, host.y + 1 + y + b[1]*scale, {steps: 8});
    await p.mouse.up();
}

test('4D: one integrated session with import, sheets, tokens, chat, tools, fog and combat', async ({browser, page: master}) => {
    test.setTimeout(300_000);
    master.setDefaultTimeout(30_000);
    const contexts = [await browser.newContext(), await browser.newContext()];
    for (const context of contexts) context.setDefaultTimeout(30_000);
    const players = await Promise.all(contexts.map(c => c.newPage()));
    players.forEach(p => p.setDefaultTimeout(30_000));
    const traffic: string[] = [];
    const title = `Integrada4D ${Date.now()}`;
    try {
        await login(master, 'mestre@freecast.test');
        await master.screenshot({path: test.info().outputPath('inicio-867.png')});
        await master.setViewportSize({width: 1440, height: 900});
        await master.screenshot({path: test.info().outputPath('inicio-1440.png')});
        await master.setViewportSize({width: 867, height: 465});
        await master.getByRole('link', {name: 'Nova mesa'}).first().click();
        await master.getByLabel('Nome da mesa').fill(title);
        await master.getByLabel('Sistema').fill('Sintético próprio');
        await master.getByRole('button', {name: 'Criar mesa', exact: true}).last().click();
        await expect(master.getByRole('button', {name: 'Novo personagem'})).toBeVisible();
        const rid = new URL(master.url()).pathname.split('/').at(-1)!;
        const room = `/mesas/${rid}`, library = `${room}/mapas`, sheets = `${room}/personagens`, commands = `${sheets}/comandos`;
        for (const [i, email] of ['jogador@freecast.test', 'convidada@freecast.test'].entries()) {
            await login(players[i], email);
            await players[i].goto('/mesas');
            await players[i].locator('.room-card').filter({hasText: title}).getByRole('button', {name: 'Entrar na mesa'}).click();
            await expect(players[i].getByRole('link', {name: `Mesa: ${title}`})).toBeVisible();
        }
        players[1].on('websocket', s => s.on('framereceived', f => traffic.push(String(f.payload))));
        await players[1].reload();
        await master.goto(library);
        await master.getByLabel('Nome do recurso').fill('Token integrado');
        await master.getByLabel('Tipo de recurso').selectOption('token');
        await master.locator('input[type=file]').setInputFiles({name: 'token.png', mimeType: 'image/png', buffer: Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aWQAAAABJRU5ErkJggg==', 'base64')});
        await master.getByRole('button', {name: 'Enviar', exact: true}).click();
        await expect(master.locator('.asset-library-card')).toHaveCount(1);
        await master.getByRole('button', {name: 'Importar dd2vtt', exact: true}).click();
        await master.getByLabel('Arquivo dd2vtt', {exact: true}).setInputFiles(path.resolve('tests/Fixtures/dd2vtt/geometria.dd2vtt'));
        await master.getByRole('button', {name: 'Validar e preparar prévia'}).click();
        await expect(master.getByTestId('dd2vtt-preview')).toContainText('1 paredes · 1 portais · 1 luzes');
        await expect(master.getByRole('button', {name: 'Confirmar importação e abrir novo mapa'})).toBeEnabled();
        await master.getByRole('button', {name: 'Confirmar importação e abrir novo mapa'}).click();
        await expect(master.locator('.map-editor-page')).toBeVisible();
        const map = new URL(master.url()).pathname, tools = `${map}/ferramentas`;
        let state = (await api(master, map)).body.props;
        expect(state.map).toMatchObject({width: 200, height: 200, cell_size: 50, fog_enabled: true});
        expect(state.import_details.walls).toHaveLength(1);
        expect(await master.evaluate(async u => {const r = await fetch(u); const b = await createImageBitmap(await r.blob()); return [r.status, b.width, b.height];}, state.map.background_url)).toEqual([200, 200, 200]);
        expect((await api(master, `${map}/ativar`, {idempotency_key: crypto.randomUUID()})).status).toBe(200);
        await master.locator('.map-tools summary').click();
        master.once('dialog', d => d.accept());
        await master.getByRole('button', {name: 'Revelar tudo', exact: true}).click();
        await master.getByRole('button', {name: 'Adicionar token Token integrado ao mapa'}).click();
        await master.locator('.map-object-list button').first().click();
        await master.getByLabel('Jogador autorizado a mover o token').selectOption({label: 'Theo Arantes'});
        await master.getByRole('button', {name: 'Novo personagem'}).click();
        await master.getByLabel('Nome do personagem').fill('Aurora integrada');
        await master.getByLabel('PV máximo').fill('20');
        await master.getByLabel('PV atual').fill('18');
        await master.getByLabel('Anotações privadas').fill('NOTA-SECRETA-INTEGRADA');
        await master.getByLabel('Jogador responsável').selectOption({label: 'Theo Arantes'});
        await master.getByLabel('Nome e PV visíveis à mesa').check();
        await master.getByRole('button', {name: 'Salvar personagem'}).click();
        const hero = master.locator('.character-list article').filter({hasText: 'Aurora integrada'});
        await expect(hero).toContainText('PV 18/20');
        await master.locator('.map-object-list button').first().click();
        const linked = master.waitForResponse(r => r.url().endsWith(commands) && r.request().method() === 'POST' && r.request().postDataJSON()?.action === 'link', {timeout: 30_000});
        await hero.getByRole('button', {name: 'Vincular token', exact: true}).click();
        expect((await linked).status()).toBe(200);
        await expect(master.locator('.map-object-list')).toContainText('PV 18/20');
        for (const p of players) { await p.goto(map); await expect(p.locator('.map-object-list')).toContainText('PV 18/20'); }
        state = (await api(master, map)).body.props;
        const token = state.layers.flatMap((l: any) => l.objects)[0];
        await players[0].getByTestId(`map-object-select-${token.id}`).click();
        const centre = [token.x + token.width/2, token.y + token.height/2];
        await drag(players[0], centre, [centre[0]+50, centre[1]+50]);
        for (const p of [master, ...players]) await expect(p.getByTestId(`map-object-select-${token.id}`)).toContainText('50, 50');
        await players[0].getByRole('button', {name: 'Aurora integrada', exact: true}).click();
        await players[0].getByLabel('PV atual').fill('12');
        await players[0].getByRole('button', {name: 'Salvar personagem'}).click();
        for (const p of [master, ...players]) await expect(p.locator('.map-object-list')).toContainText('PV 12/20');
        let snapshot = (await api(master, sheets)).body;
        const hid = snapshot.characters[0].id;
        expect((await api(players[1], sheets)).body.characters[0]).not.toHaveProperty('notes');
        expect((await api(players[1], commands, {action: 'update', character_id: hid, expected_revision: snapshot.characters[0].revision, hp_current: 1, idempotency_key: crypto.randomUUID()})).status).toBe(403);
        const cmd = {action: 'update', character_id: hid, expected_revision: snapshot.characters[0].revision, hp_current: 11, idempotency_key: crypto.randomUUID()};
        for (const csrf of ['none', 'invalid']) {expect((await api(master, commands, cmd, 'POST', csrf)).status).toBe(419); expect((await api(master, sheets)).body).toEqual(snapshot);}
        expect((await api(master, commands, cmd)).status).toBe(200);
        expect((await api(master, commands, cmd)).body.replayed).toBe(true);
        expect((await api(master, commands, {...cmd, idempotency_key: crypto.randomUUID()})).status).toBe(409);
        await expect(players[1].locator('.map-object-list')).toContainText('PV 11/20');
        const chats = [await master.context().newPage(), await contexts[0].newPage(), await contexts[1].newPage()];
        chats[2].on('websocket', s => s.on('framereceived', f => traffic.push(String(f.payload))));
        await Promise.all(chats.map(p => p.goto(room)));
        await chats[0].getByLabel('Digite uma mensagem').fill('Mensagem pública integrada');
        await chats[0].getByRole('button', {name: 'Enviar mensagem'}).click();
        for (const p of chats) await expect(p.locator('.chat-message')).toContainText(['Mensagem pública integrada']);
        await chats[0].getByLabel('Tipo da mensagem').selectOption('private');
        await chats[0].getByLabel('Destinatário da mensagem privada').selectOption({label: 'Theo Arantes'});
        await chats[0].getByLabel('Digite uma mensagem').fill('PRIVADO-INTEGRADO');
        await chats[0].getByRole('button', {name: 'Enviar mensagem'}).click();
        await expect(chats[1].locator('.chat-message').filter({hasText: 'PRIVADO-INTEGRADO'})).toHaveCount(1);
        await expect(chats[2].locator('.chat-message').filter({hasText: 'PRIVADO-INTEGRADO'})).toHaveCount(0);
        await chats[1].getByLabel('Digite uma mensagem').fill('/r 1d20 + 2');
        await chats[1].getByRole('button', {name: 'Enviar mensagem'}).click();
        for (const p of chats) await expect(p.locator('.chat-message-roll')).toHaveCount(1);
        const rolled = await chats[1].locator('.chat-message-roll').innerText();
        for (const p of [chats[0], chats[2]]) expect(await p.locator('.chat-message-roll').innerText()).toBe(rolled);
        await chats[0].getByLabel('Nome da nova macro').fill('SaudacaoIntegrada');
        await chats[0].getByLabel('Ações da nova macro').fill('MACRO-INTEGRADA');
        await chats[0].getByLabel('Escopo da nova macro').selectOption('shared_room');
        await chats[0].getByRole('button', {name: 'Criar macro'}).click();
        await chats[1].getByRole('button', {name: 'Executar macro SaudacaoIntegrada'}).click();
        for (const p of chats) await expect(p.locator('.chat-message').filter({hasText: 'MACRO-INTEGRADA'})).toHaveCount(1);
        // Grid-aligned endpoints avoid fractional screen-pixel rounding at 83% zoom.
        await master.getByLabel('Encaixe na grade').check();
        await gesture(master, 'Régua', [50,100], [150,100]);
        await expect(master.getByTestId('ruler-measurement')).toContainText('2 células');
        await master.getByLabel('Encaixe na grade').uncheck();
        await gesture(master, 'Linha', [20,140], [100,140]);
        for (const p of [master, ...players]) await expect(p.locator('.map-editor-page')).toHaveAttribute('data-drawing-count', '1');
        state = (await api(master, map)).body.props;
        await master.getByTestId(`drawing-select-${state.drawings[0].id}`).click();
        await master.getByRole('button', {name: 'Remover desenho'}).click();
        for (const p of players) await expect(p.locator('.map-editor-page')).toHaveAttribute('data-drawing-count', '0');
        await gesture(master, 'Ocultar região', [0,0], [127,127]);
        for (const p of players) {
            await expect(p.getByTestId(`map-object-select-${token.id}`)).toHaveCount(0);
            expect((await api(p, sheets)).body.characters).toEqual([]);
            const payload = JSON.stringify((await api(p, map)).body);
            expect(payload).not.toContain('Aurora integrada'); expect(payload).not.toContain('NOTA-SECRETA-INTEGRADA'); expect(payload).not.toContain('ffcc88ff');
        }
        state = (await api(master, map)).body.props;
        expect((await api(players[0], tools, {action:'fog', mode:'disable', expected_revision:state.map.revision, idempotency_key:crypto.randomUUID()})).status).toBe(403);
        await gesture(master, 'Revelar região', [0,0], [127,127]);
        for (const p of players) await expect(p.getByTestId(`map-object-select-${token.id}`)).toBeVisible();
        for (const name of ['Aurora integrada', 'Batedor integrado']) {
            if (name !== 'Aurora integrada') {await master.getByRole('button', {name:'Novo personagem'}).click(); await master.getByLabel('Nome do personagem').fill(name); await master.getByLabel('Nome e PV visíveis à mesa').check(); await master.getByRole('button', {name:'Salvar personagem'}).click();}
            await master.getByLabel('Personagem no combate').selectOption({label:name}); await master.getByLabel('Iniciativa', {exact:true}).fill(name === 'Aurora integrada' ? '10':'5'); await master.getByRole('button', {name:'Adicionar ao combate'}).click();
        }
        await master.getByRole('button', {name:'Iniciar combate', exact:true}).click();
        await master.getByRole('button', {name:'Próximo turno', exact:true}).click();
        await master.getByRole('button', {name:'Próximo turno', exact:true}).click();
        for (const p of players) await expect(p.getByText('Combate · Rodada 2', {exact:true})).toBeVisible();
        const ended = master.waitForResponse(r => r.url().endsWith(`${room}/combate/comandos`) && r.request().method() === 'POST' && r.request().postDataJSON()?.action === 'end');
        await master.getByRole('button', {name:'Encerrar combate', exact:true}).click();
        expect((await ended).status()).toBe(200);
        for (const p of [master, ...players, ...chats]) {
            await expect(p.locator('.character-panel')).toContainText('Combate · Rodada 0');
            await expect(p.locator('.character-panel')).toContainText('Encerrado');
            await expect(p.locator('.character-panel [role=alert]')).toHaveCount(0);
            expect((await api(p, sheets)).body.combat).toMatchObject({active: false, round: 0, active_id: null});
        }
        await contexts[0].setOffline(true);
        snapshot = (await api(master, sheets)).body;
        const heroState = snapshot.characters.find((c:any)=>c.id===hid);
        expect((await api(master, commands, {action:'update', character_id:hid, expected_revision:heroState.revision, hp_current:9, idempotency_key:crypto.randomUUID()})).status).toBe(200);
        await expect(players[1].locator('.map-object-list')).toContainText('PV 9/20');
        await contexts[0].setOffline(false);
        await expect(players[0].locator('.map-object-list')).toContainText('PV 9/20', {timeout:25000});
        for (const p of [master, ...players]) {await p.reload(); await expect(p.locator('.map-object-list')).toContainText('PV 9/20');}
        await players[0].getByRole('button', {name:'Aurora integrada', exact:true}).click();
        snapshot = (await api(master, sheets)).body;
        expect((await api(master, commands, {action:'update', character_id:hid, expected_revision:snapshot.characters.find((c:any)=>c.id===hid).revision, assigned_user_id:null, idempotency_key:crypto.randomUUID()})).status).toBe(200);
        await expect(players[0].locator('.character-form')).toHaveCount(0);
        expect((await api(players[0], sheets)).body.characters.find((c:any)=>c.id===hid)).not.toHaveProperty('notes');
        for (const p of players) {
            const events=(await api(p, `${room}/eventos`)).body.events;
            for(const e of events.filter((e:any)=>e.type==='map.contents.changed')) {expect(e.entityId).toBeNull(); expect(Object.keys(e.payload)).toEqual(['map_id']);}
            expect(JSON.stringify(events)).not.toContain('NOTA-SECRETA-INTEGRADA');
            expect(JSON.stringify(events)).not.toContain('ffcc88ff');
        }
        expect(traffic.join('\n')).not.toContain('NOTA-SECRETA-INTEGRADA');
        expect(traffic.join('\n')).not.toContain('PRIVADO-INTEGRADO');
        expect(traffic.join('\n')).not.toContain('ffcc88ff');
        await master.getByRole('button', {name:'Enquadrar mapa'}).click();
        for (const size of [{width:867,height:465},{width:1440,height:900}]) {
            await master.setViewportSize(size); await master.screenshot({path:test.info().outputPath(`mapa-${size.width}.png`)});
            await chats[0].setViewportSize(size); await chats[0].screenshot({path:test.info().outputPath(`mesa-${size.width}.png`)});
        }
    } finally {await Promise.all(contexts.map(c=>c.close()));}
});
