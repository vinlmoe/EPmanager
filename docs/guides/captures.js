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
const os = require('os');
const path = require('path');
const { execFileSync } = require('child_process');

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
// à mettre à jour (AUTEUR_EP=activityid), EP dont une mise à jour attend la DEVE (REVUE_EP),
// modules syllabus (SYLLABUS=sortie de syllabus_setup.php, voir plus bas).
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

// [nom, compte, url (ou fonction async(page) qui navigue), sélecteur à photographier (facultatif)].
// Pour un document PDF produit par une page : [nom, compte, url du PDF, { pdf: [pages] }] ; le PDF est
// téléchargé dans la session du compte et les pages demandées sont rendues côte à côte, sur fond
// gris, comme des feuilles (pdftoppm et montage d'ImageMagick).
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
  ['ens-syllabus-edit', auteur, `/mod/ep/syllabus_edit.php?id=${CM}&activityid=${process.env.AUTEUR_EP || 0}`],
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
  ['gu-etu-seances', etu, `/mod/ep/syllabus.php?id=${CM}&activityid=${site.activities[4]}`, 'table.syllabus-sessions'],
  // Documents PDF.
  ['gu-pdf-syllabus', etu, `/mod/ep/syllabus_pdf.php?id=${CM}&activityid=${site.activities[4]}`, { pdf: [1, 2] }],
  ['gu-pdf-livret', deve, `/mod/ep/booklet.php?id=${CM}`, { pdf: [1, 2, 4] }],
  ['gu-pdf-supplement', etu, `/mod/ep/supplement.php?id=${CM}&action=preview&lang=fr`, { pdf: [1, 2] }],
  // Feuille d'émargement d'un EP qui a des inscrits (EMARGEMENT=activityid).
  ['gu-pdf-emargement', deve, `/mod/ep/participants.php?id=${CM}&activityid=${process.env.EMARGEMENT || respact}&action=sheet`,
    { pdf: [1] }],
  ['gu-deve-propositions', deve, `/mod/ep/proposals.php?id=${CM}`],
  ['gu-deve-comparer', deve, `/mod/ep/syllabus.php?id=${CM}&activityid=${process.env.REVUE_EP || 0}&review=1`],
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

// Guide des modules syllabus (SYLLABUS=chemin de la sortie de syllabus_setup.php).
if (process.env.SYLLABUS) {
  const sy = JSON.parse(fs.readFileSync(process.env.SYLLABUS, 'utf8'));
  const m1 = sy.modules[0];
  const m2 = sy.modules[1];
  const sett = process.env.ETU || 'etux_4';
  // Référentiel créé par la DEVE pendant le scénario (SYREF=identifiant).
  const ref = process.env.SYREF || 0;
  plan.push(
    ['sy-etu-mes-modules', sett, `/mod/syllabusstudent/view.php?id=${sy.studentcmid}`],
    ['sy-etu-fiche', sett, `/mod/syllabus/view.php?id=${process.env.SYEVAL || m1.cmid}`],
    ['sy-etu-evaluer', sett, `/mod/syllabus/evaluate.php?id=${process.env.SYEVAL || m1.cmid}`],
    ['sy-ens-mes-modules', m1.leaders[0], `/mod/syllabusstaff/view.php?id=${sy.staffcmid}`],
    ['sy-ens-fiche', m1.leaders[0], `/mod/syllabus/view.php?id=${m1.cmid}`],
    ['sy-ens-redaction', m1.leaders[0], `/mod/syllabus/edit.php?id=${m1.cmid}`],
    ['sy-ens-seances', m1.leaders[0], `/mod/syllabus/edit.php?id=${m1.cmid}`, '#id_sessionshdr'],
    ['sy-ens-evaluation', m1.leaders[0], `/mod/syllabus/evaluation.php?id=${m1.cmid}`],
    ['sy-ens-responsables', m1.leaders[0], `/mod/syllabus/teachers.php?id=${m1.cmid}`],
    ['sy-ens-competences', m1.leaders[0], `/mod/syllabus/competencies.php?id=${m1.cmid}&frameworkid=${ref}`],
    ['sy-deve-competences', deve, `/mod/syllabusstaff/competencies.php?id=${sy.staffcmid}&frameworkid=${ref}`],
    ['sy-deve-referentiels', deve, `/mod/syllabusstaff/frameworks.php?id=${sy.staffcmid}&frameworkid=${ref}`],
    ['sy-deve-cours-sans-module', deve,
      `/mod/syllabusstaff/courselink.php?id=${sy.staffcmid}&courseid=${sy.nomodulecourse}&frameworkid=${ref}`],
    ['sy-deve-livret', deve, `/mod/syllabusstaff/booklet.php?id=${sy.staffcmid}&selection=y2`],
    ['sy-pdf-fiche', sett, `/mod/syllabus/pdf.php?id=${m1.cmid}`, { pdf: [1, 2] }],
    ['sy-pdf-livret', deve, `/mod/syllabusstaff/booklet.php?id=${sy.staffcmid}&selection=all&download=1`,
      { pdf: [1, 3, 4] }],
    ['sy-ens-progression', m1.leaders[0],
      `/mod/syllabusstaff/progression.php?id=${sy.staffcmid}&compid=${process.env.SYCOMP || 0}`],
    ['sy-ens-resultats', m1.leaders[0], `/mod/syllabus/results.php?id=${m1.cmid}`],
    ['sy-deve-tous', deve, `/mod/syllabusstaff/all.php?id=${sy.staffcmid}`],
    ['sy-deve-a-relire', deve, `/mod/syllabusstaff/review.php?id=${sy.staffcmid}`],
    ['sy-deve-relecture', deve, `/mod/syllabus/view.php?id=${m2.cmid}&show=working`],
    ['sy-deve-decision', deve, `/mod/syllabus/view.php?id=${m2.cmid}&show=working`, '#review'],
    ['sy-deve-referentiel', deve, `/mod/syllabusstaff/referential.php?id=${sy.staffcmid}`],
    ['sy-deve-resultats', deve, `/mod/syllabusstaff/results.php?id=${sy.staffcmid}`],
  );
}

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
      if (selector && selector.pdf) {
        // Document PDF : téléchargé avec la session du compte, pages rendues en images.
        const response = await page.request.get(BASE + target);
        const type = response.headers()['content-type'] || '';
        if (!type.includes('pdf')) {
          throw new Error(`pas un PDF (${type})`);
        }
        const dir = fs.mkdtempSync(path.join(os.tmpdir(), 'pdf-'));
        const pdf = path.join(dir, 'doc.pdf');
        fs.writeFileSync(pdf, await response.body());
        // Pages demandées qui existent : une fiche courte peut tenir sur une page.
        const count = Number(/Pages:\s+(\d+)/.exec(execFileSync('pdfinfo', [pdf]).toString())[1]);
        const images = selector.pdf.filter((n) => n <= count).map((n) => {
          const out = path.join(dir, `p${n}`);
          execFileSync('pdftoppm', ['-f', String(n), '-l', String(n), '-r', '90', '-png', '-singlefile', pdf, out]);
          return `${out}.png`;
        });
        execFileSync('montage', [...images, '-background', '#d9d9d9', '-bordercolor', '#9a9a9a', '-border', '1',
          '-geometry', '+18+18', '-tile', `${images.length}x1`, path.join(OUT, `${name}.png`)]);
        fs.rmSync(dir, { recursive: true, force: true });
        console.log(`${name} : ${BASE + target} (PDF, pages ${selector.pdf.join(', ')})`);
        continue;
      }
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
