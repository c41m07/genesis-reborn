import { test, expect } from './support/isolated-test.mjs';
import { queryIsolatedSql, runIsolatedSql } from './support/game-fixtures.mjs';
import AxeBuilder from '@axe-core/playwright';

test.use({ hasTouch:true });

async function enter(page) {
    const id=crypto.randomUUID().slice(0,8),email=`galaxy-workspace-${id}@example.test`,password=crypto.randomUUID()+'aA1!';
    await page.goto('/inscription');
    await page.getByLabel('Adresse email').fill(email);
    await page.getByLabel('Nom de commandant').fill('Galaxie-'+id);
    await page.getByLabel('Mot de passe (12 caractères minimum)',{exact:true}).fill(password);
    await page.getByLabel('Confirmer le mot de passe').fill(password);
    await page.getByRole('button',{name:'Créer mon compte'}).click();
    await page.getByLabel('Adresse email').fill(email);
    await page.getByLabel('Mot de passe',{exact:true}).fill(password);
    await page.getByRole('button',{name:/Connexion/}).click();
    await page.locator('.universe-card').filter({has:page.getByRole('heading',{name:/Omega/})}).getByRole('button').click();
    await expect(page).toHaveURL(/planetes\/\d+$/);
    const planet=Number(new URL(page.url()).pathname.split('/').pop()),project=process.env.BROWSER_ROUTER;
    const galaxy=Number(queryIsolatedSql(project,`SELECT s.galaxy_id FROM planet p JOIN solar_system s ON s.id=p.solar_system_id WHERE p.id=${planet}`));
    // La carte importée place ses douze premiers numéros sur une ligne ; la fixture utilise dix colonnes.
    runIsolatedSql(project,`UPDATE solar_system SET coord_y=coord_y+FLOOR(coord_x/30)*3,coord_x=MOD(coord_x,30) WHERE galaxy_id=${galaxy} AND coord_x>=30`);
    const rows=Array.from({length:80},(_,i)=>`SELECT ${i+1} AS number,${i%10*3} AS grid_x,${Math.floor(i/10)*3} AS grid_y`).join(' UNION ALL ');
    runIsolatedSql(project,`INSERT INTO solar_system(galaxy_id,name,coord_x,coord_y) SELECT ${galaxy},CONCAT('Système test ',points.number),points.grid_x,points.grid_y FROM (${rows}) points WHERE NOT EXISTS(SELECT 1 FROM solar_system existing WHERE existing.galaxy_id=${galaxy} AND existing.coord_x=points.grid_x AND existing.coord_y=points.grid_y)`);
    return{project,galaxy};
}

