<?php
// This file is part of Moodle - http://moodle.org/
//
// This program is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// This program is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Banc de migration : après la mise à jour, vérifie que les types et les crédits créés par
 * migration_avant.php ont été convertis à l'identique de leur comportement, et que le circuit
 * fonctionne sur les données migrées. Code de sortie non nul au premier écart.
 *
 * Usage : php migration_apres.php <config.php> <sortie.json>
 *
 * @package   mod_ep
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);
require($argv[1]);
require_once($CFG->dirroot . '/mod/ep/locallib.php');

$before = json_decode(file_get_contents($argv[2]));
$failures = 0;
$check = function ($label, $expected, $actual) use (&$failures) {
    $ok = $expected == $actual;
    $failures += $ok ? 0 : 1;
    echo ($ok ? 'OK   ' : 'ÉCART') . " $label : attendu " . json_encode($expected) . ', obtenu ' . json_encode($actual) . "\n";
};

$expected = [
    'academic' => ['catalog', 'owner'], 'stage' => ['stage', ''], 'engagement' => ['declaration', 'referent'],
    'professional' => ['declaration', 'referent'], 'sport' => ['declaration', 'referent'],
    'external' => ['declaration', 'referent'],
];
foreach ($DB->get_records('ep_type', ['epid' => $before->epid]) as $type) {
    $check("type {$type->code}", $expected[$type->code], [$type->acquisition, $type->validationflow]);
}
$columns = $DB->get_columns('ep_type');
$check('ancien champ catalog supprimé', false, isset($columns['catalog']));

$roles = ['owner', '', 'referent', '', ''];
foreach ($before->credits as $i => $creditid) {
    $credit = $DB->get_record('ep_credit', ['id' => $creditid], '*', MUST_EXIST);
    $check("crédit $i : rôle attendu", $roles[$i], $credit->pendingrole);
    $decisions = $DB->count_records('ep_credit_decision', ['creditid' => $creditid]);
    $check("crédit $i : décisions reconstituées", $roles[$i] === '' ? 1 : 0, $decisions);
}

// Le circuit fonctionne sur les données migrées : le responsable valide l'inscription en attente.
$credit = $DB->get_record('ep_credit', ['id' => $before->credits[0]], '*', MUST_EXIST);
$check('validation par le responsable', true, ep_validate_credit($credit, $before->teacher, 2, 'Après migration'));
$check('statut final', EP_STATUS_VALIDATED, (int) $DB->get_field('ep_credit', 'status', ['id' => $credit->id]));

echo $failures ? "$failures écart(s).\n" : "Migration conforme.\n";
exit($failures ? 1 : 0);
