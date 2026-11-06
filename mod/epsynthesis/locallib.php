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
 * Fonctions métier internes pour mod_epsynthesis.
 *
 * @package   mod_epsynthesis
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/epsynthesis/lib.php');
require_once($CFG->dirroot . '/mod/ep/lib.php');
require_once($CFG->dirroot . '/mod/ep/locallib.php');

/**
 * Rend, pour les utilisateurs qui ont le droit de les modifier, le rappel du nombre d'activités
 * liées et le lien vers leur gestion : affiché identiquement en tête de dashboard.php et
 * entries.php, factorisé ici pour que les deux ne divergent pas.
 *
 * @param stdClass $epsynthesis
 * @param stdClass $cm
 * @param context $context
 * @return string HTML, ou '' si l'utilisateur n'a pas la capacité de gérer les liens.
 */
function epsynthesis_render_managelinks_notice(stdClass $epsynthesis, stdClass $cm, context $context) {
    if (!has_capability('mod/epsynthesis:managelinks', $context)) {
        return '';
    }

    $links = epsynthesis_get_links($epsynthesis->id);
    return html_writer::div(
        get_string('linkedcount', 'mod_epsynthesis', count($links)) . ' ' .
        html_writer::link(
            new moodle_url('/mod/epsynthesis/administration.php', ['id' => $cm->id]),
            get_string('managelinks', 'mod_epsynthesis')
        ),
        'mb-3'
    );
}

/**
 * Liste des course-modules d'activités « Enseignement personnalisé » actuellement liées à une
 * instance de synthèse.
 *
 * @param int $synthesisid
 * @return array cmid => stdClass{linkid, epcmid, courseid, coursename, epname, visible, coursevisible}
 */
function epsynthesis_get_links($synthesisid) {
    global $DB;

    $sql = "SELECT l.id AS linkid, l.epcmid, cm.visible, e.name AS epname, c.id AS courseid,
                   c.fullname AS coursename, c.visible AS coursevisible
              FROM {epsynthesis_link} l
              JOIN {course_modules} cm ON cm.id = l.epcmid
              JOIN {ep} e ON e.id = cm.instance
              JOIN {course} c ON c.id = cm.course
             WHERE l.synthesisid = :synthesisid
          ORDER BY c.fullname ASC, e.name ASC";

    $rows = $DB->get_records_sql($sql, ['synthesisid' => $synthesisid]);

    $links = [];
    foreach ($rows as $row) {
        $links[(int) $row->epcmid] = $row;
    }
    return $links;
}

/**
 * Liste des activités « Enseignement personnalisé » que l'utilisateur donné a le droit de lier :
 * uniquement celles où il a lui-même un rôle de gestion (mod/ep:manage), pour ne jamais exposer
 * les noms de cours/activités d'une promotion à laquelle il n'a par ailleurs aucun accès. Inclut
 * les activités masquées et celles dans des cours masqués : c'est justement ce qui permet
 * d'exclure explicitement une promotion qui n'est plus suivie plutôt que de devoir supprimer son
 * activité d'origine.
 *
 * @param int $userid
 * @return array cmid => stdClass{epcmid, courseid, coursename, epname, visible, coursevisible}
 */
function epsynthesis_get_available_ep_activities($userid) {
    global $DB;

    $sql = "SELECT cm.id AS epcmid, cm.visible, e.name AS epname, c.id AS courseid,
                   c.fullname AS coursename, c.visible AS coursevisible
              FROM {course_modules} cm
              JOIN {modules} m ON m.id = cm.module AND m.name = 'ep'
              JOIN {ep} e ON e.id = cm.instance
              JOIN {course} c ON c.id = cm.course
          ORDER BY c.fullname ASC, e.name ASC";

    $candidates = $DB->get_records_sql($sql);

    $available = [];
    foreach ($candidates as $candidate) {
        $modcontext = context_module::instance($candidate->epcmid, IGNORE_MISSING);
        if ($modcontext && has_capability('mod/ep:manage', $modcontext, $userid)) {
            $available[(int) $candidate->epcmid] = $candidate;
        }
    }
    return $available;
}

