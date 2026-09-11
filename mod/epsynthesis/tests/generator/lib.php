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
 * Générateur de données de test pour mod_epsynthesis.
 *
 * L'activité « Suivi de l'enseignement personnalisé » n'a pas de données de suivi propres : tout
 * est relu à la volée dans les activités mod_ep liées. Le générateur n'a donc rien à ajouter à
 * testing_module_generator, mais il doit exister : \testing_data_generator::create_module()
 * refuse de créer une instance d'un module qui n'en fournit pas.
 *
 * @package   mod_epsynthesis
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_epsynthesis_generator extends testing_module_generator {
}
