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
    'src_dir'    => $sRootDir . '/src',
    'vendor_dir' => $sRootDir . '/vendor',
    'test_dir'   => $sRootDir . '/tests',
    'tests_resources_dir' => $sRootDir . '/tests/resources'
);

$aConfig = $aDirs + array(
    'GAubry\Shell' => array(
        // (int) Nombre maximal de processus lancés en parallèle par parallelize.sh :
        'parallelization_max_nb_processes' => 10,

        // (string) Chemin vers le shell bash :
        'bash_path' => '/bin/bash',

        // Options de type "[-o ssh_option]" à ajouter à chaque commande SSH ou SCP.
        'ssh_options' => '-o ServerAliveInterval=10 -o StrictHostKeyChecking=no -o ConnectTimeout=10 -o BatchMode=yes',

        // (int) Nombre maximal d'exécutions shell rsync en parallèle.
        // Prioritaire sur 'parallelization_max_nb_processes'.
        'rsync_max_nb_processes' => 5
    )
);

return $aConfig;
