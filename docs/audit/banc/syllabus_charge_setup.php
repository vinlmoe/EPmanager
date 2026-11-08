<?php
// Jeu de données à l'échelle d'une école pour les modules syllabus : cinq années d'étude de
// douze modules (six par semestre), 1 000 étudiants (200 par année), 120 responsables, la DEVE
// gestionnaire de la catégorie, un référentiel de 48 compétences, des fiches publiées et liées
// aux compétences, et les évaluations du premier semestre closes avec leurs réponses.
//
// Usage : sudo -u www-data php syllabus_charge_setup.php site-x.json > syllabus-charge.json
//         sudo -u www-data php syllabus_charge_setup.php site-x.json reset
// Les comptes sont ceux du site x (etux_1..1000, ensa_1..150, devea_1..4) ; mot de passe Banc123!
// Variable MOODLE_CONFIG : chemin de config.php (par défaut /home/user/moodle-stress/moodle/config.php).

define('CLI_SCRIPT', true);
require(getenv('MOODLE_CONFIG') ?: '/home/user/moodle-stress/moodle/config.php');
require_once($CFG->dirroot . '/mod/syllabus/locallib.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->dirroot . '/lib/phpunit/classes/util.php');

$site = json_decode(file_get_contents($argv[1]), true);
$tag = $site['tag'];
$catidnumber = 'CURSUS-' . $tag;
$t0 = microtime(true);
$step = function ($label) use ($t0) {
    fwrite(STDERR, sprintf("[%6.1f s] %s\n", microtime(true) - $t0, $label));
};
\core\session\manager::set_user(get_admin());

if (($argv[2] ?? '') === 'reset') {
    if ($category = $DB->get_record('course_categories', ['idnumber' => $catidnumber])) {
        foreach ($DB->get_records('course', ['category' => $category->id], '', 'id') as $course) {
            delete_course($course->id, false);
        }
        core_course_category::get($category->id)->delete_full(false);
    }
    foreach (['EQUIPE-CURSUS-' . $tag, 'PROMO1-' . $tag, 'PROMO2-' . $tag, 'PROMO3-' . $tag, 'PROMO4-' . $tag,
            'PROMO5-' . $tag] as $shortname) {
        if ($course = $DB->get_record('course', ['shortname' => $shortname])) {
            delete_course($course->id, false);
        }
    }
    if ($fw = $DB->get_record('syllabus_framework', ['idnumber' => 'REF-' . $catidnumber])) {
        $DB->delete_records_select('syllabus_complink', 'compid IN (SELECT id FROM {syllabus_comp} WHERE frameworkid = ?)',
            [$fw->id]);
        $DB->delete_records('syllabus_comp', ['frameworkid' => $fw->id]);
        $DB->delete_records('syllabus_framework', ['id' => $fw->id]);
    }
    $step('supprimé');
    exit;
}

mt_srand(20261108);
$gen = phpunit_util::get_data_generator();
$roles = $DB->get_records_menu('role', null, '', 'shortname, id');
$user = fn(string $username) => $DB->get_record('user', ['username' => $username], '*', MUST_EXIST);
$category = core_course_category::create(['name' => 'Cursus vétérinaire (banc ' . $tag . ')', 'idnumber' => $catidnumber]);
$deve = array_map(fn($i) => $user('devea_' . $i), range(1, 4));
foreach ($deve as $d) {
    role_assign($roles['manager'], $d->id, context_coursecat::instance($category->id));
}
$types = array_values(syllabus_get_types());
if (!$types) {
    $types = [(object) ['id' => syllabus_save_type('Fondamental')], (object) ['id' => syllabus_save_type('Clinique')]];
}

// Référentiel : six domaines de huit compétences.
$fwid = syllabus_save_framework((object) ['name' => 'Référentiel du cursus', 'idnumber' => 'REF-' . $catidnumber,
    'description' => 'Compétences de fin de cursus.']);
$csv = "Code;Code parent;Intitulé;Description\n";
$domains = ['Démarche clinique', 'Santé publique', 'Thérapeutique', 'Communication', 'Professionnalisme', 'Recherche'];
foreach ($domains as $d => $domain) {
    $csv .= 'C' . ($d + 1) . ";;$domain;\n";
    for ($c = 1; $c <= 8; $c++) {
        $csv .= 'C' . ($d + 1) . ".$c;C" . ($d + 1) . ";$domain : compétence $c;Description de la compétence $c.\n";
    }
}
syllabus_import_comps($fwid, $csv);
$leaves = array_values(array_map('intval', $DB->get_fieldset_select('syllabus_comp', 'id', 'frameworkid = ? AND parentid > 0',
    [$fwid])));
$step('catégorie, DEVE, référentiel');

// Équipe pédagogique et promotions.
$team = $gen->create_course(['fullname' => 'Équipe pédagogique du cursus (banc ' . $tag . ')',
    'shortname' => 'EQUIPE-CURSUS-' . $tag]);
$teachers = array_map(fn($i) => $user('ensa_' . $i), range(1, 120));
foreach ($teachers as $t) {
    $gen->enrol_user($t->id, $team->id, 'teacher');
}
foreach ($deve as $d) {
    $gen->enrol_user($d->id, $team->id, 'manager');
}
$staff = $gen->create_module('syllabusstaff', ['course' => $team->id, 'name' => 'Suivi des modules',
    'categoryid' => $category->id]);
$students = [];
$promos = [];
for ($year = 1; $year <= 5; $year++) {
    $promo = $gen->create_course(['fullname' => "Promotion A$year (banc $tag)", 'shortname' => "PROMO$year-$tag"]);
    $students[$year] = [];
    for ($i = ($year - 1) * 200 + 1; $i <= $year * 200; $i++) {
        $s = $user('etux_' . $i);
        $students[$year][] = $s;
        $gen->enrol_user($s->id, $promo->id, 'student');
    }
    $mine = $gen->create_module('syllabusstudent', ['course' => $promo->id, 'name' => 'Mes modules',
        'categoryid' => $category->id]);
    $promos[$year] = ['course' => (int) $promo->id, 'cmid' => (int) $mine->cmid,
        'students' => array_map(fn($s) => $s->username, $students[$year])];
}
$step('équipe, promotions, inscriptions aux promotions');

// Modules : douze par année, six par semestre, deux responsables chacun.
$names = ['Anatomie', 'Physiologie', 'Biochimie', 'Histologie', 'Génétique', 'Éthologie', 'Pharmacologie', 'Immunologie',
    'Bactériologie', 'Virologie', 'Parasitologie', 'Nutrition'];
$kinds = array_keys(syllabus_session_kind_options());
$text = fn(string $what, int $n) => trim(str_repeat("$what. ", $n));
$modules = [];
$k = 0;
for ($year = 1; $year <= 5; $year++) {
    foreach ($names as $i => $base) {
        $semester = 'S' . (2 * $year - 1 + intdiv($i, 6));
        $name = "$base $year";
        $course = $gen->create_course(['category' => $category->id, 'fullname' => $name, 'shortname' => "C$year-" . ($i + 1)
            . "-$tag"]);
        $leaders = [$teachers[(2 * $k) % 120], $teachers[(2 * $k + 1) % 120]];
        foreach ($leaders as $leader) {
            $gen->enrol_user($leader->id, $course->id, 'editingteacher');
        }
        foreach ($students[$year] as $s) {
            $gen->enrol_user($s->id, $course->id, 'student');
        }
        $record = $gen->create_module('syllabus', ['course' => $course->id, 'name' => $name, 'code' => sprintf('UE%d%02d',
            $year, $i + 1), 'ects' => 2 + $i % 4, 'semester' => $semester, 'studyyear' => $year,
            'typeid' => $types[$i % count($types)]->id]);
        $syllabus = $DB->get_record('syllabus', ['id' => $record->id]);
        $context = context_module::instance($record->cmid);
        syllabus_set_teachers($syllabus, $context, array_map(fn($u) => $u->id, $leaders));
        // Fiche publiée : toutes les rubriques, huit séances.
        $version = syllabus_start_working($syllabus, (int) $leaders[0]->id);
        $sessions = [];
        for ($n = 1; $n <= 8; $n++) {
            $sessions[] = ['title' => "Séance $n : " . $text('Thème', 2), 'kind' => $kinds[$n % count($kinds)],
                'duration' => [1, 1.5, 2, 3][$n % 4], 'teacherid' => $leaders[$n % 2]->id];
        }
        syllabus_save_working($version, (object) ['objectives' => $text("Objectif du module $name", 4),
            'outcomes' => $text('Identifier, expliquer et appliquer les notions du module', 3),
            'prerequisitestext' => $text('Modules de l\'année précédente', 1), 'content' => $text("Contenu détaillé de $name", 12),
            'methods' => $text('Cours magistraux, travaux dirigés et pratiques', 2),
            'assessment' => 'Examen écrit (60 %) et contrôle continu (40 %).', 'resit' => 'Examen écrit.',
            'bibliography' => $text('Ouvrage de référence', 3), 'language' => 'Français', 'personalhours' => 20],
            $sessions, (int) $leaders[0]->id);
        syllabus_publish($syllabus, syllabus_get_working((int) $syllabus->id), (int) $deve[0]->id, '');
        // Deux questions de l'enseignant.
        syllabus_save_question((object) ['syllabusid' => $syllabus->id, 'kind' => 'scale',
            'text' => 'Les supports étaient utiles.', 'required' => 1]);
        syllabus_save_question((object) ['syllabusid' => $syllabus->id, 'kind' => 'text', 'text' => 'Une suggestion ?']);
        // Compétences : six par module, niveau croissant avec l'année.
        $levels = [];
        for ($c = 0; $c < 6; $c++) {
            $comp = $leaves[($k * 5 + $c * 7) % count($leaves)];
            $levels[$comp] = max(1, min(3, intdiv($year + 1, 2) + mt_rand(-1, 0)));
        }
        syllabus_set_links((int) $course->id, (int) $syllabus->id, $fwid, $levels);
        $modules[] = ['cmid' => (int) $record->cmid, 'id' => (int) $syllabus->id, 'course' => (int) $course->id,
            'name' => $name, 'year' => $year, 'semester' => $semester, 'leaders' => array_map(fn($u) => $u->username, $leaders)];
        $k++;
    }
    $step("année $year : 12 modules");
}
// Un stage par année, sans module, lié par la DEVE.
for ($year = 1; $year <= 5; $year++) {
    $stage = $gen->create_course(['category' => $category->id, 'fullname' => "Stage A$year", 'shortname' => "STG$year-$tag"]);
    syllabus_set_links((int) $stage->id, 0, $fwid, [$leaves[$year * 3] => min(3, $year), $leaves[$year * 3 + 1] => 2]);
}

// Évaluations du premier semestre de chaque année : ouvertes, 75 % des étudiants répondent,
// puis closes. Celles du second semestre restent à ouvrir (le test les ouvre).
$now = time();
$responses = 0;
foreach ($modules as $m) {
    if ((int) substr($m['semester'], 1) % 2 === 0) {
        continue;
    }
    $syllabus = $DB->get_record('syllabus', ['id' => $m['id']]);
    syllabus_set_window($syllabus, $now - 20 * DAYSECS, $now + DAYSECS);
    $syllabus = $DB->get_record('syllabus', ['id' => $m['id']]);
    $context = context_module::instance($m['cmid']);
    syllabus_freeze_questions($syllabus);
    $questions = syllabus_get_module_questions((int) $syllabus->id);
    foreach ($students[$m['year']] as $s) {
        if (mt_rand(1, 100) > 75) {
            continue;
        }
        $answers = [];
        foreach ($questions as $q) {
            $answers[$q->id] = match ($q->kind) {
                'scale' => mt_rand(2, 5),
                'yesno' => mt_rand(0, 1),
                'choice' => mt_rand(0, count(syllabus_question_options($q)) - 1),
                default => mt_rand(1, 3) === 1 ? 'Remarque de l\'étudiant ' . $s->id : '',
            };
        }
        \core\session\manager::set_user($s);
        $errors = syllabus_submit_response($syllabus, $context, (int) $s->id, $answers);
        $responses += $errors ? 0 : 1;
    }
    \core\session\manager::set_user(get_admin());
    syllabus_set_window($syllabus, $now - 20 * DAYSECS, $now - HOURSECS);
}
$step("$responses réponses aux évaluations du premier semestre");

echo json_encode(['category' => (int) $category->id, 'staffcmid' => (int) $staff->cmid, 'teamcourse' => (int) $team->id,
    'frameworkid' => $fwid, 'promos' => $promos, 'modules' => $modules, 'responses' => $responses], JSON_UNESCAPED_UNICODE),
    "\n";
