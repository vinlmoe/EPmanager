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

namespace mod_ep\output;

use moodle_url;
use renderable;
use renderer_base;
use stdClass;
use templatable;

/**
 * Formulaire de filtres d'une liste (gabarit mod_ep/list_filters) : recherche par nom et listes
 * déroulantes facultatives, soumis en GET sur l'URL de la liste.
 *
 * @package mod_ep
 * @copyright 2026 Sébastien Lefebvre
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class list_filters implements renderable, templatable {
    /** @var moodle_url URL de soumission : la liste, débarrassée de ses paramètres de filtre et de tri. */
    private $formurl;

    /** @var string Recherche courante. */
    private $search;

    /** @var array nom => ['options' => [valeur => libellé], 'selected' => valeur courante]. */
    private $selects;

    /** @var string Texte d'aide du champ de recherche. */
    private $placeholder;

    /**
     * Prépare le formulaire à partir de l'URL de la liste et de ses filtres courants.
     *
     * @param moodle_url $listurl URL de la liste, avec ses filtres courants.
     * @param string[] $filterparams Paramètres de filtre et de tri à retirer de l'URL de soumission :
     *                               ils sont portés par les champs du formulaire, pas par son URL.
     * @param string $search Recherche courante.
     * @param array $selects Listes déroulantes : nom => ['options' => [valeur => libellé], 'selected' => valeur].
     * @param string|null $placeholder Texte d'aide du champ de recherche (par défaut : nom de l'étudiant).
     */
    public function __construct(moodle_url $listurl, array $filterparams, $search, array $selects = [], $placeholder = null) {
        $this->formurl = new moodle_url($listurl);
        $this->formurl->remove_params($filterparams);
        $this->search = (string) $search;
        $this->selects = $selects;
        $this->placeholder = $placeholder ?? get_string('searchstudent', 'mod_ep');
    }

    /**
     * Données du gabarit.
     *
     * @param renderer_base $output
     * @return stdClass
     */
    public function export_for_template(renderer_base $output) {
        $data = new stdClass();
        $data->formurl = $this->formurl->out(false);
        $data->hidden = [];
        foreach ($this->formurl->params() as $name => $value) {
            $data->hidden[] = ['name' => $name, 'value' => $value];
        }
        $data->search = $this->search;
        $data->searchplaceholder = $this->placeholder;
        $data->selects = [];
        foreach ($this->selects as $name => $select) {
            $options = [];
            foreach ($select['options'] as $value => $label) {
                $options[] = [
                    'value' => (string) $value,
                    'label' => $label,
                    'selected' => (string) $value === (string) $select['selected'],
                ];
            }
            // Nom lu par les lecteurs d'écran : le libellé fourni, sinon celui de la première option
            // (« Tous les statuts »...), plutôt que le nom technique du paramètre.
            $data->selects[] = ['name' => $name, 'options' => $options,
                'label' => $select['label'] ?? ($options[0]['label'] ?? $name)];
        }
        $data->submitlabel = get_string('search');
        $data->resetlabel = get_string('resetfilters', 'mod_ep');
        $data->reseturl = $this->formurl->out(false);
        return $data;
    }
}