test('L7C : 80 systèmes, deux vues persistantes, distances communes et signaux privés sur quatre formats',async({page},info)=>{
    test.setTimeout(240000);
    await page.emulateMedia({reducedMotion:'reduce'});
    await page.addInitScript(()=>{
        const arcs=new WeakMap(),arc=CanvasRenderingContext2D.prototype.arc,fill=CanvasRenderingContext2D.prototype.fill,clear=CanvasRenderingContext2D.prototype.clearRect;
        window.unknownBlackDisks=0;
        CanvasRenderingContext2D.prototype.arc=function(x,y,r,...args){arcs.set(this,{x,y,r});return arc.call(this,x,y,r,...args)};
        CanvasRenderingContext2D.prototype.fill=function(...args){if(this.canvas.classList.contains('gv2-canvas--galaxy')&&arcs.get(this)?.r===11&&this.fillStyle==='#000000')window.unknownBlackDisks++;return fill.apply(this,args)};
        CanvasRenderingContext2D.prototype.clearRect=function(...args){if(this.canvas.classList.contains('gv2-canvas--galaxy'))window.unknownBlackDisks=0;return clear.apply(this,args)};
    });
    const user=await enter(page),errors=[];page.on('pageerror',e=>errors.push(e.message));
    await page.goto('/carte-v2?galaxy='+user.galaxy);
    const map=page.locator('.gv2-workspace');
    await expect(map).toHaveAttribute('data-layout','spiral');
    const data=JSON.parse(await map.getAttribute('data-galaxy-v2-map-value'));
    expect(data.systems).toHaveLength(80);
    for(const system of data.systems){expect(Number.isFinite(system.physical.x)).toBe(true);if(!system.known)expect(system.planets).toEqual([])}
    const distances=data.systems.map(s=>s.distanceFromOrigin);
    await expect.poll(()=>page.evaluate(()=>window.unknownBlackDisks)).toBe(data.systems.filter(s=>!s.known).length);
    for(const width of [1440,768,390,320]){
        await page.setViewportSize({width,height:1000});
        for(const [layout,label] of [['spiral','Spirale réelle'],['grid','Grille de repérage']]){
            await page.getByRole('button',{name:label,exact:true}).click();
            await expect(map).toHaveAttribute('data-layout',layout);
            expect(JSON.parse(await map.getAttribute('data-galaxy-v2-map-value')).systems.map(s=>s.distanceFromOrigin)).toEqual(distances);
            expect(await page.evaluate(()=>document.documentElement.scrollWidth)).toBeLessThanOrEqual(width);
            await map.screenshot({path:info.outputPath(`jeu-L7C-${layout}-${width}.png`),animations:'disabled'});
        }
    }
    await page.reload();await expect(map).toHaveAttribute('data-layout','grid');
    expect(Number(queryIsolatedSql(user.project,"SELECT COUNT(*) FROM fleet WHERE status='traveling'"))).toBe(0);
    expect((await new AxeBuilder({page}).include('.gv2-workspace').analyze()).violations.filter(v=>['serious','critical'].includes(v.impact))).toEqual([]);
    expect(errors).toEqual([]);
});

