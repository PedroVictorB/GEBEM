<?php
/**
 * Local variables
 * @var \Phalcon\Mvc\Micro $app
 */

include(__DIR__."/vendor/oauth2-server-php-develop/src/OAuth2/Autoloader.php");
include (__DIR__."/vendor/guzzle/autoloader.php");
OAuth2\Autoloader::register();
use GEBEM\Models\User as User;
use GEBEM\Models\OauthUsers as OUser;
use GEBEM\Models\OauthClients as CUser;

/*
 * * * * * * * * * * * * * * * *
 * Common pages                *
 * * * * * * * * * * * * * * * *
*/

/**
 * Initial page
 */
$app->get('/', function () use ($app) {
    echo $this['view']->render('index', array(
        'baseurl' => '/GEBEM/'
    ));
});

/**
 * Not-Found page
 */
$app->notFound(function () use ($app) {
    echo json_encode(
        array("GEBEM_STATUS" =>
            array(
                "code" => "400",
                "reasonPhrase" => "Bad Request",
                "details" => "Route not found"
            )
        )
    );
});


/*
 * * * * * * * * * * * * * * * *
 * API v1                      *
 * * * * * * * * * * * * * * * *
*/

/**
 * Registration route for initial page form
 */
$app->post('/v1/form/registration', function () use ($app) {
    $user = new User();

    $user->name = $app->request->getPost('name', 'string');
    $user->email = $app->request->getPost('email', 'email');
    $user->username = $app->request->getPost('username', 'alphanum');
    $user->password = $app->request->getPost('password', 'string');
    $cpassword = $app->request->getPost('cpassword', 'string');

    if(strlen($user->name) < 4 ||
        strlen($user->username) < 4 ||
        strlen($user->password) < 6){

        echo $this['view']->render('index', array(
            'warning' => 'warning',
            'message' => 'Name, username or password is invalid.'
            )
        );
        return;
    }

    if(strcmp($user->password, $cpassword) != 0){
        echo $this['view']->render('index', array(
                'warning' => 'warning',
                'message' => 'Password confirmation is wrong.'
            )
        );
        return;
    }

    $hpass = $this->security->hash($user->password);
    $user->password = $hpass;

    $ouser = new OUser();
    $ouser->username = $user->username;
    $ouser->password = $hpass;

    $cuser = new CUser();
    $cuser->client_id = $user->username;
    $cuser->client_secret = $hpass;
    $cuser->redirect_uri = '/GEBEM/';

    $this->db->begin();

    if(!$user->save() || !$ouser->save() || !$cuser->save()){
        $this->db->rollback();
        $errorMessage = '';
        foreach ($user->getMessages() as $message){
            $errorMessage .= $message.'<br>';
        }

        echo $this['view']->render('index', array(
                'warning' => 'warning',
                'message' => 'Something went wrong.<br>'.$errorMessage
            )
        );
        return;
    }
    $this->db->commit();

    echo $this['view']->render('index', array(
            'success' => 'success',
            'message' => 'User saved.You may use the API now :) .<br>Use /token [POST] with the username and password to get a token to access the API.'
        )
    );
    return;
});

/**
 * Registration route for API
 */
$app->post('/v1/registration', function () use ($app) {
    $user = new User();

    $user->name = $app->request->getPost('name', 'string');
    $user->email = $app->request->getPost('email', 'email');
    $user->username = $app->request->getPost('username', 'alphanum');
    $user->password = $app->request->getPost('password', 'string');
    $cpassword = $app->request->getPost('cpassword', 'string');

    if(strlen($user->name) < 4 ||
        strlen($user->username) < 4 ||
        strlen($user->password) < 6){

        echo json_encode(
            array("GEBEM_STATUS" =>
                array(
                    "code" => "400",
                    "reasonPhrase" => "Invalid attributes",
                    "details" => "Name, username or password is invalid."
                )
            )
        );
        return;
    }

    if(strcmp($user->password, $cpassword) != 0){
        echo json_encode(
            array("GEBEM_STATUS" =>
                array(
                    "code" => "400",
                    "reasonPhrase" => "Invalid attributes",
                    "details" => "Password confirmation is wrong."
                )
            )
        );
        return;
    }

    $hpass = $this->security->hash($user->password);
    $user->password = $hpass;

    $ouser = new OUser();
    $ouser->username = $user->username;
    $ouser->password = $hpass;

    $cuser = new CUser();
    $cuser->client_id = $user->username;
    $cuser->client_secret = $hpass;
    $cuser->redirect_uri = '/GEBEM/';

    $this->db->begin();

    if(!$user->save() || !$ouser->save() || !$cuser->save()){
        $this->db->rollback();
        $errorMessage = '';
        foreach ($user->getMessages() as $message){
            $errorMessage .= $message.' | ';
        }

        echo json_encode(
            array("GEBEM_STATUS" =>
                array(
                    "code" => "400",
                    "reasonPhrase" => "ERROR",
                    "details" => 'Something went wrong. '.$errorMessage
                )
            )
        );
        return;
    }
    $this->db->commit();

    echo json_encode(
        array("GEBEM_STATUS" =>
            array(
                "code" => "200",
                "reasonPhrase" => "OK",
                "details" => "User saved.You may use the API now :) .Use /token [POST] with the username and password to get a token to access the API."
            )
        )
    );
    return;
});

