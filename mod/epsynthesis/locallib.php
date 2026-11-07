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
 * @return array cmid => stdClass{linkid, epcmid, epid, courseid, coursename, epname, visible, coursevisible}
 */
function epsynthesis_get_links($synthesisid) {
    global $DB;

    $sql = "SELECT l.id AS linkid, l.epcmid, cm.visible, cm.instance AS epid, e.name AS epname, c.id AS courseid,
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

    $links = array_filter(epsynthesis_get_links($synthesisid), fn($link) => $link->visible && $link->coursevisible);
    if (!$links) {
        return [];
    }
    // Les activités EP liées sont lues en une requête ; module de cours et contexte viennent des
    // informations de cours de Moodle (déjà en cache), et non d'une lecture par activité.
    $eps = $DB->get_records_list('ep', 'id', array_unique(array_map(fn($link) => (int) $link->epid, $links)));

    $active = [];
    foreach ($links as $epcmid => $link) {
        try {
            $cminfo = get_fast_modinfo($link->courseid, $userid)->get_cm($epcmid);
            $context = $cminfo->context;
        } catch (Exception $e) {
            // Lien orphelin (module ou contexte manquant) : ignoré plutôt que de faire échouer
            // toute la synthèse pour les autres activités liées, saines.
            continue;
        }

        // Inscription active au cours d'origine et accès à l'activité, pas seulement la capacité :
        // une inscription suspendue ou expirée garde l'attribution de rôle, mais celui qui l'a
        // perdue ne doit plus rien voir ni décider de cette promotion, ici pas plus que là-bas.
        if (!$cminfo->uservisible || !is_enrolled($context, $userid, 'mod/ep:evaluateteacher', true)) {
            continue;
        }

        $ep = $eps[$link->epid] ?? null;
        if (!$ep) {
            continue;
        }
        $rights = ep_get_user_rights($ep, $context, $userid);
        if (empty($rights->referentids) && empty($rights->responsibleids)) {
            continue;
        }

        $active[$epcmid] = (object) [
            'cm' => $cminfo->get_course_module_record(),
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
 * Rend le formulaire de recherche/filtre au-dessus de la liste combinée (gabarit
 * mod_ep/list_filters) : mêmes filtres que ep_render_list_filters() (nom étudiant, type, année,
 * statut), le type étant ici une valeur combinée « epcmid:typeid » (voir
 * epsynthesis_get_type_options()).
 *
 * @param moodle_url $baseurl
 * @param array $typeoptions
 * @param array $values ['search' => string, 'typekey' => string, 'status' => string, 'studyyear' => string]
 * @return string
 */
function epsynthesis_render_list_filters(moodle_url $baseurl, array $typeoptions, array $values) {
    global $OUTPUT;

    $filters = new \mod_ep\output\list_filters(
        $baseurl,
        ['search', 'typekey', 'status', 'studyyear', 'tsort', 'tdir', 'page'],
        $values['search'] ?? '',
        [
            'typekey' => [
                'options' => ['' => get_string('alltypes', 'mod_ep')] + $typeoptions,
                'selected' => $values['typekey'] ?? '',
            ],
            'studyyear' => [
                'options' => ['' => get_string('allyears', 'mod_ep')] + ep_studyyear_options(),
                'selected' => $values['studyyear'] ?? '',
            ],
            'status' => [
                'options' => ['' => get_string('allstatuses', 'mod_ep')] + ep_status_options(),
                'selected' => $values['status'] ?? '',
            ],
        ]
    );
    return $OUTPUT->render($filters);
}

/**
 * Enrichit un crédit des informations de son activité d'origine nécessaires à l'affichage dans
 * une liste combinée (cours, type, étudiant), puisque ni le cours ni le nom de l'étudiant ne
 * figurent dans la ligne elle-même.
 *
 * @param stdClass $credit
 * @param stdClass $link Une entrée de epsynthesis_get_active_links().
 * @param array $students Voir ep_get_credit_users().
 * @param array|null $typenames Libellés précalculés (voir epsynthesis_decoration_maps()).
 * @param array|null $names Noms précalculés (voir epsynthesis_decoration_maps()).
 * @return stdClass Le crédit, enrichi.
 */
function epsynthesis_decorate_credit(
    stdClass $credit,
    stdClass $link,
    array $students,
    ?array $typenames = null,
    ?array $names = null
) {
    $credit->cmid = $link->cm->id;
    $credit->coursename = $link->coursename;
    if ($typenames !== null) {
        $credit->typename = $typenames[$credit->typeid] ?? '-';
    } else {
        $credit->typename = isset($link->types[$credit->typeid]) ? format_string($link->types[$credit->typeid]->name) : '-';
    }
    if ($names !== null) {
        $credit->studentfullname = $names[$credit->userid] ?? '-';
    } else if ($typenames !== null) {
        // Noms calculés plus tard, pour les seules lignes affichées (voir
        // epsynthesis_credit_student_name()).
        $credit->studentuser = $students[$credit->userid] ?? null;
    } else {
        $student = $students[$credit->userid] ?? null;
        $credit->studentfullname = $student ? fullname($student) : '-';
    }

    return $credit;
}

/**
 * Libellés de type et noms d'étudiants d'une activité liée, mis en forme une fois chacun : une
 * liste de milliers de crédits n'en compte que quelques-uns, et format_string() passe par les
 * filtres de texte.
 *
 * @param stdClass $link Voir epsynthesis_get_active_links().
 * @param array $students userid => stdClass user
 * @return array [typeid => libellé, userid => nom complet]
 */
function epsynthesis_decoration_maps(stdClass $link, array $students, bool $withnames = true): array {
    $typenames = [];
    foreach ($link->types as $typeid => $type) {
        $typenames[$typeid] = format_string($type->name);
    }
    $names = null;
    if ($withnames) {
        $names = [];
        foreach ($students as $userid => $student) {
            $names[$userid] = fullname($student);
        }
    }
    return [$typenames, $names];
}

/**
 * Nom complet de l'étudiant d'un crédit de la liste combinée : déjà calculé pour un tri par
 * étudiant, sinon calculé ici, pour les seules lignes affichées.
 *
 * @param stdClass $credit Crédit enrichi par epsynthesis_decorate_credit().
 * @return string
 */
function epsynthesis_credit_student_name(stdClass $credit): string {
    if (isset($credit->studentfullname)) {
        return $credit->studentfullname;
    }
    return !empty($credit->studentuser) ? fullname($credit->studentuser) : '-';
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
        // Périmètre d'enseignant seulement : un enseignant éditeur qui aurait aussi les droits de
        // la DEVE ne doit pas voir remonter ici toute la file de chaque promotion.
        $credits = ep_get_credits_awaiting($link->ep, epsynthesis_teacher_rights($link->rights), 'timecreated', 'ASC');
        if (empty($credits)) {
            continue;
        }
        $students = ep_get_credit_users($credits);
        [$typenames, $names] = epsynthesis_decoration_maps($link, $students);
        foreach ($credits as $credit) {
            $rows[] = epsynthesis_decorate_credit($credit, $link, $students, $typenames, $names);
        }
    }

    usort($rows, function ($a, $b) {
        return $a->timecreated <=> $b->timecreated;
    });

    return $rows;
}

/**
 * Clause de sélection des crédits du périmètre de l'utilisateur sur toutes les activités actives :
 * la réunion des clauses de chaque activité (ep_filtered_credits_sql(), avec ses propres droits),
 * dont les paramètres sont renommés pour ne pas se confondre d'une activité à l'autre.
 *
 * @param array $activelinks Voir epsynthesis_get_active_links().
 * @param array $filters Voir epsynthesis_get_filtered_credits().
 * @return array|null [where, params], ou null si aucune activité n'a rien à proposer.
 */
function epsynthesis_filtered_credits_sql(array $activelinks, array $filters): ?array {
    $typefilter = $filters['typefilter'] ?? null;
    $clauses = [];
    $params = [];
    $k = 0;
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
        $built = ep_filtered_credits_sql(
            $link->ep->id,
            $creditfilters,
            $link->rights->referentids,
            $link->rights->responsibleids
        );
        if ($built === null) {
            continue;
        }
        [$where, $linkparams] = $built;
        $k++;
        $where = preg_replace_callback('/:([a-z][a-z0-9_]*)/i', function ($m) use ($linkparams, $k) {
            return array_key_exists($m[1], $linkparams) ? ':' . $m[1] . '_l' . $k : $m[0];
        }, $where);
        foreach ($linkparams as $name => $value) {
            $params[$name . '_l' . $k] = $value;
        }
        $clauses[] = "($where)";
    }
    return $clauses ? ['(' . implode(' OR ', $clauses) . ')', $params] : null;
}

/**
 * Une page de la liste combinée des crédits du périmètre de l'utilisateur, toutes activités
 * actives confondues, avec les mêmes filtres que credits.php : une seule requête, triée et
 * paginée par la base, puis seules les lignes de la page sont enrichies (cours, type, étudiant).
 *
 * @param array $activelinks Voir epsynthesis_get_active_links().
 * @param array $filters ['search' => string, 'typefilter' => [epcmid, typeid]|null,
 *                        'status' => string, 'studyyear' => string]
 * @param string $sort student, type, name, ects, status, course ou timecreated.
 * @param string $dir
 * @param int $limitfrom
 * @param int $limitnum
 * @return array [crédits enrichis de la page, nombre total]
 */
function epsynthesis_get_filtered_credits(
    array $activelinks,
    array $filters,
    $sort,
    $dir,
    $limitfrom = 0,
    $limitnum = 0
): array {
    global $DB;

    $built = epsynthesis_filtered_credits_sql($activelinks, $filters);
    if ($built === null) {
        return [[], 0];
    }
    [$where, $params] = $built;
    $dir = strtoupper($dir) === 'ASC' ? 'ASC' : 'DESC';
    $sortmap = [
        'student' => "u.lastname $dir, u.firstname $dir",
        'type' => "t.sortorder $dir, t.name $dir",
        'name' => "c.name $dir",
        'ects' => "c.claimedects $dir",
        'status' => "c.status $dir",
        'course' => "co.fullname $dir",
        'timecreated' => "c.timecreated $dir",
    ];
    $order = ($sortmap[$sort] ?? $sortmap['timecreated']) . ", c.id $dir";
    $from = "FROM {ep_credit} c
             JOIN {user} u ON u.id = c.userid
        LEFT JOIN {ep_type} t ON t.id = c.typeid
             JOIN {ep} e ON e.id = c.epid
             JOIN {course} co ON co.id = e.course
            WHERE $where";
    $total = (int) $DB->count_records_sql("SELECT COUNT(1) $from", $params);
    $credits = $total ? $DB->get_records_sql("SELECT c.* $from ORDER BY $order", $params, $limitfrom, $limitnum) : [];

    $byepid = [];
    foreach ($activelinks as $link) {
        $byepid[(int) $link->ep->id] = $link;
    }
    $students = ep_get_credit_users($credits);
    $rows = [];
    $maps = [];
    foreach ($credits as $credit) {
        $link = $byepid[(int) $credit->epid];
        $maps[$link->cm->id] = $maps[$link->cm->id] ?? epsynthesis_decoration_maps($link, $students);
        [$typenames, $names] = $maps[$link->cm->id];
        $rows[] = epsynthesis_decorate_credit($credit, $link, $students, $typenames, $names);
    }
    return [$rows, $total];
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

/**
 * Rend la barre de navigation commune aux écrans de la synthèse (validation, pilotage, « À
 * traiter »), l'écran courant mis en évidence. Le lien « À traiter » porte le nombre de ce qui
 * attend une action de l'utilisateur quand il est connu et non nul.
 *
 * @param stdClass $cm Course-module de la synthèse.
 * @param string $current Écran courant : 'entries', 'dashboard' ou 'todo'.
 * @param stdClass|null $counts Voir epsynthesis_count_todo() ; null si non calculé.
 * @return string HTML
 */
function epsynthesis_render_navlinks(stdClass $cm, $current, ?stdClass $counts = null) {
    $todolabel = ($counts && $counts->total > 0)
        ? get_string('todowithcount', 'mod_epsynthesis', $counts->total)
        : get_string('todo', 'mod_epsynthesis');
    $pages = [
        'entries' => get_string('validation', 'mod_ep'),
        'dashboard' => get_string('pilotage', 'mod_ep'),
        'todo' => $todolabel,
    ];

    $html = '';
    foreach ($pages as $page => $label) {
        $attributes = ['class' => 'btn btn-secondary mr-1 mb-1'];
        if ($page === $current) {
            $attributes = ['class' => 'btn btn-primary mr-1 mb-1', 'aria-current' => 'page'];
        }
        $html .= html_writer::link(new moodle_url('/mod/epsynthesis/' . $page . '.php', ['id' => $cm->id]), $label, $attributes);
    }
    return html_writer::div($html, 'epsynthesis-navlinks mb-3');
}

/**
 * Droits « enseignant » de l'utilisateur dans une activité liée : ceux calculés par
 * ep_get_user_rights(), sans ce que donnent les capacités de la DEVE. « À traiter » ne recense que
 * ce qui revient à l'utilisateur comme responsable d'EP ou comme enseignant référent ; ce qu'il
 * peut trancher en tant que DEVE se traite dans l'activité d'origine, avec ses écrans de gestion.
 *
 * @param stdClass $rights Voir ep_get_user_rights().
 * @return stdClass Copie des droits, sans validatedeve ni viewall.
 */
function epsynthesis_teacher_rights(stdClass $rights) {
    $teacher = clone $rights;
    $teacher->validatedeve = false;
    $teacher->viewall = false;
    return $teacher;
}

/**
 * Index des activités actives par identifiant d'instance mod_ep : les lignes lues en une requête
 * sur toutes les activités ne portent que l'epid, d'où il faut retrouver le cours et le
 * course-module d'origine.
 *
 * @param array $activelinks Voir epsynthesis_get_active_links().
 * @return array epid => entrée de $activelinks
 */
function epsynthesis_links_by_epid(array $activelinks) {
    $byepid = [];
    foreach ($activelinks as $link) {
        $byepid[(int) $link->ep->id] = $link;
    }
    return $byepid;
}

/**
 * Clause WHERE (sur {ep_credit} c) des inscriptions qui attendent une décision de l'utilisateur
 * en tant que responsable d'EP du catalogue : en attente, à l'étape « responsable », sur un EP
 * dont il est responsable dans l'une des activités actives. Les identifiants d'EP du catalogue
 * étant propres à une instance, la liste suffit à cibler la bonne activité.
 *
 * @param array $activelinks Voir epsynthesis_get_active_links().
 * @return array{0: string, 1: array}|null [clause, paramètres], ou null si l'utilisateur n'est
 *                                         responsable d'aucun EP.
 */
function epsynthesis_todo_participants_sql(array $activelinks) {
    global $DB;

    $activityids = [];
    foreach ($activelinks as $link) {
        foreach ($link->rights->responsibleids as $activityid) {
            $activityids[] = (int) $activityid;
        }
    }
    if (empty($activityids)) {
        return null;
    }

    [$insql, $params] = $DB->get_in_or_equal($activityids, SQL_PARAMS_NAMED, 'tdact');
    // Même périmètre que ep_get_credits_awaiting() en mode « decide », restreint à l'étape
    // « responsable » : l'étape « référent » d'un EP du catalogue relève de entries.php.
    $where = "c.activityid $insql AND c.status = :tdpending AND c.pendingrole = :tdowner AND c.source <> :tdstage";
    $params += [
        'tdpending' => EP_STATUS_PENDING,
        'tdowner' => EP_ROLE_OWNER,
        'tdstage' => EP_SOURCE_STAGE,
    ];
    return [$where, $params];
}

/**
 * Clause WHERE (sur {ep_syllabus} s JOIN {ep_activity} a) des versions de travail de syllabus qui
 * concernent l'utilisateur : celles des EP qu'il a proposés ou dont il est responsable, dans les
 * activités actives — le périmètre « mes propositions » de mod/ep/proposals.php (voir
 * ep_get_proposals()), sans ce que la DEVE a à examiner.
 *
 * @param array $activelinks Voir epsynthesis_get_active_links().
 * @param int $userid
 * @param string[] $statuses Statuts de syllabus retenus.
 * @return array{0: string, 1: array}|null [clause, paramètres], ou null sans activité active.
 */
function epsynthesis_todo_syllabi_sql(array $activelinks, $userid, array $statuses = ['draft', 'submitted', 'returned']) {
    global $DB;

    if (empty($activelinks) || empty($statuses)) {
        return null;
    }
    $epids = [];
    foreach ($activelinks as $link) {
        $epids[] = (int) $link->ep->id;
    }
    [$epsql, $params] = $DB->get_in_or_equal($epids, SQL_PARAMS_NAMED, 'tdep');
    [$stsql, $stparams] = $DB->get_in_or_equal($statuses, SQL_PARAMS_NAMED, 'tdst');
    $where = "a.epid $epsql AND s.status $stsql
              AND (a.proposedby = :tdproposer
                   OR EXISTS (SELECT 1 FROM {ep_activity_teacher} t
                               WHERE t.activityid = a.id AND t.teacherid = :tdteacher))";
    $params += $stparams + ['tdproposer' => $userid, 'tdteacher' => $userid];
    return [$where, $params];
}

/**
 * Clause WHERE (sur {ep_supplement_item} i) des éléments du supplément au diplôme que
 * l'utilisateur peut trancher comme enseignant référent : en attente, à l'étape « référent »,
 * demandés par l'un de ses étudiants — dans l'activité où il en est le référent, puisque la
 * relation de référent est propre à chaque promotion.
 *
 * @param array $activelinks Voir epsynthesis_get_active_links().
 * @return array{0: string, 1: array}|null [clause, paramètres], ou null si l'utilisateur n'est
 *                                         référent d'aucun étudiant.
 */
function epsynthesis_todo_supplement_sql(array $activelinks) {
    global $DB;

    $scope = [];
    $params = [];
    foreach (array_values($activelinks) as $i => $link) {
        if (empty($link->rights->referentids)) {
            continue;
        }
        [$insql, $inparams] = $DB->get_in_or_equal($link->rights->referentids, SQL_PARAMS_NAMED, 'tdstu');
        $scope[] = "(i.epid = :tdsep$i AND i.userid $insql)";
        $params['tdsep' . $i] = (int) $link->ep->id;
        $params += $inparams;
    }
    if (empty($scope)) {
        return null;
    }

    $where = 'i.status = :tdspending AND i.pendingrole = :tdsreferent AND (' . implode(' OR ', $scope) . ')';
    $params += ['tdspending' => EP_STATUS_PENDING, 'tdsreferent' => EP_ROLE_REFERENT];
    return [$where, $params];
}

/**
 * Nombres de ce qui attend une action de l'utilisateur sur les activités actives, en trois
 * requêtes de comptage quel que soit le nombre d'activités liées : assez peu coûteux pour figurer
 * dans la barre de navigation de chaque écran.
 *
 * Le total ne compte que ce que l'utilisateur a à faire : les versions de syllabus soumises,
 * données pour information, attendent la DEVE et n'y entrent pas.
 *
 * @param array $activelinks Voir epsynthesis_get_active_links().
 * @param int $userid
 * @return stdClass {participants, syllabi (à reprendre), submitted (en attente de la DEVE),
 *                  supplement, total}
 */
function epsynthesis_count_todo(array $activelinks, $userid) {
    global $DB;

    $counts = (object) ['participants' => 0, 'syllabi' => 0, 'submitted' => 0, 'supplement' => 0, 'total' => 0];

    $built = epsynthesis_todo_participants_sql($activelinks);
    if ($built !== null) {
        [$where, $params] = $built;
        $counts->participants = $DB->count_records_sql(
            "SELECT COUNT(c.id)
               FROM {ep_credit} c
               JOIN {user} u ON u.id = c.userid
              WHERE $where",
            $params
        );
    }

    $built = epsynthesis_todo_syllabi_sql($activelinks, $userid);
    if ($built !== null) {
        [$where, $params] = $built;
        $rows = $DB->get_records_sql(
            "SELECT s.status, COUNT(s.id) AS n
               FROM {ep_syllabus} s
               JOIN {ep_activity} a ON a.id = s.activityid
              WHERE $where
           GROUP BY s.status",
            $params
        );
        foreach ($rows as $row) {
            if ($row->status === 'submitted') {
                $counts->submitted += (int) $row->n;
            } else {
                $counts->syllabi += (int) $row->n;
            }
        }
    }

    $built = epsynthesis_todo_supplement_sql($activelinks);
    if ($built !== null) {
        [$where, $params] = $built;
        $counts->supplement = $DB->count_records_sql(
            "SELECT COUNT(i.id)
               FROM {ep_supplement_item} i
               JOIN {user} u ON u.id = i.userid
              WHERE $where",
            $params
        );
    }

    $counts->total = $counts->participants + $counts->syllabi + $counts->supplement;
    return $counts;
}

/**
 * EP du catalogue dont l'utilisateur est responsable et qui ont des inscriptions en attente de sa
 * décision, avec leur nombre, en une requête groupée sur toutes les activités actives. Triés de
 * la plus ancienne demande à la plus récente : c'est le retard accumulé qui doit remonter.
 *
 * @param array $activelinks Voir epsynthesis_get_active_links().
 * @return array Liste de {id, epid, name, code, pendingcount, oldest, cmid, coursename, epname}.
 */
function epsynthesis_get_todo_participants(array $activelinks) {
    global $DB;

    $built = epsynthesis_todo_participants_sql($activelinks);
    if ($built === null) {
        return [];
    }
    [$where, $params] = $built;
    $rows = $DB->get_records_sql(
        "SELECT a.id, a.epid, a.name, a.code, COUNT(c.id) AS pendingcount, MIN(c.timecreated) AS oldest
           FROM {ep_credit} c
           JOIN {ep_activity} a ON a.id = c.activityid
           JOIN {user} u ON u.id = c.userid
          WHERE $where
       GROUP BY a.id, a.epid, a.name, a.code",
        $params
    );

    $byepid = epsynthesis_links_by_epid($activelinks);
    $result = [];
    foreach ($rows as $row) {
        $link = $byepid[(int) $row->epid] ?? null;
        if (!$link) {
            continue;
        }
        $row->pendingcount = (int) $row->pendingcount;
        $row->oldest = (int) $row->oldest;
        $row->cmid = (int) $link->cm->id;
        $row->coursename = $link->coursename;
        $row->epname = $link->epname;
        $result[] = $row;
    }

    usort($result, function ($a, $b) {
        return [$a->oldest, $a->id] <=> [$b->oldest, $b->id];
    });
    return $result;
}

/**
 * Versions de travail de syllabus de l'utilisateur sur toutes les activités actives (voir
 * epsynthesis_todo_syllabi_sql()), en une requête, enrichies de ce qu'il faut pour les afficher
 * et les lier : activité d'origine, droit de rédaction, droit de consultation.
 *
 * Le droit de rédaction reprend la règle de ep_can_edit_syllabus() à partir de ce que la requête
 * a déjà lu (responsable ou non, auteur de la proposition, état de l'EP) et des capacités
 * vérifiées une fois par activité, plutôt que de rejouer ses requêtes ligne à ligne ; la page de
 * rédaction le revérifie de toute façon à l'ouverture.
 *
 * @param array $activelinks Voir epsynthesis_get_active_links().
 * @param int $userid
 * @return array Liste de stdClass syllabus {id, activityid, version, status, authorid,
 *               reviewcomment, timesubmitted, timereviewed, timemodified, epid, activityname,
 *               activitycode, activitystate, proposedby, isteacher, cmid, coursename, epname,
 *               canedit, canview}, du plus récemment modifié au plus ancien.
 */
function epsynthesis_get_todo_syllabi(array $activelinks, $userid) {
    global $DB;

    $built = epsynthesis_todo_syllabi_sql($activelinks, $userid);
    if ($built === null) {
        return [];
    }
    [$where, $params] = $built;
    $rows = $DB->get_records_sql(
        "SELECT s.id, s.activityid, s.version, s.status, s.authorid, s.reviewcomment, s.timesubmitted,
                s.timereviewed, s.timemodified, a.epid, a.name AS activityname, a.code AS activitycode,
                a.state AS activitystate, a.proposedby,
                CASE WHEN EXISTS (SELECT 1 FROM {ep_activity_teacher} t2
                                   WHERE t2.activityid = a.id AND t2.teacherid = :tdteacher2)
                     THEN 1 ELSE 0 END AS isteacher
           FROM {ep_syllabus} s
           JOIN {ep_activity} a ON a.id = s.activityid
          WHERE $where
       ORDER BY s.timemodified DESC, s.id DESC",
        $params + ['tdteacher2' => $userid]
    );

    $byepid = epsynthesis_links_by_epid($activelinks);
    $canpropose = [];
    $result = [];
    foreach ($rows as $row) {
        $link = $byepid[(int) $row->epid] ?? null;
        if (!$link) {
            continue;
        }
        $epid = (int) $row->epid;
        if (!isset($canpropose[$epid])) {
            $canpropose[$epid] = has_capability('mod/ep:propose', $link->context, $userid);
        }
        $isproposer = !empty($row->proposedby) && (int) $row->proposedby === (int) $userid
            && in_array($row->activitystate, [EP_ACTIVITY_DRAFT, EP_ACTIVITY_PROPOSED], true);
        // Règle de ep_can_edit_syllabus().
        $canedit = $link->rights->manage
            || ($canpropose[$epid] && (!empty($row->isteacher) || $isproposer));

        $row->isteacher = (int) $row->isteacher;
        $row->cmid = (int) $link->cm->id;
        $row->coursename = $link->coursename;
        $row->epname = $link->epname;
        $row->canedit = $canedit;
        // Règle de mod/ep/syllabus.php : un EP hors catalogue n'est lisible que de qui le rédige
        // ou le publie.
        $row->canview = $canedit
            || in_array($row->activitystate, [EP_ACTIVITY_PUBLISHED, EP_ACTIVITY_CLOSED], true);
        $result[] = $row;
    }
    return $result;
}

/**
 * Éléments du supplément au diplôme que l'utilisateur peut trancher comme référent (voir
 * epsynthesis_todo_supplement_sql()), du plus ancien au plus récent, une page à la fois : une
 * promotion entière peut en avoir déposé en même temps.
 *
 * @param array $activelinks Voir epsynthesis_get_active_links().
 * @param int $limitfrom
 * @param int $limitnum
 * @return array id => stdClass élément, enrichi de cmid et coursename.
 */
function epsynthesis_get_todo_supplement_items(array $activelinks, $limitfrom = 0, $limitnum = 0) {
    global $DB;

    $built = epsynthesis_todo_supplement_sql($activelinks);
    if ($built === null) {
        return [];
    }
    [$where, $params] = $built;
    $items = $DB->get_records_sql(
        "SELECT i.*
           FROM {ep_supplement_item} i
           JOIN {user} u ON u.id = i.userid
          WHERE $where
       ORDER BY i.timecreated ASC, i.id ASC",
        $params,
        $limitfrom,
        $limitnum
    );

    $byepid = epsynthesis_links_by_epid($activelinks);
    foreach ($items as $id => $item) {
        $link = $byepid[(int) $item->epid] ?? null;
        if (!$link) {
            unset($items[$id]);
            continue;
        }
        $item->cmid = (int) $link->cm->id;
        $item->coursename = $link->coursename;
    }
    return $items;
}

/**
 * Preuves déposées pour une liste d'éléments du supplément, lues en une requête plutôt qu'élément
 * par élément : seuls les fichiers de la zone des preuves, dans le contexte de l'activité de
 * chaque élément, sont retenus — comme le fait mod/ep/supplement_file.php, qui en contrôle
 * l'accès au téléchargement.
 *
 * @param array $items Éléments (voir epsynthesis_get_todo_supplement_items()).
 * @param array $activelinks Voir epsynthesis_get_active_links().
 * @return array itemid => liste de {filename, pathnamehash}, triés par nom.
 */
function epsynthesis_get_supplement_proofs(array $items, array $activelinks) {
    global $DB;

    if (empty($items)) {
        return [];
    }
    $byepid = epsynthesis_links_by_epid($activelinks);
    $contextbyitem = [];
    foreach ($items as $item) {
        if (isset($byepid[(int) $item->epid])) {
            $contextbyitem[(int) $item->id] = (int) $byepid[(int) $item->epid]->context->id;
        }
    }
    if (empty($contextbyitem)) {
        return [];
    }

    [$itemsql, $params] = $DB->get_in_or_equal(array_keys($contextbyitem), SQL_PARAMS_NAMED, 'pit');
    [$ctxsql, $ctxparams] = $DB->get_in_or_equal(array_values(array_unique($contextbyitem)), SQL_PARAMS_NAMED, 'pctx');
    $files = $DB->get_records_sql(
        "SELECT f.id, f.contextid, f.itemid, f.filename, f.pathnamehash
           FROM {files} f
          WHERE f.component = :component AND f.filearea = :filearea AND f.filename <> :dot
                AND f.itemid $itemsql AND f.contextid $ctxsql
       ORDER BY f.filename ASC, f.id ASC",
        $params + $ctxparams + [
            'component' => 'mod_ep',
            'filearea' => EP_SUPPLEMENT_EVIDENCE_FILEAREA,
            'dot' => '.',
        ]
    );

    $proofs = [];
    foreach ($files as $file) {
        $itemid = (int) $file->itemid;
        if (($contextbyitem[$itemid] ?? 0) !== (int) $file->contextid) {
            continue;
        }
        $proofs[$itemid][] = (object) ['filename' => $file->filename, 'pathnamehash' => $file->pathnamehash];
    }
    return $proofs;
}

/**
 * Décision de l'utilisateur, comme enseignant référent, sur un élément du supplément au diplôme,
 * depuis la synthèse. L'élément est relu, son activité doit faire partie des activités actives
 * et le droit de statuer est réévalué sur son état courant juste avant l'écriture, elle-même
 * conditionnée à l'étape « référent » : le formulaire affiché a pu être dépassé par une autre
 * décision (la DEVE, un autre référent) ou par un retrait de l'étudiant.
 *
 * @param array $activelinks Voir epsynthesis_get_active_links().
 * @param int $itemid
 * @param int $userid
 * @param bool $validate True pour valider, false pour refuser.
 * @param string $comment
 * @return bool False si l'élément n'existe plus, est hors du périmètre de l'utilisateur ou n'est
 *              plus en attente de sa décision : rien n'a été écrit.
 */
function epsynthesis_decide_supplement_item(array $activelinks, $itemid, $userid, $validate, $comment) {
    global $DB;

    $item = $DB->get_record('ep_supplement_item', ['id' => $itemid]);
    if (!$item) {
        return false;
    }
    $link = epsynthesis_links_by_epid($activelinks)[(int) $item->epid] ?? null;
    if (!$link) {
        return false;
    }
    if (!ep_rights_can_decide_supplement_item(epsynthesis_teacher_rights($link->rights), $item)) {
        return false;
    }
    return ep_decide_supplement_item($link->ep, $item, $userid, (bool) $validate, (string) $comment, EP_ROLE_REFERENT);
}

/**
 * Rend, en tête de la page d'atterrissage, le résumé de ce qui attend une action de
 * l'utilisateur (voir epsynthesis_count_todo()), avec un lien vers chaque section de todo.php.
 *
 * @param stdClass $cm Course-module de la synthèse.
 * @param stdClass $counts Voir epsynthesis_count_todo().
 * @return string HTML, ou '' s'il n'y a rien à traiter.
 */
function epsynthesis_render_todo_summary(stdClass $cm, stdClass $counts) {
    if ($counts->total <= 0) {
        return '';
    }
    $todourl = new moodle_url('/mod/epsynthesis/todo.php', ['id' => $cm->id]);
    $lines = [
        'participants' => $counts->participants,
        'syllabi' => $counts->syllabi,
        'supplement' => $counts->supplement,
    ];
    $items = [];
    foreach ($lines as $key => $count) {
        $text = get_string('todosummary_' . $key, 'mod_epsynthesis', $count);
        $items[] = $count > 0
            ? html_writer::link(new moodle_url($todourl, null, 'todo-' . $key), $text)
            : html_writer::span($text, 'text-muted');
    }
    return html_writer::div(
        html_writer::tag('strong', get_string('todo', 'mod_epsynthesis')) .
        html_writer::alist($items, ['class' => 'mb-0']),
        'alert alert-info'
    );
}
