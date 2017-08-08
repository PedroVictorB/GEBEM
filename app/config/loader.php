<?php

/**
 * Registering an autoloader
 */
$loader = new \Phalcon\Loader();

$loader->registerDirs(
    [
        $config->application->modelsDir,
        $config->application->utilDir,
        $config->application->controllersDir,
        $config->application->databaseDir,
    ]
)->register();

$loader->registerNamespaces([
    'GEBEM\Utilities'     => $config->application->utilDir,
    'GEBEM\Controllers'   => $config->application->controllersDir,
    'GEBEM\Models'        => $config->application->modelsDir,
    'GEBEM\Database'        => $config->application->databaseDir
])->register();