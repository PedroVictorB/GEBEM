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
        $resToken = Util::getKeystoneToken($configToken);

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

    $q = Util::getBestParamValue("q", "", $configParams, $_GET);

    try{
        if(empty($q)){
            $load = array(
                'entities' => $entities,
                'attributes' => $attributes
            );
        }else{
            $load = array(
                'entities' => $entities,
                'attributes' => $attributes,
                'restriction' => [
                    'scopes' => [[
                        'type' => "FIWARE::StringQuery",
                        'value' => $q
                    ]]
                ]
            );
        }

        $res = $client->post(
            $this->config->GEBEM->ORION_CONFIGURATION->protocol.'://'
            .$this->config->GEBEM->ORION_CONFIGURATION->url.':'
            .$this->config->GEBEM->ORION_CONFIGURATION->port
            .'/v1/queryContext'
            .$params
            , array(
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                    'X-Auth-Token' => $token
                ],
                "json" => $load
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
                    "details" => isset($response->errorCode->details) ? $response->errorCode->details : "Error"
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
$app->get('/v1/buildings/{id_b}', function ($id_b) use ($app) {

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
            "isPattern" => false,
            "id" => $id_b
        ));
    }

    $attributes = array();
    if(isset($_GET["attributes"])){
        $attributes = explode(",", Util::getBestParamValue("attributes", "", $configParams, $_GET));
    }

    $token = '';
    if($this->config->GEBEM->ORION_CONFIGURATION->isProtected){
        $configToken = $this->config->GEBEM->IDM_CONFIGURATION->toArray();
        $resToken = Util::getKeystoneToken($configToken);

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

    $q = Util::getBestParamValue("q", "", $configParams, $_GET);

    try{
        if(empty($q)){
            $load = array(
                'entities' => $entities,
                'attributes' => $attributes
            );
        }else{
            $load = array(
                'entities' => $entities,
                'attributes' => $attributes,
                'restriction' => [
                    'scopes' => [[
                        'type' => "FIWARE::StringQuery",
                        'value' => $q
                    ]]
                ]
            );
        }

        $res = $client->post(
            $this->config->GEBEM->ORION_CONFIGURATION->protocol.'://'
            .$this->config->GEBEM->ORION_CONFIGURATION->url.':'
            .$this->config->GEBEM->ORION_CONFIGURATION->port
            .'/v1/queryContext'
            .$params
            , array(
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                    'X-Auth-Token' => $token
                ],
                "json" => $load
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
                    "details" => isset($response->errorCode->details) ? $response->errorCode->details : "Error"
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
 * [GET] Get all sensors of a building
 */
$app->get('/v1/buildings/{id_b}/sensors', function ($id_b) use ($app) {
    $client = new GuzzleHttp\Client();

    $configParams = $this->config->GEBEM->API_CONFIGURATION->params->toArray();

    $showDetails = Util::getBestParamValue("details", "on", $configParams, $_GET) == "on" ? true : false;

    $params =   "?offset=".Util::getBestParamValue("offset", "0", $configParams, $_GET)
        ."&limit=".Util::getBestParamValue("offset", "100", $configParams, $_GET)
        ."&details=".Util::getBestParamValue("details", "off", $configParams, $_GET)
        ."&orderBy=".Util::getBestParamValue("orderBy", "", $configParams, $_GET);

    $roomTypes = $this->config->GEBEM->API_CONFIGURATION->roomTypes;
    $entities = array();
    for($i = 0;$i < count($roomTypes);$i++){
        array_push($entities, array(
            "type" => $roomTypes[$i],
            "isPattern" => true,
            "id" => ".*"
        ));
    }

    $attributes = array();
    if(isset($_GET["attributes"])){
        $attributes = explode(",", Util::getBestParamValue("attributes", "", $configParams, $_GET));
    }

    $token = '';
    if($this->config->GEBEM->ORION_CONFIGURATION->isProtected){
        $configToken = $this->config->GEBEM->IDM_CONFIGURATION->toArray();
        $resToken = Util::getKeystoneToken($configToken);

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

    $q = Util::getBestParamValue("q", "", $configParams, $_GET);

    try{
        $load = array(
            'entities' => $entities,
            'attributes' => array(),
            'restriction' => [
                'scopes' => [[
                    'type' => "FIWARE::StringQuery",
                    'value' => $this->config->GEBEM->API_CONFIGURATION->attributes_names->rooms."=='".$id_b."'"
                ]]
            ]
        );

        $res = $client->post(
            $this->config->GEBEM->ORION_CONFIGURATION->protocol.'://'
            .$this->config->GEBEM->ORION_CONFIGURATION->url.':'
            .$this->config->GEBEM->ORION_CONFIGURATION->port
            .'/v1/queryContext'
            .$params
            , array(
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                    'X-Auth-Token' => $token
                ],
                "json" => $load
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
                    "details" => isset($response->errorCode->details) ? $response->errorCode->details : "Error"
                )
            )
        );
        return;
    }

    $rooms = json_decode($res->getBody())->contextResponses;

    $sensorQuery = $this->config->GEBEM->API_CONFIGURATION->attributes_names->sensors."==";
    $count = 0;
    foreach ($rooms as $room){
        if($count == 0){
            $sensorQuery .= "'".$room->contextElement->id."'";
        }else{
            $sensorQuery .= ",'".$room->contextElement->id."'";
        }
        $count++;
    }

    $sensorTypes = $this->config->GEBEM->API_CONFIGURATION->sensorTypes;
    $entities = array();
    for($i = 0;$i < count($sensorTypes);$i++){
        array_push($entities, array(
            "type" => $sensorTypes[$i],
            "isPattern" => true,
            "id" => Util::getBestParamValue("patternId", ".*", $configParams, $_GET)
        ));
    }

    try{
        if(empty($q)){
            $load = array(
                'entities' => $entities,
                'attributes' => $attributes,
                'restriction' => [
                    'scopes' => [[
                        'type' => "FIWARE::StringQuery",
                        'value' => $sensorQuery
                    ]]
                ]
            );
        }else{
            $load = array(
                'entities' => $entities,
                'attributes' => $attributes,
                'restriction' => [
                    'scopes' => [[
                        'type' => "FIWARE::StringQuery",
                        'value' => $q.";".$sensorQuery
                    ]]
                ]
            );
        }

        $res = $client->post(
            $this->config->GEBEM->ORION_CONFIGURATION->protocol.'://'
            .$this->config->GEBEM->ORION_CONFIGURATION->url.':'
            .$this->config->GEBEM->ORION_CONFIGURATION->port
            .'/v1/queryContext'
            .$params
            , array(
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                    'X-Auth-Token' => $token
                ],
                "json" => $load
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
                    "details" => isset($response->errorCode->details) ? $response->errorCode->details : "Error"
                )
            )
        );
        return;
    }

    $sensors = json_decode($res->getBody())->contextResponses;

    $tempSensors = array();
    foreach ($sensors as $sensor){
        $tempAttribute = array();
        if(!empty($sensor->contextElement->attributes)){
            foreach ($sensor->contextElement->attributes as $attribute){
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
        array_push($tempSensors, array(
            "id" => $sensor->contextElement->id,
            "type" =>$sensor->contextElement->type,
            "isPattern" => $sensor->contextElement->isPattern,
            "attributes" => $tempAttribute
        ));
    }

    echo json_encode(
        array(
            "GEBEM_BUILDINGS" =>
                $tempSensors
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
 * [GET] Get all modules of a building
 */
$app->get('/v1/buildings/{id_b}/modules', function ($id_b) use ($app) {
    $client = new GuzzleHttp\Client();

    $configParams = $this->config->GEBEM->API_CONFIGURATION->params->toArray();

    $showDetails = Util::getBestParamValue("details", "on", $configParams, $_GET) == "on" ? true : false;

    $params =   "?offset=".Util::getBestParamValue("offset", "0", $configParams, $_GET)
        ."&limit=".Util::getBestParamValue("offset", "100", $configParams, $_GET)
        ."&details=".Util::getBestParamValue("details", "off", $configParams, $_GET)
        ."&orderBy=".Util::getBestParamValue("orderBy", "", $configParams, $_GET);

    $roomTypes = $this->config->GEBEM->API_CONFIGURATION->roomTypes;
    $entities = array();
    for($i = 0;$i < count($roomTypes);$i++){
        array_push($entities, array(
            "type" => $roomTypes[$i],
            "isPattern" => true,
            "id" => ".*"
        ));
    }

    $attributes = array();
    if(isset($_GET["attributes"])){
        $attributes = explode(",", Util::getBestParamValue("attributes", "", $configParams, $_GET));
    }

    $token = '';
    if($this->config->GEBEM->ORION_CONFIGURATION->isProtected){
        $configToken = $this->config->GEBEM->IDM_CONFIGURATION->toArray();
        $resToken = Util::getKeystoneToken($configToken);

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

    $q = Util::getBestParamValue("q", "", $configParams, $_GET);

    try{
        $load = array(
            'entities' => $entities,
            'attributes' => array(),
            'restriction' => [
                'scopes' => [[
                    'type' => "FIWARE::StringQuery",
                    'value' => $this->config->GEBEM->API_CONFIGURATION->attributes_names->rooms."=='".$id_b."'"
                ]]
            ]
        );

        $res = $client->post(
            $this->config->GEBEM->ORION_CONFIGURATION->protocol.'://'
            .$this->config->GEBEM->ORION_CONFIGURATION->url.':'
            .$this->config->GEBEM->ORION_CONFIGURATION->port
            .'/v1/queryContext'
            .$params
            , array(
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                    'X-Auth-Token' => $token
                ],
                "json" => $load
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
                    "details" => isset($response->errorCode->details) ? $response->errorCode->details : "Error"
                )
            )
        );
        return;
    }

    $rooms = json_decode($res->getBody())->contextResponses;

    $moduleQuery = $this->config->GEBEM->API_CONFIGURATION->attributes_names->modules."==";
    $count = 0;
    foreach ($rooms as $room){
        if($count == 0){
            $moduleQuery .= "'".$room->contextElement->id."'";
        }else{
            $moduleQuery .= ",'".$room->contextElement->id."'";
        }
        $count++;
    }

    $moduleTypes = $this->config->GEBEM->API_CONFIGURATION->moduleTypes;
    $entities = array();
    for($i = 0;$i < count($moduleTypes);$i++){
        array_push($entities, array(
            "type" => $moduleTypes[$i],
            "isPattern" => true,
            "id" => Util::getBestParamValue("patternId", ".*", $configParams, $_GET)
        ));
    }

    try{
        if(empty($q)){
            $load = array(
                'entities' => $entities,
                'attributes' => $attributes,
                'restriction' => [
                    'scopes' => [[
                        'type' => "FIWARE::StringQuery",
                        'value' => $moduleQuery
                    ]]
                ]
            );
        }else{
            $load = array(
                'entities' => $entities,
                'attributes' => $attributes,
                'restriction' => [
                    'scopes' => [[
                        'type' => "FIWARE::StringQuery",
                        'value' => $q.";".$moduleQuery
                    ]]
                ]
            );
        }

        $res = $client->post(
            $this->config->GEBEM->ORION_CONFIGURATION->protocol.'://'
            .$this->config->GEBEM->ORION_CONFIGURATION->url.':'
            .$this->config->GEBEM->ORION_CONFIGURATION->port
            .'/v1/queryContext'
            .$params
            , array(
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                    'X-Auth-Token' => $token
                ],
                "json" => $load
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
                    "details" => isset($response->errorCode->details) ? $response->errorCode->details : "Error"
                )
            )
        );
        return;
    }

    $modules = json_decode($res->getBody())->contextResponses;

    $tempModules = array();
    foreach ($modules as $module){
        $tempAttribute = array();
        if(!empty($module->contextElement->attributes)){
            foreach ($module->contextElement->attributes as $attribute){
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
        array_push($tempModules, array(
            "id" => $module->contextElement->id,
            "type" =>$module->contextElement->type,
            "isPattern" => $module->contextElement->isPattern,
            "attributes" => $tempAttribute
        ));
    }

    echo json_encode(
        array(
            "GEBEM_BUILDINGS" =>
                $tempModules
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
 * [GET] Get all rooms of a building
 */
$app->get('/v1/buildings/{id_b}/rooms', function ($id_b) use ($app) {
    $client = new GuzzleHttp\Client();

    $configParams = $this->config->GEBEM->API_CONFIGURATION->params->toArray();

    $showDetails = Util::getBestParamValue("details", "on", $configParams, $_GET) == "on" ? true : false;

    $params =   "?offset=".Util::getBestParamValue("offset", "0", $configParams, $_GET)
        ."&limit=".Util::getBestParamValue("offset", "100", $configParams, $_GET)
        ."&details=".Util::getBestParamValue("details", "off", $configParams, $_GET)
        ."&orderBy=".Util::getBestParamValue("orderBy", "", $configParams, $_GET);

    $roomTypes = $this->config->GEBEM->API_CONFIGURATION->roomTypes;
    $entities = array();
    for($i = 0;$i < count($roomTypes);$i++){
        array_push($entities, array(
            "type" => $roomTypes[$i],
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
        $resToken = Util::getKeystoneToken($configToken);

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

    $q = Util::getBestParamValue("q", "", $configParams, $_GET);

    try{
        if(empty($q)){
            $load = array(
                'entities' => $entities,
                'attributes' => $attributes,
                'restriction' => [
                    'scopes' => [[
                        'type' => "FIWARE::StringQuery",
                        'value' => $this->config->GEBEM->API_CONFIGURATION->attributes_names->rooms."=='".$id_b."'"
                    ]]
                ]
            );
        }else{
            $load = array(
                'entities' => $entities,
                'attributes' => $attributes,
                'restriction' => [
                    'scopes' => [[
                        'type' => "FIWARE::StringQuery",
                        'value' => $q.";".$this->config->GEBEM->API_CONFIGURATION->attributes_names->rooms."=='".$id_b."'"
                    ]]
                ]
            );
        }

        $res = $client->post(
            $this->config->GEBEM->ORION_CONFIGURATION->protocol.'://'
            .$this->config->GEBEM->ORION_CONFIGURATION->url.':'
            .$this->config->GEBEM->ORION_CONFIGURATION->port
            .'/v1/queryContext'
            .$params
            , array(
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                    'X-Auth-Token' => $token
                ],
                "json" => $load
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
                    "details" => isset($response->errorCode->details) ? $response->errorCode->details : "Error"
                )
            )
        );
        return;
    }

    $rooms = json_decode($res->getBody())->contextResponses;

    $tempRooms = array();
    foreach ($rooms as $room){
        $tempAttribute = array();
        if(!empty($room->contextElement->attributes)){
            foreach ($room->contextElement->attributes as $attribute){
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
        array_push($tempRooms, array(
            "id" => $room->contextElement->id,
            "type" =>$room->contextElement->type,
            "isPattern" => $room->contextElement->isPattern,
            "attributes" => $tempAttribute
        ));
    }

    echo json_encode(
        array(
            "GEBEM_BUILDINGS" =>
                $tempRooms
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
 * Rooms route
 * [GET] Get all rooms
 */
$app->get('/v1/rooms', function () use ($app) {
    $client = new GuzzleHttp\Client();

    $configParams = $this->config->GEBEM->API_CONFIGURATION->params->toArray();

    $showDetails = Util::getBestParamValue("details", "on", $configParams, $_GET) == "on" ? true : false;

    $params =   "?offset=".Util::getBestParamValue("offset", "0", $configParams, $_GET)
        ."&limit=".Util::getBestParamValue("offset", "100", $configParams, $_GET)
        ."&details=".Util::getBestParamValue("details", "off", $configParams, $_GET)
        ."&orderBy=".Util::getBestParamValue("orderBy", "", $configParams, $_GET);

    $roomTypes = $this->config->GEBEM->API_CONFIGURATION->roomTypes;
    $entities = array();
    for($i = 0;$i < count($roomTypes);$i++){
        array_push($entities, array(
            "type" => $roomTypes[$i],
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
        $resToken = Util::getKeystoneToken($configToken);

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

    $q = Util::getBestParamValue("q", "", $configParams, $_GET);

    try{
        if(empty($q)){
            $load = array(
                'entities' => $entities,
                'attributes' => $attributes
            );
        }else{
            $load = array(
                'entities' => $entities,
                'attributes' => $attributes,
                'restriction' => [
                    'scopes' => [[
                        'type' => "FIWARE::StringQuery",
                        'value' => $q
                    ]]
                ]
            );
        }

        $res = $client->post(
            $this->config->GEBEM->ORION_CONFIGURATION->protocol.'://'
            .$this->config->GEBEM->ORION_CONFIGURATION->url.':'
            .$this->config->GEBEM->ORION_CONFIGURATION->port
            .'/v1/queryContext'
            .$params
            , array(
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                    'X-Auth-Token' => $token
                ],
                "json" => $load
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
                    "details" => isset($response->errorCode->details) ? $response->errorCode->details : "Error"
                )
            )
        );
        return;
    }

    $rooms = json_decode($res->getBody())->contextResponses;

    $tempRooms = array();
    foreach ($rooms as $room){
        $tempAttribute = array();
        if(!empty($room->contextElement->attributes)){
            foreach ($room->contextElement->attributes as $attribute){
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
        array_push($tempRooms, array(
            "id" => $room->contextElement->id,
            "type" =>$room->contextElement->type,
            "isPattern" => $room->contextElement->isPattern,
            "attributes" => $tempAttribute
        ));
    }

    echo json_encode(
        array(
            "GEBEM_BUILDINGS" =>
                $tempRooms
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
 * Rooms route
 * [GET] Get one room based on its ID
 */
$app->get('/v1/rooms/{id_r}', function ($id_r) use ($app) {
    $client = new GuzzleHttp\Client();

    $configParams = $this->config->GEBEM->API_CONFIGURATION->params->toArray();

    $showDetails = Util::getBestParamValue("details", "on", $configParams, $_GET) == "on" ? true : false;

    $params =   "?offset=".Util::getBestParamValue("offset", "0", $configParams, $_GET)
        ."&limit=".Util::getBestParamValue("offset", "100", $configParams, $_GET)
        ."&details=".Util::getBestParamValue("details", "off", $configParams, $_GET)
        ."&orderBy=".Util::getBestParamValue("orderBy", "", $configParams, $_GET);

    $roomTypes = $this->config->GEBEM->API_CONFIGURATION->roomTypes;
    $entities = array();
    for($i = 0;$i < count($roomTypes);$i++){
        array_push($entities, array(
            "type" => $roomTypes[$i],
            "isPattern" => true,
            "id" => $id_r
        ));
    }

    $attributes = array();
    if(isset($_GET["attributes"])){
        $attributes = explode(",", Util::getBestParamValue("attributes", "", $configParams, $_GET));
    }

    $token = '';
    if($this->config->GEBEM->ORION_CONFIGURATION->isProtected){
        $configToken = $this->config->GEBEM->IDM_CONFIGURATION->toArray();
        $resToken = Util::getKeystoneToken($configToken);

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

    $q = Util::getBestParamValue("q", "", $configParams, $_GET);

    try{
        if(empty($q)){
            $load = array(
                'entities' => $entities,
                'attributes' => $attributes
            );
        }else{
            $load = array(
                'entities' => $entities,
                'attributes' => $attributes,
                'restriction' => [
                    'scopes' => [[
                        'type' => "FIWARE::StringQuery",
                        'value' => $q
                    ]]
                ]
            );
        }

        $res = $client->post(
            $this->config->GEBEM->ORION_CONFIGURATION->protocol.'://'
            .$this->config->GEBEM->ORION_CONFIGURATION->url.':'
            .$this->config->GEBEM->ORION_CONFIGURATION->port
            .'/v1/queryContext'
            .$params
            , array(
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                    'X-Auth-Token' => $token
                ],
                "json" => $load
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
                    "details" => isset($response->errorCode->details) ? $response->errorCode->details : "Error"
                )
            )
        );
        return;
    }

    $rooms = json_decode($res->getBody())->contextResponses;

    $tempRooms = array();
    foreach ($rooms as $room){
        $tempAttribute = array();
        if(!empty($room->contextElement->attributes)){
            foreach ($room->contextElement->attributes as $attribute){
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
        array_push($tempRooms, array(
            "id" => $room->contextElement->id,
            "type" =>$room->contextElement->type,
            "isPattern" => $room->contextElement->isPattern,
            "attributes" => $tempAttribute
        ));
    }

    echo json_encode(
        array(
            "GEBEM_BUILDINGS" =>
                $tempRooms
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
 * Rooms route
 * [GET] Get all sensors of one room
 */
$app->get('/v1/rooms/{id_r}/sensors', function ($id_r) use ($app) {
    $client = new GuzzleHttp\Client();

    $configParams = $this->config->GEBEM->API_CONFIGURATION->params->toArray();

    $showDetails = Util::getBestParamValue("details", "on", $configParams, $_GET) == "on" ? true : false;

    $params =   "?offset=".Util::getBestParamValue("offset", "0", $configParams, $_GET)
        ."&limit=".Util::getBestParamValue("offset", "100", $configParams, $_GET)
        ."&details=".Util::getBestParamValue("details", "off", $configParams, $_GET)
        ."&orderBy=".Util::getBestParamValue("orderBy", "", $configParams, $_GET);

    $sensorTypes = $this->config->GEBEM->API_CONFIGURATION->sensorTypes;
    $entities = array();
    for($i = 0;$i < count($sensorTypes);$i++){
        array_push($entities, array(
            "type" => $sensorTypes[$i],
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
        $resToken = Util::getKeystoneToken($configToken);

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

    $q = Util::getBestParamValue("q", "", $configParams, $_GET);

    try{
        if(empty($q)){
            $load = array(
                'entities' => $entities,
                'attributes' => $attributes,
                'restriction' => [
                    'scopes' => [[
                        'type' => "FIWARE::StringQuery",
                        'value' => $this->config->GEBEM->API_CONFIGURATION->attributes_names->sensors."=='".$id_r."'"
                    ]]
                ]
            );
        }else{
            $load = array(
                'entities' => $entities,
                'attributes' => $attributes,
                'restriction' => [
                    'scopes' => [[
                        'type' => "FIWARE::StringQuery",
                        'value' => $q.";".$this->config->GEBEM->API_CONFIGURATION->attributes_names->sensors."=='".$id_r."'"
                    ]]
                ]
            );
        }

        $res = $client->post(
            $this->config->GEBEM->ORION_CONFIGURATION->protocol.'://'
            .$this->config->GEBEM->ORION_CONFIGURATION->url.':'
            .$this->config->GEBEM->ORION_CONFIGURATION->port
            .'/v1/queryContext'
            .$params
            , array(
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                    'X-Auth-Token' => $token
                ],
                "json" => $load
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
                    "details" => isset($response->errorCode->details) ? $response->errorCode->details : "Error"
                )
            )
        );
        return;
    }

    $sensors = json_decode($res->getBody())->contextResponses;

    $tempSensors = array();
    foreach ($sensors as $sensor){
        $tempAttribute = array();
        if(!empty($sensor->contextElement->attributes)){
            foreach ($sensor->contextElement->attributes as $attribute){
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
        array_push($tempSensors, array(
            "id" => $sensor->contextElement->id,
            "type" =>$sensor->contextElement->type,
            "isPattern" => $sensor->contextElement->isPattern,
            "attributes" => $tempAttribute
        ));
    }

    echo json_encode(
        array(
            "GEBEM_BUILDINGS" =>
                $tempSensors
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
 * Rooms route
 * [GET] Get all modules of a room
 */
$app->get('/v1/rooms/{id_r}/modules', function ($id_r) use ($app) {
    $client = new GuzzleHttp\Client();

    $configParams = $this->config->GEBEM->API_CONFIGURATION->params->toArray();

    $showDetails = Util::getBestParamValue("details", "on", $configParams, $_GET) == "on" ? true : false;

    $params =   "?offset=".Util::getBestParamValue("offset", "0", $configParams, $_GET)
        ."&limit=".Util::getBestParamValue("offset", "100", $configParams, $_GET)
        ."&details=".Util::getBestParamValue("details", "off", $configParams, $_GET)
        ."&orderBy=".Util::getBestParamValue("orderBy", "", $configParams, $_GET);

    $moduleTypes = $this->config->GEBEM->API_CONFIGURATION->moduleTypes;
    $entities = array();
    for($i = 0;$i < count($moduleTypes);$i++){
        array_push($entities, array(
            "type" => $moduleTypes[$i],
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
        $resToken = Util::getKeystoneToken($configToken);

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

    $q = Util::getBestParamValue("q", "", $configParams, $_GET);

    try{
        if(empty($q)){
            $load = array(
                'entities' => $entities,
                'attributes' => $attributes,
                'restriction' => [
                    'scopes' => [[
                        'type' => "FIWARE::StringQuery",
                        'value' => $this->config->GEBEM->API_CONFIGURATION->attributes_names->modules."=='".$id_r."'"
                    ]]
                ]
            );
        }else{
            $load = array(
                'entities' => $entities,
                'attributes' => $attributes,
                'restriction' => [
                    'scopes' => [[
                        'type' => "FIWARE::StringQuery",
                        'value' => $q.";".$this->config->GEBEM->API_CONFIGURATION->attributes_names->modules."=='".$id_r."'"
                    ]]
                ]
            );
        }

        $res = $client->post(
            $this->config->GEBEM->ORION_CONFIGURATION->protocol.'://'
            .$this->config->GEBEM->ORION_CONFIGURATION->url.':'
            .$this->config->GEBEM->ORION_CONFIGURATION->port
            .'/v1/queryContext'
            .$params
            , array(
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                    'X-Auth-Token' => $token
                ],
                "json" => $load
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
                    "details" => isset($response->errorCode->details) ? $response->errorCode->details : "Error"
                )
            )
        );
        return;
    }

    $modules = json_decode($res->getBody())->contextResponses;

    $tempModules = array();
    foreach ($modules as $module){
        $tempAttribute = array();
        if(!empty($module->contextElement->attributes)){
            foreach ($module->contextElement->attributes as $attribute){
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
        array_push($tempModules, array(
            "id" => $module->contextElement->id,
            "type" =>$module->contextElement->type,
            "isPattern" => $module->contextElement->isPattern,
            "attributes" => $tempAttribute
        ));
    }

    echo json_encode(
        array(
            "GEBEM_BUILDINGS" =>
                $tempModules
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
 * Sensors route
 * [GET] Get all sensors
 */
$app->get('/v1/sensors', function () use ($app) {

});

/**
 * Sensors route
 * [GET] Get all rooms
 */
$app->get('/v1/sensors/{id_s}', function ($id_s) use ($app) {

});

/**
 * Modules route
 * [GET] Get all modules
 */
$app->get('/v1/modules/', function () use ($app) {

});

/**
 * Modules route
 * [GET] Get one module based on its ID
 */
$app->get('/v1/modules/{id_m}', function ($id_m) use ($app) {

});