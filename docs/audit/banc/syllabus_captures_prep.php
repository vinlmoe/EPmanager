<?php
// Mise en scène des captures du guide des modules syllabus, après le scénario de bout en bout
// (syllabus_interactions.py) : une mise à jour de fiche soumise à la DEVE (relecture avec
// rubriques modifiées), un étudiant qui n'a pas encore donné son avis sur le premier module, dont
// le recueil est rouvert le temps des captures (dans la même année : rien n'est archivé).
//
// Usage : sudo -u www-data php syllabus_captures_prep.php site-x.json syllabus.json prepare
//         sudo -u www-data php syllabus_captures_prep.php site-x.json syllabus.json restore <fermeture>
// « prepare » écrit sur la sortie standard les variables d'environnement de captures.js :
// SYREF (référentiel du scénario), SYCOMP (compétence la plus visée), SYEVAL (module à évaluer),
// ETU (étudiant), SYCLOSE (fermeture d'origine, à repasser à « restore »).

define('CLI_SCRIPT', true);
require(getenv('MOODLE_CONFIG') ?: '/home/user/moodle-stress/moodle/config.php');
require_once($CFG->dirroot . '/mod/syllabus/locallib.php');

$site = json_decode(file_get_contents($argv[1]), true);
$setup = json_decode(file_get_contents($argv[2]), true);
$mode = $argv[3] ?? 'prepare';
$first = $setup['modules'][0];
$second = $setup['modules'][1];

if ($mode === 'restore') {
    $DB->set_field('syllabus', 'evalclose', (int) $argv[4], ['id' => $first['id']]);
    echo "ok\n";
    exit;
}

// Mise à jour de la fiche du deuxième module, soumise par son responsable.
$cm = get_coursemodule_from_id('syllabus', $second['cmid'], 0, false, MUST_EXIST);
$syllabus = $DB->get_record('syllabus', ['id' => $cm->instance], '*', MUST_EXIST);
if (!$DB->record_exists('syllabus_version', ['syllabusid' => $syllabus->id, 'status' => SYLLABUS_STATUS_SUBMITTED])) {
    $leader = current(syllabus_get_teachers((int) $syllabus->id));
    \core\session\manager::set_user($leader);
    $version = syllabus_start_working($syllabus, (int) $leader->id);
    $sessions = array_map(fn($s) => (array) $s, array_values(syllabus_get_sessions((int) $version->id)));
    $sessions[] = ['title' => 'Étude de cas', 'kind' => 'tutorial', 'duration' => 2, 'teacherid' => $leader->id];
    syllabus_save_working($version, (object) ['outcomes' => trim($version->outcomes) . "\nInterpréter un cas clinique simple."],
        $sessions, (int) $leader->id);
    syllabus_submit($syllabus, $cm, $DB->get_record('syllabus_version', ['id' => $version->id]), (int) $leader->id);
    \core\session\manager::set_user(get_admin());
}

// Recueil du premier module rouvert pour un étudiant qui n'a pas encore répondu cette année.
$students = $DB->get_fieldset_sql("SELECT u.username
                                 FROM {user} u
                                 JOIN {user_enrolments} ue ON ue.userid = u.id
                                 JOIN {enrol} e ON e.id = ue.enrolid
                                 JOIN {syllabus} s ON s.course = e.courseid AND s.id = ?
                                WHERE " . $DB->sql_like('u.username', '?') . "
                                      AND NOT EXISTS (SELECT 1 FROM {syllabus_participant} p
                                                       WHERE p.syllabusid = s.id AND p.userid = u.id)
                             ORDER BY u.id", [$first['id'], 'etu' . $site['tag'] . '_%']);
$student = reset($students);
$close = (int) $DB->get_field('syllabus', 'evalclose', ['id' => $first['id']]);
$DB->set_field('syllabus', 'evalclose', time() + DAYSECS, ['id' => $first['id']]);

$ids = implode(',', array_map(fn($m) => (int) $m['id'], $setup['modules']));
$ref = (int) $DB->get_field('syllabus_framework', 'id', ['idnumber' => 'REFVET']);
$counts = $DB->get_records_sql_menu("SELECT compid, COUNT(1) FROM {syllabus_complink} WHERE syllabusid IN ($ids)
                                      GROUP BY compid ORDER BY COUNT(1) DESC, compid");
$comp = (int) array_key_first($counts);
echo "SYREF=$ref SYCOMP=$comp SYEVAL={$first['cmid']} ETU=$student SYCLOSE=$close\n";
