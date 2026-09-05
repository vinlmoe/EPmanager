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
 * Point d'entrée standard de l'activité (lien « Voir » depuis la page du cours) : redirige
 * directement vers l'écran de validation (entries.php), page d'atterrissage habituelle de
 * l'enseignant — c'est ce qui attend une décision de sa part qui l'amène ici.
 *
 * @package   mod_epsynthesis
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

$id = required_param('id', PARAM_INT);

$cm = get_coursemodule_from_id('epsynthesis', $id, 0, false, MUST_EXIST);
$course = get_course($cm->course);

require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/epsynthesis:view', $context);

redirect(new moodle_url('/mod/epsynthesis/entries.php', ['id' => $cm->id]));
