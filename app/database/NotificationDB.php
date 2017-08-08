<?php
/**
 * Created by PhpStorm.
 * User: pedro
 * Date: 08/08/2017
 * Time: 14:37
 */

namespace GEBEM\Database;

use Phalcon\Di as Di;
use Phalcon\Db as Db;
use Phalcon\Db\Column as Column;

class NotificationDB implements NotificationDBInteface
{
    protected $db;

    /**
     * NotificationDB constructor.
     */
    public function __construct()
    {
        $this->db = $this->db = Di::getDefault()->get('db');
    }

    public function insertEntity($tableName, $values, $values_names_db){
        $this->db->insert(
            $tableName,
            [$values[0], $values[1], $values[2], $values[3], $values[4], $values[5], $values[6]],
            [$values_names_db[0], $values_names_db[1], $values_names_db[2], $values_names_db[3],$values_names_db[4], $values_names_db[5], $values_names_db[6]]
        );
    }

    public function checkTableExists($tableName, $config){
        return $this->db->tableExists($tableName, $config);
    }

    public function createElementTable($entity){
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