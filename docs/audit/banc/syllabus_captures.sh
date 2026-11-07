#!/bin/bash
# Captures du guide des modules syllabus (docs/guides/guide-syllabus.tex), après le scénario de
# bout en bout : mise en scène (syllabus_captures_prep.php), captures des enseignants et de la
# DEVE, puis des étudiants (recueil rouvert), et remise en état.
#
# Usage : bash syllabus_captures.sh site-x.json syllabus.json [nom de capture ...]
set -euo pipefail
HERE=$(cd "$(dirname "$0")" && pwd)
cd "$HERE"
SITE=$1
SETUP=$(realpath "$2")
shift 2
VARS=$(sudo -u www-data php "$HERE/syllabus_captures_prep.php" "$SITE" "$SETUP" prepare)
eval "export $VARS"
restore() { sudo -u www-data php "$HERE/syllabus_captures_prep.php" "$SITE" "$SETUP" restore "$SYCLOSE" >/dev/null; }
trap restore EXIT
cd "$HERE/../../guides"
NAMES=${*:-$(grep -o "\['sy-[a-z-]*'" captures.js | tr -d "['" | tr '\n' ' ')}
PERSONNEL=${PERSONNEL:-a} SYLLABUS=$SETUP node captures.js "$HERE/$SITE" $NAMES