test('L7C : système et globe conservent clic, rotation, zoom, gestes tactiles et retour',async({page},info)=>{
    test.setTimeout(180000);
    await page.emulateMedia({reducedMotion:'reduce'});
    await page.addInitScript(()=>{
        const draw=CanvasRenderingContext2D.prototype.drawImage;
        CanvasRenderingContext2D.prototype.drawImage=function(...args){if(this.canvas.classList.contains('gv2-canvas--planet')&&args.length===5)window.planetRenderedSize=Number(args[3]);return draw.apply(this,args)};
    });
    const user=await enter(page),errors=[];page.on('pageerror',e=>errors.push(e.message));
    await page.goto('/carte-v2?galaxy='+user.galaxy);
    const map=page.locator('.gv2-workspace'),data=JSON.parse(await map.getAttribute('data-galaxy-v2-map-value'));
    const home=data.systems.find(s=>s.planets.some(p=>p.ownedByCurrentPlayer));
    await page.setViewportSize({width:1920,height:1080});
    await page.getByRole('searchbox').fill(home.name);
    await page.locator('.gv2-search-results button').filter({hasText:home.name}).first().click();
    await expect(map).toHaveAttribute('data-view','galaxy');
    const inspector=page.locator('.gv2-system-panel'), scene=page.locator('.gv2-scene');
    await expect(inspector).toContainText('Système sélectionné');
    await expect.poll(async()=>{const panel=await inspector.boundingBox(), canvas=await scene.boundingBox();return panel.x>=canvas.x+canvas.width;}).toBe(true);
    await page.setViewportSize({width:390,height:844});
    await expect.poll(async()=>{const panel=await inspector.boundingBox(), canvas=await scene.boundingBox();return panel.y+panel.height<=canvas.y;}).toBe(true);
    await inspector.getByRole('button',{name:'Fermer la fiche du système'}).focus();
    await page.keyboard.press('Tab');
    await expect(inspector.getByRole('button',{name:'Entrer dans le système',exact:true})).toBeFocused();
    await page.setViewportSize({width:1920,height:1080});
    await page.getByRole('button',{name:'Entrer dans le système',exact:true}).click();
    await expect(map).toHaveAttribute('data-view','system');
    const choice=page.locator('.gv2-planet-choice').filter({hasText:home.planets.find(p=>p.ownedByCurrentPlayer).name});
    await choice.click();
    await page.locator('.gv2-planet-panel').getByRole('button',{name:'◉ Vue rapprochée'}).click();
    await expect(map).toHaveAttribute('data-view','planet');
    const canvas=page.locator('.gv2-canvas--planet');await canvas.scrollIntoViewIfNeeded();
    const size=await page.evaluate(()=>window.planetRenderedSize);
    await page.getByRole('button',{name:'Zoomer',exact:true}).click();
    await expect.poll(()=>page.evaluate(()=>window.planetRenderedSize)).toBeGreaterThan(size);
    await canvas.scrollIntoViewIfNeeded();const r=await canvas.boundingBox();
    const before=await canvas.screenshot();await page.mouse.move(r.x+r.width/2,r.y+r.height/2);await page.mouse.down();await page.mouse.move(r.x+r.width/2+70,r.y+r.height/2,{steps:8});await page.mouse.up();
    expect(await canvas.screenshot()).not.toEqual(before);
    await page.screenshot({path:info.outputPath('jeu-L7C-globe-desktop.png'),fullPage:true,animations:'disabled'});
    await page.getByRole('button',{name:'Vue entière',exact:true}).click();
    await page.setViewportSize({width:390,height:1000});await canvas.scrollIntoViewIfNeeded();
    const touchBounds=await canvas.boundingBox(),session=await page.context().newCDPSession(page),initialSize=await page.evaluate(()=>window.planetRenderedSize);
    const planetPanelBounds=await page.locator('.gv2-planet-panel').boundingBox();
    expect(planetPanelBounds.y+planetPanelBounds.height).toBeLessThanOrEqual(touchBounds.y);
    const fingers=spread=>[{x:touchBounds.x+190-spread,y:touchBounds.y+210,id:1},{x:touchBounds.x+190+spread,y:touchBounds.y+210,id:2}];
    await session.send('Input.dispatchTouchEvent',{type:'touchStart',touchPoints:fingers(25)});await session.send('Input.dispatchTouchEvent',{type:'touchMove',touchPoints:fingers(50)});await session.send('Input.dispatchTouchEvent',{type:'touchEnd',touchPoints:[]});
    await expect.poll(()=>page.evaluate(()=>window.planetRenderedSize)).toBeGreaterThan(initialSize);
    await page.screenshot({path:info.outputPath('jeu-L7C-globe-mobile.png'),fullPage:true,animations:'disabled'});
    await page.locator('.gv2-planet-panel').getByRole('button',{name:/Revenir au système/}).click();await expect(map).toHaveAttribute('data-view','system');
    await expect(choice).toBeFocused();
    await page.locator('[data-galaxy-v2-target=systemBack]').click();await expect(map).toHaveAttribute('data-view','galaxy');
    const unknown=data.systems.find(s=>!s.known);await page.getByRole('searchbox').fill(unknown.name);await page.locator('.gv2-search-results button').filter({hasText:unknown.name}).first().click();
    await expect(map).toHaveAttribute('data-view','galaxy');await expect(page.locator('.gv2-system-panel')).toContainText('aucune planète révélée');
    const panel = page.locator('.gv2-system-panel');
    await expect(panel.locator('.gv2-planet-choice')).toHaveCount(0);
    await expect(panel.getByRole('button',{name:'Entrer dans le système',exact:true})).toHaveCount(0);
    await page.setViewportSize({width:320,height:800});
    await expect.poll(()=>page.evaluate(()=>document.documentElement.scrollWidth)).toBeLessThanOrEqual(320);
    await expect(panel).toBeVisible();
    await page.getByRole('button',{name:'Zoomer',exact:true}).tap();
    await page.getByRole('button',{name:'Dézoomer',exact:true}).tap();
    await expect(panel).toBeVisible();
    expect(errors).toEqual([]);
});
