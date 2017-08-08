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

class NotificationController extends Controller
{
    public function notify(){

        $entities = json_decode(file_get_contents('php://input'))->contextResponses;
        date_default_timezone_set('America/Recife');
        foreach ($entities as $entity){
            $tableName = "GEBEM_".$entity->contextElement->id;
            $elementType = $entity->contextElement->type;
            $elementId = $entity->contextElement->id;
            $value_date = date("Y-m-d H:i:s");
            
            $notificationDB = new NotificationDB();

            if(!$notificationDB->checkTableExists($tableName, $this->config->database->dbname)){
                $notificationDB->createElementTable($entity);
            }

            foreach ($entity->contextElement->attributes as $attribute){
                $attr_name = $attribute->name;
                $attr_type = $attribute->type;
                $attr_value = $attribute->value;

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