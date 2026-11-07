# Banc de concurrence de mod_ep

Scripts du test décrit dans `../test-de-concurrence.tex`, sur le banc de `../banc/` (même site,
même jeu de données `site-<tag>.json`).

- `race_prep.php site.json prep|check` : prépare les cibles (un EP de 30 places, un EP sans
  limite, des demandes en attente) ; vérifie les invariants (inscrits par EP, doublons,
  décisions en double ou manquantes, un seul supplément en vigueur par étudiant, numéros
  uniques, verrous consultatifs restants, transactions ouvertes).
- `race.py site.json` : courses HTTP à départ synchronisé (barrière) : ruée de 200 étudiants sur
  30 places, double-clic ×10, décisions simultanées du responsable et de la DEVE ou de deux
  membres de la DEVE, émissions simultanées de suppléments, charge mixte de 60 s pendant un
  export complet.
- `worker.php site.json <scénario> [args]` : une procédure lourde dans son propre processus
  (`restore <archive.zip> <cmid cible>`, `credits <fichier.csv>`), une ligne JSON en sortie.
- `wave.sh site.json <nom> "scénario args" ...` : lance les workers ensemble, puis `check`.

```bash
./wave.sh ../banc/site-a.json restaurations "restore export.zip 9" "restore export.zip 9"
./wave.sh ../banc/site-a.json imports "credits credits.csv" "credits credits.csv" "credits credits.csv"
```
