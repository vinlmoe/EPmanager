<?php
// Jeu de données du banc de charge d'EP Manager : une promotion volumineuse dans un vrai Moodle.
//
// Usage : php charge_setup.php <tag> [étudiants=2000] [enseignants=150] [ep=200] [crédits par étudiant=30]
// Variable MOODLE_CONFIG : chemin de config.php (par défaut /home/user/moodle-stress/moodle/config.php).
// Écrit site-<tag>.json à côté du script. Mot de passe de tous les comptes : Banc123!
//
// Contenu : un cours, une activité « Gestion des stages » (référents) et une activité EP en
// année 3 ; des étudiants répartis sur les six années ; des enseignants (rôle « teacher »),
// chacun référent d'une partie des étudiants et responsable de quelques EP ; quatre membres de
// la DEVE (rôle « manager ») ; un catalogue d'EP avec syllabus publiés de huit séances et
// prérequis ; par étudiant, des inscriptions au catalogue, des déclarations, des ajouts de la
// DEVE, à tous les états (80 % validés, 12 % en attente à la première étape du circuit, 8 %
// refusés) avec l'historique de leurs décisions ; trois éléments du supplément ; les décisions
// des années passées.

define('CLI_SCRIPT', true);
require(getenv('MOODLE_CONFIG') ?: '/home/user/moodle-stress/moodle/config.php');
require_once($CFG->dirroot . '/mod/ep/locallib.php');
require_once($CFG->dirroot . '/mod/stage/locallib.php');
require_once($CFG->dirroot . '/lib/phpunit/classes/util.php');

$tag = $argv[1] ?? 'a';
$nstudents = (int) ($argv[2] ?? 2000);
$nteachers = (int) ($argv[3] ?? 150);
$nactivities = (int) ($argv[4] ?? 200);
$ncredits = (int) ($argv[5] ?? 30);
$password = 'Banc123!';
mt_srand(20261106);
$t0 = microtime(true);
$step = function ($label) use ($t0) {
    fwrite(STDERR, sprintf("[%6.1f s] %s\n", microtime(true) - $t0, $label));
};

\core\session\manager::set_user(get_admin());
$gen = phpunit_util::get_data_generator();
$course = $gen->create_course(['fullname' => "Promotion banc $tag", 'shortname' => "BANC-$tag"]);
$stage = $gen->create_module('stage', ['course' => $course->id, 'name' => 'Gestion des stages']);
$epmodule = $gen->create_module('ep', ['course' => $course->id, 'name' => 'Enseignement personnalisé',
    'currentstudyyear' => 3]);
$ep = $DB->get_record('ep', ['id' => $epmodule->id], '*', MUST_EXIST);
$context = context_module::instance($epmodule->cmid);
$step('cours et activités');

$roles = $DB->get_records_menu('role', null, '', 'shortname, id');
$users = ['students' => [], 'teachers' => [], 'deve' => []];
// Enseignants et DEVE partagés : avec la variable PERSONNEL=<tag>, ceux d'une promotion déjà
// générée sont inscrits dans ce cours au lieu d'en créer de nouveaux, comme dans une école où les
// mêmes enseignants suivent toutes les promotions.
$staff = getenv('PERSONNEL') ?: '';
$make = function ($kind, $i, $role) use ($gen, $course, $tag, $password, $roles, &$users, $staff, $DB) {
    $existing = $staff !== '' && $kind !== 'etu'
        ? $DB->get_record('user', ['username' => "{$kind}{$staff}_{$i}"]) : false;
    $user = $existing ?: $gen->create_user([
        'username' => "{$kind}{$tag}_{$i}",
        'password' => $password,
        'email' => "{$kind}{$tag}_{$i}@example.com",
        'idnumber' => strtoupper($kind) . "-$tag-" . sprintf('%05d', $i),
        'firstname' => ucfirst($kind) . " $i",
        'lastname' => 'Banc ' . $tag,
    ]);
    $gen->enrol_user($user->id, $course->id, $roles[$role]);
    $users[$kind === 'etu' ? 'students' : ($kind === 'ens' ? 'teachers' : 'deve')][] = (int) $user->id;
    return $user;
};
for ($i = 1; $i <= 4; $i++) {
    $make('deve', $i, 'manager');
}
for ($i = 1; $i <= $nteachers; $i++) {
    $make('ens', $i, 'teacher');
}
$step('enseignants et DEVE');
for ($i = 1; $i <= $nstudents; $i++) {
    $make('etu', $i, 'student');
    if ($i % 500 === 0) {
        $step("$i étudiants");
    }
}

// Référents : chaque étudiant a un enseignant référent dans la gestion des stages.
$referent = [];
foreach ($users['students'] as $k => $studentid) {
    $referent[$studentid] = $users['teachers'][$k % $nteachers];
    stage_set_student_teachers($stage->id, $studentid, [$referent[$studentid]]);
}
$step('référents');

