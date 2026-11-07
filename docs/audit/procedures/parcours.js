#!/usr/bin/env node
/**
 * Parcours de bout en bout des procédures de mod_ep (Enseignement personnalisé) dans un vrai
 * navigateur : proposition et publication d'un EP, révision de son syllabus, inscription et
 * déclarations d'un étudiant, décisions du responsable, du référent et de la DEVE, supplément au
 * diplôme émis et vérifié, prérequis bloquants.
 *
 * Usage : node parcours.js            (toutes les procédures)
 *         ETAPES=1,2,3 node parcours.js (une partie seulement ; l'état est relu dans etat.json)
 *
 * Tout passe par l'interface (formulaires, boutons, liens). Les seules exceptions, signalées dans
 * le journal, sont les vérifications en base (facultatives, CONF.PSQL) et le téléchargement de la
 * fiche PDF d'une version non publiée, qu'aucun bouton ne propose.
 *
 * Produit :
 *  - une capture pleine page par écran utile dans CONF.CAPTURES (NN-role-action.png) ;
 *  - le journal resultats.json (étape, rôle, attendu, obtenu, durée, captures, erreurs relevées) ;
 *  - les PDF téléchargés dans CONF.DOWNLOADS.
 *
 * Pour rejouer sur un autre jeu de données, il suffit d'adapter les constantes de CONF.
 */
'use strict';

const fs = require('fs');
const path = require('path');
const { execFileSync, execSync } = require('child_process');
const { chromium } = require('playwright');

// ---------------------------------------------------------------------------------------------
// Constantes du jeu de données (site-a.json du banc).
// ---------------------------------------------------------------------------------------------
const CONF = {
    BASE: process.env.BASE || 'http://localhost:8100',
    CMID: 4, // Activité « Enseignement personnalisé ».
    COURSEID: 3,
    PASSWORD: 'Banc123!',
    ADMIN: { user: 'admin', password: 'Admin123!' },
    TEACHER: 'ensa_5', // Enseignant qui propose l'EP, puis le gère.
    TEACHER_LABEL: 'Ens 5 Banc a',
    OTHER_TEACHER_LABEL: 'Ens 6 Banc a', // Assure une séance de l'EP.
    DEVE: 'devea_1',
    STUDENT: 'etua_301',
    STUDENT_LABEL: 'Etu 301 Banc a',
    STUDENT_SEARCH: 'Etu 301 Banc', // Recherche par nom dans les listes de la DEVE.
    REFERENT: 'ensa_1', // Référent de STUDENT dans mod_stage.
    ADDED_STUDENT_LABEL: 'Etu 302 Banc a', // Ajouté en cours de session par le responsable.
    WITHDRAWN_STUDENT_LABEL: 'Etu 303 Banc a', // Ajouté puis retiré.
    PREREQ_CODE: 'EP030', // EP du catalogue dont le prérequis (EP020) n'est pas validé par STUDENT.
    STUDYYEAR: 3, // Année courante de la promotion : celle des inscriptions et de la validation.
    SPORT_TYPE: /Sport/, // Type de la déclaration.
    SUPPLEMENT_CATEGORY: /Award|Prix|distinction/i,
    SYNTHESIS_NAME: 'Suivi de l\'enseignement personnalisé',
    CAPTURES: path.resolve(__dirname, '../../guides/captures'),
    DOWNLOADS: path.resolve(__dirname, 'telechargements'),
    RESULTS: path.resolve(__dirname, 'resultats.json'),
    STATE: path.resolve(__dirname, 'etat.json'),
    // Facultatif : débogage développeur le temps du parcours (journalisé, non affiché), puis
    // remis à sa valeur d'origine. null pour ne pas y toucher.
    MOODLE_DIR: '/home/user/moodle-stress/moodle',
    SERVER_LOG: '/home/user/moodle-stress/server.log',
    // Facultatif : vérifications en base. null pour s'en passer.
    PSQL: ['sudo', '-u', 'postgres', 'psql', 'moodlestress', '-At', '-F', '|', '-c'],
    DBPREFIX: 'mdl_',
    TIMEOUT: 180000,
};
// Suffixe propre à l'exécution : le parcours peut être relancé sans collision d'intitulés.
// Reprise partielle (ETAPES=...) : le suffixe et l'état (identifiants, numéro de supplément) de
// l'exécution précédente sont relus dans etat.json.
const PREVIOUS = process.env.ETAPES && fs.existsSync(CONF.STATE) ? JSON.parse(fs.readFileSync(CONF.STATE, 'utf8')) : {};
const RUN = process.env.RUN || PREVIOUS.run || Date.now().toString(36).slice(-4).toUpperCase();
const EP_NAME = `Bien-être animal en élevage (${RUN})`;

// Motifs d'erreurs PHP / Moodle recherchés dans chaque page HTML reçue.
const PATTERNS = [
    /Fatal error[^<]*/, /Maximum execution time[^<]*/, /class="errormessage"[^>]*>[^<]*/,
    /Debug info:[^<]*/, /Error code: [a-z_]+/, /Exception - [^<]*/, /<b>Warning<\/b>:[^<]*/,
    /<b>Notice<\/b>:[^<]*/, /<b>Deprecated<\/b>:[^<]*/, /Coding error detected[^<]*/,
    /class="[^"]*debuggingmessage[^"]*"[^>]*>[^<]*/, /\bWarning: [^<]{0,200}/, /Stack trace:[^<]*/,
];

// ---------------------------------------------------------------------------------------------
// Outils.
// ---------------------------------------------------------------------------------------------
const results = [];
let current = null;
let state = { ...PREVIOUS, run: RUN };
const contexts = {};
let browser;

function url(file, params = {}) {
    const u = new URL(CONF.BASE + file);
    for (const [k, v] of Object.entries(params)) {
        u.searchParams.set(k, String(v));
    }
    return u.toString();
}

function epurl(file, params = {}) {
    return url('/mod/ep/' + file, { id: CONF.CMID, ...params });
}

function saveResults() {
    fs.writeFileSync(CONF.RESULTS, JSON.stringify({
        execution: RUN,
        date: new Date().toISOString(),
        site: CONF.BASE,
        ep: EP_NAME,
        etapes: results,
    }, null, 2));
    fs.writeFileSync(CONF.STATE, JSON.stringify(state, null, 2));
}

function db(sql) {
    if (!CONF.PSQL) {
        return null;
    }
    const [cmd, ...args] = CONF.PSQL;
    return execFileSync(cmd, [...args, sql.replace(/\{(\w+)\}/g, CONF.DBPREFIX + '$1')], { encoding: 'utf8' }).trim();
}

function note(text) {
    if (current) {
        current.remarques.push(text);
    }
}

function anomaly(text) {
    if (current) {
        current.anomalies.push(text);
    }
}

function check(condition, message) {
    if (!condition) {
        throw new Error(message);
    }
}

function pdftext(file) {
    return execFileSync('pdftotext', ['-layout', file, '-'], { encoding: 'utf8' });
}

/** Petit PDF valide d'une page, écrit à la volée (justificatifs et preuves). */
function makePdf(file, text) {
    const content = `BT /F1 14 Tf 60 760 Td (${text.replace(/[()\\]/g, '')}) Tj ET`;
    const objects = [
        '<< /Type /Catalog /Pages 2 0 R >>',
        '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
        '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >>',
        `<< /Length ${content.length} >>\nstream\n${content}\nendstream`,
        '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
    ];
    let out = '%PDF-1.4\n';
    const offsets = [];
    objects.forEach((obj, i) => {
        offsets.push(out.length);
        out += `${i + 1} 0 obj\n${obj}\nendobj\n`;
    });
    const xref = out.length;
    out += `xref\n0 ${objects.length + 1}\n0000000000 65535 f \n`;
    offsets.forEach((o) => {
        out += String(o).padStart(10, '0') + ' 00000 n \n';
    });
    out += `trailer\n<< /Size ${objects.length + 1} /Root 1 0 R >>\nstartxref\n${xref}\n%%EOF\n`;
    fs.writeFileSync(file, out, 'latin1');
    return file;
}

/** Ouvre (ou reprend) la session d'un utilisateur dans son propre contexte de navigation. */
async function session(user, password = CONF.PASSWORD) {
    if (contexts[user]) {
        return contexts[user];
    }
    const context = await browser.newContext({
        viewport: { width: 1280, height: 900 },
        deviceScaleFactor: 1,
        acceptDownloads: true,
        locale: 'fr-FR',
    });
    context.setDefaultTimeout(CONF.TIMEOUT);
    context.setDefaultNavigationTimeout(CONF.TIMEOUT);
    const page = await context.newPage();
    instrument(page, user);
    if (user !== '__anonyme__') {
        await page.goto(url('/login/index.php'));
        await page.fill('#username', user);
        await page.fill('#password', password);
        await Promise.all([page.waitForNavigation({ waitUntil: 'load' }), page.click('#loginbtn')]);
        check(!/login\/index\.php/.test(page.url()), `Connexion de ${user} refusée`);
    }
    contexts[user] = { context, page };
    return contexts[user];
}

