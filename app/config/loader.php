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
    ]
)->register();

$loader->registerNamespaces([
    'GEBEM\Utilities'     => $config->application->utilDir,
    'GEBEM\Controllers'   => $config->application->controllersDir,
    'GEBEM\Models'        => $config->application->modelsDir
])->register();