// Règles : minimum de 10 ECTS par année.
for ($year = 1; $year <= 6; $year++) {
    ep_set_year_requirement($ep->id, $year, 10);
}
$types = ep_get_types($ep->id);
$bycode = [];
foreach ($types as $type) {
    $bycode[$type->code] = $type;
}
$catalogtype = $bycode[EP_TYPE_ACADEMIC];
$declarable = array_values(array_filter($types, fn($t) => ep_type_is_declarable($t)));

// Catalogue : EP publiés avec un ou deux responsables, syllabus de huit séances, prérequis.
$activities = [];
$kinds = array_keys(ep_session_kind_options());
for ($i = 1; $i <= $nactivities; $i++) {
    $minyear = 1 + ($i % 5);
    $activityid = ep_save_activity($ep, (object) [
        'typeid' => $catalogtype->id,
        'code' => sprintf('EP%03d', $i),
        'name' => "Enseignement personnalisé n° $i",
        'description' => str_repeat("Description de l'EP $i. ", 10),
        'ects' => [1, 1.5, 2, 2.5, 3][$i % 5],
        'minstudyyear' => $minyear,
        'maxstudyyear' => min(6, $minyear + 2),
        'capacity' => $i % 3 === 0 ? 0 : 40,
        'state' => EP_ACTIVITY_PUBLISHED,
        'grading' => $i % 4 === 0 ? EP_GRADING_GRADE : '',
    ]);
    $owners = [$users['teachers'][$i % $nteachers]];
    if ($i % 2 === 0) {
        $owners[] = $users['teachers'][($i * 7) % $nteachers];
    }
    ep_set_activity_teachers($activityid, array_unique($owners));
    $activity = $DB->get_record('ep_activity', ['id' => $activityid]);
    $sessions = [];
    for ($s = 1; $s <= 8; $s++) {
        $sessions[] = ['title' => "Séance $s", 'kind' => $kinds[$s % count($kinds)], 'duration' => 2,
            'teacherid' => $s % 3 ? $owners[0] : 0, 'teachername' => $s % 3 ? '' : 'Intervenant extérieur'];
    }
    $text = [];
    foreach (ep_syllabus_text_fields() as $field) {
        $text[$field] = str_repeat("Texte de la rubrique $field de l'EP $i. ", 8);
    }
    ep_publish_syllabus_directly($activity, (object) ($text + ['language' => 'français', 'personalhours' => 20]), $sessions,
        $users['deve'][0]);
    if ($i > 10 && $i % 10 === 0) {
        ep_set_activity_prerequisites($activity, [$activities[$i - 10]->id]);
    }
    $activities[$i] = $activity;
}
$step('catalogue');