/** Relève dans chaque page HTML reçue les erreurs PHP, exceptions et messages de débogage. */
function instrument(page, user) {
    page.on('response', async (response) => {
        try {
            if (response.request().resourceType() !== 'document') {
                return;
            }
            const type = response.headers()['content-type'] || '';
            if (!type.includes('text/html')) {
                return;
            }
            const body = await response.text();
            for (const pattern of PATTERNS) {
                const m = body.match(pattern);
                if (m && current) {
                    current.erreurs_page.push({ utilisateur: user, url: response.url(), message: m[0].slice(0, 300) });
                }
            }
        } catch (e) {
            // Corps indisponible (redirection, téléchargement) : rien à relever.
        }
    });
    page.on('pageerror', (error) => {
        if (current) {
            current.erreurs_page.push({ utilisateur: user, url: page.url(), message: 'JavaScript : ' + error.message });
        }
    });
}

const SHOT_CSS = `
    .performanceinfo, #page-footer .perfdebug { display: none !important; }
    html, body { height: auto !important; overflow: visible !important; }
    #page.drawers, #page-wrapper, #page { height: auto !important; overflow: visible !important; position: static !important; }
    #page.drawers { margin-top: 60px !important; }
    nav.navbar.fixed-top { position: absolute !important; }
    .drawer, .drawer-toggles, #theme_boost-drawers-courseindex, #theme_boost-drawers-blocks { display: none !important; }
    #page.drawers.show-drawer-left, #page.drawers.show-drawer-right { margin-left: 0 !important; margin-right: 0 !important; }
    #page-footer, .btn-footer-popover, .footer-popover { display: none !important; }
`;

/** Capture pleine page, sans le pied de page de performance du banc. */
async function shot(page, name) {
    // Boost 4.x fait défiler #page et non la fenêtre, sous une barre fixe et des tiroirs : pour une
    // capture d'un seul tenant, la page reprend un défilement ordinaire et les tiroirs sont masqués.
    await page.addStyleTag({ content: SHOT_CSS }).catch(() => {});
    await page.evaluate(() => window.scrollTo(0, 0)).catch(() => {});
    const file = path.join(CONF.CAPTURES, name.endsWith('.png') ? name : name + '.png');
    await page.screenshot({ path: file, fullPage: true });
    if (current) {
        current.captures.push(path.relative(path.resolve(__dirname, '../..'), file));
    }
    return file;
}

/** Texte de la zone principale (notifications comprises). */
async function mainText(page) {
    return page.locator('#page-content, #region-main, body').first().innerText();
}

/** Vérifie la présence d'une notification ou d'un texte dans la page. */
async function expectText(page, text, message) {
    const body = await mainText(page);
    const ok = text instanceof RegExp ? text.test(body) : body.includes(text);
    check(ok, (message || 'Texte attendu absent') + ` : « ${text} » (page ${page.url()})`);
}

async function notifications(page) {
    const texts = await page.locator('.alert').allInnerTexts().catch(() => []);
    return texts.map((t) => t.replace(/\s+/g, ' ').replace(/^×\s*/, '').replace(/\s*×$/, '').trim()).filter(Boolean);
}

/** Déplie toutes les sections repliées d'un formulaire Moodle. */
async function expandAll(page) {
    const link = page.locator('a.collapseexpand').first();
    if (await link.count() && /collapsed/.test(await link.getAttribute('class') || '')) {
        await link.click();
    }
    // Repli par section (selon le thème) : chaque en-tête encore replié est ouvert.
    for (const header of await page.locator('fieldset.collapsed legend a, a.fheader[aria-expanded="false"]').all()) {
        await header.click().catch(() => {});
    }
}

async function clickAndWait(page, locator) {
    await Promise.all([page.waitForNavigation({ waitUntil: 'load' }), locator.click()]);
}

/** Choisit une option d'une liste d'après une expression sur son libellé. */
async function selectByText(page, selector, pattern) {
    const value = await page.locator(selector).evaluate((select, src) => {
        const re = new RegExp(src.source, src.flags);
        const option = [...select.options].find((o) => re.test(o.textContent.trim()));
        return option ? option.value : null;
    }, { source: pattern.source, flags: pattern.flags });
    check(value !== null, `Option ${pattern} introuvable dans ${selector}`);
    await page.selectOption(selector, value);
    return value;
}

/** Remplit un sélecteur de date Moodle (date_selector), facultatif ou non. */
async function setDate(page, name, date) {
    const enabled = page.locator(`[name="${name}[enabled]"]`);
    if (await enabled.count()) {
        await enabled.check();
    }
    await page.selectOption(`[name="${name}[day]"]`, String(date.getDate()));
    await page.selectOption(`[name="${name}[month]"]`, String(date.getMonth() + 1));
    await page.selectOption(`[name="${name}[year]"]`, String(date.getFullYear()));
}

/** Dépose un fichier dans un champ « gestionnaire de fichiers » par le sélecteur de fichiers. */
async function uploadFile(page, field, file) {
    const item = page.locator(`#fitem_id_${field}`);
    await item.locator('.fp-btn-add').first().click();
    const picker = page.locator('.file-picker.fp-generallayout:visible').last();
    await picker.waitFor({ state: 'visible' });
    // Le dépôt depuis l'ordinateur est le premier dépôt proposé ; on le choisit explicitement s'il y en a d'autres.
    const upload = picker.locator('.fp-repo').filter({ hasText: /Déposer|Upload|Téléverser|Téléverser|Importer un fichier/i });
    await picker.locator('.fp-repo').first().waitFor({ state: 'visible' });
    if (await upload.count()) {
        await upload.first().click();
    }
    const input = picker.locator('input[type="file"][name="repo_upload_file"]');
    await input.waitFor({ state: 'attached' });
    await input.setInputFiles(file);
    await picker.locator('.fp-upload-btn').click();
    await item.locator('.fp-content').getByText(path.basename(file)).first().waitFor({ state: 'visible' });
}

/** Téléchargement déclenché par un clic : enregistre le fichier et le renvoie. */
async function download(page, action, name) {
    const [dl] = await Promise.all([page.waitForEvent('download'), action()]);
    const file = path.join(CONF.DOWNLOADS, name || dl.suggestedFilename());
    await dl.saveAs(file);
    const head = fs.readFileSync(file).subarray(0, 5).toString('latin1');
    check(head === '%PDF-', `Le fichier téléchargé ${path.basename(file)} n'est pas un PDF`);
    if (current) {
        current.telechargements.push(path.relative(path.resolve(__dirname, '../..'), file));
    }
    return file;
}

/** Une étape du parcours : chronométrée, journalisée, capturée en cas d'échec. */
async function step(id, role, action, attendu, fn) {
    current = {
        etape: id, role, action, attendu, obtenu: '', resultat: '', duree_s: 0,
        captures: [], telechargements: [], erreurs_page: [], anomalies: [], remarques: [],
    };
    const started = Date.now();
    console.error(`\n== ${id} [${role}] ${action}`);
    try {
        const obtained = await fn();
        current.obtenu = obtained || 'Conforme à l\'attendu.';
        current.resultat = current.anomalies.length || current.erreurs_page.length ? 'réussi avec anomalie' : 'réussi';
    } catch (e) {
        current.resultat = 'échec';
        current.obtenu = e.message.split('\n').slice(0, 3).join(' ');
        // Capture de l'écran fautif, de chaque session ouverte sur la page en cours.
        for (const [user, { page }] of Object.entries(contexts)) {
            if (page.url().startsWith(CONF.BASE)) {
                await page.screenshot({ path: path.join(CONF.DOWNLOADS, `echec-${id}-${user}.png`), fullPage: true })
                    .catch(() => {});
            }
        }
    }
    current.duree_s = Math.round((Date.now() - started) / 100) / 10;
    console.error(`   -> ${current.resultat} (${current.duree_s} s) ${current.obtenu}`);
    for (const a of current.anomalies) {
        console.error('   ! ' + a);
    }
    for (const e of current.erreurs_page) {
        console.error('   ! erreur page : ' + e.message);
    }
    results.push(current);
    saveResults();
    return current.resultat !== 'échec';
}

// ---------------------------------------------------------------------------------------------
// Procédures.
// ---------------------------------------------------------------------------------------------
const day = (offset) => {
    const d = new Date();
    d.setDate(d.getDate() + offset);
    return d;
};

const SYLLABUS = {
    description: 'Approche pratique du bien-être des animaux d\'élevage : évaluation en exploitation, réglementation, '
        + 'leviers d\'amélioration.',
    objectives: 'Savoir évaluer le bien-être d\'un lot d\'animaux en élevage et proposer des mesures correctives '
        + 'réalistes à l\'éleveur.',
    outcomes: '- appliquer un protocole d\'évaluation (Welfare Quality) en élevage bovin et porcin ;\n'
        + '- interpréter les indicateurs fondés sur l\'animal ;\n- rédiger un compte rendu pour l\'éleveur.',
    prerequisitestext: 'Éthologie et zootechnie de 2e année.',
    content: 'Concepts et définitions du bien-être ; réglementation européenne ; indicateurs fondés sur l\'animal et '
        + 'sur les ressources ; visites d\'élevages ; restitution à l\'éleveur.',
    methods: 'Cours magistraux, travaux dirigés sur cas, deux demi-journées en élevage.',
    assessment: 'Compte rendu écrit d\'une évaluation en élevage (60 %) et oral de restitution (40 %).',
    resit: 'Oral de 20 minutes sur un cas fourni.',
    bibliography: 'Broom D. M., Fraser A. F., Domestic Animal Behaviour and Welfare, CABI, 2015.',
    language: 'Français',
    personalhours: '20',
};

