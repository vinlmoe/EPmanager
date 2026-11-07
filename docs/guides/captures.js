// Captures d'écran des guides, sur le banc (docs/audit/banc) : une page par capture, par compte.
//
// Usage : node captures.js <site-tag.json> [nom ...]
// Variables : MOODLE_URL (par défaut celle du site), SORTIE (par défaut ./captures).
// Les comptes et identifiants viennent du jeu de données du banc ; ceux qui dépendent de l'état
// du site (EP proposé en attente, synthèse) sont cherchés dans les pages.
const { chromium } = require('playwright');
const fs = require('fs');
const path = require('path');

const site = JSON.parse(fs.readFileSync(process.argv[2], 'utf8'));
const only = process.argv.slice(3);
const BASE = process.env.MOODLE_URL || site.wwwroot;
const OUT = process.env.SORTIE || path.join(__dirname, 'captures');
const CM = site.cmid;
const T = site.tag;
const etu = `etu${T}_301`;
const ens = `ens${T}_1`;
const auteur = `ens${T}_5`;
const deve = `deve${T}_1`;

// [nom, compte, url (ou fonction async(page) qui navigue), sélecteur à photographier (facultatif)]
const plan = [
  ['etu-tableau-de-bord', etu, `/mod/ep/view.php?id=${CM}`],
  ['etu-catalogue', etu, `/mod/ep/catalog.php?id=${CM}`],
  ['etu-syllabus', etu, `/mod/ep/syllabus.php?id=${CM}&activityid=${site.activities[29]}`],
  ['etu-inscription', etu, `/mod/ep/catalog.php?id=${CM}&action=register&activityid=${site.activities[2]}`],
  ['etu-declarer', etu, `/mod/ep/declare.php?id=${CM}`],
  ['etu-supplement', etu, `/mod/ep/supplement.php?id=${CM}`],
  ['etu-supplement-element', etu, `/mod/ep/supplement_item.php?id=${CM}`],
  ['ens-synthese-a-traiter', ens, async (page) => {
    await page.goto(`${BASE}/course/view.php?id=${site.courseid}`);
    const link = page.locator('a[href*="/mod/epsynthesis/view.php"]').first();
    const href = await link.getAttribute('href');
    await page.goto(href.replace('view.php', 'todo.php'));
  }],
  ['ens-validation', ens, `/mod/ep/credits.php?id=${CM}`],
  ['ens-participants', ens, `/mod/ep/participants.php?id=${CM}&activityid=${site.activities[site.teachers.length - 1]}`],
  ['ens-supplement-items', ens, `/mod/ep/supplement_items.php?id=${CM}`],
  ['ens-propositions', auteur, `/mod/ep/proposals.php?id=${CM}`],
  ['ens-syllabus-edit', auteur, async (page) => {
    await page.goto(`${BASE}/mod/ep/proposals.php?id=${CM}`);
    const link = page.locator('a[href*="syllabus_edit.php"]').first();
    await page.goto(await link.getAttribute('href'));
  }],
  ['deve-administration', deve, `/mod/ep/administration.php?id=${CM}`],
  ['deve-types', deve, `/mod/ep/types.php?id=${CM}`],
  ['deve-type-edit', deve, async (page) => {
    await page.goto(`${BASE}/mod/ep/types.php?id=${CM}`);
    const link = page.locator('a[href*="type_edit.php"][href*="typeid="]').nth(2);
    await page.goto(await link.getAttribute('href'));
  }],
  ['deve-catalogue', deve, `/mod/ep/activities.php?id=${CM}`],
  ['deve-pilotage', deve, `/mod/ep/dashboard.php?id=${CM}`],
  ['deve-validation-annees', deve, `/mod/ep/year_validation.php?id=${CM}`],
  ['deve-supplement-reglages', deve, `/mod/ep/supplement_settings.php?id=${CM}`],
  ['deve-supplement-emission', deve, `/mod/ep/supplement_issue.php?id=${CM}`],
  ['deve-transfert', deve, `/mod/ep/settings_transfer.php?id=${CM}`],
];

(async () => {
  fs.mkdirSync(OUT, { recursive: true });
  const browser = await chromium.launch();
  const contexts = {};
  const login = async (user) => {
    if (!contexts[user]) {
      const context = await browser.newContext({ viewport: { width: 1280, height: 900 }, deviceScaleFactor: 1.5,
        locale: 'fr-FR' });
      const page = await context.newPage();
      await page.goto(`${BASE}/login/index.php`);
      await page.fill('#username', user);
      await page.fill('#password', site.password);
      await page.click('#loginbtn');
      await page.waitForLoadState('load');
      contexts[user] = page;
    }
    return contexts[user];
  };
  for (const [name, user, target] of plan) {
    if (only.length && !only.includes(name)) {
      continue;
    }
    const page = await login(user);
    try {
      if (typeof target === 'function') {
        await target(page);
      } else {
        await page.goto(BASE + target);
      }
      await page.waitForLoadState('networkidle');
      // Ni barre de pied de page Moodle ni infos de performance : seulement le contenu.
      await page.addStyleTag({ content: '#page-footer, .performanceinfo, footer, .btn-footer-popover,'
        + ' .footer-popover { display: none !important; }' });
      // Haut de la page, sur 1 500 pixels au plus : ce que l'utilisateur voit en arrivant, avec la
      // barre de navigation ; les longues listes sont coupées plutôt que réduites à l'illisible.
      const height = await page.evaluate(() => document.documentElement.scrollHeight);
      const file = path.join(OUT, `${name}.png`);
      await page.screenshot({ path: file, fullPage: true, clip: { x: 0, y: 0, width: 1280, height: Math.min(height, 1500) } });
      console.log(`${name} : ${page.url()}`);
    } catch (e) {
      console.log(`${name} : ÉCHEC ${e.message.split('\n')[0]}`);
    }
  }
  await browser.close();
})();
