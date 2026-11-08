<?php
// Liaison avec mod_stage : saisies de stage préparées pour liaison_stage.py, par les fonctions de
// mod_stage (comme le ferait l'inscription d'un stage par l'étudiant).
//
// Usage : sudo -u www-data php liaison_stage_prep.php site-x.json > liaison.json
//   prepare   : 200 stages complémentaires (A), 50 obligatoires (B), 50 complémentaires (C), non
//               validés ; affiche leurs identifiants en JSON.
//   details <entryid> <jours> : correction de la durée retenue écrite en base, sans événement
//               (mod_stage n'enregistre plus la durée retenue avec les détails de la saisie) ;
//   type <entryid> <obligatoire|complementaire> : stage_set_entry_stagetype() ;
//   reset     : supprime les saisies préparées (thématique « Faune sauvage (EP) »), remet le barème
//               du type stage à 0 et etux_1 à son référent d'origine (ensa_1), pour rejouer le test ;
//   signature : durée moyenne de ep_stage_sync_signature() et de
//               ep_sync_stage_credits_if_due() quand rien n'a changé.
define('CLI_SCRIPT', true);
require(getenv('MOODLE_CONFIG') ?: '/home/user/moodle-stress/moodle/config.php');
require_once($CFG->dirroot . '/mod/stage/lib.php');
require_once($CFG->dirroot . '/mod/stage/locallib.php');
require_once($CFG->dirroot . '/mod/ep/locallib.php');
require_once($CFG->dirroot . '/lib/phpunit/classes/util.php');

$site = json_decode(file_get_contents($argv[1]), true);
$command = $argv[2] ?? 'prepare';
\core\session\manager::set_user(get_admin());

if ($command === 'details') {
    $entry = $DB->get_record('stage_entry', ['id' => (int) $argv[3]], '*', MUST_EXIST);
    $DB->update_record('stage_entry', (object) ['id' => $entry->id, 'retainedduration' => (int) $argv[4],
        'timemodified' => time()]);
    echo "ok\n";
    exit;
}
if ($command === 'type') {
    stage_set_entry_stagetype((int) $argv[3], $argv[4]);
    echo "ok\n";
    exit;
}
if ($command === 'reset') {
    $stage = $DB->get_record('stage', ['id' => $site['stageid']], '*', MUST_EXIST);
    $context = context_module::instance(get_coursemodule_from_instance('stage', $stage->id)->id);
    foreach ($DB->get_records('stage_theme', ['stageid' => $stage->id, 'name' => 'Faune sauvage (EP)']) as $theme) {
        stage_delete_entries(array_keys($DB->get_records('stage_entry', ['themeid' => $theme->id], '', 'id')), $context);
        $DB->delete_records('stage_theme', ['id' => $theme->id]);
    }
    $DB->set_field('ep_type', 'ectsperday', 0, ['epid' => $site['epid'], 'code' => 'stage']);
    $student = $DB->get_field('user', 'id', ['username' => 'etu' . $site['tag'] . '_1']);
    stage_set_student_teachers($stage->id, $student, [$DB->get_field('user', 'id', ['username' => 'ensa_1'])]);
    $ep = $DB->get_record('ep', ['id' => $site['epid']], '*', MUST_EXIST);
    ep_sync_stage_credits($ep);
    echo "ok\n";
    exit;
}
if ($command === 'signature') {
    $ep = $DB->get_record('ep', ['id' => $site['epid']], '*', MUST_EXIST);
    ep_sync_stage_credits_if_due($ep);
    $t = microtime(true);
    for ($i = 0; $i < 100; $i++) {
        ep_stage_sync_signature($ep);
    }
    $signature = (microtime(true) - $t) * 10;
    $t = microtime(true);
    for ($i = 0; $i < 100; $i++) {
        ep_sync_stage_credits_if_due($ep);
    }
    printf("{\"signature_ms\": %.2f, \"verification_ms\": %.2f}\n", $signature, (microtime(true) - $t) * 10);
    exit;
}

$gen = phpunit_util::get_data_generator();
$stagegen = $gen->get_plugin_generator('mod_stage');
$stage = $DB->get_record('stage', ['id' => $site['stageid']], '*', MUST_EXIST);
$theme = $stagegen->create_theme($stage, ['name' => 'Faune sauvage (EP)']);
$users = $DB->get_records_list('user', 'id', $site['students'], '', 'id, username');
$byrank = [];
foreach ($users as $user) {
    $byrank[(int) substr($user->username, strrpos($user->username, '_') + 1)] = $user;
}
$out = ['stagecmid' => (int) get_coursemodule_from_instance('stage', $stage->id)->id, 'theme' => $theme->name];
foreach (['A' => [300, 499, 'complementaire'], 'B' => [500, 549, 'obligatoire'], 'C' => [550, 599, 'complementaire']]
        as $group => [$from, $to, $kind]) {
    $out[$group] = [];
    for ($k = $from; $k <= $to; $k++) {
        $user = $byrank[$k];
        $days = 4 + $k % 17;
        $entry = $stagegen->create_entry($stage, $user->id, $theme, [
            'declaredduration' => $days, 'studyyear' => 3, 'abroad' => (int) ($k % 7 === 0),
            'country' => $k % 7 === 0 ? 'Canada' : '', 'structure' => 'Centre de soins ' . $k,
        ]);
        stage_set_entry_stagetype($entry->id, $kind);
        $out[$group][] = ['entry' => (int) $entry->id, 'userid' => (int) $user->id, 'username' => $user->username, 'days' => $days];
    }
}
echo json_encode($out), "\n";