const SESSIONS = [
    { title: 'Concepts et réglementation du bien-être animal', kind: 'lecture', duration: '4', teacher: CONF.TEACHER_LABEL, date: day(30) },
    { title: 'Indicateurs fondés sur l\'animal : travaux dirigés', kind: 'tutorial', duration: '3', teacher: CONF.OTHER_TEACHER_LABEL },
    { title: 'Visite et évaluation en élevage porcin', kind: 'fieldwork', duration: '4', external: 'Dr Claire Martin, vétérinaire praticienne' },
    { title: 'Restitution à l\'éleveur : jeu de rôle', kind: 'project', duration: '2', teacher: CONF.TEACHER_LABEL },
];

async function fillSession(page, index, session) {
    await page.fill(`[name="sessionrow[${index}][sessiontitle]"]`, session.title);
    await page.selectOption(`[name="sessionrow[${index}][sessionkind]"]`, session.kind);
    await page.fill(`[name="sessionrow[${index}][sessionduration]"]`, session.duration);
    if (session.teacher) {
        await selectByText(page, `[name="sessionrow[${index}][sessionteacher]"]`, new RegExp('^' + session.teacher + '$'));
    }
    if (session.external) {
        await page.selectOption(`[name="sessionrow[${index}][sessionteacher]"]`, '0');
        await page.fill(`[name="sessionrow[${index}][sessionteachername]"]`, session.external);
    }
    if (session.date) {
        await setDate(page, `sessiondate[${index}]`, session.date);
    }
}

async function procedure1() {
    await step('1.1', 'enseignant (ensa_5)', 'Ouvrir « Propositions et syllabus » depuis l\'activité',
        'La page liste les propositions de l\'enseignant et propose « Proposer un nouvel EP ».', async () => {
            const { page } = await session(CONF.TEACHER);
            await page.goto(epurl('view.php'));
            const link = page.locator('a[href*="/mod/ep/proposals.php"]').first();
            check(await link.count(), 'Pas de lien vers les propositions depuis la page d\'accueil de l\'activité');
            await clickAndWait(page, link);
            await expectText(page, 'Proposer un nouvel EP');
            await shot(page, '01-enseignant-propositions');
        });

    await step('1.2', 'enseignant (ensa_5)', 'Rédiger la proposition : caractéristiques, syllabus, quatre séances '
        + '(dont une par un autre enseignant et une par un intervenant extérieur), enregistrer le brouillon',
    'Brouillon enregistré (« Syllabus enregistré. »), les quatre séances sont relues dans le formulaire.', async () => {
        const { page } = await session(CONF.TEACHER);
        await clickAndWait(page, page.locator('a[href*="syllabus_edit.php"][href*="activityid=0"]').first());
        await expandAll(page);
        await page.fill('#id_name', EP_NAME);
        await page.fill('#id_ects', '6');
        await page.fill('#id_capacity', '30');
        await page.selectOption('#id_minstudyyear', String(CONF.STUDYYEAR));
        await page.selectOption('#id_maxstudyyear', String(CONF.STUDYYEAR + 1));
        await page.fill('#id_description', SYLLABUS.description);
        for (const field of ['objectives', 'outcomes', 'prerequisitestext', 'content', 'methods', 'assessment', 'resit', 'bibliography']) {
            await page.fill(`#id_${field}`, SYLLABUS[field]);
        }
        await page.fill('#id_language', SYLLABUS.language);
        await page.fill('#id_personalhours', SYLLABUS.personalhours);
        const rows = await page.locator('[name$="[sessiontitle]"]').count();
        for (let i = 0; i < Math.min(rows, 3); i++) {
            await fillSession(page, i, SESSIONS[i]);
        }
        // Lignes supplémentaires : le bouton recharge le formulaire en gardant la saisie.
        await clickAndWait(page, page.locator('#id_addsessions'));
        await expandAll(page);
        const kept = await page.inputValue('[name="sessionrow[2][sessiontitle]"]');
        if (kept !== SESSIONS[2].title) {
            anomaly(`« Ajouter des séances » a perdu la saisie de la 3e séance (relu : « ${kept} »).`);
        }
        const kepttitle = await page.inputValue('#id_name');
        check(kepttitle === EP_NAME, 'La saisie de l\'intitulé est perdue après « Ajouter des séances »');
        await fillSession(page, 3, SESSIONS[3]);
        await shot(page, '01-enseignant-proposer-ep-formulaire');
        await clickAndWait(page, page.locator('#id_submitbutton'));
        await expectText(page, 'Syllabus enregistré.');
        const m = page.url().match(/activityid=(\d+)/);
        check(m && m[1] !== '0', 'Identifiant du nouvel EP absent de l\'URL après enregistrement');
        state.activityid = Number(m[1]);
        state.epname = EP_NAME;
        await expandAll(page);
        for (let i = 0; i < SESSIONS.length; i++) {
            const title = await page.inputValue(`[name="sessionrow[${i}][sessiontitle]"]`);
            check(title === SESSIONS[i].title, `Séance ${i + 1} non relue après enregistrement (« ${title} »)`);
        }
        const external = await page.inputValue('[name="sessionrow[2][sessionteachername]"]');
        check(external === SESSIONS[2].external, 'Intervenant extérieur non relu');
        const other = await page.locator('[name="sessionrow[1][sessionteacher]"] option:checked').innerText();
        check(other.trim() === CONF.OTHER_TEACHER_LABEL, `Intervenant de la séance 2 non relu (« ${other} »)`);
        const date0 = await page.locator('[name="sessiondate[0][enabled]"]').isChecked();
        if (!date0) {
            anomaly('La date de la première séance n\'est pas relue après enregistrement.');
        }
        await shot(page, '01-enseignant-brouillon-enregistre');
        return `EP n° ${state.activityid} créé en brouillon, ${SESSIONS.length} séances relues.`;
    });

    await step('1.3', 'enseignant (ensa_5)', 'Soumettre la proposition à la DEVE',
        'Redirection vers la fiche de l\'EP, message « Syllabus soumis à la DEVE. », version 1 « Soumise ».', async () => {
            const { page } = await session(CONF.TEACHER);
            await page.goto(epurl('syllabus_edit.php', { activityid: state.activityid }));
            await clickAndWait(page, page.locator('#id_submittodeve'));
            await expectText(page, 'Syllabus soumis à la DEVE.');
            check(/syllabus\.php/.test(page.url()), 'Pas de retour sur la fiche de l\'EP');
            await shot(page, '01-enseignant-syllabus-soumis');
            if (CONF.PSQL) {
                const row = db(`SELECT a.state, s.status, s.version FROM {ep_activity} a JOIN {ep_syllabus} s ON s.activityid = a.id WHERE a.id = ${state.activityid}`);
                check(row === 'proposed|submitted|1', `État en base inattendu : ${row}`);
                const n = db(`SELECT count(*) FROM {ep_session} se JOIN {ep_syllabus} s ON s.id = se.syllabusid WHERE s.activityid = ${state.activityid}`);
                check(n === String(SESSIONS.length), `Séances en base : ${n}`);
            }
            const pdfbutton = await page.locator('a[href*="syllabus_pdf.php"]').count();
            if (!pdfbutton) {
                note('La fiche d\'un EP non publié n\'offre pas de bouton « Fiche PDF » : syllabus_pdf.php accepte pourtant version=working pour le rédacteur et la DEVE.');
            }
        });

    await step('1.4', 'enseignant (ensa_5)', 'Télécharger la fiche PDF du syllabus (version de travail)',
        'Un PDF contenant l\'intitulé, les rubriques et les séances (dont l\'intervenant extérieur).', async () => {
            const { page, context } = await session(CONF.TEACHER);
            // Aucun bouton pour une version non publiée : l'adresse prévue par syllabus_pdf.php est ouverte directement.
            const response = await context.request.get(epurl('syllabus_pdf.php', { activityid: state.activityid, version: 'working' }));
            check(response.ok(), `syllabus_pdf.php?version=working répond ${response.status()}`);
            const file = path.join(CONF.DOWNLOADS, `syllabus-${RUN}-v1-travail.pdf`);
            fs.writeFileSync(file, await response.body());
            current.telechargements.push(path.relative(path.resolve(__dirname, '../..'), file));
            check(fs.readFileSync(file).subarray(0, 5).toString() === '%PDF-', 'Réponse non PDF');
            const text = pdftext(file);
            const missing = [EP_NAME.split(' (')[0], 'Welfare Quality', SESSIONS[1].title.slice(0, 30), 'Claire Martin', 'Ens 6']
                .filter((t) => !text.replace(/\s+/g, ' ').includes(t));
            if (missing.length) {
                anomaly('Fiche PDF : contenu attendu absent : ' + missing.join(' ; '));
            }
            note('Téléchargement par l\'adresse syllabus_pdf.php?version=working, faute de bouton dans l\'interface.');
            await page.goto(epurl('syllabus.php', { activityid: state.activityid }));
            return `PDF de ${fs.statSync(file).size} octets` + (missing.length ? ', contenu incomplet' : ', contenu vérifié');
        });
}

