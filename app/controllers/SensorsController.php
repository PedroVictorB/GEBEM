<?php

/**
 * Created by PhpStorm.
 * User: pedro
 * Date: 25/07/2017
 * Time: 12:31
 */

namespace GEBEM\Controllers;

use GEBEM\Utilities\Util as Util;
use Phalcon\Mvc\Controller as Controller;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Exception\RequestException as GuzzleRequestException;

class SensorsController extends Controller
{
    public function getSensors(){
        header('Content-Type: application/json');
        $client = new GuzzleClient();

        $configParams = $this->config->GEBEM->API_CONFIGURATION->params->toArray();

        $showDetails = Util::getBestParamValue("details", "on", $configParams, $_GET) == "on" ? true : false;

        $params =   "?offset=".Util::getBestParamValue("offset", "0", $configParams, $_GET)
            ."&limit=".Util::getBestParamValue("limit", "100", $configParams, $_GET)
            ."&details=".Util::getBestParamValue("details", "off", $configParams, $_GET)
            ."&orderBy=".Util::getBestParamValue("orderBy", "foo", $configParams, $_GET);

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
                "GEBEM_MODULES" =>
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
    }

    public function getOneSensor($id_s){
        header('Content-Type: application/json');
        $client = new GuzzleClient();

        $configParams = $this->config->GEBEM->API_CONFIGURATION->params->toArray();

        $showDetails = Util::getBestParamValue("details", "on", $configParams, $_GET) == "on" ? true : false;

        $params =   "?offset=".Util::getBestParamValue("offset", "0", $configParams, $_GET)
            ."&limit=".Util::getBestParamValue("limit", "100", $configParams, $_GET)
            ."&details=".Util::getBestParamValue("details", "off", $configParams, $_GET)
            ."&orderBy=".Util::getBestParamValue("orderBy", "foo", $configParams, $_GET);

        $sensorTypes = $this->config->GEBEM->API_CONFIGURATION->sensorTypes;
        $entities = array();
        for($i = 0;$i < count($sensorTypes);$i++){
            array_push($entities, array(
                "type" => $sensorTypes[$i],
                "isPattern" => true,
                "id" => $id_s
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
                "GEBEM_MODULES" =>
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
    }

}