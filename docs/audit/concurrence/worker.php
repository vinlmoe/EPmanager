<?php
// Une procédure lourde dans son propre processus, départ synchronisé (variable STARTAT) ; une
// ligne JSON en sortie : durée, requêtes, mémoire, résultat ou exception.
//
// Usage : php worker.php site-<tag>.json restore <archive.zip> <cmid cible>
//         php worker.php site-<tag>.json credits <fichier.csv>
define('CLI_SCRIPT', true);
require(getenv('MOODLE_CONFIG') ?: '/home/user/moodle-stress/moodle/config.php');
require_once($CFG->dirroot . '/mod/ep/locallib.php');

[$self, $sitefile, $scenario] = $argv;
$args = array_slice($argv, 3);
$site = json_decode(file_get_contents($sitefile), true);
$deve = $DB->get_record('user', ['username' => "deve{$site['tag']}_" . (1 + getmypid() % 4)], '*', MUST_EXIST);
\core\session\manager::set_user($deve);
if (getenv('STARTAT')) {
    while (microtime(true) < (float) getenv('STARTAT')) {
        usleep(2000);
    }
}
$q0 = $DB->perf_get_queries();
$t0 = microtime(true);
$out = ['scenario' => $scenario, 'pid' => getmypid()];
try {
    switch ($scenario) {
        case 'restore':
            $cm = get_coursemodule_from_id('ep', (int) $args[1], 0, false, MUST_EXIST);
            $ep = $DB->get_record('ep', ['id' => $cm->instance], '*', MUST_EXIST);
            $archive = \mod_ep\local\global_archive::open($args[0]);
            $report = \mod_ep\local\global_archive::restore($ep, context_module::instance($cm->id), $archive, ['data'], $deve->id);
            $out += ['credits' => $report->credits, 'items' => $report->items, 'years' => $report->years,
                'skipped' => $report->skipped];
            break;
        case 'credits':
            $ep = $DB->get_record('ep', ['id' => $site['epid']], '*', MUST_EXIST);
            $read = \mod_ep\local\credits_importer::read($ep, context_module::instance($site['cmid']), file_get_contents($args[0]));
            $result = \mod_ep\local\credits_importer::apply($ep, $read->rows, $deve->id);
            $out += ['lues' => count($read->rows), 'created' => $result->created, 'skipped' => $result->skipped];
            break;
    }
} catch (Throwable $e) {
    $out['exception'] = get_class($e) . ': ' . $e->getMessage();
}
$out['seconds'] = round(microtime(true) - $t0, 2);
$out['queries'] = $DB->perf_get_queries() - $q0;
$out['memory_mb'] = round(memory_get_peak_usage() / 1048576, 1);
echo json_encode($out), "\n";
