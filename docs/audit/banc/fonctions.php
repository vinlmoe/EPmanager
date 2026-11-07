<?php
// Chronométrage des fonctions de lecture de mod_ep sur le jeu du banc (médiane de 5 appels).
// Usage : php fonctions.php site-<tag>.json
define('CLI_SCRIPT', true);
require(getenv('MOODLE_CONFIG') ?: '/home/user/moodle-stress/moodle/config.php');
require_once($CFG->dirroot . '/mod/ep/locallib.php');

$site = json_decode(file_get_contents($argv[1]), true);
$ep = $DB->get_record('ep', ['id' => $site['epid']], '*', MUST_EXIST);
$context = context_module::instance($site['cmid']);
$deve = $DB->get_record('user', ['username' => "deve{$site['tag']}_1"]);
$teacher = $DB->get_record('user', ['username' => "ens{$site['tag']}_1"]);
$student = $site['students'][3];
\core\session\manager::set_user($deve);
$devrights = ep_get_user_rights($ep, $context, $deve->id);
$teacherrights = ep_get_user_rights($ep, $context, $teacher->id);
$activities = ep_get_activities($ep->id);

$measure = function (string $label, callable $fn) use ($DB) {
    $times = [];
    $queries = 0;
    for ($i = 0; $i < 5; $i++) {
        $q = $DB->perf_get_queries();
        $t = microtime(true);
        $fn();
        $times[] = (microtime(true) - $t) * 1000;
        $queries = $DB->perf_get_queries() - $q;
    }
    sort($times);
    printf("%-58s %8.1f ms %6d req.\n", $label, $times[2], $queries);
};

$measure('Demandes en attente de la DEVE (compte)', fn() => ep_count_credits_awaiting($ep, $devrights));
$measure('Demandes en attente de la DEVE (page de 40)', fn() => ep_get_credits_awaiting($ep, $devrights, 'timecreated', 'ASC', 0, 40));
$measure('Demandes en attente d\'un enseignant (toutes)', fn() => ep_get_credits_awaiting($ep, $teacherrights));
$measure('Liste filtrée, recherche « Etu 1 », page 3', fn() => ep_get_filtered_credits($ep->id,
    ['search' => 'Etu 1', 'typeid' => 0, 'status' => '', 'studyyear' => ''], 'student', 'ASC', null, null, 120, 40));
$measure('Liste filtrée (compte)', fn() => ep_count_filtered_credits($ep->id,
    ['search' => '', 'typeid' => 0, 'status' => 0, 'studyyear' => '']));
$measure('Bilan d\'une page de pilotage (40 étudiants)', fn() => ep_build_pilotage_rows($ep,
    ep_get_enrolled_students($context, '', null, 0, 40)));
$measure('Bilan de 500 étudiants', fn() => ep_build_pilotage_rows($ep, ep_get_enrolled_students($context, '', null, 0, 500)));
$measure('Bilan d\'un étudiant', fn() => ep_get_student_progress($ep, $student));
$measure('Places prises de tous les EP', fn() => ep_get_activities_taken_places($ep->id));
$measure('Statuts par EP (catalogue)', fn() => ep_get_activities_status_counts($ep->id));
$measure('Prérequis manquants, catalogue entier', fn() => ep_get_missing_prerequisites_bulk($ep, $activities, $student));
$measure('Participants d\'un EP', fn() => ep_get_participants($activities[array_key_first($activities)]));
$measure('Contenu du supplément d\'un étudiant', fn() => ep_build_supplement($ep, $DB->get_record('user', ['id' => $student]), 'fr'));
$measure('Éléments du supplément en attente (page de 40)', fn() => ep_get_supplement_items($ep->id,
    ['status' => EP_STATUS_PENDING], 0, 40));
$measure('Décisions d\'année (500 étudiants)', fn() => ep_get_year_validations($ep->id, array_slice($site['students'], 0, 500)));
$measure('Export du paramétrage avec catalogue', fn() => ep_export_settings($ep, true));