async function procedure2() {
    await step('2.1', 'DEVE (devea_1)', 'Examiner la proposition et la renvoyer à l\'auteur avec un commentaire',
        'La proposition figure dans « À examiner » ; après renvoi : « Décision enregistrée : version renvoyée pour modification. »',
        async () => {
            const { page } = await session(CONF.DEVE);
            await page.goto(epurl('view.php'));
            await clickAndWait(page, page.locator('a[href*="/mod/ep/proposals.php"]').first());
            const row = page.locator('table tr', { hasText: EP_NAME });
            check(await row.count(), 'La proposition n\'apparaît pas dans la liste de la DEVE');
            await shot(page, '02-deve-propositions-a-examiner');
            await clickAndWait(page, row.first().locator('a[href*="syllabus.php"]').first());
            await page.fill('#reviewcomment', 'Merci de préciser le barème de l\'oral de restitution et la date de la visite en élevage.');
            await shot(page, '02-deve-renvoyer-proposition');
            await clickAndWait(page, page.locator('button[name="decision"][value="return"]'));
            await expectText(page, 'renvoyée pour modification');
            await shot(page, '02-deve-proposition-renvoyee');
        });

    await step('2.2', 'enseignant (ensa_5)', 'Lire le commentaire de la DEVE, corriger et soumettre de nouveau',
        'Le commentaire de la DEVE est affiché dans le formulaire ; nouvelle soumission acceptée.', async () => {
            const { page } = await session(CONF.TEACHER);
            await page.goto(epurl('proposals.php'));
            const row = page.locator('table tr', { hasText: EP_NAME }).first();
            check(await row.count(), 'La proposition renvoyée n\'apparaît pas dans « Mes propositions »');
            const rowtext = await row.innerText();
            if (!/Renvoy/i.test(rowtext)) {
                anomaly('Le statut « renvoyée » n\'apparaît pas dans la liste : ' + rowtext.replace(/\s+/g, ' '));
            }
            await shot(page, '02-enseignant-propositions-renvoyee');
            await clickAndWait(page, row.locator('a[href*="syllabus_edit.php"]').first());
            await expectText(page, 'préciser le barème', 'Commentaire de la DEVE absent du formulaire');
            await expandAll(page);
            await page.fill('#id_assessment', SYLLABUS.assessment + '\nOral : 10 min de présentation, 10 min de questions ; '
                + 'grille critériée remise en début d\'EP.');
            await setDate(page, 'sessiondate[2]', day(45));
            await shot(page, '02-enseignant-corriger-proposition');
            await clickAndWait(page, page.locator('#id_submittodeve'));
            await expectText(page, 'Syllabus soumis à la DEVE.');
        });

    await step('2.3', 'DEVE (devea_1)', 'Publier la proposition corrigée',
        '« Décision enregistrée : version publiée. » ; l\'EP passe au catalogue.', async () => {
            const { page } = await session(CONF.DEVE);
            await page.goto(epurl('proposals.php'));
            const row = page.locator('table tr', { hasText: EP_NAME }).first();
            check(await row.count(), 'La proposition resoumise n\'apparaît pas');
            await clickAndWait(page, row.locator('a[href*="syllabus.php"]').first());
            await expectText(page, 'Oral : 10 min', 'La correction de l\'auteur n\'est pas visible par la DEVE');
            await page.fill('#reviewcomment', 'Publié, merci.');
            await clickAndWait(page, page.locator('button[name="decision"][value="publish"]'));
            await expectText(page, 'publiée');
            await shot(page, '02-deve-ep-publie');
        });

    await step('2.4', 'DEVE (devea_1)', 'Vérifier que l\'EP apparaît au catalogue',
        'L\'EP figure au catalogue avec son lien « Syllabus », ses ECTS et ses responsables.', async () => {
            const { page } = await session(CONF.DEVE);
            await page.goto(epurl('view.php'));
            await clickAndWait(page, page.locator('a[href*="/mod/ep/catalog.php"]').first());
            const row = page.locator('table tr', { hasText: EP_NAME }).first();
            check(await row.count(), 'EP absent du catalogue');
            const text = (await row.innerText()).replace(/\s+/g, ' ');
            check(/Syllabus/i.test(text), 'Pas de lien vers le syllabus dans le catalogue');
            check(text.includes(CONF.TEACHER_LABEL), 'Responsable absent du catalogue : ' + text);
            if (/brouillon|propos/i.test(text)) {
                anomaly('Le catalogue affiche encore un état non publié : ' + text);
            }
            await row.scrollIntoViewIfNeeded();
            await shot(page, '02-deve-catalogue-ep-publie');
            return 'Ligne du catalogue : ' + text.slice(0, 200);
        });
}

async function procedure3() {
    await step('3.1', 'enseignant (ensa_5)', 'Télécharger la fiche PDF de l\'EP publié (bouton « Fiche PDF »)',
        'Le PDF de la version 1 publiée.', async () => {
            const { page } = await session(CONF.TEACHER);
            await page.goto(epurl('syllabus.php', { activityid: state.activityid }));
            const button = page.locator('a[href*="syllabus_pdf.php"]').first();
            check(await button.count(), 'Pas de bouton « Fiche PDF » sur la fiche publiée');
            const file = await download(page, () => button.click(), `syllabus-${RUN}-v1-publiee.pdf`);
            const text = pdftext(file).replace(/\s+/g, ' ');
            check(text.includes('Welfare Quality'), 'Le PDF ne contient pas le syllabus');
            await shot(page, '03-enseignant-fiche-ep-publiee');
        });

    await step('3.2', 'enseignant (ensa_5)', 'Ouvrir une révision du syllabus publié, modifier le contenu, soumettre',
        'Le lien « Proposer une mise à jour » ouvre le formulaire prérempli ; soumission acceptée (version 2).', async () => {
            const { page } = await session(CONF.TEACHER);
            await page.goto(epurl('proposals.php'));
            const row = page.locator('table tr', { hasText: EP_NAME }).last();
            const link = row.locator('a[href*="syllabus_edit.php"]');
            check(await link.count(), 'Pas de lien « Proposer une mise à jour » pour l\'EP publié');
            await shot(page, '03-enseignant-proposer-mise-a-jour');
            await clickAndWait(page, link.first());
            await expandAll(page);
            check(await page.locator('#id_name').count() === 0, 'Les caractéristiques d\'un EP publié restent modifiables par l\'enseignant');
            const before = await page.inputValue('#id_content');
            check(before === SYLLABUS.content, 'Le formulaire de révision n\'est pas prérempli avec la version publiée');
            await page.fill('#id_content', SYLLABUS.content + ' Nouveau en 2026 : module sur le bien-être des volailles '
                + 'en bâtiment.');
            const rows = await page.locator('[name$="[sessiontitle]"]').count();
            check(rows >= SESSIONS.length, 'Séances publiées non reprises dans la révision');
            await fillSession(page, SESSIONS.length, {
                title: 'Bien-être des volailles en bâtiment', kind: 'lecture', duration: '2', teacher: CONF.TEACHER_LABEL,
            });
            await shot(page, '03-enseignant-revision-syllabus');
            await clickAndWait(page, page.locator('#id_submittodeve'));
            await expectText(page, 'Syllabus soumis à la DEVE.');
            await expectText(page, 'volailles', 'La version en révision n\'est pas affichée sur la fiche');
            await shot(page, '03-enseignant-revision-soumise');
        });

    await step('3.3', 'DEVE (devea_1)', 'Publier la révision',
        'La version 2 remplace la version 1 au catalogue.', async () => {
            const { page } = await session(CONF.DEVE);
            await page.goto(epurl('proposals.php'));
            const row = page.locator('table tr', { hasText: EP_NAME }).first();
            check(await row.count(), 'La révision n\'apparaît pas dans « À examiner »');
            await shot(page, '03-deve-revision-a-examiner');
            await clickAndWait(page, row.locator('a[href*="syllabus.php"]').first());
            await shot(page, '03-deve-comparer-versions');
            await clickAndWait(page, page.locator('button[name="decision"][value="publish"]'));
            await expectText(page, 'publiée');
            await expectText(page, 'volailles');
            await expectText(page, /Version 2/i, 'La fiche n\'indique pas la version 2');
        });

    await step('3.4', 'enseignant (ensa_5)', 'Vérifier que l\'ancienne version reste dans l\'historique',
        'La version 1 est conservée (statut « Remplacé ») et consultable.', async () => {
            const { page } = await session(CONF.TEACHER);
            await page.goto(epurl('syllabus.php', { activityid: state.activityid }));
            const body = await mainText(page);
            const shown = /Version 1\b/.test(body) || /Remplac/i.test(body) || /historique/i.test(body);
            if (CONF.PSQL) {
                const rows = db(`SELECT version || ':' || status FROM {ep_syllabus} WHERE activityid = ${state.activityid} ORDER BY version`);
                check(rows.split('\n').includes('1:superseded'), 'Version 1 non conservée en base : ' + rows);
                note('En base : ' + rows.replace(/\n/g, ', '));
            }
            await shot(page, '03-enseignant-fiche-version-2');
            const file = await download(page, () => page.locator('a[href*="syllabus_pdf.php"]').first().click(), `syllabus-${RUN}-v2.pdf`);
            check(pdftext(file).includes('volailles'), 'La fiche PDF n\'est pas celle de la version 2');
            if (!shown) {
                anomaly('Aucun écran ne montre l\'historique des versions : la version 1 est conservée en base (« superseded ») '
                    + 'mais n\'est consultable nulle part dans l\'interface (syllabus.php n\'affiche que la version publiée et la '
                    + 'version de travail).');
            }
            return shown ? 'Historique visible.' : 'Version 1 conservée en base seulement.';
        });
}

