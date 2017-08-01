<?php

use Phalcon\Di\FactoryDefault;
use Phalcon\Mvc\Micro;
use Phalcon\Events\Event;
use Phalcon\Events\Manager as EventsManager;
use GEBEM\Controllers\ModulesController as ModulesController;
use GEBEM\Controllers\BuildingsController as BuildingsController;
use GEBEM\Controllers\RoomsController as RoomsController;
use GEBEM\Controllers\SensorsController as SensorsController;

error_reporting(E_ALL);

define('BASE_PATH', dirname(__DIR__));
//define('APP_PATH', empty(getenv('IS_HEROKU_DEPLOYED')) ? BASE_PATH . '/app' : BASE_PATH);
define('APP_PATH', BASE_PATH . '/app');

try {

    /**
     * The FactoryDefault Dependency Injector automatically registers the services that
     * provide a full stack framework. These default services can be overidden with custom ones.
     */
    $di = new FactoryDefault();

    /**
     * Include Services
     */
    include APP_PATH . '/config/services.php';

    /**
     * Get config service for use in inline setup below
     */
    $config = $di->getConfig();

    /**
     * Include Autoloader
     */
    include APP_PATH . '/config/loader.php';

    /**
     * Create de EventsManager
     */
    $eventsManager = new EventsManager();

    $eventsManager->attach(
        "micro:beforeExecuteRoute",
        function (Event $event, $app) {

            if(!$app->config->GEBEM->API_CONFIGURATION->use_oauth2_protection){
                return true;
            }

            //Acesso a index, token e registros pode ser feito sem token
            if ($app['router']->getRewriteUri() == '/' ||
                $app['router']->getRewriteUri() == '/v1/token' ||
                $app['router']->getRewriteUri() == '/v1/form/registration' ||
                $app['router']->getRewriteUri() == '/v1/registration') {
                return true;
            }

            //Verifica o token OAuth2
            if (!$app->oauth2->verifyResourceRequest(OAuth2\Request::createFromGlobals())) {
                $app->oauth2->getResponse()->send();

                return false;
            }
            return true;
        }
    );

    /**
     * Starting the application
     * Assign service locator to the application
     */
    $app = new Micro($di);

    /**
     * Bind the events manager to the app
     */
    $app->setEventsManager($eventsManager);

    /**
     * Routes controllers
     */
    $buildingsController = new BuildingsController();
    $roomsController = new RoomsController();
    $sensorsController = new SensorsController();
    $modulesController = new ModulesController();

    /**
     * Include Application
     */
    include APP_PATH . '/app.php';

    /**
     * Handle the request
     */
    $app->handle();

} catch (\Exception $e) {
      echo $e->getMessage() . '<br>';
      echo '<pre>' . $e->getTraceAsString() . '</pre>';
}