// Crédits, insérés par paquets avec l'historique de leurs décisions.
$now = time();
$credits = [];
$decisions = [];
$flush = function () use (&$credits, &$decisions, $DB) {
    if (!$credits) {
        return;
    }
    $transaction = $DB->start_delegated_transaction();
    foreach ($credits as $k => $credit) {
        $id = $DB->insert_record('ep_credit', $credit);
        foreach ($decisions[$k] ?? [] as $decision) {
            $decision->creditid = $id;
            $DB->insert_record('ep_credit_decision', $decision);
        }
    }
    $transaction->allow_commit();
    $credits = [];
    $decisions = [];
};
$taken = [];
foreach ($users['students'] as $k => $studentid) {
    $studentyear = 1 + ($k % 6);
    for ($c = 0; $c < $ncredits; $c++) {
        $year = max(1, $studentyear - ($c % 3));
        $roll = mt_rand(1, 100);
        $status = $roll <= 80 ? EP_STATUS_VALIDATED : ($roll <= 92 ? EP_STATUS_PENDING : EP_STATUS_REJECTED);
        $kind = $c % 3;
        if ($kind === 0) {
            $activity = $activities[1 + mt_rand(0, $nactivities - 1)];
            if (isset($taken[$studentid][$activity->id])) {
                continue;
            }
            $taken[$studentid][$activity->id] = true;
            $type = $catalogtype;
            $name = $activity->name;
            $ects = (float) $activity->ects;
            $source = EP_SOURCE_STUDENT;
            $activityid = (int) $activity->id;
            $firstrole = EP_ROLE_OWNER;
        } else {
            $type = $kind === 1 ? $declarable[$c % count($declarable)] : $bycode[EP_TYPE_EXTERNAL] ?? $declarable[0];
            $name = "Activité déclarée $c";
            $ects = [0.5, 1, 1.5, 2][$c % 4];
            $source = $kind === 1 ? EP_SOURCE_STUDENT : EP_SOURCE_DEVE;
            $activityid = null;
            $flow = ep_flow_steps($type->validationflow);
            $firstrole = $flow[0] ?? EP_ROLE_DEVE;
        }
        $decided = $status !== EP_STATUS_PENDING;
        $created = $now - mt_rand(30, 900) * DAYSECS;
        $validator = $firstrole === EP_ROLE_REFERENT ? $referent[$studentid] : $users['deve'][$c % 4];
        $credits[] = (object) [
            'epid' => $ep->id, 'userid' => $studentid, 'typeid' => $type->id, 'activityid' => $activityid,
            'studyyear' => $year, 'name' => $name, 'description' => "Description du crédit $c", 'claimedects' => $ects,
            'retainedects' => $status === EP_STATUS_VALIDATED ? $ects : 0, 'status' => $status,
            'step' => $decided ? 0 : 0, 'pendingrole' => $decided ? '' : $firstrole,
            'grade' => $status === EP_STATUS_VALIDATED && $activityid && $activity->grading === EP_GRADING_GRADE
                ? mt_rand(100, 200) / 10 : null,
            'source' => $source, 'sourceref' => 0, 'validatedby' => $decided ? $validator : null,
            'addedby' => $source === EP_SOURCE_DEVE ? $users['deve'][0] : null,
            'validatetime' => $decided ? $created + DAYSECS : null,
            'validatorcomment' => $decided ? 'Décision du banc' : null,
            'timecreated' => $created, 'timemodified' => $created,
        ];
        if ($decided) {
            $decisions[count($credits) - 1][] = (object) [
                'step' => 0, 'role' => $firstrole, 'decision' => $status === EP_STATUS_VALIDATED ? 'validated' : 'rejected',
                'userid' => $validator, 'grade' => null, 'retainedects' => $status === EP_STATUS_VALIDATED ? $ects : 0,
                'comment' => 'Décision du banc', 'timecreated' => $created + DAYSECS,
            ];
        }
    }
    if (count($credits) >= 2000) {
        $flush();
    }
    if (($k + 1) % 500 === 0) {
        $step(($k + 1) . ' dossiers');
    }
}
$flush();
$step('crédits : ' . $DB->count_records('ep_credit', ['epid' => $ep->id]));

// Éléments du supplément et décisions des années passées.
$categories = array_values(ep_get_supplement_categories($ep->id));
$DB->set_field('ep', 'supplementflow', 'referent,deve', ['id' => $ep->id]);
$DB->set_field('ep', 'supplementevidence', EP_EVIDENCE_OPTIONAL, ['id' => $ep->id]);
$transaction = $DB->start_delegated_transaction();
foreach ($users['students'] as $k => $studentid) {
    for ($i = 0; $i < 3; $i++) {
        $status = [EP_STATUS_VALIDATED, EP_STATUS_PENDING, EP_STATUS_REJECTED][($k + $i) % 3];
        $DB->insert_record('ep_supplement_item', (object) [
            'epid' => $ep->id, 'userid' => $studentid, 'categoryid' => $categories[$i % count($categories)]->id,
            'title' => "Activité complémentaire $i", 'titleen' => "Additional activity $i", 'description' => 'Banc',
            'periodstart' => $now - 400 * DAYSECS, 'periodend' => $now - 300 * DAYSECS, 'status' => $status,
            'step' => 0, 'pendingrole' => $status === EP_STATUS_PENDING ? EP_ROLE_REFERENT : '',
            'validatedby' => $status === EP_STATUS_PENDING ? null : $users['deve'][0],
            'validatetime' => $status === EP_STATUS_PENDING ? null : $now - 200 * DAYSECS,
            'validatorcomment' => '', 'timecreated' => $now - 300 * DAYSECS, 'timemodified' => $now - 300 * DAYSECS,
        ]);
    }
    for ($year = 1; $year < 1 + ($k % 6); $year++) {
        $DB->insert_record('ep_year_validation', (object) [
            'epid' => $ep->id, 'userid' => $studentid, 'studyyear' => $year, 'decision' => 1, 'retainedects' => 12,
            'comment' => '', 'decidedby' => $users['deve'][0], 'timedecided' => $now - (6 - $year) * 300 * DAYSECS,
        ]);
    }
}
$transaction->allow_commit();
$step('supplément et années');

$site = [
    'tag' => $tag, 'courseid' => (int) $course->id, 'epid' => (int) $ep->id, 'cmid' => (int) $epmodule->cmid,
    'stageid' => (int) $stage->id, 'password' => $password, 'students' => $users['students'],
    'teachers' => $users['teachers'], 'deve' => $users['deve'],
    'activities' => array_map(fn($a) => (int) $a->id, array_values($activities)),
    'wwwroot' => $CFG->wwwroot,
];
file_put_contents(__DIR__ . "/site-$tag.json", json_encode($site));
$step("site-$tag.json écrit");