/**
 * Remplace la liste des activités « Enseignement personnalisé » liées à une instance de synthèse.
 *
 * @param int $synthesisid
 * @param int[] $epcmids
 * @return void
 */
function epsynthesis_set_links($synthesisid, array $epcmids) {
    global $DB;

    $epcmids = array_filter(array_unique(array_map('intval', $epcmids)));

    $DB->delete_records('epsynthesis_link', ['synthesisid' => $synthesisid]);

    $now = time();
    foreach ($epcmids as $epcmid) {
        $DB->insert_record('epsynthesis_link', (object) [
            'synthesisid' => $synthesisid,
            'epcmid' => $epcmid,
            'timecreated' => $now,
        ]);
    }
}

/**
 * Détermine, pour l'utilisateur donné, le sous-ensemble des activités liées sur lesquelles il a
 * effectivement quelque chose à suivre : une activité liée n'entre dans le périmètre que si
 * l'utilisateur a toujours la capacité mod/ep:evaluateteacher sur l'instance d'origine (retrait
 * de rôle, promotion archivée...) et qu'il y est référent d'au moins un étudiant ou responsable
 * d'au moins un EP du catalogue. La synthèse ne fait ainsi que refléter les droits déjà accordés
 * dans chaque cours, elle n'en accorde aucun de plus.
 *
 * @param int $synthesisid
 * @param int $userid
 * @return array epcmid => stdClass{cm, context, ep, rights, coursename, epname, types}
 */
function epsynthesis_get_active_links($synthesisid, $userid) {
    global $DB;

    $active = [];
    foreach (epsynthesis_get_links($synthesisid) as $epcmid => $link) {
        if (!$link->visible || !$link->coursevisible) {
            continue;
        }

        try {
            $cm = get_coursemodule_from_id('ep', $epcmid, 0, false, IGNORE_MISSING);
            if (!$cm) {
                continue;
            }
            $context = context_module::instance($cm->id);
        } catch (Exception $e) {
            // Lien orphelin (contexte manquant/corrompu...) : ignoré plutôt que de faire échouer
            // toute la synthèse pour les autres activités liées, saines.
            continue;
        }

        if (!has_capability('mod/ep:evaluateteacher', $context, $userid)) {
            continue;
        }

        $ep = $DB->get_record('ep', ['id' => $cm->instance], '*', MUST_EXIST);
        $rights = ep_get_user_rights($ep, $context, $userid);
        if (empty($rights->referentids) && empty($rights->responsibleids)) {
            continue;
        }

        $active[$epcmid] = (object) [
            'cm' => $cm,
            'context' => $context,
            'ep' => $ep,
            'rights' => $rights,
            'coursename' => $link->coursename,
            'epname' => $link->epname,
            'types' => ep_get_types($ep->id),
        ];
    }
    return $active;
}

/**
 * Resynchronise les EP de type stage de chaque activité active, comme le font les pages de
 * mod_ep elles-mêmes : sans quoi la synthèse afficherait un décompte plus ancien que celui de
 * l'activité d'origine, ce qui ferait douter du bon chiffre.
 *
 * @param array $activelinks Voir epsynthesis_get_active_links().
 * @return void
 */
function epsynthesis_sync_active_links(array $activelinks) {
    foreach ($activelinks as $link) {
        ep_sync_stage_credits_if_due($link->ep);
    }
}

/**
 * Types proposés dans le filtre, toutes activités actives confondues : chaque type est propre à
 * une instance mod_ep, donc identifié dans le filtre par la paire « epcmid:typeid » (deux
 * instances peuvent avoir un même id de type sans rapport entre elles) et étiqueté avec son cours
 * pour rester compréhensible dans une liste combinée.
 *
 * @param array $activelinks Voir epsynthesis_get_active_links().
 * @return array "epcmid:typeid" => libellé
 */
function epsynthesis_get_type_options(array $activelinks) {
    $options = [];
    foreach ($activelinks as $epcmid => $link) {
        foreach ($link->types as $type) {
            $options[$epcmid . ':' . $type->id] = format_string($link->coursename) . ' – ' . format_string($type->name);
        }
    }
    return $options;
}