async function procedure4() {
    await step('4.1', 'étudiant (etua_301)', 'S\'inscrire à l\'EP depuis le catalogue',
        'Page de confirmation (année d\'étude) puis « Inscription enregistrée, en attente de validation par le responsable. »',
        async () => {
            const { page } = await session(CONF.STUDENT);
            await page.goto(epurl('view.php'));
            await clickAndWait(page, page.locator('a[href*="/mod/ep/catalog.php"]').first());
            const row = page.locator('table tr', { hasText: EP_NAME }).first();
            check(await row.count(), 'EP absent du catalogue de l\'étudiant');
            const register = row.locator('a[href*="action=register"]');
            check(await register.count(), 'Pas de bouton « S\'inscrire » : ' + (await row.innerText()).replace(/\s+/g, ' '));
            await clickAndWait(page, register.first());
            await expectText(page, '6 ECTS', 'Le nombre d\'ECTS n\'est pas rappelé');
            await page.selectOption('#studyyear', String(CONF.STUDYYEAR));
            await shot(page, '04-etudiant-inscription-confirmer');
            await clickAndWait(page, page.locator('form[action*="action=register"] input[type="submit"]'));
            await expectText(page, 'Inscription enregistrée');
            await shot(page, '04-etudiant-inscription-enregistree');
        });

    await step('4.2', 'étudiant (etua_301)', 'Déclarer une activité sportive avec un justificatif PDF',
        '« Demande enregistrée, en attente de validation. » ; le référent est annoncé sur le formulaire.', async () => {
            const { page } = await session(CONF.STUDENT);
            await page.goto(epurl('view.php'));
            await clickAndWait(page, page.locator('a[href*="/mod/ep/declare.php"]').first());
            await expectText(page, 'Ens 1', 'Le référent de l\'étudiant n\'est pas annoncé');
            state.declaration = `Section handball universitaire (${RUN})`;
            await selectByText(page, '#id_typeid', CONF.SPORT_TYPE);
            await page.fill('#id_name', state.declaration);
            await page.fill('#id_description', 'Entraînements hebdomadaires et championnat universitaire régional, saison 2026-2027.');
            await page.selectOption('#id_studyyear', String(CONF.STUDYYEAR));
            await page.fill('#id_claimedects', '4');
            const pdf = makePdf(path.join(CONF.DOWNLOADS, 'attestation-club-handball.pdf'), 'Attestation de licence - handball universitaire');
            await uploadFile(page, 'evidence', pdf);
            await shot(page, '04-etudiant-declarer-activite');
            await clickAndWait(page, page.locator('#id_submitbutton'));
            await expectText(page, 'Demande enregistrée');
            await shot(page, '04-etudiant-declaration-enregistree');
            if (CONF.PSQL) {
                const row = db(`SELECT c.id, c.status, c.studyyear, (SELECT count(*) FROM {files} f WHERE f.component = 'mod_ep' AND f.filearea = 'evidence' AND f.itemid = c.id AND f.filename <> '.') FROM {ep_credit} c WHERE c.name = '${state.declaration.replace(/'/g, "''")}'`);
                const [id, status, year, files] = row.split('|');
                state.declarationid = Number(id);
                check(status === '0' && year === String(CONF.STUDYYEAR) && files === '1', 'Déclaration en base : ' + row);
            }
        });

    await step('4.3', 'étudiant (etua_301)', 'Demander l\'ajout d\'un élément au supplément au diplôme, avec une preuve PDF',
        '« Demande enregistrée. » ; l\'élément apparaît « En attente » dans « Mon supplément ».', async () => {
            const { page } = await session(CONF.STUDENT);
            await page.goto(epurl('view.php'));
            await clickAndWait(page, page.locator('a[href*="/mod/ep/supplement.php"]').first());
            await clickAndWait(page, page.locator('form[action*="supplement_item.php"] button, form[action*="supplement_item.php"] input[type="submit"]').first());
            state.item = `Prix du meilleur poster étudiant, congrès AFVAC (${RUN})`;
            await selectByText(page, '#id_categoryid', CONF.SUPPLEMENT_CATEGORY);
            await page.fill('#id_title', state.item);
            await page.fill('#id_titleen', 'Best student poster award, AFVAC congress');
            await page.fill('#id_description', 'Poster sur la prévalence des boiteries en élevage laitier, primé au congrès national.');
            await setDate(page, 'periodstart', day(-40));
            await setDate(page, 'periodend', day(-38));
            const pdf = makePdf(path.join(CONF.DOWNLOADS, 'diplome-prix-poster.pdf'), 'Prix du meilleur poster etudiant - AFVAC');
            await uploadFile(page, 'proofs', pdf);
            await shot(page, '04-etudiant-demander-element-supplement');
            await clickAndWait(page, page.locator('#id_submitbutton'));
            await expectText(page, 'Demande enregistrée.');
            const row = page.locator('table tr', { hasText: state.item }).first();
            check(await row.count(), 'L\'élément demandé n\'apparaît pas dans « Mon supplément »');
            const text = (await row.innerText()).replace(/\s+/g, ' ');
            check(/attente/i.test(text), 'Statut inattendu : ' + text);
            check(await row.locator('a[href*="supplement_file.php"]').count(), 'Preuve non listée pour l\'élément');
        });

    await step('4.4', 'étudiant (etua_301)', 'Consulter son tableau de bord et l\'aperçu de son supplément',
        'Le tableau de bord montre l\'inscription et la déclaration en attente ; l\'aperçu PDF est marqué non certifié.', async () => {
            const { page } = await session(CONF.STUDENT);
            await page.goto(epurl('view.php'));
            const body = await mainText(page);
            check(body.includes(EP_NAME), 'Inscription absente du tableau de bord');
            check(body.includes(state.declaration), 'Déclaration absente du tableau de bord');
            await shot(page, '04-etudiant-tableau-de-bord');
            await page.goto(epurl('supplement.php'));
            await shot(page, '04-etudiant-mon-supplement');
            const file = await download(page, () => page.locator('form:has(select[name="lang"]) [type="submit"]').first().click(),
                `apercu-supplement-${RUN}.pdf`);
            // Filigrane oblique : lu en extraction brute, espaces et retours à la ligne retirés.
            const text = execFileSync('pdftotext', ['-raw', file, '-'], { encoding: 'utf8' }).replace(/\s+/g, '');
            if (!/NONCERTIFI|NOTCERTIFIED/i.test(text)) {
                anomaly('L\'aperçu du supplément ne porte pas de mention « non certifié » lisible.');
            }
            return `Aperçu PDF de ${fs.statSync(file).size} octets.`;
        });
}

