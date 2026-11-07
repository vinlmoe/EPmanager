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
 * Chaînes françaises de mod_syllabusstaff.
 *
 * @package   mod_syllabusstaff
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['allevalstates'] = 'Toutes les évaluations';
$string['allmodules'] = 'Tous les modules';
$string['allstatuses'] = 'Toutes les fiches';
$string['alltypes'] = 'Tous les types';
$string['bulkwindow'] = 'Fenêtre d\'évaluation des modules cochés';
$string['bulkwindow_help'] = 'Fixe la même ouverture (et fermeture) pour les modules cochés où vous avez le rôle de la DEVE. Laisser l\'ouverture vide retire l\'évaluation.';
$string['commonquestions'] = 'Questions d\'évaluation communes';
$string['commonquestions_help'] = 'Posées aux étudiants avant les questions de l\'enseignant, à tous les modules ou aux modules d\'un type. Elles sont copiées dans chaque module à l\'ouverture de son évaluation : les modifier ensuite ne change pas les évaluations déjà ouvertes. Une question désactivée n\'est plus posée.';
$string['evaluation'] = 'Évaluation';
$string['modulename'] = 'Suivi des modules (enseignants et DEVE)';
$string['modulename_help'] = 'Rassemble, pour chaque enseignant, les modules dont il est responsable dans tous les cours : état de leur fiche, de leur évaluation, réponses. La DEVE y voit tous les modules, les fiches à relire, la synthèse des évaluations, et y tient le référentiel commun (types de module, questions communes, réglages).';
$string['modulenameplural'] = 'Suivis des modules';
$string['modulescount'] = 'Modules';
$string['moduletypes'] = 'Types de module';
$string['mymodules'] = 'Mes modules';
$string['mymodules_help'] = 'Les modules dont vous êtes responsable : vous en rédigez la fiche, posez vos questions d\'évaluation et en voyez les résultats.';
$string['mymodulestodo'] = '{$a} module(s) sans fiche publiée ou avec une fiche renvoyée par la DEVE.';
$string['newtype'] = 'Nouveau type de module';
$string['nomodules'] = 'Aucun module.';
$string['nothingtoreview'] = 'Aucune fiche n\'attend de relecture.';
$string['notpublished'] = 'Fiche non publiée';
$string['pluginadministration'] = 'Administration du suivi des modules';
$string['pluginname'] = 'Suivi des modules (enseignants et DEVE)';
$string['privacy:metadata'] = 'Le suivi des modules ne stocke aucune donnée personnelle : il affiche celles de l\'activité « Syllabus du module ».';
$string['referential'] = 'Référentiel commun';
$string['referential_help'] = 'Ce référentiel vaut pour tous les modules du site, quelle que soit l\'activité de suivi depuis laquelle il est modifié.';
$string['responses'] = 'Réponses';
$string['results'] = 'Résultats';
$string['resultsoverview'] = 'Résultats des évaluations';
$string['resultsoverview_help'] = 'Moyenne (sur 5) de chaque question commune à échelle, module par module. Un module qui a moins de {$a} réponses n\'a pas de moyenne, pour préserver l\'anonymat.';
$string['resultsthreshold'] = 'Nombre minimal de réponses pour afficher des résultats';
$string['review'] = 'Relire';
$string['reviewdisabled'] = 'La relecture par la DEVE est désactivée (référentiel) : les fiches sont publiées dès leur soumission.';
$string['reviewrequired'] = 'Les fiches soumises par les responsables sont relues par la DEVE avant publication';
$string['scope'] = 'Périmètre';
$string['scope_help'] = 'Catégorie de cours dont les modules sont suivis (sous-catégories comprises), ou tout le site.';
$string['setwindow'] = 'Appliquer';
$string['settings'] = 'Réglages';
$string['settingssaved'] = 'Réglages enregistrés.';
$string['submittedon'] = 'Soumise le';
$string['syllabusstaff:addinstance'] = 'Ajouter un suivi des modules';
$string['syllabusstaff:manageall'] = 'Gérer tous les modules et le référentiel commun (DEVE)';
$string['syllabusstaff:view'] = 'Voir ses modules';
$string['teacherresults'] = 'Les responsables voient les résultats';
$string['teacherresults_afterclose'] = 'après la fermeture de l\'évaluation';
$string['teacherresults_always'] = 'dès l\'ouverture';
$string['toreview'] = 'Fiches à relire';
$string['toreview_help'] = 'Fiches soumises par les responsables : ouvrez-les pour les publier, les renvoyer avec un commentaire ou les refuser.';
$string['typedeleted'] = 'Type supprimé.';
$string['typeinuse'] = 'Ce type est porté par des modules ou visé par des questions : il ne peut pas être supprimé.';
$string['types_help'] = 'Chaque module peut être rattaché à un type (dans ses réglages) ; des questions communes peuvent viser un type.';
$string['versionn'] = 'Version {$a} publiée';
$string['windowsset'] = 'Fenêtre fixée pour {$a->done} module(s) ; {$a->skipped} écarté(s) (pas le rôle de la DEVE dans leur cours).';
$string['competencymatrix'] = 'Compétences';
$string['competencymatrix_help'] = 'Quels cours du périmètre visent quelles compétences du référentiel, et à quel niveau. Un en-tête de colonne ouvre la liaison des compétences du module (ou, pour un cours sans module, la page des compétences du cours de Moodle).';
$string['coverage'] = 'Cours';
$string['coveragesummary'] = '{$a->covered} compétence(s) sur {$a->total} visée(s) par au moins un des {$a->courses} cours ; les compétences qu\'aucun cours ne vise sont surlignées.';
$string['matrixlegend'] = 'I : introduite, A : approfondie, M : maîtrisée (niveau donné par le module) ; ✓ : liée au module sans niveau ; • : liée au cours seulement.';