/**
 * Décompose une valeur de filtre de type combiné (« epcmid:typeid ») si elle correspond bien à
 * une des options proposées ; sinon, ignore silencieusement une valeur invalide ou forgée plutôt
 * que de la répercuter dans une requête SQL.
 *
 * @param string $value
 * @param array $typeoptions Voir epsynthesis_get_type_options().
 * @return array{0:int,1:int}|null [epcmid, typeid], ou null si absent/invalide.
 */
function epsynthesis_parse_type_filter($value, array $typeoptions) {
    if ($value === '' || !isset($typeoptions[$value])) {
        return null;
    }
    [$epcmid, $typeid] = explode(':', $value, 2);
    return [(int) $epcmid, (int) $typeid];
}

/**
 * Rend le formulaire de recherche/filtre au-dessus de la liste combinée : mêmes filtres que
 * ep_render_list_filters() (nom étudiant, type, année, statut), le type étant ici une valeur
 * combinée « epcmid:typeid » (voir epsynthesis_get_type_options()).
 *
 * @param moodle_url $baseurl
 * @param array $typeoptions
 * @param array $values ['search' => string, 'typekey' => string, 'status' => string, 'studyyear' => string]
 * @return string
 */
function epsynthesis_render_list_filters(moodle_url $baseurl, array $typeoptions, array $values) {
    $formurl = new moodle_url($baseurl);
    $formurl->remove_params('search', 'typekey', 'status', 'studyyear', 'tsort', 'tdir', 'page');

    $out = html_writer::start_tag(
        'form',
        ['method' => 'get', 'action' => $formurl, 'class' => 'form-inline ep-filters mb-3']
    );
    foreach ($formurl->params() as $key => $value) {
        $out .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => $key, 'value' => $value]);
    }
    $out .= html_writer::empty_tag('input', [
        'type' => 'text', 'name' => 'search', 'value' => s($values['search'] ?? ''),
        'placeholder' => get_string('searchstudent', 'mod_ep'), 'class' => 'form-control mr-2',
    ]);

    $out .= html_writer::select(
        ['' => get_string('alltypes', 'mod_ep')] + $typeoptions,
        'typekey',
        $values['typekey'] ?? '',
        false,
        ['class' => 'form-control mr-2']
    );

    $yearoptions = ['' => get_string('allyears', 'mod_ep')] + ep_studyyear_options();
    $out .= html_writer::select(
        $yearoptions,
        'studyyear',
        $values['studyyear'] ?? '',
        false,
        ['class' => 'form-control mr-2']
    );

    $statusoptions = ['' => get_string('allstatuses', 'mod_ep')] + ep_status_options();
    $out .= html_writer::select(
        $statusoptions,
        'status',
        $values['status'] ?? '',
        false,
        ['class' => 'form-control mr-2']
    );

    $out .= html_writer::empty_tag('input', [
        'type' => 'submit', 'value' => get_string('search'), 'class' => 'btn btn-secondary mr-2',
    ]);
    $out .= html_writer::link($formurl, get_string('resetfilters', 'mod_ep'), ['class' => 'btn btn-link']);
    $out .= html_writer::end_tag('form');

    return $out;
}

/**
 * Enrichit un crédit des informations de son activité d'origine nécessaires à l'affichage dans
 * une liste combinée (cours, type, étudiant), puisque ni le cours ni le nom de l'étudiant ne
 * figurent dans la ligne elle-même.
 *
 * @param stdClass $credit
 * @param stdClass $link Une entrée de epsynthesis_get_active_links().
 * @param array $students Voir ep_get_credit_users().
 * @return stdClass Le crédit, enrichi.
 */
function epsynthesis_decorate_credit(stdClass $credit, stdClass $link, array $students) {
    $credit->cmid = $link->cm->id;
    $credit->coursename = $link->coursename;
    $credit->typename = isset($link->types[$credit->typeid])
        ? format_string($link->types[$credit->typeid]->name) : '-';
    $student = $students[$credit->userid] ?? null;
    $credit->studentfullname = $student ? fullname($student) : '-';

    return $credit;
}

/**
 * Crédits en attente de la décision de l'utilisateur, agrégés sur toutes les activités actives et
 * triés du plus ancien au plus récent : c'est le retard accumulé qui doit remonter en premier.
 *
 * @param array $activelinks Voir epsynthesis_get_active_links().
 * @return array Crédits enrichis (cmid, coursename, typename, studentfullname).
 */