async function procedure5() {
    await step('5.1', 'enseignant responsable (ensa_5)', 'Ouvrir la liste des participants de son EP',
        'Depuis « Validation » > « Mes EP », la liste montre etua_301 en attente.', async () => {
            const { page } = await session(CONF.TEACHER);
            await page.goto(epurl('view.php'));
            const validation = page.locator('a[href*="/mod/ep/credits.php"]').first();
            check(await validation.count(), 'Pas de lien « Validation » pour le responsable');
            await clickAndWait(page, validation);
            const row = page.locator('table tr', { hasText: EP_NAME }).first();
            check(await row.count(), 'L\'EP n\'est pas listé dans « Mes EP » de la page Validation');
            await shot(page, '05-enseignant-validation-mes-ep');
            await clickAndWait(page, row.locator('a[href*="participants.php"]').first());
            await expectText(page, CONF.STUDENT_LABEL, 'L\'étudiant inscrit n\'apparaît pas');
        });

    await step('5.2', 'enseignant responsable (ensa_5)', 'Valider l\'inscription d\'etua_301 dans la liste',
        '« Décisions enregistrées : 1 validé(s)… » ; etua_301 « Validé » avec 6 ECTS retenus.', async () => {
            const { page } = await session(CONF.TEACHER);
            await page.goto(epurl('participants.php', { activityid: state.activityid }));
            const row = page.locator('table tr', { hasText: CONF.STUDENT_LABEL }).first();
            await row.locator('select[name^="decision["]').selectOption('validate');
            await row.locator('input[name^="comment["]').fill('Assiduité complète, compte rendu de qualité.');
            await shot(page, '05-enseignant-participants-valider');
            await clickAndWait(page, page.locator('form:has(input[name="action"][value="decide"]) input[type="submit"]'));
            await expectText(page, /1 validé/);
            const after = (await page.locator('table tr', { hasText: CONF.STUDENT_LABEL }).first().innerText()).replace(/\s+/g, ' ');
            check(/Valid/i.test(after) && /6/.test(after), 'Ligne après décision : ' + after);
            return 'Ligne : ' + after;
        });

    await step('5.3', 'enseignant responsable (ensa_5)', 'Inscrire deux étudiants en cours de session (etua_302, etua_303)',
        '« 2 étudiant(s) inscrit(s). » ; les deux apparaissent avec la mention « Ajouté par ».', async () => {
            const { page } = await session(CONF.TEACHER);
            await page.goto(epurl('participants.php', { activityid: state.activityid }));
            const select = '#addparticipants_userids';
            const values = await page.locator(select).evaluate((el, labels) => [...el.options]
                .filter((o) => labels.some((l) => o.textContent.trim().startsWith(l + ' ') || o.textContent.trim() === l))
                .map((o) => o.value), [CONF.ADDED_STUDENT_LABEL, CONF.WITHDRAWN_STUDENT_LABEL]);
            check(values.length === 2, `Étudiants à ajouter introuvables dans la liste (${values.length})`);
            const count = await page.locator(`${select} option`).count();
            note(`La liste d'ajout est une liste à sélection multiple de ${count} noms, sans recherche.`);
            await page.selectOption(select, values);
            await page.locator(select).scrollIntoViewIfNeeded();
            await shot(page, '05-enseignant-inscrire-etudiants');
            await clickAndWait(page, page.locator('form:has(input[name="action"][value="add"]) input[type="submit"]'));
            await expectText(page, '2 étudiant(s) inscrit(s).');
            for (const label of [CONF.ADDED_STUDENT_LABEL, CONF.WITHDRAWN_STUDENT_LABEL]) {
                const text = (await page.locator('table tr', { hasText: label }).first().innerText()).replace(/\s+/g, ' ');
                check(/attente/i.test(text), `${label} : ${text}`);
                if (!/(Ajouté|Inscrit) par/i.test(text)) {
                    anomaly(`${label} : la mention de l'auteur de l'ajout est absente.`);
                }
            }
        });

    await step('5.4', 'enseignant responsable (ensa_5)', 'Retirer un participant (etua_303) avec un motif',
        'Page de confirmation, puis « Participant retiré. » ; etua_303 apparaît « Retiré ».', async () => {
            const { page } = await session(CONF.TEACHER);
            await page.goto(epurl('participants.php', { activityid: state.activityid }));
            const row = page.locator('table tr', { hasText: CONF.WITHDRAWN_STUDENT_LABEL }).first();
            await clickAndWait(page, row.locator('a[href*="action=withdraw"]'));
            await page.fill('#withdrawreason', 'Inscrit par erreur : l\'étudiant suit un autre EP sur le même créneau.');
            await shot(page, '05-enseignant-retirer-participant');
            await clickAndWait(page, page.locator('form[action*="action=withdraw"] input[type="submit"]'));
            await expectText(page, 'Participant retiré.');
            const text = (await page.locator('table tr', { hasText: CONF.WITHDRAWN_STUDENT_LABEL }).first().innerText()).replace(/\s+/g, ' ');
            check(/Retir|Annul/i.test(text), 'Statut après retrait : ' + text);
            await shot(page, '05-enseignant-participants-apres');
            return 'etua_303 : ' + text;
        });
}

async function ensureSynthesis() {
    await step('6.0', 'administrateur (admin)', 'Préparer une activité « Suivi de l\'enseignement personnalisé » liée à l\'activité EP',
        'Une activité epsynthesis existe dans le cours et est liée au cmid de l\'EP.', async () => {
            const { page } = await session(CONF.ADMIN.user, CONF.ADMIN.password);
            await page.goto(url('/mod/epsynthesis/index.php', { id: CONF.COURSEID }));
            let link = page.locator('#region-main a[href*="/mod/epsynthesis/view.php"]').first();
            if (!(await link.count())) {
                await page.goto(url('/course/modedit.php', { add: 'epsynthesis', type: '', course: CONF.COURSEID, section: 0, return: 0, sr: 0 }));
                await page.fill('#id_name', CONF.SYNTHESIS_NAME);
                await shot(page, '06-admin-creer-synthese');
                await clickAndWait(page, page.locator('#id_submitbutton'));
                note('Activité epsynthesis créée par l\'interface d\'ajout d\'activité du cours.');
                await page.goto(url('/mod/epsynthesis/index.php', { id: CONF.COURSEID }));
                link = page.locator('#region-main a[href*="/mod/epsynthesis/view.php"]').first();
            }
            const href = await link.getAttribute('href');
            state.synthesiscmid = Number(new URL(href, CONF.BASE).searchParams.get('id'));
            await page.goto(url('/mod/epsynthesis/administration.php', { id: state.synthesiscmid }));
            const box = page.locator(`input[name="epcmid[]"][value="${CONF.CMID}"]`);
            check(await box.count(), 'L\'activité EP n\'est pas proposée au lien');
            if (!(await box.isChecked())) {
                await box.check();
                await shot(page, '06-admin-lier-synthese');
                await clickAndWait(page, page.locator('#region-main form input[type="submit"], #region-main form button[type="submit"]').first());
                await expectText(page, /enregistr/i);
            }
            return `Synthèse cmid ${state.synthesiscmid}, liée à cmid ${CONF.CMID}.`;
        });
}

async function procedure6() {
    await step('6.1', 'enseignant référent (ensa_1)', 'Valider la déclaration sportive d\'etua_301 (validate.php)',
        'La déclaration est dans « En attente de ma décision » ; le justificatif s\'ouvre ; « EP validé. »', async () => {
            const { page } = await session(CONF.REFERENT);
            await page.goto(epurl('view.php'));
            await clickAndWait(page, page.locator('a[href*="/mod/ep/credits.php"]').first());
            const row = page.locator('table tr', { hasText: state.declaration }).first();
            check(await row.count(), 'La déclaration n\'est pas dans la file du référent');
            await shot(page, '06-referent-validation-file');
            await clickAndWait(page, row.locator('a[href*="validate.php"]').first());
            const evidence = page.locator('a[href*="evidence_file.php"]').first();
            check(await evidence.count(), 'Justificatif non listé');
            await download(page, () => evidence.click(), `justificatif-${RUN}.pdf`).catch((e) => {
                anomaly('Le justificatif ne se télécharge pas : ' + e.message.split('\n')[0]);
            });
            await page.fill('#validatorcomment', 'Licence et calendrier des matchs vérifiés.');
            const retained = await page.inputValue('#retainedects');
            check(Number(retained) === 4, 'ECTS proposés : ' + retained);
            await shot(page, '06-referent-valider-declaration');
            await clickAndWait(page, page.locator('input[name="validatecredit"]'));
            await expectText(page, 'EP validé.');
            if (CONF.PSQL && state.declarationid) {
                const st = db(`SELECT status || '|' || retainedects FROM {ep_credit} WHERE id = ${state.declarationid}`);
                check(st === '1|4.00', 'Déclaration en base : ' + st);
            }
        });

    await step('6.2', 'enseignant référent (ensa_1)', 'Vérifier la page « À traiter » de la synthèse avant décision',
        'L\'élément de supplément d\'etua_301 y figure, avec ses boutons de décision.', async () => {
            check(state.synthesiscmid, 'Pas d\'activité de synthèse');
            const { page } = await session(CONF.REFERENT);
            await page.goto(url('/mod/epsynthesis/view.php', { id: state.synthesiscmid }));
            await shot(page, '06-referent-synthese-accueil');
            const todo = page.locator('a[href*="/mod/epsynthesis/todo.php"]').first();
            if (await todo.count()) {
                await clickAndWait(page, todo);
            } else {
                anomaly('Pas de lien « À traiter » sur la page d\'accueil de la synthèse.');
                await page.goto(url('/mod/epsynthesis/todo.php', { id: state.synthesiscmid }));
            }
            await expectText(page, state.item, 'L\'élément du supplément n\'est pas dans « À traiter »');
            await shot(page, '06-referent-synthese-a-traiter');
        });

    await step('6.3', 'enseignant référent (ensa_1)', 'Valider à son étape l\'élément du supplément (supplement_items.php)',
        '« Décision enregistrée. » ; l\'élément quitte la file du référent et attend la DEVE.', async () => {
            const { page } = await session(CONF.REFERENT);
            await page.goto(epurl('dashboard.php'));
            await clickAndWait(page, page.locator('a[href*="/mod/ep/supplement_items.php"]').first());
            const row = page.locator('table tr', { hasText: state.item }).first();
            check(await row.count(), 'L\'élément n\'est pas dans la file du référent');
            check(await row.locator('a[href*="supplement_file.php"]').count(), 'Preuve non proposée au référent');
            await row.locator('textarea[name="validatorcomment"]').fill('Diplôme vu, conforme.');
            await shot(page, '06-referent-element-supplement');
            await clickAndWait(page, row.locator('input[name="validate"]'));
            await expectText(page, 'Décision enregistrée.');
            check(!(await page.locator('table tr', { hasText: state.item }).count()), 'L\'élément reste dans la file du référent');
            if (CONF.PSQL) {
                const st = db(`SELECT status || '|' || coalesce(pendingrole, '') FROM {ep_supplement_item} WHERE title = '${state.item.replace(/'/g, "''")}'`);
                check(st === '0|deve', 'Élément en base après l\'étape du référent : ' + st);
            }
            await page.goto(url('/mod/epsynthesis/todo.php', { id: state.synthesiscmid }));
            if ((await mainText(page)).includes(state.item)) {
                anomaly('L\'élément tranché reste affiché dans « À traiter » de la synthèse.');
            }
            await shot(page, '06-referent-synthese-apres-decision');
        });
}

