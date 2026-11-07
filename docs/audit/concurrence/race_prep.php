<?php
// Prépare les cibles des courses du banc de concurrence et en vérifie les invariants.
//
// Usage : php race_prep.php site-<tag>.json prep    -> JSON des cibles
//         php race_prep.php site-<tag>.json check   -> JSON des invariants
// Variable MOODLE_CONFIG : chemin de config.php (par défaut celui du banc).
define('CLI_SCRIPT', true);
require(getenv('MOODLE_CONFIG') ?: '/home/user/moodle-stress/moodle/config.php');
require_once($CFG->dirroot . '/mod/ep/locallib.php');

$site = json_decode(file_get_contents($argv[1]), true);
$mode = $argv[2] ?? 'check';
$ep = $DB->get_record('ep', ['id' => $site['epid']], '*', MUST_EXIST);
\core\session\manager::set_user(get_admin());
set_debugging(DEBUG_DEVELOPER, true);

if ($mode === 'prep') {
    $catalog = ep_get_type_by_code($ep->id, EP_TYPE_ACADEMIC);
    $make = function ($code, $capacity) use ($ep, $catalog, $DB) {
        $id = $DB->get_field('ep_activity', 'id', ['epid' => $ep->id, 'code' => $code]);
        if ($id) {
            $DB->delete_records_select('ep_credit_decision', 'creditid IN (SELECT id FROM {ep_credit} WHERE activityid = ?)', [$id]);
            $DB->delete_records('ep_credit', ['activityid' => $id]);
        }
        return ep_save_activity($ep, (object) ['id' => $id ?: 0, 'typeid' => $catalog->id, 'code' => $code,
            'name' => "Course $code", 'ects' => 1, 'capacity' => $capacity, 'state' => EP_ACTIVITY_PUBLISHED,
            'minstudyyear' => 0, 'maxstudyyear' => 0, 'registrationstart' => 0, 'registrationend' => 0, 'minects' => 0]);
    };
    $out = ['rush' => $make('RUSH', 30), 'click' => $make('CLICK', 0)];
    // Demandes en attente à trancher par deux personnes à la fois : celles des EP dont le premier
    // enseignant est responsable (il les tranche comme responsable, la DEVE en même temps).
    $sql = "SELECT c.id FROM {ep_credit} c JOIN {ep_activity_teacher} t ON t.activityid = c.activityid
             WHERE c.epid = :epid AND c.status = :pending AND c.pendingrole = :owner AND t.teacherid = :teacher";
    $out['ownercredits'] = array_map('intval', $DB->get_fieldset_sql($sql, ['epid' => $ep->id, 'pending' => EP_STATUS_PENDING,
        'owner' => EP_ROLE_OWNER, 'teacher' => $site['teachers'][0]]));
    $sql = "SELECT id FROM {ep_credit} WHERE epid = :epid AND status = :pending AND activityid IS NULL ORDER BY id";
    $out['devecredits'] = array_map('intval', array_slice($DB->get_fieldset_sql($sql, ['epid' => $ep->id,
        'pending' => EP_STATUS_PENDING]), 0, 100));
    echo json_encode($out);
    exit;
}

// Invariants.
$check = [];
foreach (['RUSH', 'CLICK'] as $code) {
    $activityid = $DB->get_field('ep_activity', 'id', ['epid' => $ep->id, 'code' => $code]);
    $check[$code] = [
        'actives' => $DB->count_records_select('ep_credit', 'activityid = ? AND status IN (0, 1)', [$activityid]),
        'doubles' => (int) $DB->get_field_sql('SELECT COUNT(1) FROM (SELECT userid FROM {ep_credit} WHERE activityid = ?
            AND status IN (0, 1) GROUP BY userid HAVING COUNT(1) > 1) d', [$activityid]),
    ];
}
// Une demande tranchée a une seule décision finale ; une décision par étape au plus.
$check['decisions_en_double'] = (int) $DB->get_field_sql("SELECT COUNT(1) FROM (SELECT d.creditid, d.step FROM {ep_credit_decision} d
    JOIN {ep_credit} c ON c.id = d.creditid WHERE c.epid = ? GROUP BY d.creditid, d.step HAVING COUNT(1) > 1) x", [$ep->id]);
$check['tranchees_sans_decision'] = (int) $DB->count_records_sql("SELECT COUNT(1) FROM {ep_credit} c WHERE c.epid = ?
    AND c.status IN (1, -1) AND c.source <> 'stage' AND NOT EXISTS (SELECT 1 FROM {ep_credit_decision} d WHERE d.creditid = c.id)",
    [$ep->id]);
// Suppléments : un seul en vigueur par étudiant, numéros uniques.
$check['supplements_en_vigueur_multiples'] = (int) $DB->get_field_sql("SELECT COUNT(1) FROM (SELECT userid FROM
    {ep_supplement_issue} WHERE epid = ? AND replacedby IS NULL GROUP BY userid HAVING COUNT(1) > 1) x", [$ep->id]);
$check['numeros_en_double'] = (int) $DB->get_field_sql("SELECT COUNT(1) FROM (SELECT number FROM {ep_supplement_issue}
    GROUP BY number HAVING COUNT(1) > 1) x");
$check['verrous_restants'] = (int) $DB->get_field_sql("SELECT COUNT(1) FROM pg_locks WHERE locktype = 'advisory'");
$check['transactions_ouvertes'] = (int) $DB->get_field_sql("SELECT COUNT(1) FROM pg_stat_activity
    WHERE state = 'idle in transaction'");
echo json_encode($check);
