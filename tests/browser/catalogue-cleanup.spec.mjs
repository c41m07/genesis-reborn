import { test, expect } from './support/isolated-test.mjs';

test('Le catalogue affiche les consommations et les tourelles spécialisées sans illustrations', async ({ page }) => {
  test.setTimeout(180000);
  const id=crypto.randomUUID().slice(0,8),email='catalogue-'+id+'@example.test',password='Browser-test-2026!';
  await page.goto('/inscription');
  await page.getByLabel('Adresse email').fill(email);
  await page.getByLabel('Nom de commandant').fill('Catalog-'+id);
  await page.getByLabel('Mot de passe (12 caractères minimum)',{exact:true}).fill(password);
  await page.getByLabel('Confirmer le mot de passe').fill(password);
  await page.getByRole('button',{name:'Créer mon compte'}).click();
  await expect(page).toHaveURL(/connexion/);
  await page.getByLabel('Adresse email').fill(email);
  await page.getByLabel('Mot de passe',{exact:true}).fill(password);
  await page.getByRole('button',{name:/Connexion/}).click();
  await page.locator('article.universe-card').filter({has:page.getByRole('heading',{name:/Turbo/})}).getByRole('button').click();
  await expect(page).toHaveURL(/planetes\/\d+$/);
  const planetPath=new URL(page.url()).pathname;
  await page.goto('/profil/preferences');
  await page.getByLabel('Afficher les bâtiments, technologies, vaisseaux et défenses verrouillés').check();
  await page.getByRole('button',{name:'Enregistrer les paramètres'}).click();

  await page.setViewportSize({width:1440,height:1000});
  await page.goto(planetPath+'?section=buildings');
  const metalBenefits=page.locator('#item-mine_metal-basic .card-benefits');
  await expect(metalBenefits).not.toHaveAttribute('open');
  await metalBenefits.locator('summary').click();
  await expect(metalBenefits).toHaveAttribute('open','');
  await expect(metalBenefits).toContainText('Consommation électrique');
  await expect(metalBenefits.locator('.card-benefit-consumption')).toContainText(/-\d+.*→.*-\d+ \/ h/);
  await expect(metalBenefits.locator('.card-benefit-consumption strong')).toHaveCSS('color','rgb(240, 139, 139)');
  await expect(page.locator('#item-mine_metal-basic .costs [title="Antimatière"]')).toHaveCount(0);
  await metalBenefits.locator('summary').click();
  const antimatterBenefits=page.locator('#item-mine_antimatter-basic .card-benefits');
  await antimatterBenefits.locator('summary').click();
  await expect(antimatterBenefits).toContainText('Consommation d’hydrogène');
  await antimatterBenefits.locator('summary').click();
  for(const slug of ['lab','shipyard','fortress'])await expect(page.locator('#item-'+slug+'-basic .card-benefits')).toContainText(/→ [1-9]\d* %/);
  await expect(page.locator('.generated-catalogue-art, .card-art')).toHaveCount(0);

  await page.goto(planetPath+'?section=technologies');
  await expect(page.locator('.catalog-card')).toHaveCount(57);
  await expect(page.locator('#item-engineering_foundations-basic .card-benefits')).toContainText('Réduction du temps de construction des bâtiments');
  await expect(page.locator('#item-nanomaterial_synthesis-basic .card-benefits')).toContainText('Réduction du coût de construction des bâtiments');
  await expect(page.locator('#item-naval_engineering-basic .card-benefits')).toContainText('Réduction du coût de construction des vaisseaux');
  const levelOneEffects={engineering_foundations:'1 %',naval_engineering:'1 %',automated_defense_repair:'10 %',nanomaterial_synthesis:'1 %',vector_propulsion:'1 %',weapon_calibration:'1 %',target_acquisition:'1 %',structural_armor:'1 %',shield_harmonics:'1 %',evasive_navigation:'1 %',signature_reduction:'-1 %',rapid_fire_cycle:'1 %',cryogenic_reserves:'1 %',modular_cargo_bays:'1 %',weapons_engineering:'1 %'};
  for(const [slug,expected] of Object.entries(levelOneEffects))await expect(page.locator('#item-'+slug+'-basic .card-benefits')).toContainText('→ '+expected);
  await expect(page.locator('.generated-catalogue-art, .card-art')).toHaveCount(0);

  await page.goto(planetPath+'?section=ships');
  await expect(page.locator('.generated-catalogue-art, .card-art')).toHaveCount(0);
  await page.goto(planetPath+'?section=defenses');
  const groups={ground_turret_anti_light:['anti-léger','intercepteur, chasseur, chasseur lourd, chasseur furtif'],ground_turret_anti_escort:['anti-escorteur','corvette, frégate, destroyer'],ground_turret_anti_heavy:['anti-lourd','croiseur, cuirassé'],ground_turret_anti_capital:['anti-capital','vaisseau capital']};
  await expect(page.locator('.catalog-card')).toHaveCount(12);
  for(const [slug,[label,targets]] of Object.entries(groups)){
    const card=page.locator('article[id^="item-'+slug+'-"]').first();
    await expect(card).toBeVisible();
    await expect(card.locator('.catalog-card-heading > h3')).toContainText('Tourelle au sol');
    await expect(card.locator('.card-detail')).toContainText(label);
    const benefits=card.locator('.card-benefits');
    await expect(benefits).not.toHaveAttribute('open');
    await benefits.locator('summary').click();
    await expect(benefits).toContainText(targets);
    await benefits.locator('summary').click();
    await expect(benefits).not.toHaveAttribute('open');
  }
  await expect(page.locator('.generated-catalogue-art, .card-art')).toHaveCount(0);
  await expect.poll(()=>page.evaluate(()=>document.documentElement.scrollWidth)).toBeLessThanOrEqual(1440);
  await page.setViewportSize({width:390,height:844});
  await page.reload();
  await expect.poll(()=>page.evaluate(()=>document.documentElement.scrollWidth)).toBeLessThanOrEqual(390);
});
