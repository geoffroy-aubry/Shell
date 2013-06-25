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
        // (string) Path of Bash:
        'bash_path' => '/bin/bash',

        // (string) List of '-o option' options used for all SSH and SCP commands:
        'ssh_options' => '-o ServerAliveInterval=10 -o StrictHostKeyChecking=no -o ConnectTimeout=10 -o BatchMode=yes',

        // (int) Maximal number of command shells launched simultaneously (parallel processes):
        'parallelization_max_nb_processes' => 10,

        // (int) Maximal number of parallel RSYNC (overriding 'parallelization_max_nb_processes'):
        'rsync_max_nb_processes' => 5,

        // (array) List of exclusion patterns for RSYNC command (converted into list of '--exclude <pattern>'):
        'default_rsync_exclude' => array(
            '.bzr/', '.cvsignore', '.git/', '.gitignore', '.svn/', 'cvslog.*', 'CVS', 'CVS.adm'
        )
    )
);

return $aConfig;
