<?php
// Banc des modules syllabus : 12 cours de module dans une catégorie, chacun avec son activité
// « Syllabus du module » et deux responsables (ensa_*), 200 étudiants de la promotion x inscrits à
// tous, la DEVE (devea_1, devea_2) gestionnaire de chaque cours ; un cours d'équipe avec le suivi
// des modules ; l'activité « Mes modules » dans le cours de la promotion.
// Usage : sudo -u www-data php syllabus_setup.php site-x.json > syllabus.json ; avec « reset » en second
// argument, supprime ce que le script a créé, pour rejouer.
define('CLI_SCRIPT', true);
require(getenv('MOODLE_CONFIG') ?: '/home/user/moodle-stress/moodle/config.php');
require_once($CFG->dirroot . '/mod/syllabus/locallib.php');
require_once($CFG->dirroot . '/lib/phpunit/classes/util.php');

$site = json_decode(file_get_contents($argv[1]), true);
\core\session\manager::set_user(get_admin());
if (($argv[2] ?? '') === 'reset') {
    $tag = $site['tag'];
    foreach ($DB->get_records_select('course', 'shortname LIKE ? OR shortname = ?', ['M%-' . $tag, 'EQUIPE-SYL-' . $tag]) as $c) {
        delete_course($c, false);
    }
    foreach ($DB->get_records('course_categories', ['name' => 'Modules A3 (banc ' . $tag . ')']) as $cat) {
        core_course_category::get($cat->id)->delete_full(false);
    }
    foreach (get_coursemodules_in_course('syllabusstudent', $site['courseid']) as $cm) {
        course_delete_module($cm->id);
    }
    if ($framework = $DB->get_record('competency_framework', ['idnumber' => 'REFVET-' . $tag])) {
        \core_competency\api::delete_framework($framework->id);
    }
    $DB->delete_records('syllabus_question', ['syllabusid' => 0]);
    $DB->delete_records('syllabus_type');
    echo "ok\n";
    exit;
}
$gen = phpunit_util::get_data_generator();
$user = fn($name) => $DB->get_record('user', ['username' => $name], '*', MUST_EXIST);
$category = $gen->create_category(['name' => 'Modules A3 (banc ' . $site['tag'] . ')']);
$students = array_slice($site['students'], 0, 200);
$deve = [$user('devea_1'), $user('devea_2')];
$names = ['Anatomie comparée', 'Physiologie', 'Pharmacologie', 'Pathologie médicale', 'Chirurgie', 'Imagerie',
    'Parasitologie', 'Bactériologie', 'Nutrition', 'Reproduction', 'Santé publique', 'Clinique des équidés'];
$out = ['category' => (int) $category->id, 'modules' => []];
foreach ($names as $k => $name) {
    $course = $gen->create_course(['category' => $category->id, 'fullname' => $name, 'shortname' => 'M' . ($k + 1) . '-'
        . $site['tag']]);
    $leaders = [$user('ensa_' . (2 * $k + 1)), $user('ensa_' . (2 * $k + 2))];
    foreach ($leaders as $leader) {
        $gen->enrol_user($leader->id, $course->id, 'editingteacher');
    }
    foreach ($deve as $d) {
        $gen->enrol_user($d->id, $course->id, 'manager');
    }
    foreach ($students as $sid) {
        $gen->enrol_user($sid, $course->id, 'student');
    }
    $module = $gen->create_module('syllabus', ['course' => $course->id, 'name' => $name, 'code' => 'UE' . ($k + 1),
        'ects' => 2 + $k % 4, 'semester' => $k < 6 ? 'S5' : 'S6', 'studyyear' => 3]);
    $syllabus = $DB->get_record('syllabus', ['id' => $module->id]);
    syllabus_set_teachers($syllabus, context_module::instance($module->cmid), array_map(fn($u) => $u->id, $leaders));
    $out['modules'][] = ['cmid' => (int) $module->cmid, 'id' => (int) $module->id, 'course' => (int) $course->id,
        'name' => $name, 'leaders' => array_map(fn($u) => $u->username, $leaders)];
}
// Référentiel de compétences : 5 domaines de 4 compétences. Le cours de « Santé publique » vise
// en plus une compétence au niveau du cours seulement (lien de Moodle, sans module).
$cgen = $gen->get_plugin_generator('core_competency');
$framework = $cgen->create_framework(['shortname' => 'Compétences du vétérinaire', 'idnumber' => 'REFVET-' . $site['tag']]);
$domains = ['Démarche clinique', 'Santé publique vétérinaire', 'Thérapeutique', 'Communication', 'Professionnalisme'];
$leaves = [];
foreach ($domains as $d => $domainname) {
    $domain = $cgen->create_competency(['competencyframeworkid' => $framework->get('id'), 'shortname' => $domainname,
        'idnumber' => 'D' . ($d + 1)]);
    for ($c = 1; $c <= 4; $c++) {
        $leaves[] = $cgen->create_competency(['competencyframeworkid' => $framework->get('id'), 'parentid' => $domain->get('id'),
            'shortname' => $domainname . ' : compétence ' . $c, 'idnumber' => 'D' . ($d + 1) . '.' . $c])->get('id');
    }
}
\core_competency\api::add_competency_to_course($out['modules'][10]['course'], $leaves[19]);
$out['frameworkid'] = (int) $framework->get('id');
$out['competencies'] = $leaves;
$team = $gen->create_course(['fullname' => 'Équipe pédagogique (banc ' . $site['tag'] . ')', 'shortname' => 'EQUIPE-SYL-'
    . $site['tag']]);
for ($i = 1; $i <= 24; $i++) {
    $gen->enrol_user($user('ensa_' . $i)->id, $team->id, 'teacher');
}
foreach ($deve as $d) {
    $gen->enrol_user($d->id, $team->id, 'manager');
}
$staff = $gen->create_module('syllabusstaff', ['course' => $team->id, 'name' => 'Suivi des modules',
    'categoryid' => $category->id]);
$student = $gen->create_module('syllabusstudent', ['course' => $site['courseid'], 'name' => 'Mes modules',
    'categoryid' => $category->id]);
$out['staffcmid'] = (int) $staff->cmid;
$out['studentcmid'] = (int) $student->cmid;
$out['students'] = array_values($DB->get_fieldset_select('user', 'username', 'id IN (' . implode(',', $students) . ')'));
echo json_encode($out), "\n";
