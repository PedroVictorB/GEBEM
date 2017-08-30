<?php
/**
 * Created by PhpStorm.
 * User: pedro
 * Date: 04/08/2017
 * Time: 09:20
 */

namespace GEBEM\Controllers;

use GEBEM\Database\NotificationDB;
use Phalcon\Mvc\Controller as Controller;
use GEBEM\Utilities\Util as Util;

class NotificationController extends Controller
{
    public function notify(){

        $entities = json_decode(file_get_contents('php://input'))->contextResponses;
        $configParams = $this->config->GEBEM->API_CONFIGURATION->energy_config->toArray();
        date_default_timezone_set('America/Recife');
        $value_date = date("Y-m-d H:i:s");
        $notificationDB = new NotificationDB();

        foreach ($entities as $entity){
            $tableName = "GEBEM_".$entity->contextElement->id;
            $elementType = $entity->contextElement->type;
            $elementId = $entity->contextElement->id;

            if(!$notificationDB->checkTableExists($tableName, $this->config->database->dbname)){
                $notificationDB->createElementTable($entity, $this->config->database->dbname);
            }
            $teste = $entity->contextElement->attributes;
            foreach ($entity->contextElement->attributes as $attribute){
                $attr_name = $attribute->name;
                $attr_type = $attribute->type;
                $attr_value = $attribute->value;

                //If we receive an module off status, calculate the consumption
                if($attr_name == Util::getBestParamValue("on_off", "Status", $configParams, array()) && $attr_value == Util::getBestParamValue("off_value", "OFF", $configParams, array())){
                    $potencia = 0;
                    $date_last = null;
                    foreach ($teste as $attribute_t){
                        if($attribute_t->name == Util::getBestParamValue("potency", "Potencia", $configParams, array())){
                            $potencia = $attribute_t->value;
                            break;
                        }
                    }

                    // If no potency is found in the element, look for the last potency saved in the db
                    if($potencia == 0){
                        $return = $notificationDB->getLastAttr($tableName, Util::getBestParamValue("potency", "Potencia", $configParams, array()));
                        error_log("looking in db for potency");
                        if(!empty($return)){
                            $potencia = $return[0]['attr_value'];
                        }
                    }
                    
                    //If potency is still 0 we print a log :(
                    if($potencia == 0){
                        error_log($elementId." Potency not found :(");
                    }

                    $return = $notificationDB->getLastAttr($tableName, $attr_name);

                    if(!empty($return)){
                        if($return[0]['attr_value'] == Util::getBestParamValue("off_value", "OFF", $configParams, array())){
                            error_log("Last module ON signal was lost.Consumption data on ".$value_date." was not recorded.");
                            break;
                        }
                        $date_last = $return[0]['value_date'];
                    }

                    if($date_last != null){
                        $diff = (strtotime($value_date) - strtotime($date_last)) / (60 * 60);//hours
                        $consumption = $diff * $potencia;

                        $notificationDB->insertEntity(
                            $tableName,
                            [0 => null, 1 => $elementType, 2 => $elementId, 3 => "Consumption", 4 => "float", 5 => $consumption, 6 =>$value_date],
                            [0 => 'id', 1 => 'element_type', 2 => 'element_id', 3 => 'attr_name', 4 => 'attr_type', 5 => 'attr_value', 6 =>'value_date']
                        );
                    }
                }

//                $sql = "INSERT INTO $tableName (id, element_type, element_id, attr_name, attr_type, attr_value, value_date)
//                        VALUES null, '$elementType', '$elementId', '$attr_name', '$attr_type', '$attr_value', '$value_date'";

                $notificationDB->insertEntity(
                    $tableName,
                    [0 => null, 1 => $elementType, 2 => $elementId, 3 => $attr_name, 4 => $attr_type, 5 => $attr_value, 6 =>$value_date],
                    [0 => 'id', 1 => 'element_type', 2 => 'element_id', 3 => 'attr_name', 4 => 'attr_type', 5 => 'attr_value', 6 =>'value_date']
                );
                
            }

        }

    }
}