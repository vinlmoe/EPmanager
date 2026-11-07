// Captures d'écran des guides, sur le banc (docs/audit/banc) : une page par capture, par compte.
//
// Usage : node captures.js <site-tag.json> [nom ...]
// Variables : MOODLE_URL (par défaut celle du site), SORTIE (par défaut ./captures), PERSONNEL
// (étiquette des comptes enseignants et DEVE quand ils sont partagés avec une autre promotion,
// comme pour charge_setup.php).
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
const ST = process.env.PERSONNEL || T;
const etu = process.env.ETU || `etu${T}_301`;
const ens = `ens${ST}_1`;
const auteur = `ens${ST}_5`;
const deve = `deve${ST}_1`;
// Guide utilisateur : responsable d'un EP qui a des inscrits en attente (RESP=compte:activityid),
// supplément émis à vérifier (VERIF=numéro:code), synthèse (SYNTHESE=cmid), EP publié de l'auteur
// à mettre à jour (AUTEUR_EP=activityid), EP dont une mise à jour attend la DEVE (REVUE_EP).
const [resp, respact] = (process.env.RESP || `${ens}:${site.activities[0]}`).split(':');
const [verifnumber, verifcode] = (process.env.VERIF || ':').split(':');
const SYN = process.env.SYNTHESE;
// Premier lien de la page courante dont l'adresse contient « motif » et, au besoin, le texte.
const follow = async (page, url, motif, texte) => {
  await page.goto(BASE + url);
  let link = page.locator(`a[href*="${motif}"]`);
  if (texte) {
    link = link.filter({ hasText: texte });
  }
  await page.goto(await link.first().getAttribute('href'));
};

