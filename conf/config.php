<?php

/**
 * Répertoire racine de l'application.
 * @var string
 */
define('ROOT_DIR', realpath(__DIR__ . '/../'));

return array(
    'Main' => array(
        // Répertoire des fichiers de configuration de l'application elle-même.
        'conf_dir' => ROOT_DIR . '/conf',
    ),

    'GAubry\Shell' => array(
        // Nombre maximal de processus lancés en parallèle par parallelize.inc.sh.
        'parallelization_max_nb_processes' => 10,

        // Chemin vers le shell bash.
        'bash_path' => '/bin/bash',

        // Répertoire des bibliothèques utilisées par l'application.
        'lib_dir' => ROOT_DIR . '/lib',

        // Nombre de secondes avant timeout lors d'une connexion SSH.
        'ssh_connection_timeout' => 10,

        // Chemin du répertoire temporaire système utilisable par l'application.
        'tmp_dir' => '/tmp',

        // Nombre maximal d'exécutions shell rsync en parallèle.
        // Prioritaire sur 'parallelization_max_nb_processes'.
        'rsync_max_nb_processes' => 5,

        'tests_resources_dir' => ROOT_DIR . '/tests/resources',
    ),
);