function epsynthesis_get_credits_awaiting(array $activelinks) {
    $rows = [];
    foreach ($activelinks as $link) {
        $credits = ep_get_credits_awaiting($link->ep, $link->rights, 'timecreated', 'ASC');
        if (empty($credits)) {
            continue;
        }
        $students = ep_get_credit_users($credits);
        foreach ($credits as $credit) {
            $rows[] = epsynthesis_decorate_credit($credit, $link, $students);
        }
    }

    usort($rows, function ($a, $b) {
        return $a->timecreated <=> $b->timecreated;
    });

    return $rows;
}

/**
 * Construit la liste combinée des crédits du périmètre de l'utilisateur, toutes activités actives
 * confondues, avec les mêmes filtres que credits.php et triée globalement selon la même clé/sens.
 *
 * @param array $activelinks Voir epsynthesis_get_active_links().
 * @param array $filters ['search' => string, 'typefilter' => [epcmid, typeid]|null,
 *                        'status' => string, 'studyyear' => string]
 * @param string $sort
 * @param string $dir
 * @return array Crédits enrichis, triés.
 */
function epsynthesis_get_filtered_credits(array $activelinks, array $filters, $sort, $dir) {
    $typefilter = $filters['typefilter'] ?? null;

    $rows = [];
    foreach ($activelinks as $epcmid => $link) {
        // Un type donné n'existe que dans son instance d'origine : si le filtre en cible un dans
        // une autre activité, celle-ci ne peut rien avoir à proposer.
        if ($typefilter !== null && $typefilter[0] !== $epcmid) {
            continue;
        }

        $creditfilters = [
            'search' => $filters['search'] ?? '',
            'status' => $filters['status'] ?? '',
            'studyyear' => $filters['studyyear'] ?? '',
        ];
        if ($typefilter !== null) {
            $creditfilters['typeid'] = $typefilter[1];
        }

        $credits = ep_get_filtered_credits(
            $link->ep->id,
            $creditfilters,
            $sort,
            $dir,
            $link->rights->referentids,
            $link->rights->responsibleids
        );
        if (empty($credits)) {
            continue;
        }

        $students = ep_get_credit_users($credits);
        foreach ($credits as $credit) {
            $rows[] = epsynthesis_decorate_credit($credit, $link, $students);
        }
    }

    $sortmap = [
        'student' => 'studentfullname',
        'type' => 'typename',
        'name' => 'name',
        'ects' => 'claimedects',
        'status' => 'status',
        'course' => 'coursename',
        'timecreated' => 'timecreated',
    ];
    $sortfield = $sortmap[$sort] ?? $sortmap['timecreated'];
    $reverse = strtoupper($dir) !== 'ASC';

    usort($rows, function ($a, $b) use ($sortfield, $reverse) {
        $result = $a->$sortfield <=> $b->$sortfield;
        return $reverse ? -$result : $result;
    });

    return $rows;
}

/**
 * Construit la vue d'ensemble par étudiant (voir ep_get_pilotage_overview()), agrégée sur toutes
 * les activités actives : une ligne par étudiant dont l'utilisateur est enseignant référent,
 * enrichie de son activité d'origine (cmid, cours) pour permettre le lien vers son détail.
 *
 * Seuls les étudiants dont l'utilisateur est référent y figurent : être responsable d'un EP du
 * catalogue donne à valider des inscriptions, pas à suivre le dossier complet d'un étudiant.
 *
 * @param array $activelinks Voir epsynthesis_get_active_links().
 * @return array Lignes enrichies, non triées (le tri se fait à l'affichage, comme dashboard.php).
 */
function epsynthesis_get_pilotage_rows(array $activelinks) {
    $rows = [];
    foreach ($activelinks as $epcmid => $link) {
        if (empty($link->rights->referentids)) {
            continue;
        }
        $overview = ep_get_pilotage_overview($link->ep, $link->context, $link->rights->referentids);
        foreach ($overview as $row) {
            $row->cmid = $link->cm->id;
            $row->coursename = $link->coursename;
            $rows[] = $row;
        }
    }
    return $rows;
}
