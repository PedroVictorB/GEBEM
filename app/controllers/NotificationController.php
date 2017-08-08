<?php
/**
 * Created by PhpStorm.
 * User: pedro
 * Date: 04/08/2017
 * Time: 09:20
 */

namespace GEBEM\Controllers;

use Phalcon\Mvc\Controller as Controller;
use Phalcon\Db\Column as Column;

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

            if(!$this->db->tableExists($tableName, $this->config->database->dbname)){
                $this->createElementTable($entity);
            }

            foreach ($entity->contextElement->attributes as $attribute){
                $attr_name = $attribute->name;
                $attr_type = $attribute->type;
                $attr_value = $attribute->value;

//                $sql = "INSERT INTO $tableName (id, element_type, element_id, attr_name, attr_type, attr_value, value_date)
//                        VALUES null, '$elementType', '$elementId', '$attr_name', '$attr_type', '$attr_value', '$value_date'";

                $this->db->insert(
                    $tableName,
                    [null, $elementType, $elementId, $attr_name, $attr_type, $attr_value, $value_date],
                    ['id', 'element_type', 'element_id', 'attr_name', 'attr_type', 'attr_value', 'value_date']
                );
                
            }

        }

    }

    private function createElementTable($entity){
        $this->db->createTable("GEBEM_".$entity->contextElement->id, $this->config->database->dbname, [
            'columns' => [
                new Column(
                    'id',
                    [
                        'type'          => Column::TYPE_INTEGER,
                        'notNull'       => true,
                        'autoIncrement' => true,
                        'primary'       => true,
                    ]
                ),
                new Column(
                    'element_type',
                    [
                        'type'    => Column::TYPE_VARCHAR,
                        'size'    => 100,
                        'notNull' => true,
                    ]
                ),
                new Column(
                    'element_id',
                    [
                        'type'    => Column::TYPE_VARCHAR,
                        'size'    => 500,
                        'notNull' => true,
                    ]
                ),
                new Column(
                    'attr_name',
                    [
                        'type'    => Column::TYPE_VARCHAR,
                        'size'    => 500,
                        'notNull' => true,
                    ]
                ),
                new Column(
                    'attr_type',
                    [
                        'type'    => Column::TYPE_VARCHAR,
                        'size'    => 100,
                        'notNull' => true,
                    ]
                ),
                new Column(
                    'attr_value',
                    [
                        'type'    => Column::TYPE_VARCHAR,
                        'size'    => 100,
                        'notNull' => true,
                    ]
                ),
                new Column(
                    'value_date',
                    [
                        'type'    => Column::TYPE_DATETIME,
                        'notNull' => true,
                    ]
                ),
            ]
        ]);
    }
}