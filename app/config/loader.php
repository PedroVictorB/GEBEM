<?php

/**
 * Registering an autoloader
 */
$loader = new \Phalcon\Loader();

$loader->registerDirs(
    [
        $config->application->modelsDir,
        $config->application->utilDir,
    ]
)->register();

$loader->registerNamespaces([
    'GEBEM\Utilities' => $config->application->utilDir,
])->register();