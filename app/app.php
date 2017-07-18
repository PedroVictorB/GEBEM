<?php
/**
 * Local variables
 * @var \Phalcon\Mvc\Micro $app
 */

include(__DIR__."/vendor/oauth2-server-php-develop/src/OAuth2/Autoloader.php");
include (__DIR__."/vendor/guzzle/autoloader.php");
OAuth2\Autoloader::register();
use GEBEM\Utilities\Util as Util;

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
    $user = new Usuario();

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

    $user->password = $this->security->hash($user->password);

    if(!$user->save()){

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
    $user = new Usuario();

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

    $user->password = $this->security->hash($user->password);

    if(!$user->save()){

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
 * Token route
 */
$app->post('/v1/token', function () use ($app) {
    // Handle a request for an OAuth2.0 Access Token and send the response to the client
    $this->oauth2->handleTokenRequest(OAuth2\Request::createFromGlobals())->send();
});

/**
 * Token refresh route
 */
$app->put('/v1/token', function () use ($app) {
    // Handle a request for an OAuth2.0 Access Token and send the response to the client
    $this->oauth2->handleTokenRequest(OAuth2\Request::createFromGlobals())->send();
});

/**
 * Buildings route
 * [GET] Get all the buildings
 */
$app->get('/v1/buildings', function () use ($app) {

    $client = new GuzzleHttp\Client();

    $configParams = $this->config->GEBEM->API_CONFIGURATION->params->toArray();

    $showDetails = Util::getBestParamValue("details", "on", $configParams, $_GET) == "on" ? true : false;

    $params =   "?offset=".Util::getBestParamValue("offset", "0", $configParams, $_GET)
                ."&limit=".Util::getBestParamValue("offset", "100", $configParams, $_GET)
                ."&details=".Util::getBestParamValue("details", "off", $configParams, $_GET)
                ."&orderBy=".Util::getBestParamValue("orderBy", "", $configParams, $_GET);

    $buildingsTypes = $this->config->GEBEM->API_CONFIGURATION->buildingTypes;
    $entities = array();
    for($i = 0;$i < count($buildingsTypes);$i++){
        array_push($entities, array(
            "type" => $buildingsTypes[$i],
            "isPattern" => true,
            "id" => Util::getBestParamValue("patternId", ".*", $configParams, $_GET)
        ));
    }

    $attributes = array();
    if(isset($_GET["attributes"])){
        $attributes = explode(",", Util::getBestParamValue("attributes", "", $configParams, $_GET));
    }

    $token = '';
    if($this->config->GEBEM->ORION_CONFIGURATION->isProtected){
        $configToken = $this->config->GEBEM->IDM_CONFIGURATION->toArray();
        $resToken = $client->post(
            $this->config->GEBEM->IDM_CONFIGURATION->protocol.'://'
            .$this->config->GEBEM->IDM_CONFIGURATION->url.':'
            .$this->config->GEBEM->IDM_CONFIGURATION->port
            .'/v3/auth/tokens'
            , array(
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json'
                ],
                "json" => [
                    'auth' => array(
                        'identity' => array(
                            'methods' => array(
                                'password'
                            ),
                            'password' => array(
                                'user' => array(
                                    'name' => Util::getBestParamValue("username", "idm", $configToken, array()),
                                    'domain' => array(
                                        'name' => 'Default'
                                    ),
                                    'password' => Util::getBestParamValue("password", "idm", $configToken, array())
                                )
                            )
                        )
                    )
                ]
            )

        );

        if(empty($resToken->getHeader('X-Subject-Token')) || $resToken->getStatusCode() !== 201){
            echo json_encode(
                array("GEBEM_STATUS" =>
                    array(
                        "code" => $resToken->getStatusCode(),
                        "reasonPhrase" => $resToken->getReasonPhrase(),
                        "details" => "Error getting token from keystone"
                    )
                )
            );
            return;
        }

        $token = $resToken->getHeader('X-Subject-Token')[0];
    }

    try{
        $res = $client->post(
            $this->config->GEBEM->ORION_CONFIGURATION->protocol.'://'
            .$this->config->GEBEM->ORION_CONFIGURATION->url.':'
            .$this->config->GEBEM->ORION_CONFIGURATION->port
            .'/v1/queryContext'
            .$params
            , array(
                //'http_errors' => false,
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                    'X-Auth-Token' => $token
                ],
                "json" => [
                    'entities' => $entities,
                    'attributes' => $attributes
                ]
            )

        );
    }catch (GuzzleHttp\Exception\RequestException $e){
        echo json_encode(
            array("GEBEM_STATUS" =>
                array(
                    "code" => $e->getResponse()->getStatusCode(),
                    "reasonPhrase" => $e->getResponse()->getReasonPhrase(),
                    "details" => "Error while communicating to ORION (Contact admin)"
                )
            )
        );
        return;
    }

    $response = json_decode($res->getBody()->getContents());

    if(isset($response->errorCode) && $response->errorCode->code != 200){
        echo json_encode(
            array("GEBEM_STATUS" =>
                array(
                    "code" => $response->errorCode->code,
                    "reasonPhrase" => $response->errorCode->reasonPhrase,
                    "details" => $response->errorCode->details
                )
            )
        );
        return;
    }

    $buildings = json_decode($res->getBody())->contextResponses;

    $tempBuildings = array();
    foreach ($buildings as $building){
        $tempAttribute = array();
        if(!empty($building->contextElement->attributes)){
            foreach ($building->contextElement->attributes as $attribute){
                $tempMetadata = array();
                if(!empty($attribute->metadatas)){
                    foreach ($attribute->metadatas as $metadata){
                        array_push($tempMetadata, array(
                            "name" => $metadata->name,
                            "type" => $metadata->type,
                            "value" => $metadata->value
                        ));
                    }
                }
                array_push($tempAttribute, array(
                    "name" => $attribute->name,
                    "type" => $attribute->type,
                    "value" => $attribute->value,
                    "metadata" => $tempMetadata
                ));
            }
        }
        array_push($tempBuildings, array(
            "id" => $building->contextElement->id,
            "type" =>$building->contextElement->type,
            "isPattern" => $building->contextElement->isPattern,
            "attributes" => $tempAttribute
        ));
    }

    echo json_encode(
        array(
            "GEBEM_BUILDINGS" =>
                $tempBuildings
            ,
            "GEBEM_STATUS" =>
                array(
                    "code" => "200",
                    "reasonPhrase" => "OK",
                    "details" => $showDetails ? $response->errorCode->details : ""
                )
        )
    );
});

/**
 * Buildings route
 * [GET] Get one building based on it's ID
 */
$app->get('/v1/buildings/{id}', function () use ($app) {
    echo json_encode(
        array("GEBEM_STATUS" =>
            array(
                "code" => "200",
                "reasonPhrase" => "OK",
                "details" => "Buildings"
            )
        )
    );
});