// [nom, compte, url (ou fonction async(page) qui navigue), sélecteur à photographier (facultatif)]
const plan = [
  ['etu-tableau-de-bord', etu, `/mod/ep/view.php?id=${CM}`],
  ['etu-catalogue', etu, `/mod/ep/catalog.php?id=${CM}`],
  ['etu-syllabus', etu, `/mod/ep/syllabus.php?id=${CM}&activityid=${site.activities[29]}`],
  
  ['etu-declarer', etu, `/mod/ep/declare.php?id=${CM}`],
  ['etu-supplement', etu, `/mod/ep/supplement.php?id=${CM}`],
  ['etu-supplement-element', etu, `/mod/ep/supplement_item.php?id=${CM}`],
  ['ens-synthese-a-traiter', ens, SYN ? `/mod/epsynthesis/todo.php?id=${SYN}` : async (page) => {
    await page.goto(`${BASE}/course/view.php?id=${site.courseid}`);
    const link = page.locator('a[href*="/mod/epsynthesis/view.php"]').first();
    const href = await link.getAttribute('href');
    await page.goto(href.replace('view.php', 'todo.php'));
  }],
  ['ens-validation', ens, `/mod/ep/credits.php?id=${CM}`],
  ['ens-participants', resp, `/mod/ep/participants.php?id=${CM}&activityid=${respact}`],
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

  // Guide utilisateur.
  ['gu-etu-tableau-de-bord', etu, `/mod/ep/view.php?id=${CM}`],
  ['gu-etu-catalogue', etu, `/mod/ep/catalog.php?id=${CM}`],
  ['gu-etu-syllabus', etu, `/mod/ep/syllabus.php?id=${CM}&activityid=${site.activities[4]}`],
  ['gu-etu-declarer', etu, `/mod/ep/declare.php?id=${CM}`],
  ['gu-etu-detail', etu, (page) => follow(page, `/mod/ep/view.php?id=${CM}`, 'validate.php', 'Détail')],
  ['gu-etu-supplement', etu, `/mod/ep/supplement.php?id=${CM}`],
  ['gu-etu-supplement-element', etu, `/mod/ep/supplement_item.php?id=${CM}`],
  ['gu-public-verification', etu,
    `/mod/ep/supplement_verify.php?number=${encodeURIComponent(verifnumber)}&code=${verifcode}`],
  ['gu-ens-synthese-a-traiter', ens, `/mod/epsynthesis/todo.php?id=${SYN}`],
  ['gu-ens-synthese-validation', ens, `/mod/epsynthesis/entries.php?id=${SYN}`],
  ['gu-ens-synthese-pilotage', ens, `/mod/epsynthesis/dashboard.php?id=${SYN}`],
  ['gu-ens-validation', ens, `/mod/ep/credits.php?id=${CM}`],
  ['gu-ens-demande', ens, (page) => follow(page, `/mod/ep/credits.php?id=${CM}`, 'validate.php', 'Valider')],
  ['gu-ens-supplement-items', ens, `/mod/ep/supplement_items.php?id=${CM}`],
  ['gu-ens-propositions', auteur, `/mod/ep/proposals.php?id=${CM}`],
  ['gu-ens-proposer', auteur, `/mod/ep/syllabus_edit.php?id=${CM}`],
  ['gu-ens-syllabus-edit', auteur, `/mod/ep/syllabus_edit.php?id=${CM}&activityid=${process.env.AUTEUR_EP || 0}`],
  ['gu-ens-seances', auteur, `/mod/ep/syllabus_edit.php?id=${CM}&activityid=${process.env.AUTEUR_EP || 0}`,
    '#id_sessionshdr'],
  ['gu-etu-seances', etu, `/mod/ep/syllabus.php?id=${CM}&activityid=${site.activities[4]}`, 'table.ep-syllabus-sessions'],
  ['gu-deve-propositions', deve, `/mod/ep/proposals.php?id=${CM}`],
  ['gu-deve-comparer', deve, `/mod/ep/syllabus.php?id=${CM}&activityid=${process.env.REVUE_EP || 0}`],
  ['gu-ens-participants', resp, `/mod/ep/participants.php?id=${CM}&activityid=${respact}`],
  ['gu-ens-import-notes', resp, `/mod/ep/grades_import.php?id=${CM}&activityid=${respact}`],
  ['gu-deve-administration', deve, `/mod/ep/administration.php?id=${CM}`],
  ['gu-deve-types', deve, `/mod/ep/types.php?id=${CM}`],
  ['gu-deve-type-edit', deve, async (page) => {
    await page.goto(`${BASE}/mod/ep/types.php?id=${CM}`);
    const link = page.locator('a[href*="type_edit.php"][href*="typeid="]').nth(2);
    await page.goto(await link.getAttribute('href'));
  }],
  ['gu-deve-minimums', deve, `/mod/ep/year_requirements.php?id=${CM}`],
  ['gu-deve-catalogue', deve, `/mod/ep/activities.php?id=${CM}`],
  ['gu-deve-ep-edit', deve, `/mod/ep/activities.php?id=${CM}&action=edit&activityid=${site.activities[4]}`],
  ['gu-deve-responsables', deve, `/mod/ep/activity_teachers.php?id=${CM}&activityid=${site.activities[4]}`],
  ['gu-deve-validation', deve, `/mod/ep/credits.php?id=${CM}`],
  ['gu-deve-pilotage', deve, `/mod/ep/dashboard.php?id=${CM}`],
  ['gu-deve-export', deve, `/mod/ep/export.php?id=${CM}`],
  ['gu-deve-courriels', deve, `/mod/ep/notifications.php?id=${CM}`],
  ['gu-deve-pdf', deve, `/mod/ep/pdf_settings.php?id=${CM}`],
  ['gu-deve-annees', deve, `/mod/ep/year_validation.php?id=${CM}`],
  ['gu-deve-rentree', deve, `/mod/ep/rollover.php?id=${CM}`],
  ['gu-deve-supplement-reglages', deve, `/mod/ep/supplement_settings.php?id=${CM}`],
  ['gu-deve-supplement-elements', deve, `/mod/ep/supplement_items.php?id=${CM}`],
  ['gu-deve-emission', deve, `/mod/ep/supplement_issue.php?id=${CM}`],
  ['gu-deve-transfert', deve, `/mod/ep/settings_transfer.php?id=${CM}`],
  ['gu-deve-import-catalogue', deve, `/mod/ep/import_catalog.php?id=${CM}`],
  ['gu-deve-import-credits', deve, `/mod/ep/import_credits.php?id=${CM}`],
  ['gu-deve-archive', deve, `/mod/ep/archive.php?id=${CM}`],
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
      // Les requêtes de fond de la page de connexion doivent être finies avant l'envoi.
      await page.waitForLoadState('networkidle');
      await page.fill('#username', user);
      await page.fill('#password', site.password);
      await page.click('#loginbtn');
      await page.waitForLoadState('load');
      contexts[user] = page;
    }
    return contexts[user];
  };
  for (const [name, user, target, selector] of plan) {
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
      // Tiroirs de Moodle (index du cours, blocs) fermés : en capture pleine page, celui de
      // l'index recouvrirait la gauche du contenu.
      await page.addStyleTag({ content: '.drawer, .drawer-toggles { display: none !important; }'
        + ' #page.drawers { margin-left: 0 !important; margin-right: 0 !important; }'
        + ' #page.drawers .main-inner { max-width: 100% !important; }'
        // Barre de navigation non fixe : elle recouvrirait la capture d'un élément de la page.
        + ' .navbar.fixed-top { position: absolute !important; }' });
      // Haut de la page, sur 1 500 pixels au plus : ce que l'utilisateur voit en arrivant, avec la
      // barre de navigation ; les longues listes sont coupées plutôt que réduites à l'illisible.
      const file = path.join(OUT, `${name}.png`);
      if (selector) {
        // Un seul élément de la page, quelle que soit sa place : un programme de séances, par
        // exemple, qui tombe sous les 1 500 premiers pixels.
        await page.locator(selector).first().screenshot({ path: file });
      } else {
        const height = await page.evaluate(() => document.documentElement.scrollHeight);
        await page.screenshot({ path: file, fullPage: true, clip: { x: 0, y: 0, width: 1280, height: Math.min(height, 1500) } });
      }
      console.log(`${name} : ${page.url()}`);
    } catch (e) {
      console.log(`${name} : ÉCHEC ${e.message.split('\n')[0]}`);
    }
  }
  await browser.close();
})();
