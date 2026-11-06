<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

namespace mod_ep\event;

/**
 * Crédit de type stage créé, mis à jour ou retiré par la synchronisation des stages complémentaires
 * validés par la DEVE (ep_sync_stage_credits()). « other » porte l'action (action : created,
 * updated ou deleted) et l'identifiant de la saisie mod_stage d'origine (sourceref). Pour un
 * retrait, l'objet est l'ancien identifiant du crédit, qui n'existe plus en base.
 *
 * L'utilisateur est souvent absent (0) : la synchronisation tourne en tâche planifiée.
 *
 * @package mod_ep
 * @copyright 2026 Sébastien Lefebvre
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class credit_synced extends credit_event_base {
    /** @var string Lettre CRUD de l'événement. */
    const CRUD = 'u';

    /** @var int Niveau éducatif de l'événement. */
    const EDULEVEL = self::LEVEL_OTHER;

    /**
     * Description pour les journaux, adaptée à l'action de la synchronisation.
     *
     * @return string
     */
    public function get_description() {
        $sourceref = (int) ($this->other['sourceref'] ?? 0);
        switch ($this->other['action'] ?? '') {
            case 'created':
                $verb = 'créé';
                break;
            case 'deleted':
                $verb = 'retiré';
                break;
            default:
                $verb = 'mis à jour';
        }
        return $this->describe("{$verb}, par synchronisation du stage #{$sourceref},");
    }
}
