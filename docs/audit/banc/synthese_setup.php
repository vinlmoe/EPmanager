<?php
// Synthèse enseignant du banc : un cours d'équipe, une activité « Suivi de l'enseignement
// personnalisé » liée aux activités EP de toutes les promotions générées, les enseignants et la
// DEVE inscrits. Usage : php synthese_setup.php site-a.json [site-b.json ...] ; affiche le cmid.
define('CLI_SCRIPT', true);
require(getenv('MOODLE_CONFIG') ?: '/home/user/moodle-stress/moodle/config.php');
require_once($CFG->dirroot . '/mod/epsynthesis/locallib.php');
require_once($CFG->dirroot . '/lib/phpunit/classes/util.php');

\core\session\manager::set_user(get_admin());
$gen = phpunit_util::get_data_generator();
$sites = array_map(fn($f) => json_decode(file_get_contents($f), true), array_slice($argv, 1));
$course = $gen->create_course(['fullname' => 'Équipe enseignante EP', 'shortname' => 'EQUIPE-EP']);
$synthesis = $gen->create_module('epsynthesis', ['course' => $course->id, 'name' => 'Suivi de l\'enseignement personnalisé']);
epsynthesis_set_links($synthesis->id, array_map(fn($s) => (int) $s['cmid'], $sites));
$roles = $DB->get_records_menu('role', null, '', 'shortname, id');
foreach (array_merge($sites[0]['teachers'], $sites[0]['deve']) as $userid) {
    $gen->enrol_user($userid, $course->id, $roles['teacher']);
}
echo $synthesis->cmid, "\n";
