import { test, expect, type Page } from '@playwright/test';

async function login(page: Page, email: string) {
    await page.goto('/login');
    await page.getByLabel('E-mail').fill(email);
    await page.getByLabel('Senha', { exact: true }).fill('Mesa2026!');
    await page.getByRole('button', { name: 'Entrar', exact: true }).click();
    await expect(page.getByRole('heading', { name: 'Início' })).toBeVisible();
}
async function api(page: Page, url: string, body?: Record<string, unknown>, method = body ? 'POST' : 'GET', csrf = 'valid') {
    return page.evaluate(async ({ url, body, method, csrf }) => {
        const cookie = document.cookie.split('; ').find(c => c.startsWith('XSRF-TOKEN='));
        const response = await fetch(url, { method, headers: {
            Accept: 'application/json', 'Content-Type': 'application/json',
            ...(url.match(/\/mapas\/\d+$/) && (method === 'GET' || method === 'DELETE') ? { 'X-Inertia': 'true', 'X-Requested-With': 'XMLHttpRequest', 'X-Inertia-Version': JSON.parse(document.getElementById('app')!.getAttribute('data-page')!).version } : {}),
            ...(csrf === 'valid' && cookie ? { 'X-XSRF-TOKEN': decodeURIComponent(cookie.slice(11)) } : csrf === 'invalid' ? { 'X-CSRF-TOKEN': 'invalid' } : {}),
        }, ...(body ? { body: JSON.stringify(body) } : {}) });
        const text = await response.text();
        return { status: response.status, body: text.startsWith('{') ? JSON.parse(text) : text };
    }, { url, body, method, csrf });
}
async function camera(page: Page) {
    const root = page.locator('.map-editor-page');
    const host = await page.locator('.map-canvas-host').boundingBox();
    if (!host) throw new Error('No canvas');
    return { x: Number(await root.getAttribute('data-camera-x')), y: Number(await root.getAttribute('data-camera-y')), scale: Number(await root.getAttribute('data-camera-scale')), host };
}
async function gesture(page: Page, name: string, a: [number,number], b: [number,number], freehand = false) {
    await page.getByRole('button', { name, exact: true }).click();
    const c = await camera(page);
    const screen = ([x,y]: [number,number]) => [c.host.x + 1 + c.x + x*c.scale, c.host.y + 1 + c.y + y*c.scale];
    const [ax,ay] = screen(a), [bx,by] = screen(b);
    await page.mouse.move(ax,ay); await page.mouse.down();
    if (freehand) await page.mouse.move((ax+bx)/2,ay+3, { steps: 5 });
    await page.mouse.move(bx,by,{ steps: 6 }); await page.mouse.up();
}
async function props(page: Page, url: string) { const response=await api(page,url); if (!response.body.props) throw new Error('Invalid Inertia snapshot: '+response.status+' '+JSON.stringify(response.body).slice(0,400)); return response.body.props; }