async function procedure7() {
    await step('7.1', 'DEVE (devea_1)', 'Trancher l\'élément du supplément à son étape',
        'L\'élément d\'etua_301 est dans la file de la DEVE ; après validation, « Décision enregistrée. »', async () => {
            const { page } = await session(CONF.DEVE);
            await page.goto(epurl('dashboard.php'));
            await clickAndWait(page, page.locator('a[href*="/mod/ep/supplement_items.php"]').first());
            const heading = (await mainText(page)).slice(0, 400);
            // La file n'a ni recherche ni filtre par étudiant : on parcourt les pages.
            let pages = 0;
            let row = page.locator('table tr', { hasText: state.item }).first();
            while (!(await row.count())) {
                const next = page.locator('nav.pagination a[href*="qpage="], .pagination a[href*="qpage="]')
                    .filter({ hasText: /»|Suivant|Next|›/ }).first();
                if (!(await next.count()) || pages > 80) {
                    break;
                }
                await clickAndWait(page, next);
                pages++;
                row = page.locator('table tr', { hasText: state.item }).first();
            }
            check(await row.count(), `Élément introuvable dans la file de la DEVE (${pages} pages parcourues)`);
            if (pages > 0) {
                anomaly(`Pour trouver l'élément d'etua_301, la DEVE a dû parcourir ${pages + 1} pages de la file (aucune recherche ni filtre par étudiant).`);
            }
            await row.locator('textarea[name="validatorcomment"]').fill('Validé pour la rubrique 6.1.');
            await shot(page, '07-deve-element-supplement');
            await clickAndWait(page, row.locator('input[name="validate"]'));
            await expectText(page, 'Décision enregistrée.');
            if (CONF.PSQL) {
                const st = db(`SELECT status FROM {ep_supplement_item} WHERE title = '${state.item.replace(/'/g, "''")}'`);
                check(st === '1', 'Élément en base : ' + st);
            }
            return heading.includes('Mon') ? '' : `Trouvé après ${pages + 1} page(s).`;
        });

    await step('7.2', 'DEVE (devea_1)', `Valider l'année ${CONF.STUDYYEAR} d'etua_301 (year_validation.php)`,
        'Recherche de l\'étudiant, minimum atteint (6 + 4 ECTS), décision « Validée » enregistrée.', async () => {
            const { page } = await session(CONF.DEVE);
            await page.goto(epurl('administration.php'));
            const link = page.locator('a[href*="/mod/ep/year_validation.php"]').first();
            check(await link.count(), 'Pas de lien vers la validation des années dans l\'administration');
            await clickAndWait(page, link);
            await page.fill('input[name="search"]', CONF.STUDENT_SEARCH);
            await Promise.all([page.waitForNavigation({ waitUntil: 'load' }), page.press('input[name="search"]', 'Enter')]);
            const row = page.locator('table tr', { hasText: CONF.STUDENT_LABEL }).first();
            check(await row.count(), 'Étudiant introuvable par la recherche');
            const text = (await row.innerText()).replace(/\s+/g, ' ');
            if (!/atteint/i.test(text) || /non atteint/i.test(text)) {
                anomaly('Minimum de l\'année non atteint pour etua_301 : ' + text);
            }
            await row.locator('select[name="decision"]').selectOption('1');
            await row.locator('input[name="comment"]').fill('Année validée en jury.');
            await shot(page, '07-deve-validation-annee');
            await clickAndWait(page, row.locator('input[type="submit"]'));
            await expectText(page, 'Décision enregistrée.');
            const after = (await page.locator('table tr', { hasText: CONF.STUDENT_LABEL }).first().innerText()).replace(/\s+/g, ' ');
            check(/Validée|validée/.test(after), 'Ligne après décision : ' + after);
            return 'Ligne : ' + after;
        });

    await step('7.3', 'DEVE (devea_1)', 'Émettre le supplément au diplôme certifié d\'etua_301 et le télécharger',
        '« Supplément émis : SD-… » ; le PDF porte le numéro et le code de vérification.', async () => {
            const { page } = await session(CONF.DEVE);
            await page.goto(epurl('administration.php'));
            await clickAndWait(page, page.locator('a[href*="/mod/ep/supplement_issue.php"]').first());
            await page.fill('input[name="search"]', CONF.STUDENT_SEARCH);
            await Promise.all([page.waitForNavigation({ waitUntil: 'load' }), page.press('input[name="search"]', 'Enter')]);
            let row = page.locator('table tr', { hasText: CONF.STUDENT_LABEL }).first();
            check(await row.count(), 'Étudiant introuvable par la recherche');
            const missing = (await row.locator('td').nth(1).innerText()).trim();
            const form = row.locator('form:has(input[name="action"][value="issue"])');
            await form.locator('select[name="lang"]').selectOption('fr');
            if (missing !== '-' && missing !== '') {
                anomaly('Informations manquantes signalées pour etua_301 : ' + missing + ' — émission forcée.');
                await form.locator('input[name="force"]').check();
            }
            await shot(page, '07-deve-emettre-supplement');
            await clickAndWait(page, form.locator('input[type="submit"]'));
            const notes = await notifications(page);
            const issued = notes.find((n) => /Supplément émis/.test(n));
            check(issued, 'Pas de confirmation d\'émission : ' + notes.join(' | '));
            state.number = issued.match(/Supplément émis\s*:\s*([A-Za-z0-9-]+?)\.?(\s|$)/)[1];
            row = page.locator('table tr', { hasText: CONF.STUDENT_LABEL }).first();
            await shot(page, '07-deve-supplement-emis');
            const file = await download(page, () => row.locator('a[href*="supplement_file.php"]').first().click(), `supplement-${state.number}.pdf`);
            const text = pdftext(file);
            const flat = text.replace(/\s+/g, ' ');
            check(flat.includes(state.number), 'Le numéro ne figure pas dans le PDF');
            const m = text.match(/Code de v[ée]rification\s*:\s*([0-9a-f\s]{16,})/i);
            check(m, 'Code de vérification introuvable dans le PDF');
            state.code = m[1].replace(/\s+/g, '').slice(0, 64);
            check(flat.includes(state.item.split(' (')[0]) || flat.includes('Prix du meilleur poster'), 'L\'élément validé ne figure pas au supplément');
            check(flat.includes('Bien-être animal'), 'L\'EP validé ne figure pas au supplément');
            const verifyurl = flat.match(/https?:\/\/\S*supplement_verify\.php/);
            if (!verifyurl) {
                anomaly('L\'adresse de vérification n\'est pas lisible dans le PDF.');
            }
            return `Supplément ${state.number}, code ${state.code.slice(0, 12)}… (${state.code.length} car.)`;
        });

    await step('7.4', 'public (sans connexion)', 'Vérifier le numéro et le code sur supplement_verify.php',
        'Page accessible sans connexion ; « Ce supplément est authentique : émis le … pour Etu 301 Banc a. »', async () => {
            const { page } = await session('__anonyme__');
            await page.goto(url('/mod/ep/supplement_verify.php'));
            const onlogin = /login\/index\.php/.test(page.url()) || (await page.locator('#loginbtn').count()) > 0;
            if (onlogin) {
                await shot(page, '07-public-verification-redirigee-connexion');
                anomaly('supplement_verify.php renvoie vers la page de connexion : la vérification n\'est pas accessible sans compte '
                    + '(require_login() dans supplement_verify.php).');
            } else {
                await page.fill('#ep-verify-number', state.number);
                await page.fill('#ep-verify-code', state.code);
                await shot(page, '07-public-verifier-supplement');
                await clickAndWait(page, page.locator('form input[type="submit"]'));
                await expectText(page, 'authentique');
                await shot(page, '07-public-supplement-authentique');
            }
            // Vérification par un utilisateur connecté (DEVE), pour contrôler le numéro et le code dans tous les cas.
            const deve = (await session(CONF.DEVE)).page;
            await deve.goto(url('/mod/ep/supplement_verify.php'));
            await deve.fill('#ep-verify-number', state.number);
            await deve.fill('#ep-verify-code', state.code);
            await clickAndWait(deve, deve.locator('#region-main form input[type="submit"]'));
            await expectText(deve, 'authentique');
            check((await mainText(deve)).includes(CONF.STUDENT_LABEL), 'Le titulaire n\'est pas nommé');
            if (onlogin) {
                await shot(deve, '07-deve-supplement-authentique');
            }
            // Code faux : refus.
            await deve.fill('#ep-verify-code', '0'.repeat(64));
            await clickAndWait(deve, deve.locator('#region-main form input[type="submit"]'));
            await expectText(deve, 'Aucun supplément ne correspond');
            return onlogin ? 'Vérification impossible sans connexion ; numéro et code vérifiés une fois connecté.' : 'Vérifié sans connexion.';
        });
}

