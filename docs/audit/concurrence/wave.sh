#!/bin/bash
# Usage : wave.sh <site.json> <nom> "scénario args" "scénario args" ...
# Lance les workers ensemble (départ synchronisé), puis vérifie les invariants.
cd "$(dirname "$0")"
site=$1; name=$2; shift 2
export STARTAT=$(python3 -c "import time;print(time.time()+3)")
mkdir -p logs; : > "logs/$name.jsonl"
for job in "$@"; do
  # shellcheck disable=SC2086
  php worker.php "$site" $job >> "logs/$name.jsonl" 2>>"logs/$name.err" &
done
wait
echo "== $name"; cat "logs/$name.jsonl"
echo -n "check: "; php race_prep.php "$site" check
