<?php
/**
 * Created by PhpStorm.
 * User: pedro
 * Date: 04/08/2017
 * Time: 09:19
 */

namespace GEBEM\Controllers;

use GEBEM\Utilities\Util as Util;
use Phalcon\Mvc\Controller as Controller;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Exception\RequestException as GuzzleRequestException;
use Phalcon\Db as Db;

class EnergyController extends Controller
{
    public function getBuildings(){
        
    }

    public function getOneBuilding($id_b){

    }

    public function getRooms(){

    }

    public function getOneRoom($id_r){

    }

    public function getModules(){
        $client = new GuzzleClient();

        $configParams = $this->config->GEBEM->API_CONFIGURATION->params->toArray();

        $showDetails = Util::getBestParamValue("details", "on", $configParams, $_GET) == "on" ? true : false;

        $params =   "?offset=".Util::getBestParamValue("from", "0", $configParams, $_GET)
            ."&limit=".Util::getBestParamValue("limit", "100", $configParams, $_GET)
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

        foreach ($modules as $module){
            $tableName = "GEBEM_".$module->contextElement->id;
            
            $sumtotal = $this->db->query(
                "SELECT COALESCE(SUM(attr_value), 0) as total FROM $tableName WHERE element_id = :id AND attr_name = :attrn AND value_date BETWEEN :datef AND :datet",
                [
                    'id' => $module->contextElement->id,
                    'attrn' => "Consumption",
                    'datef' => $datef,
                    'datet' => $datet
                ]
            )->fetchAll()[0];

            $result = $this->db->query(
                "SELECT * FROM $tableName WHERE element_id = :id AND attr_name = :attrn AND value_date BETWEEN :datef AND :datet",
                [
                    'id' => $module->contextElement->id,
                    'attrn' => "Consumption",
                    'datef' => $datef,
                    'datet' => $datet
                ]
            );
            $result->setFetchMode(Db::FETCH_OBJ);
            $elements = $result->fetchAll();
            $h = array();
            foreach ($elements as $element){
                array_push($h, array(
                    'date' => $element->value_date,
                    'value' => $element->attr_value
                ));
            }

            array_push($energyModules, array(
                "id" => $module->contextElement->id,
                "type" =>$module->contextElement->type,
                "total_consumption" => $sumtotal['total'],
                "from" => $module->contextElement->isPattern,
                "to" => $module->contextElement->isPattern,
                "historical_values" => $h
            ));
        }

        echo json_encode(
            array(
                "GEBEM_ENERGY" =>
                    $energyModules
            ,
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
            ."&orderBy=".Util::getBestParamValue("orderBy", "", $configParams, $_GET);

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

        foreach ($modules as $module){
            $tableName = "GEBEM_".$module->contextElement->id;

            $sumtotal = $this->db->query(
                "SELECT COALESCE(SUM(attr_value), 0) as total FROM $tableName WHERE element_id = :id AND attr_name = :attrn AND value_date BETWEEN :datef AND :datet",
                [
                    'id' => $module->contextElement->id,
                    'attrn' => "Consumption",
                    'datef' => $datef,
                    'datet' => $datet
                ]
            )->fetchAll()[0];

            $result = $this->db->query(
                "SELECT * FROM $tableName WHERE element_id = :id AND attr_name = :attrn AND value_date BETWEEN :datef AND :datet",
                [
                    'id' => $module->contextElement->id,
                    'attrn' => "Consumption",
                    'datef' => $datef,
                    'datet' => $datet
                ]
            );
            $result->setFetchMode(Db::FETCH_OBJ);
            $elements = $result->fetchAll();
            $h = array();
            foreach ($elements as $element){
                array_push($h, array(
                    'date' => $element->value_date,
                    'value' => $element->attr_value
                ));
            }

            array_push($energyModules, array(
                "id" => $module->contextElement->id,
                "type" =>$module->contextElement->type,
                "total_consumption" => $sumtotal['total'],
                "from" => $module->contextElement->isPattern,
                "to" => $module->contextElement->isPattern,
                "historical_values" => $h
            ));
        }

        echo json_encode(
            array(
                "GEBEM_ENERGY" =>
                    $energyModules
            ,
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