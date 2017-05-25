<?php

use Phalcon\Mvc\View\Simple as View;
use Phalcon\Mvc\Url as UrlResolver;
use Phalcon\Mvc\View\Engine\Volt as Volt;
use Phalcon\Security as Security;

/**
 * Shared configuration service
 */
$di->setShared('config', function () {
    return include APP_PATH . "/config/config.php";
});

/**
 * Sets the view component
 */
$di->setShared('view', function () {
    $config = $this->getConfig();

    $view = new View();
    $view->setViewsDir($config->application->viewsDir);
    
    $view->registerEngines(array(
        '.volt' => function ($view, $di) use ($config) {

            $volt = new Volt($view, $di);

            $volt->setOptions(array(
                'compiledPath' => $config->application->cacheDir,
                'compiledSeparator' => '_'
            ));

            return $volt;
        },
        '.phtml' => 'Phalcon\Mvc\View\Engine\Php'
    ));
    
    return $view;
});

/**
 * The URL component is used to generate all kind of urls in the application
 */
$di->setShared('url', function () {
    $config = $this->getConfig();

    $url = new UrlResolver();
    $url->setBaseUri($config->application->baseUri);
    return $url;
});

/**
 * Database connection is created based in the parameters defined in the configuration file
 */
$di->setShared('db', function () {
    $config = $this->getConfig();

    $class = 'Phalcon\Db\Adapter\Pdo\\' . $config->database->adapter;
    $connection = new $class([
        'host'     => $config->database->host,
        'username' => $config->database->username,
        'password' => $config->database->password,
        'dbname'   => $config->database->dbname,
        'charset'  => $config->database->charset
    ]);

    return $connection;
});

/**
 * Security
 */
$di->setShared('security', function () {

    $security = new Security();

    // Hash das senhas cadastradas(Mudar conforme a capacidade do servidor)
    $security->setWorkFactor(12);

    return $security;
});

/**
 * Router
 */
$di->set('router', function() {

    $router = new Phalcon\Mvc\Router();

    $router->add('/', array(
        'controller' => 'estatisticas',
        'action' => 'index'
    ));

    return $router;
});

/**
 * OAuth2
 */
$di->setShared('oauth2', function () {
    $config = $this->getConfig();

    $storage = new OAuth2\Storage\Pdo(array(
        'dsn' => $config->oauth2->dsn,
        'username' => $config->oauth2->username,
        'password' => $config->oauth2->password)
    );

    // Pass a storage object or array of storage objects to the OAuth2 server class
    $server = new OAuth2\Server($storage);

    // Add the "Client Credentials" grant type (it is the simplest of the grant types)
    $server->addGrantType(new OAuth2\GrantType\ClientCredentials($storage));

    // Add the "Authorization Code" grant type (this is where the oauth magic happens)
    $server->addGrantType(new OAuth2\GrantType\AuthorizationCode($storage));

    //Changed token lifetime
    $server->setConfig("id_lifetime", $config->oauth2->token_lifetime);
    $server->setConfig("access_lifetime", $config->oauth2->token_lifetime);

    return $server;
});

