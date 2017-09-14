<?php
/**
 * Created by PhpStorm.
 * User: pedro
 * Date: 04/08/2017
 * Time: 09:19
 */

namespace GEBEM\Controllers;

use GEBEM\Database\EnergyDB;
use GEBEM\Utilities\Util as Util;
use Phalcon\Mvc\Controller as Controller;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Exception\RequestException as GuzzleRequestException;
use Phalcon\Db as Db;

class EnergyController extends Controller
{
    public function getBuildings(){
        $client = new GuzzleClient();

        $configParams = $this->config->GEBEM->API_CONFIGURATION->params->toArray();

        $showDetails = Util::getBestParamValue("details", "on", $configParams, $_GET) == "on" ? true : false;

        $params =   "?offset=".Util::getBestParamValue("offset", "0", $configParams, $_GET)
            ."&limit=".Util::getBestParamValue("limit", "100", $configParams, $_GET)
            ."&details=".Util::getBestParamValue("details", "off", $configParams, $_GET)
            ."&orderBy=".Util::getBestParamValue("orderBy", "foo", $configParams, $_GET);

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
        }catch (GuzzleRequestException $e){
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

        $configAttributes = $this->config->GEBEM->API_CONFIGURATION->attributes_names->toArray();
        $roomQuery = Util::getBestParamValue("rooms", "Sala", $configAttributes, array())."==";
        $count = 0;
        $predios = array();
        foreach ($buildings as $building){
            if($count == 0){
                $roomQuery .= "'".$building->contextElement->id."'";
            }else{
                $roomQuery .= ",'".$building->contextElement->id."'";
            }
            $count++;
            array_push($predios, array(
                "id" => $building->contextElement->id,
                "total_consumption" => 0,
                "salas" => array()
            ));
        }

        $roomTypes = $this->config->GEBEM->API_CONFIGURATION->roomTypes;
        $entities = array();
        for($i = 0;$i < count($roomTypes);$i++){
            array_push($entities, array(
                "type" => $roomTypes[$i],
                "isPattern" => true,
                "id" => ".*"
            ));
        }

        try{
            $load = array(
                'entities' => $entities,
                'attributes' => array(),
                'restriction' => [
                    'scopes' => [[
                        'type' => "FIWARE::StringQuery",
                        'value' => $roomQuery
                    ]]
                ]
            );

            $res = $client->post(
                $this->config->GEBEM->ORION_CONFIGURATION->protocol.'://'
                .$this->config->GEBEM->ORION_CONFIGURATION->url.':'
                .$this->config->GEBEM->ORION_CONFIGURATION->port
                .'/v1/queryContext'
                , array(
                    'headers' => [
                        'Content-Type' => 'application/json',
                        'Accept' => 'application/json',
                        'X-Auth-Token' => $token
                    ],
                    "json" => $load
                )

            );
        }catch (GuzzleRequestException $e){
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
        $configAttributes = $this->config->GEBEM->API_CONFIGURATION->attributes_names->toArray();
        $moduleQuery = Util::getBestParamValue("modules", "Sala", $configAttributes, array())."==";
        $count = 0;
        $salas = array();
        foreach ($rooms as $room){
            if($count == 0){
                $moduleQuery .= "'".$room->contextElement->id."'";
            }else{
                $moduleQuery .= ",'".$room->contextElement->id."'";
            }
            $count++;
            array_push($salas, array(
                "id" => $room->contextElement->id,
                "total_consumption" => 0,
                "modules" => array()
            ));
        }

        $moduleTypes = $this->config->GEBEM->API_CONFIGURATION->moduleTypes;
        $entities = array();
        for($i = 0;$i < count($moduleTypes);$i++){
            array_push($entities, array(
                "type" => $moduleTypes[$i],
                "isPattern" => true,
                "id" => ".*"
            ));
        }

        try{
            $load = array(
                'entities' => $entities,
                'attributes' => array(),
                'restriction' => [
                    'scopes' => [[
                        'type' => "FIWARE::StringQuery",
                        'value' => $moduleQuery
                    ]]
                ]
            );

            $res = $client->post(
                $this->config->GEBEM->ORION_CONFIGURATION->protocol.'://'
                .$this->config->GEBEM->ORION_CONFIGURATION->url.':'
                .$this->config->GEBEM->ORION_CONFIGURATION->port
                .'/v1/queryContext'
                , array(
                    'headers' => [
                        'Content-Type' => 'application/json',
                        'Accept' => 'application/json',
                        'X-Auth-Token' => $token
                    ],
                    "json" => $load
                )

            );
        }catch (GuzzleRequestException $e){
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

        date_default_timezone_set('America/Recife');
        if(isset($_GET['from'])){
            $_GET['from'] = date("Y-m-d H:i:s", strtotime(str_replace('/', '-', $_GET['from'])));
        }
        if(isset($_GET['to'])){
            $_GET['to'] = date("Y-m-d H:i:s", strtotime(str_replace('/', '-', $_GET['to'])));
        }
        $datef = Util::getBestParamValue("from", (new \DateTime())->modify('-24 hours')->format('Y-m-d H:i:s'), array(), $_GET);
        $datet = Util::getBestParamValue("to", (new \DateTime())->format('Y-m-d H:i:s'), array(), $_GET);
        $energyDB = new EnergyDB();

        $predios_r = array();
        foreach ($predios as $predio){
            $salas_r = array();
            $totalConsumptionP = 0;
            foreach ($rooms as $room){
                $energyModules = array();
                $totalConsumption = 0;

                $idpredio = "";
                foreach ($room->contextElement->attributes as $attribute){
                    if(Util::getBestParamValue("rooms", "Sala", $configAttributes, array()) == $attribute->name){
                        $idpredio = $attribute->value;
                    }
                }
                if($predio["id"] != $idpredio){
                    continue;
                }

                foreach ($modules as $module){

                    $idsala = "";
                    foreach ($module->contextElement->attributes as $attribute){
                        if(Util::getBestParamValue("modules", "Sala", $configAttributes, array()) == $attribute->name){
                            $idsala = $attribute->value;
                        }
                    }

                    if($room->contextElement->id != $idsala){
                        continue;
                    }

                    $tableName = "GEBEM_".$module->contextElement->id;
                    if(!$energyDB->checkTableExists($tableName, $this->config->database->dbname)){
                        $energyDB->createElementTable($module, $this->config->database->dbname);
                    }

                    $sumtotal = $energyDB->getModuleEnergySumTotal($module->contextElement->id, $tableName, "Consumption", $datef, $datet);

                    $elements = $energyDB->getModuleData($module->contextElement->id, $tableName, "Consumption", $datef, $datet);

                    $h = array();
                    foreach ($elements as $element){
                        array_push($h, array(
                            'date' => $element->value_date,
                            'value' => $element->attr_value
                        ));
                    }

                    $totalConsumption += $sumtotal;

                    array_push($energyModules, array(
                        "id" => $module->contextElement->id,
                        "type" =>$module->contextElement->type,
                        "total_consumption" => $sumtotal,
                        "historical_values" => $h
                    ));

                }

                $totalConsumptionP += $totalConsumption;
                $sala['id'] = $room->contextElement->id;
                $sala['total_consumption'] = $totalConsumption;
                $sala['modules'] = $energyModules;

                array_push($salas_r, $sala);
            }
            $predio['total_consumption'] = $totalConsumptionP;
            $predio['salas'] = $salas_r;

            array_push($predios_r, $predio);
        }

        echo json_encode(
            array(
                "GEBEM_ROOMS" =>
                    $predios_r,
                "GEBEM_STATUS" =>
                    array(
                        "code" => "200",
                        "reasonPhrase" => "OK",
                        "details" => $showDetails && isset($response->errorCode->details) ? $response->errorCode->details : ""
                    )
            )
        );
    }

    public function getOneBuilding($id_b){
        $client = new GuzzleClient();

        $configParams = $this->config->GEBEM->API_CONFIGURATION->params->toArray();

        $showDetails = Util::getBestParamValue("details", "on", $configParams, $_GET) == "on" ? true : false;

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

        try{
            $configAttribute = $this->config->GEBEM->API_CONFIGURATION->attributes_names->toArray();

            $load = array(
                'entities' => $entities,
                'attributes' => $attributes,
                'restriction' => [
                    'scopes' => [[
                        'type' => "FIWARE::StringQuery",
                        'value' => Util::getBestParamValue("rooms", "Predio", $configAttribute, array())."=='".$id_b."'"
                    ]]
                ]
            );

            $res = $client->post(
                $this->config->GEBEM->ORION_CONFIGURATION->protocol.'://'
                .$this->config->GEBEM->ORION_CONFIGURATION->url.':'
                .$this->config->GEBEM->ORION_CONFIGURATION->port
                .'/v1/queryContext'
                , array(
                    'headers' => [
                        'Content-Type' => 'application/json',
                        'Accept' => 'application/json',
                        'X-Auth-Token' => $token
                    ],
                    "json" => $load
                )

            );
        }catch (GuzzleRequestException $e){
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
        $configAttributes = $this->config->GEBEM->API_CONFIGURATION->attributes_names->toArray();
        $moduleQuery = Util::getBestParamValue("modules", "Sala", $configAttributes, array())."==";
        $count = 0;
        $salas = array();
        foreach ($rooms as $room){
            if($count == 0){
                $moduleQuery .= "'".$room->contextElement->id."'";
            }else{
                $moduleQuery .= ",'".$room->contextElement->id."'";
            }
            $count++;
            array_push($salas, array(
                "id" => $room->contextElement->id,
                "total_consumption" => 0,
                "modules" => array()
            ));
        }

        $moduleTypes = $this->config->GEBEM->API_CONFIGURATION->moduleTypes;
        $entities = array();
        for($i = 0;$i < count($moduleTypes);$i++){
            array_push($entities, array(
                "type" => $moduleTypes[$i],
                "isPattern" => true,
                "id" => ".*"
            ));
        }

        try{
            $load = array(
                'entities' => $entities,
                'attributes' => array(),
                'restriction' => [
                    'scopes' => [[
                        'type' => "FIWARE::StringQuery",
                        'value' => $moduleQuery
                    ]]
                ]
            );

            $res = $client->post(
                $this->config->GEBEM->ORION_CONFIGURATION->protocol.'://'
                .$this->config->GEBEM->ORION_CONFIGURATION->url.':'
                .$this->config->GEBEM->ORION_CONFIGURATION->port
                .'/v1/queryContext'
                , array(
                    'headers' => [
                        'Content-Type' => 'application/json',
                        'Accept' => 'application/json',
                        'X-Auth-Token' => $token
                    ],
                    "json" => $load
                )

            );
        }catch (GuzzleRequestException $e){
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

        date_default_timezone_set('America/Recife');
        if(isset($_GET['from'])){
            $_GET['from'] = date("Y-m-d H:i:s", strtotime(str_replace('/', '-', $_GET['from'])));
        }
        if(isset($_GET['to'])){
            $_GET['to'] = date("Y-m-d H:i:s", strtotime(str_replace('/', '-', $_GET['to'])));
        }
        $datef = Util::getBestParamValue("from", (new \DateTime())->modify('-24 hours')->format('Y-m-d H:i:s'), array(), $_GET);
        $datet = Util::getBestParamValue("to", (new \DateTime())->format('Y-m-d H:i:s'), array(), $_GET);
        $energyDB = new EnergyDB();
        $totalConsumption = 0;
        $salas_r = array();
        foreach ($salas as $sala){
            $energyModules = array();
            $totalConsumption = 0;
            foreach ($modules as $module){

                $idsala = "";
                foreach ($module->contextElement->attributes as $attribute){
                    if(Util::getBestParamValue("modules", "Sala", $configAttributes, array()) == $attribute->name){
                        $idsala = $attribute->value;
                    }
                }

                if($sala["id"] != $idsala){
                    continue;
                }

                $tableName = "GEBEM_".$module->contextElement->id;

                if(!$energyDB->checkTableExists($tableName, $this->config->database->dbname)){
                    $energyDB->createElementTable($module, $this->config->database->dbname);
                }

                $sumtotal = $energyDB->getModuleEnergySumTotal($module->contextElement->id, $tableName, "Consumption", $datef, $datet);

                $elements = $energyDB->getModuleData($module->contextElement->id, $tableName, "Consumption", $datef, $datet);

                $h = array();
                foreach ($elements as $element){
                    array_push($h, array(
                        'date' => $element->value_date,
                        'value' => $element->attr_value
                    ));
                }

                $totalConsumption += $sumtotal;

                array_push($energyModules, array(
                    "id" => $module->contextElement->id,
                    "type" =>$module->contextElement->type,
                    "total_consumption" => $sumtotal,
                    "historical_values" => $h
                ));

            }

            $sala['total_consumption'] = $totalConsumption;
            $sala['modules'] = $energyModules;

            array_push($salas_r, $sala);
        }

        echo json_encode(
            array(
                "GEBEM_ROOMS" =>
                    array(
                        "id" => $id_b,
                        "total_consumption" => $totalConsumption,
                        "salas" => $salas_r
                    ),
                "GEBEM_STATUS" =>
                    array(
                        "code" => "200",
                        "reasonPhrase" => "OK",
                        "details" => $showDetails && isset($response->errorCode->details) ? $response->errorCode->details : ""
                    )
            )
        );
    }

    public function getRooms(){
        $client = new GuzzleClient();

        $configParams = $this->config->GEBEM->API_CONFIGURATION->params->toArray();

        $showDetails = Util::getBestParamValue("details", "on", $configParams, $_GET) == "on" ? true : false;

        $buildingsTypes = $this->config->GEBEM->API_CONFIGURATION->buildingTypes;
        $entities = array();
        for($i = 0;$i < count($buildingsTypes);$i++){
            array_push($entities, array(
                "type" => $buildingsTypes[$i],
                "isPattern" => true,
                "id" => ".*"
            ));
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

        try{
            $load = array(
                'entities' => $entities,
                'attributes' => array()
            );

            $res = $client->post(
                $this->config->GEBEM->ORION_CONFIGURATION->protocol.'://'
                .$this->config->GEBEM->ORION_CONFIGURATION->url.':'
                .$this->config->GEBEM->ORION_CONFIGURATION->port
                .'/v1/queryContext'
                , array(
                    'headers' => [
                        'Content-Type' => 'application/json',
                        'Accept' => 'application/json',
                        'X-Auth-Token' => $token
                    ],
                    "json" => $load
                )

            );
        }catch (GuzzleRequestException $e){
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
        $configAttributes = $this->config->GEBEM->API_CONFIGURATION->attributes_names->toArray();

        $buildingQuery = Util::getBestParamValue("rooms", "Predio", $configAttributes, array())."==";
        $count = 0;
        foreach ($buildings as $building){
            if($count == 0){
                $buildingQuery .= "'".$building->contextElement->id."'";
            }else{
                $buildingQuery .= ",'".$building->contextElement->id."'";
            }
            $count++;
        }

        $params =   "?offset=".Util::getBestParamValue("offset", "0", $configParams, $_GET)
            ."&limit=".Util::getBestParamValue("limit", "100", $configParams, $_GET)
            ."&details=".Util::getBestParamValue("details", "off", $configParams, $_GET)
            ."&orderBy=".Util::getBestParamValue("orderBy", "foo", $configParams, $_GET);

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
                            'value' => $buildingQuery
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
                            'value' => $q.";".$buildingQuery
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
        }catch (GuzzleRequestException $e){
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

        $moduleQuery = Util::getBestParamValue("modules", "Sala", $configAttributes, array())."==";
        $count = 0;
        $salas = array();
        foreach ($rooms as $room){
            if($count == 0){
                $moduleQuery .= "'".$room->contextElement->id."'";
            }else{
                $moduleQuery .= ",'".$room->contextElement->id."'";
            }
            $count++;
            array_push($salas, array(
                "id" => $room->contextElement->id,
                "total_consumption" => 0,
                "modules" => array()
            ));
        }

        $moduleTypes = $this->config->GEBEM->API_CONFIGURATION->moduleTypes;
        $entities = array();
        for($i = 0;$i < count($moduleTypes);$i++){
            array_push($entities, array(
                "type" => $moduleTypes[$i],
                "isPattern" => true,
                "id" => ".*"
            ));
        }

        try{
            $load = array(
                'entities' => $entities,
                'attributes' => array(),
                'restriction' => [
                    'scopes' => [[
                        'type' => "FIWARE::StringQuery",
                        'value' => $moduleQuery
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
        }catch (GuzzleRequestException $e){
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

        date_default_timezone_set('America/Recife');
        if(isset($_GET['from'])){
            $_GET['from'] = date("Y-m-d H:i:s", strtotime(str_replace('/', '-', $_GET['from'])));
        }
        if(isset($_GET['to'])){
            $_GET['to'] = date("Y-m-d H:i:s", strtotime(str_replace('/', '-', $_GET['to'])));
        }
        $datef = Util::getBestParamValue("from", (new \DateTime())->modify('-24 hours')->format('Y-m-d H:i:s'), array(), $_GET);
        $datet = Util::getBestParamValue("to", (new \DateTime())->format('Y-m-d H:i:s'), array(), $_GET);
        $energyDB = new EnergyDB();

        $salas_r = array();
        foreach ($salas as $sala){
            $energyModules = array();
            $totalConsumption = 0;
            foreach ($modules as $module){

                $idsala = "";
                foreach ($module->contextElement->attributes as $attribute){
                    if(Util::getBestParamValue("modules", "Sala", $configAttributes, array()) == $attribute->name){
                        $idsala = $attribute->value;
                    }
                }

                if($sala["id"] != $idsala){
                    continue;
                }

                $tableName = "GEBEM_".$module->contextElement->id;

                if(!$energyDB->checkTableExists($tableName, $this->config->database->dbname)){
                    $energyDB->createElementTable($module, $this->config->database->dbname);
                }

                $sumtotal = $energyDB->getModuleEnergySumTotal($module->contextElement->id, $tableName, "Consumption", $datef, $datet);

                $elements = $energyDB->getModuleData($module->contextElement->id, $tableName, "Consumption", $datef, $datet);

                $h = array();
                foreach ($elements as $element){
                    array_push($h, array(
                        'date' => $element->value_date,
                        'value' => $element->attr_value
                    ));
                }

                $totalConsumption += $sumtotal;

                array_push($energyModules, array(
                    "id" => $module->contextElement->id,
                    "type" =>$module->contextElement->type,
                    "total_consumption" => $sumtotal,
                    "historical_values" => $h
                ));

            }

            $sala['total_consumption'] = $totalConsumption;
            $sala['modules'] = $energyModules;

            array_push($salas_r, $sala);
        }

        echo json_encode(
            array(
                "GEBEM_ROOMS" =>
                    $salas_r,
                "GEBEM_STATUS" =>
                    array(
                        "code" => "200",
                        "reasonPhrase" => "OK",
                        "details" => $showDetails && isset($response->errorCode->details) ? $response->errorCode->details : ""
                    )
            )
        );
    }

    public function getOneRoom($id_r){
        $client = new GuzzleClient();

        $configParams = $this->config->GEBEM->API_CONFIGURATION->params->toArray();

        $showDetails = Util::getBestParamValue("details", "on", $configParams, $_GET) == "on" ? true : false;

        $moduleTypes = $this->config->GEBEM->API_CONFIGURATION->moduleTypes;
        $entities = array();
        for($i = 0;$i < count($moduleTypes);$i++){
            array_push($entities, array(
                "type" => $moduleTypes[$i],
                "isPattern" => true,
                "id" => ".*"
            ));
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

        try{
            $configAttributes = $this->config->GEBEM->API_CONFIGURATION->attributes_names->toArray();

            $load = array(
                'entities' => $entities,
                'attributes' => array(),
                'restriction' => [
                    'scopes' => [[
                        'type' => "FIWARE::StringQuery",
                        'value' => Util::getBestParamValue("modules", "Sala", $configAttributes, array())."=='".$id_r."'"
                    ]]
                ]
            );

            $res = $client->post(
                $this->config->GEBEM->ORION_CONFIGURATION->protocol.'://'
                .$this->config->GEBEM->ORION_CONFIGURATION->url.':'
                .$this->config->GEBEM->ORION_CONFIGURATION->port
                .'/v1/queryContext'
                , array(
                    'headers' => [
                        'Content-Type' => 'application/json',
                        'Accept' => 'application/json',
                        'X-Auth-Token' => $token
                    ],
                    "json" => $load
                )

            );
        }catch (GuzzleRequestException $e){
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

        date_default_timezone_set('America/Recife');
        if(isset($_GET['from'])){
            $_GET['from'] = date("Y-m-d H:i:s", strtotime(str_replace('/', '-', $_GET['from'])));
        }
        if(isset($_GET['to'])){
            $_GET['to'] = date("Y-m-d H:i:s", strtotime(str_replace('/', '-', $_GET['to'])));
        }
        $energyModules = array();
        $datef = Util::getBestParamValue("from", (new \DateTime())->modify('-24 hours')->format('Y-m-d H:i:s'), array(), $_GET);
        $datet = Util::getBestParamValue("to", (new \DateTime())->format('Y-m-d H:i:s'), array(), $_GET);
        $energyDB = new EnergyDB();

        $totalConsumption = 0;

        foreach ($modules as $module){
            $tableName = "GEBEM_".$module->contextElement->id;

            if(!$energyDB->checkTableExists($tableName, $this->config->database->dbname)){
                $energyDB->createElementTable($module, $this->config->database->dbname);
            }

            $sumtotal = $energyDB->getModuleEnergySumTotal($module->contextElement->id, $tableName, "Consumption", $datef, $datet);

            $elements = $energyDB->getModuleData($module->contextElement->id, $tableName, "Consumption", $datef, $datet);

            $h = array();
            foreach ($elements as $element){
                array_push($h, array(
                    'date' => $element->value_date,
                    'value' => $element->attr_value
                ));
            }

            $totalConsumption += $sumtotal;

            array_push($energyModules, array(
                "id" => $module->contextElement->id,
                "type" =>$module->contextElement->type,
                "total_consumption" => $sumtotal,
                "historical_values" => $h
            ));
        }

        $sala = array(
            "id" => $id_r,
            "total_consumption" => $totalConsumption,
            "modules" => $energyModules
        );

        echo json_encode(
            array(
                "GEBEM_ROOMS" =>
                    $sala,
                "GEBEM_STATUS" =>
                    array(
                        "code" => "200",
                        "reasonPhrase" => "OK",
                        "details" => $showDetails && isset($response->errorCode->details) ? $response->errorCode->details : ""
                    )
            )
        );
    }

    public function getModules(){
        $client = new GuzzleClient();

        $configParams = $this->config->GEBEM->API_CONFIGURATION->params->toArray();

        $showDetails = Util::getBestParamValue("details", "on", $configParams, $_GET) == "on" ? true : false;

        $params =   "?offset=".Util::getBestParamValue("offset", "0", $configParams, $_GET)
            ."&limit=".Util::getBestParamValue("limit", "100", $configParams, $_GET)
            ."&details=".Util::getBestParamValue("details", "off", $configParams, $_GET)
            ."&orderBy=".Util::getBestParamValue("orderBy", "foo", $configParams, $_GET);

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
        }catch (GuzzleRequestException $e){
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

        date_default_timezone_set('America/Recife');
        if(isset($_GET['from'])){
            $_GET['from'] = date("Y-m-d H:i:s", strtotime(str_replace('/', '-', $_GET['from'])));
        }
        if(isset($_GET['to'])){
            $_GET['to'] = date("Y-m-d H:i:s", strtotime(str_replace('/', '-', $_GET['to'])));
        }
        $energyModules = array();
        $datef = Util::getBestParamValue("from", (new \DateTime())->modify('-24 hours')->format('Y-m-d H:i:s'), array(), $_GET);
        $datet = Util::getBestParamValue("to", (new \DateTime())->format('Y-m-d H:i:s'), array(), $_GET);
        $energyDB = new EnergyDB();

        $totalConsumption = 0;

        foreach ($modules as $module){
            $tableName = "GEBEM_".$module->contextElement->id;

            if(!$energyDB->checkTableExists($tableName, $this->config->database->dbname)){
                $energyDB->createElementTable($module, $this->config->database->dbname);
            }

            $sumtotal = $energyDB->getModuleEnergySumTotal($module->contextElement->id, $tableName, "Consumption", $datef, $datet);

            $elements = $energyDB->getModuleData($module->contextElement->id, $tableName, "Consumption", $datef, $datet);
            
            $h = array();
            foreach ($elements as $element){
                array_push($h, array(
                    'date' => $element->value_date,
                    'value' => $element->attr_value
                ));
            }

            $totalConsumption += $sumtotal;

            array_push($energyModules, array(
                "id" => $module->contextElement->id,
                "type" =>$module->contextElement->type,
                "total_consumption" => $sumtotal,
                "historical_values" => $h
            ));
        }

        echo json_encode(
            array(
                "GEBEM_ENERGY" =>
                    array(
                        'total_consumption' => $totalConsumption,
                        "from" => $datef,
                        "to" => $datet,
                        'modules' => $energyModules
                    ),
                "GEBEM_STATUS" =>
                    array(
                        "code" => "200",
                        "reasonPhrase" => "OK",
                        "details" => $showDetails && isset($response->errorCode->details) ? $response->errorCode->details : ""
                    )
            )
        );
    }

    public function getOneModule($id_m){
        $client = new GuzzleClient();

        $configParams = $this->config->GEBEM->API_CONFIGURATION->params->toArray();

        $showDetails = Util::getBestParamValue("details", "on", $configParams, $_GET) == "on" ? true : false;

        $params =   "?offset=".Util::getBestParamValue("from", "0", $configParams, $_GET)
            ."&limit=".Util::getBestParamValue("limit", "100", $configParams, $_GET)
            ."&details=".Util::getBestParamValue("details", "off", $configParams, $_GET)
            ."&orderBy=".Util::getBestParamValue("orderBy", "foo", $configParams, $_GET);

        $moduleTypes = $this->config->GEBEM->API_CONFIGURATION->moduleTypes;
        $entities = array();
        for($i = 0;$i < count($moduleTypes);$i++){
            array_push($entities, array(
                "type" => $moduleTypes[$i],
                "isPattern" => true,
                "id" => $id_m
            ));
        }

        $attributes = array();

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
        }catch (GuzzleRequestException $e){
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

        date_default_timezone_set('America/Recife');
        $energyModules = array();
        $datef = Util::getBestParamValue("from", (new \DateTime())->modify('-24 hours')->format('Y-m-d H:i:s'), array(), $_GET);
        $datet = Util::getBestParamValue("to", (new \DateTime())->format('Y-m-d H:i:s'), array(), $_GET);
        $energyDB = new EnergyDB();

        $totalConsumption = 0;

        foreach ($modules as $module){
            $tableName = "GEBEM_".$module->contextElement->id;

            if(!$energyDB->checkTableExists($tableName, $this->config->database->dbname)){
                $energyDB->createElementTable($module, $this->config->database->dbname);
            }

            $sumtotal = $energyDB->getModuleEnergySumTotal($module->contextElement->id, $tableName, "Consumption", $datef, $datet);

            $elements = $energyDB->getModuleData($module->contextElement->id, $tableName, "Consumption", $datef, $datet);
            
            $h = array();
            foreach ($elements as $element){
                array_push($h, array(
                    'date' => $element->value_date,
                    'value' => $element->attr_value
                ));
            }

            $totalConsumption += $sumtotal;

            array_push($energyModules, array(
                "id" => $module->contextElement->id,
                "type" =>$module->contextElement->type,
                "total_consumption" => $sumtotal,
                "historical_values" => $h
            ));
        }

        echo json_encode(
            array(
                "GEBEM_ENERGY" => array(
                    'total_consumption' => $totalConsumption,
                    "from" => $datef,
                    "to" => $datet,
                    'modules' => $energyModules
                ),
                "GEBEM_STATUS" =>
                    array(
                        "code" => "200",
                        "reasonPhrase" => "OK",
                        "details" => $showDetails && isset($response->errorCode->details) ? $response->errorCode->details : ""
                    )
            )
        );
    }
}