test('4B: ruler world coordinates, drawings and server fog with master and two players, active CSRF', async ({ browser, page: master }) => {
    test.setTimeout(240_000);
    const contexts = [await browser.newContext(),await browser.newContext()];
    const player = await contexts[0].newPage(), third = await contexts[1].newPage();
    const title = `Parte4B ${Date.now()}`;
    try {
        await login(master,'mestre@freecast.test');
        await master.getByRole('link',{ name:'Nova mesa' }).first().click();
        await master.getByLabel('Nome da mesa').fill(title); await master.getByLabel('Sistema').fill('Próprio');
        await master.getByRole('button',{ name:'Criar mesa',exact:true }).last().click();
        await expect(master.getByRole('button',{ name:'Novo personagem' })).toBeVisible();
        const roomId = new URL(master.url()).pathname.split('/').at(-1)!;
        for (const [p,email] of [[player,'jogador@freecast.test'],[third,'convidada@freecast.test']] as const) {
            await login(p,email); await p.goto('/mesas');
            await p.locator('.room-card').filter({ hasText:title }).getByRole('button',{ name:'Entrar na mesa' }).click();
            await expect(p.getByRole('link',{ name:`Mesa: ${title}` })).toBeVisible();
        }
        await master.goto(`/mesas/${roomId}/mapas`);
        await master.getByLabel('Nome do recurso').fill('Template sintético');
        await master.getByLabel('Tipo de recurso').selectOption('token');
        await master.locator('input[type=file]').setInputFiles({ name:'token.png',mimeType:'image/png',buffer:Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aWQAAAABJRU5ErkJggg==','base64') });
        await master.getByRole('button',{ name:'Enviar',exact:true }).click();
        await expect(master.locator('.asset-library-card')).toHaveCount(1);
        await master.getByLabel('Nome do mapa').fill('Campo 4B');
        await master.getByLabel('Largura do mapa').fill('800'); await master.getByLabel('Altura do mapa').fill('600');
        await master.getByRole('button',{ name:'Criar mapa',exact:true }).click();
        await expect(master.locator('.map-editor-page')).toBeVisible();
        const url = new URL(master.url()).pathname, toolsUrl = `${url}/ferramentas`;
        await master.getByRole('button',{ name:'Adicionar token Template sintético ao mapa' }).click();
        await expect(master.locator('.map-object-list-section button')).toHaveCount(1);
        let state = await props(master,url);
        const token = state.layers.flatMap((l:any)=>l.objects)[0];
        // Use the real pointer tool; visual camera coordinates are read without changing app state.
        await gesture(master,'Régua',[50,50],[200,250]);
        await expect(master.getByTestId('ruler-measurement')).toHaveText('5 células · 7.5 m');
        await master.getByRole('button',{ name:'Aumentar zoom' }).click();
        const c = await camera(master);
        await master.locator('.map-canvas-host').focus();
        await master.keyboard.down('Space'); await master.mouse.move(c.host.x+10,c.host.y+10);
        await master.mouse.down(); await master.mouse.move(c.host.x+25,c.host.y+20,{steps:5}); await master.mouse.up(); await master.keyboard.up('Space');
        await expect.poll(async () => (await camera(master)).x).not.toBe(c.x);
        await gesture(master,'Régua',[50,50],[200,250]);
        await expect(master.getByTestId('ruler-measurement')).toHaveText('5 células · 7.5 m');
        expect((await props(master,url)).layers.flatMap((l:any)=>l.objects)[0]).toMatchObject({ x:token.x,y:token.y,revision:token.revision });
        await master.locator('.map-tools summary').click();
        await master.getByLabel('Escala da régua').fill('2'); await master.getByLabel('Unidade da régua').fill('metros');
        await master.getByRole('button',{name:'Salvar escala'}).click();
        await expect(master.getByTestId('ruler-measurement')).toHaveText('5 células · 10 metros');
        await master.keyboard.press('Escape'); await expect(master.getByTestId('ruler-measurement')).not.toContainText('5 células');
        await master.locator('.map-tools summary').click();
        await master.getByRole('button',{name:'Enquadrar mapa'}).click();
        for (const [i,name] of ['Linha','Retângulo','Elipse','Livre'].entries()) {
            await gesture(master,name,[100+i*50,100],[200+i*50,200],name==='Livre');
            await expect(master.locator('.map-editor-page')).toHaveAttribute('data-drawing-count',String(i+1));
        }
        await Promise.all([player.goto(url),third.goto(url)]);
        for (const p of [player,third]) await expect(p.locator('.map-editor-page')).toHaveAttribute('data-drawing-count','4');
        await master.reload(); await expect(master.locator('.map-editor-page')).toHaveAttribute('data-drawing-count','4');
        await master.getByRole('button',{ name:'Enquadrar mapa' }).click();
        await gesture(player,'Linha',[100,250],[200,250]);
        await expect(third.locator('.map-editor-page')).toHaveAttribute('data-drawing-count','5');
        state = await props(master,url);
        const playerDrawing = state.drawings.at(-1), masterDrawing = state.drawings[0];
        expect((await api(third,toolsUrl,{action:'delete',drawing_id:playerDrawing.id,drawing_revision:1,expected_revision:state.map.revision,idempotency_key:crypto.randomUUID()})).status).toBe(403);
        expect((await api(player,toolsUrl,{action:'delete',drawing_id:masterDrawing.id,drawing_revision:1,expected_revision:state.map.revision,idempotency_key:crypto.randomUUID()})).status).toBe(403);
        await player.getByTestId(`drawing-select-${playerDrawing.id}`).click();
        await player.getByRole('button',{name:'Remover desenho'}).click();
        await expect(master.locator('.map-editor-page')).toHaveAttribute('data-drawing-count','4');
        // Fixture character created through existing HTTP contract, preserving 4A UI regression separately.
        const users = state.assignable_members;
        const sheetUrl=`/mesas/${roomId}/personagens`, characterUrl=`${sheetUrl}/comandos`, combatUrl=`/mesas/${roomId}/combate/comandos`;
        const created=await api(master,characterUrl,{action:'create',name:'HEROI-COBERTO-4B',notes:'NOTAS-PRIVADAS-4B',hp_max:20,hp_current:13,assigned_user_id:users.find((u:any)=>u.name==='Theo Arantes').id,is_public:true,portrait_asset_id:token.asset_id,idempotency_key:crypto.randomUUID()});
        expect(created.status).toBe(200); const characterId=created.body.characterId;
        expect((await api(master,characterUrl,{action:'link',character_id:characterId,expected_revision:1,token_id:token.id,token_revision:1,idempotency_key:crypto.randomUUID()})).status).toBe(200);
        expect((await api(master,combatUrl,{action:'add',character_id:characterId,token_id:token.id,initiative:10,expected_revision:1,idempotency_key:crypto.randomUUID()})).status).toBe(200);
        expect((await api(master,combatUrl,{action:'start',expected_revision:2,idempotency_key:crypto.randomUUID()})).status).toBe(200);
        await expect(player.locator('.character-list')).toContainText('HEROI-COBERTO-4B');
        await player.getByRole('button',{name:'HEROI-COBERTO-4B',exact:true}).click();
        await expect(player.locator('.character-form')).toBeVisible();
        // Keep another live view on the room combat panel.
        const thirdRoom=await contexts[1].newPage(); await thirdRoom.goto(`/mesas/${roomId}`);
        await expect(thirdRoom.locator('.combat-list')).toContainText('HEROI-COBERTO-4B');
        await master.locator('.map-tools summary').click();
        await master.getByRole('button',{name:'Ativar névoa',exact:true}).click();
        await expect(master.locator('.map-editor-page')).toHaveAttribute('data-fog-enabled','true');
        for (const p of [player,third]) {
            await expect(p.getByTestId(`map-object-select-${token.id}`)).toHaveCount(0);
            await expect(p.locator('.character-list')).not.toContainText('HEROI-COBERTO-4B');
            expect((await api(p,sheetUrl)).body.characters).toEqual([]);
            expect((await api(p,sheetUrl)).body.combat.participants).toEqual([]);
            const payload=JSON.stringify(await props(p,url));
            expect(payload).not.toContain('HEROI-COBERTO-4B'); expect(payload).not.toContain('NOTAS-PRIVADAS-4B');
            expect((await api(p,`${sheetUrl}/${characterId}/retrato`)).status).toBe(404);
        }
        await expect(player.locator('.character-form')).toHaveCount(0);
        await expect(thirdRoom.locator('.combat-list')).not.toContainText('HEROI-COBERTO-4B');
        state=await props(master,url);
        expect((await api(player,toolsUrl,{action:'fog',mode:'disable',expected_revision:state.map.revision,idempotency_key:crypto.randomUUID()})).status).toBe(403);
        // Reveal/cover by the real rectangular tool. Token centre is 25,25.
        await gesture(master,'Revelar região',[0,0],[100,100]);
        await expect(player.getByTestId(`map-object-select-${token.id}`)).toBeVisible();
        await expect(thirdRoom.locator('.combat-list')).toContainText('HEROI-COBERTO-4B');
        await gesture(master,'Ocultar região',[0,0],[100,100]);
        await expect(player.getByTestId(`map-object-select-${token.id}`)).toHaveCount(0);
        const recoveredAfterReload = third.waitForResponse(r => r.url().includes(`/mesas/${roomId}/eventos`) && r.status() === 200);
        await third.reload(); await recoveredAfterReload;
        await expect(third.locator('.map-editor-page')).toBeVisible();
        await expect(third.getByTestId(`map-object-select-${token.id}`)).toHaveCount(0);
        await contexts[0].setOffline(true);
        master.once('dialog',dialog=>dialog.accept()); await master.getByRole('button',{name:'Revelar tudo',exact:true}).click();
        await expect(third.getByTestId(`map-object-select-${token.id}`)).toBeVisible();
        await contexts[0].setOffline(false);
        await expect(player.getByTestId(`map-object-select-${token.id}`)).toBeVisible({timeout:20000});
        master.once('dialog',dialog=>dialog.dismiss()); await master.getByRole('button',{name:'Ocultar tudo',exact:true}).click();
        await expect(third.getByTestId(`map-object-select-${token.id}`)).toBeVisible();
        state=await props(master,url);
        const cmd={action:'fog',mode:'cover_all',confirmed:true,expected_revision:state.map.revision,idempotency_key:crypto.randomUUID()};
        for(const invalid of ['none','invalid']) {
            expect((await api(master,toolsUrl,cmd,'POST',invalid)).status).toBe(419);
            expect((await props(master,url)).map).toEqual(state.map);
        }
        expect((await api(master,toolsUrl,cmd)).status).toBe(200);
        expect((await api(master,toolsUrl,cmd)).body.replayed).toBe(true);
        expect((await api(master,toolsUrl,{...cmd,idempotency_key:crypto.randomUUID()})).status).toBe(409);
        await expect(player.getByTestId(`map-object-select-${token.id}`)).toHaveCount(0);
        const events=(await api(third,`/mesas/${roomId}/eventos`)).body.events;
        for(const e of events.filter((e:any)=>e.type==='map.contents.changed')) {
            expect(e.entityId).toBeNull(); expect(e.entityRevision).toBeNull(); expect(Object.keys(e.payload)).toEqual(['map_id']);
        }
        expect(JSON.stringify(events)).not.toContain('HEROI-COBERTO-4B');
        await master.reload();
        await expect(master.locator('.map-editor-page')).toHaveAttribute('data-drawing-count','4');
        await expect(master.locator('.map-editor-page')).toHaveAttribute('data-fog-enabled','true');
        await master.screenshot({path:test.info().outputPath('parte4b-mestre.png')});
        await third.screenshot({path:test.info().outputPath('parte4b-jogador-coberto.png')});
    } finally { await Promise.all(contexts.map(c=>c.close())); }
});

test('4B cross-map: covered representation on B purges sheet name and PV from open map A', async ({browser,page:master}) => {
    test.setTimeout(150_000);
    const contexts=[await browser.newContext(),await browser.newContext()];
    const player=await contexts[0].newPage(), third=await contexts[1].newPage();
    const title=`CrossMap4B ${Date.now()}`;
    try {
        await login(master,'mestre@freecast.test');
        await master.getByRole('link',{name:'Nova mesa'}).first().click();
        await master.getByLabel('Nome da mesa').fill(title); await master.getByLabel('Sistema').fill('Próprio');
        await master.getByRole('button',{name:'Criar mesa',exact:true}).last().click();
        await expect(master.getByRole('button',{name:'Novo personagem'})).toBeVisible();
        const roomId=new URL(master.url()).pathname.split('/').at(-1)!;
        for(const [p,email] of [[player,'jogador@freecast.test'],[third,'convidada@freecast.test']] as const) {
            await login(p,email); await p.goto('/mesas');
            await p.locator('.room-card').filter({hasText:title}).getByRole('button',{name:'Entrar na mesa'}).click();
            await expect(p.getByRole('link',{name:`Mesa: ${title}`})).toBeVisible();
        }
        await master.goto(`/mesas/${roomId}/mapas`);
        await master.getByLabel('Nome do recurso').fill('Base pública entre mapas');
        await master.getByLabel('Tipo de recurso').selectOption('token');
        await master.locator('input[type=file]').setInputFiles({name:'base.png',mimeType:'image/png',buffer:Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aWQAAAABJRU5ErkJggg==','base64')});
        await master.getByRole('button',{name:'Enviar',exact:true}).click();
        await expect(master.locator('.asset-library-card')).toHaveCount(1);
        await master.getByLabel('Nome do mapa').fill('Mapa A');
        await master.getByRole('button',{name:'Criar mapa',exact:true}).click();
        await master.getByRole('button',{name:'Adicionar token Base pública entre mapas ao mapa'}).click();
        await expect(master.locator('.map-object-list-section button')).toHaveCount(1);
        const mapA=new URL(master.url()).pathname;
        let state=await props(master,mapA); const tokenA=state.layers.flatMap((l:any)=>l.objects)[0];
        const snapshotUrl=`/mesas/${roomId}/personagens`, charUrl=`${snapshotUrl}/comandos`;
        const result=await api(master,charUrl,{action:'create',name:'FICHA-ENTRE-MAPAS-4B',notes:'SEGREDO-ENTRE-MAPAS',hp_max:30,hp_current:21,is_public:true,assigned_user_id:state.assignable_members.find((u:any)=>u.name==='Theo Arantes').id,idempotency_key:crypto.randomUUID()});
        expect(result.status).toBe(200); const cid=result.body.characterId;
        expect((await api(master,charUrl,{action:'link',character_id:cid,expected_revision:1,token_id:tokenA.id,token_revision:1,idempotency_key:crypto.randomUUID()})).status).toBe(200);
        expect((await api(master,`/mesas/${roomId}/combate/comandos`,{action:'add',character_id:cid,token_id:tokenA.id,initiative:10,expected_revision:1,idempotency_key:crypto.randomUUID()})).status).toBe(200);
        await master.goto(`/mesas/${roomId}/mapas`);
        await master.locator('.map-card-actions summary').click();
        await master.getByRole('button',{name:'Duplicar',exact:true}).click();
        await expect(master.locator('.map-toolbar-title strong')).toHaveText('Mapa A (cópia)');
        const mapB=new URL(master.url()).pathname;
        for(const p of [player,third]) {
            await p.goto(mapA); await expect(p.getByTestId(`map-object-select-${tokenA.id}`)).toContainText('FICHA-ENTRE-MAPAS-4B · PV 21/30');
            await expect(p.locator('.combat-list')).toContainText('FICHA-ENTRE-MAPAS-4B');
        }
        await player.getByRole('button',{name:'FICHA-ENTRE-MAPAS-4B',exact:true}).click();
        await expect(player.locator('.character-form')).toBeVisible();
        await master.locator('.map-tools summary').click();
        await master.getByRole('button',{name:'Ativar névoa',exact:true}).click();
        for(const p of [player,third]) {
            await expect(p.getByTestId(`map-object-select-${tokenA.id}`)).toContainText('Base pública entre mapas');
            await expect(p.getByTestId(`map-object-select-${tokenA.id}`)).not.toContainText('PV');
            await expect(p.locator('.character-list')).not.toContainText('FICHA-ENTRE-MAPAS-4B');
            await expect(p.locator('.combat-list')).not.toContainText('FICHA-ENTRE-MAPAS-4B');
            const current=await props(p,mapA), token=current.layers.flatMap((l:any)=>l.objects)[0];
            expect(token.character).toBeNull(); expect(token.character_id).toBeNull(); expect(current.map.fog_enabled).toBe(false);
            expect(JSON.stringify(current)).not.toContain('FICHA-ENTRE-MAPAS-4B');
        }
        await expect(player.locator('.character-form')).toHaveCount(0);
        await master.getByRole('button',{name:'Desativar névoa',exact:true}).click();
        await expect(player.getByTestId(`map-object-select-${tokenA.id}`)).toContainText('PV 21/30');
        await master.getByRole('button',{name:'Ativar névoa',exact:true}).click();
        await expect(player.getByTestId(`map-object-select-${tokenA.id}`)).not.toContainText('PV');
        expect((await api(master,mapB,{idempotency_key:crypto.randomUUID()},'DELETE')).status).toBe(200);
        await expect(player.getByTestId(`map-object-select-${tokenA.id}`)).toContainText('PV 21/30');
        await expect(third.locator('.combat-list')).toContainText('FICHA-ENTRE-MAPAS-4B');
    } finally { await Promise.all(contexts.map(c=>c.close())); }
});
