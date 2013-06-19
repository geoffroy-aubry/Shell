<?php

/**
 * Répertoire racine de l'application.
 * @var string
 */
define('ROOT_DIR', realpath(__DIR__ . '/../'));

$sRootDir = realpath(__DIR__ . '/..');
$aDirs = array(
    'root_dir'   => $sRootDir,
    'conf_dir'   => $sRootDir . '/conf',
    'lib_dir'    => $sRootDir . '/lib',
    'src_dir'    => $sRootDir . '/src',
    'inc_dir'    => $sRootDir . '/src/inc',
    'vendor_dir' => $sRootDir . '/vendor',
    'tmp_dir'    => '/tmp',
    'test_dir'   => $sRootDir . '/tests',
    'tests_resources_dir' => $sRootDir . '/tests/resources'
);

$aConfig = $aDirs + array(
    'GAubry\Shell' => array(
        // (int) Nombre maximal de processus lancés en parallèle par parallelize.inc.sh :
        'parallelization_max_nb_processes' => 10,

        // (string) Chemin vers le shell bash :
        'bash_path' => '/bin/bash',

        // (string) Répertoire des bibliothèques utilisées par l'application :
        'lib_dir' => $aDirs['lib_dir'],

        // (int) Nombre de secondes avant timeout lors d'une connexion SSH :
        'ssh_connection_timeout' => 10,

        // (string) Chemin du répertoire temporaire système utilisable par l'application :
        'tmp_dir' => $aDirs['tmp_dir'],

        // (int) Nombre maximal d'exécutions shell rsync en parallèle.
        // Prioritaire sur 'parallelization_max_nb_processes'.
        'rsync_max_nb_processes' => 5
    )
);

return $aConfig;