async function procedure8() {
    await step('8.1', 'étudiant (etua_301)', 'Télécharger son supplément émis',
        'Le supplément figure dans « Suppléments émis » (en vigueur) et se télécharge.', async () => {
            const { page } = await session(CONF.STUDENT);
            await page.goto(epurl('supplement.php'));
            const row = page.locator('table tr', { hasText: state.number }).first();
            check(await row.count(), 'Le supplément émis n\'est pas listé');
            await shot(page, '08-etudiant-supplement-emis');
            const file = await download(page, () => row.locator('a[href*="supplement_file.php"]').first().click(), `supplement-etudiant-${state.number}.pdf`);
            check(pdftext(file).replace(/\s+/g, ' ').includes(state.number), 'PDF étudiant sans numéro');
        });

    await step('8.2', 'étudiant (etua_301)', 'Voir ses décisions (inscription, déclaration, élément du supplément)',
        'Statuts « Validé » et commentaires des validateurs visibles ; historique des décisions de la déclaration.', async () => {
            const { page } = await session(CONF.STUDENT);
            await page.goto(epurl('view.php'));
            const body = await mainText(page);
            for (const name of [EP_NAME, state.declaration]) {
                const row = page.locator('table tr', { hasText: name }).first();
                check(await row.count(), name + ' absent du tableau de bord');
                const text = (await row.innerText()).replace(/\s+/g, ' ');
                check(/Valid/i.test(text), `${name} : ${text}`);
            }
            await shot(page, '08-etudiant-tableau-de-bord-decisions');
            const detail = page.locator('table tr', { hasText: state.declaration }).first().locator('a[href*="validate.php"]');
            if (await detail.count()) {
                await clickAndWait(page, detail.first());
                await expectText(page, 'Licence et calendrier', 'Commentaire du référent invisible pour l\'étudiant');
                await shot(page, '08-etudiant-detail-decision');
            } else {
                anomaly('Pas de lien vers le détail de la déclaration depuis le tableau de bord.');
            }
            await page.goto(epurl('supplement.php'));
            const item = (await page.locator('table tr', { hasText: state.item }).first().innerText()).replace(/\s+/g, ' ');
            check(/Valid/i.test(item), 'Élément du supplément : ' + item);
            if (!/rubrique 6\.1/.test(item)) {
                anomaly('Le commentaire de la DEVE sur l\'élément n\'est pas affiché à l\'étudiant : ' + item);
            }
            if (!/ann[ée]e/i.test(body) || !/valid/i.test(body)) {
                note('Le tableau de bord ne mentionne pas la décision d\'année.');
            }
            return 'Élément : ' + item.slice(0, 200);
        });
}

async function procedure9() {
    await step('9.1', 'étudiant (etua_301)', `Prérequis bloquant : ${CONF.PREREQ_CODE} sans avoir validé son prérequis`,
        'Le catalogue signale le prérequis manquant et ne propose pas « S\'inscrire » ; l\'adresse d\'inscription forgée est refusée.',
        async () => {
            const { page } = await session(CONF.STUDENT);
            await page.goto(epurl('catalog.php'));
            const row = page.locator('table tr', { hasText: CONF.PREREQ_CODE }).first();
            check(await row.count(), CONF.PREREQ_CODE + ' absent du catalogue');
            const text = (await row.innerText()).replace(/\s+/g, ' ');
            check(/Prérequis manquants/.test(text), 'Prérequis manquant non signalé : ' + text);
            check(!(await row.locator('a[href*="action=register"]').count()), 'Bouton « S\'inscrire » proposé malgré le prérequis bloquant');
            check(/ne remplissez pas les prérequis/.test(text), 'Motif du refus non affiché : ' + text);
            await row.scrollIntoViewIfNeeded();
            await shot(page, '09-etudiant-catalogue-prerequis-manquant');
            // Adresse forgée : l'identifiant de l'EP est lu dans le lien vers sa fiche, ou en base.
            let activityid = null;
            const href = await row.locator('a[href*="activityid="]').first().getAttribute('href').catch(() => null);
            if (href) {
                activityid = new URL(href, CONF.BASE).searchParams.get('activityid');
            } else if (CONF.PSQL) {
                activityid = db(`SELECT a.id FROM {ep_activity} a JOIN {course_modules} cm ON cm.instance = a.epid JOIN {modules} m ON m.id = cm.module AND m.name = 'ep' WHERE cm.id = ${CONF.CMID} AND a.code = '${CONF.PREREQ_CODE}'`);
            }
            check(activityid, 'Identifiant de l\'EP introuvable');
            await page.goto(epurl('catalog.php', { action: 'register', activityid }));
            const body = await mainText(page);
            check(/prérequis/i.test(body), 'L\'inscription forcée n\'est pas refusée : ' + body.slice(0, 300));
            check(!(await page.locator('form[action*="action=register"] input[type="submit"]').count()), 'Formulaire de confirmation proposé malgré le prérequis');
            await shot(page, '09-etudiant-inscription-refusee-prerequis');
            return `${CONF.PREREQ_CODE} (id ${activityid}) : ${text.slice(0, 160)}`;
        });

    await step('9.2', 'DEVE (devea_1)', 'Contrôler dans le pilotage la situation d\'etua_301 (année validée, ECTS retenus)',
        'La fiche de l\'étudiant montre les 10 ECTS de l\'année et la décision d\'année.', async () => {
            const { page } = await session(CONF.DEVE);
            await page.goto(epurl('dashboard.php'));
            await page.fill('input[name="search"]', CONF.STUDENT_SEARCH);
            await Promise.all([page.waitForNavigation({ waitUntil: 'load' }), page.press('input[name="search"]', 'Enter')]);
            const row = page.locator('table tr', { hasText: CONF.STUDENT_LABEL }).first();
            check(await row.count(), 'Étudiant introuvable dans le pilotage');
            await clickAndWait(page, row.locator('a[href*="studentid="]').first());
            await shot(page, '09-deve-pilotage-etudiant');
            const body = await mainText(page);
            return /valid/i.test(body) ? 'Fiche consultée.' : 'Fiche consultée (pas de mention de validation).';
        });
}

// ---------------------------------------------------------------------------------------------
// Exécution.
// ---------------------------------------------------------------------------------------------
function cfg(name, value) {
    const args = ['admin/cli/cfg.php', `--name=${name}`];
    if (value === null) {
        args.push('--unset');
    } else if (value !== undefined) {
        args.push(`--set=${value}`);
    }
    return execFileSync('php', args, { cwd: CONF.MOODLE_DIR, encoding: 'utf8' }).trim();
}

(async () => {
    for (const dir of [CONF.CAPTURES, CONF.DOWNLOADS]) {
        fs.mkdirSync(dir, { recursive: true });
    }
    const only = process.env.ETAPES ? process.env.ETAPES.split(',').map(Number) : null;
    let logoffset = 0;
    let olddebug = null;
    if (CONF.MOODLE_DIR) {
        olddebug = cfg('debug');
        cfg('debug', 32767); // Erreurs et débogage journalisés (affichage inchangé).
    }
    if (CONF.SERVER_LOG && fs.existsSync(CONF.SERVER_LOG)) {
        logoffset = fs.statSync(CONF.SERVER_LOG).size;
    }
    browser = await chromium.launch();
    const procedures = [procedure1, procedure2, procedure3, procedure4, procedure5,
        async () => { await ensureSynthesis(); await procedure6(); }, procedure7, procedure8, procedure9];
    try {
        for (let i = 0; i < procedures.length; i++) {
            if (!only || only.includes(i + 1)) {
                await procedures[i]();
            }
        }
    } finally {
        await browser.close();
        if (CONF.MOODLE_DIR && olddebug !== null) {
            cfg('debug', olddebug === '' ? 0 : olddebug);
        }
        // Erreurs PHP journalisées par le serveur pendant le parcours.
        if (CONF.SERVER_LOG && fs.existsSync(CONF.SERVER_LOG)) {
            const fd = fs.openSync(CONF.SERVER_LOG, 'r');
            const size = fs.statSync(CONF.SERVER_LOG).size - logoffset;
            const buffer = Buffer.alloc(Math.max(0, size));
            fs.readSync(fd, buffer, 0, buffer.length, logoffset);
            fs.closeSync(fd);
            const lines = buffer.toString('utf8').split('\n')
                .filter((l) => /PHP (Warning|Notice|Fatal|Deprecated|Parse)|Debugging:|Exception|Stack trace|error/i.test(l))
                .filter((l) => !/Failed to poll event/.test(l));
            results.push({ etape: 'journal', role: 'serveur', action: 'Erreurs journalisées par le serveur pendant le parcours',
                attendu: 'Aucune', obtenu: lines.length ? `${lines.length} ligne(s)` : 'Aucune', resultat: lines.length ? 'anomalie' : 'réussi',
                lignes: lines.slice(0, 200) });
        }
        saveResults();
        const failed = results.filter((r) => r.resultat === 'échec').length;
        console.error(`\nTerminé : ${results.length} entrées, ${failed} échec(s). Journal : ${CONF.RESULTS}`);
    }
})();