/**
 * Authentication route
 */
$app->post('/v1/token', function () use ($app) {
    // Handle a request for an OAuth2.0 Access Token and send the response to the client
    $this->oauth2->handleTokenRequest(OAuth2\Request::createFromGlobals())->send();
});

/**
 * Notification route
 * [POST] Entrance point for ORION subscriptions
 */
$app->post('/v1/notify', array($notificationController, 'notify'));

/**
 * Buildings route
 * [GET] Get all the buildings
 */
$app->get('/v1/buildings', array($buildingsController, 'getBuildings'));

/**
 * Buildings route
 * [GET] Get one building based on it's ID
 */
$app->get('/v1/buildings/{id_b}', array($buildingsController, 'getOneBuilding'));

/**
 * Buildings route
 * [GET] Get all rooms of a building
 */
$app->get('/v1/buildings/{id_b}/rooms', array($buildingsController, 'getOneBuildingRooms'));

/**
 * Buildings route
 * [GET] Get all sensors of a building
 */
$app->get('/v1/buildings/{id_b}/sensors', array($buildingsController, 'getOneBuildingSensors'));

/**
 * Buildings route
 * [GET] Get all modules of a building
 */
$app->get('/v1/buildings/{id_b}/modules', array($buildingsController, 'getOneBuildingModules'));

/**
 * Rooms route
 * [GET] Get all rooms
 */
$app->get('/v1/rooms', array($roomsController, 'getRooms'));

/**
 * Rooms route
 * [GET] Get one room based on its ID
 */
$app->get('/v1/rooms/{id_r}', array($roomsController, 'getOneRoom'));

/**
 * Rooms route
 * [GET] Get all sensors of one room
 */
$app->get('/v1/rooms/{id_r}/sensors', array($roomsController, 'getOneRoomSensors'));

/**
 * Rooms route
 * [GET] Get all modules of a room
 */
$app->get('/v1/rooms/{id_r}/modules', array($roomsController, 'getOneRoomModules'));

/**
 * Sensors route
 * [GET] Get all sensors
 */
$app->get('/v1/sensors', array($sensorsController, 'getSensors'));

/**
 * Sensors route
 * [GET] Get all rooms
 */
$app->get('/v1/sensors/{id_s}', array($sensorsController, 'getOneSensor'));

/**
 * Modules route
 * [GET] Get all modules
 */
$app->get('/v1/modules', array($modulesController, 'getModules'));

/**
 * Modules route
 * [GET] Get one module based on its ID
 */
$app->get('/v1/modules/{id_m}', array($modulesController, 'getOneModule'));

/**
 * Energy route
 * [GET] Get all buildings energy information
 */
$app->get('/v1/energy/buildings', array($energyController, 'getBuildings'));

/**
 * Energy route
 * [GET] Get one building energy information
 */
$app->get('/v1/energy/buildings/{id_b}', array($energyController, 'getOneBuilding'));

/**
 * Energy route
 * [GET] Get all rooms energy information
 */
$app->get('/v1/energy/rooms', array($energyController, 'getRooms'));

/**
 * Energy route
 * [GET] Get one room energy information
 */
$app->get('/v1/energy/rooms/{id_r}', array($energyController, 'getOneRoom'));

/**
 * Energy route
 * [GET] Get all modules energy information
 */
$app->get('/v1/energy/modules', array($energyController, 'getModules'));

/**
 * Energy route
 * [GET] Get one module energy information
 */
$app->get('/v1/energy/modules/{id_m}', array($energyController, 'getOneModule'));
