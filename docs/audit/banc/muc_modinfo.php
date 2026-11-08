<?php
// Cache des cours (core/coursemodinfo) dans le magasin fichier, à verrouillage natif bloquant ;
// APCu reste le magasin des autres caches applicatifs.
define('CLI_SCRIPT', true);
require(getenv('MOODLE_CONFIG') ?: '/home/user/moodle-stress/moodle/config.php');
\core_cache\config_writer::instance()->set_definition_mappings('core/coursemodinfo', ['default_application']);
echo "ok\n";
