<?php

/**
 * Created by PhpStorm.
 * User: pedro
 * Date: 08/08/2017
 * Time: 13:53
 */

namespace GEBEM\Database;

use Phalcon\Di as Di;
use Phalcon\Db as Db;
use Phalcon\Db\Column as Column;

class EnergyDB implements EnergyDBInterface
{
    protected $db;

    /**
     * EnergyDB constructor.
     */
    public function __construct()
    {
        $this->db = Di::getDefault()->get('db');
    }

    /**
     * @param $id_m
     * @param $tableName
     * @param $attrn
     * @param $datef
     * @param $datet
     * @return mixed
     *
     * Receive the id of the module along with the name of the table, attribute to serach for consumption and the date from and to
     *
     * Returns all the data from the table
     *
     * @return object
     *
     */
    public function getModuleData($id_m, $tableName, $attrn, $datef, $datet){
        $result = $this->db->query(
            "SELECT * FROM $tableName WHERE element_id = :id AND attr_name = :attrn AND value_date BETWEEN :datef AND :datet",
            [
                'id' => $id_m,
                'attrn' => $attrn,
                'datef' => $datef,
                'datet' => $datet
            ]
        );

        $result->setFetchMode(Db::FETCH_OBJ);
        return $result->fetchAll();
    }

    /**
     * @param $id_m
     * @param $tableName
     * @param $attrn
     * @param $datef
     * @param $datet
     * @return mixed
     *
     * Receive the id of the module along with the name of the table, attribute to serach for consumption and the date from and to
     *
     * Returns a float with all the data
     *
     * @return float
     *
     */
    public function getModuleEnergySumTotal($id_m, $tableName, $attrn, $datef, $datet){
        return $this->db->query(
            "SELECT COALESCE(SUM(attr_value), 0) as total FROM $tableName WHERE element_id = :id AND attr_name = :attrn AND value_date BETWEEN :datef AND :datet",
            [
                'id' => $id_m,
                'attrn' => $attrn,
                'datef' => $datef,
                'datet' => $datet
            ]
        )->fetchAll()[0]['total'];
    }

    /**
     * @param $tableName
     * @param $dbname
     * @return mixed
     *
     * Check if table exists
     *
     * @return boolean
     */
    public function checkTableExists($tableName, $dbname){
        return $this->db->tableExists($tableName, $dbname);
    }

    /**
     * @param $entity
     * @param $dbname
     * @return mixed
     *
     * Create entity table
     *
     * @return boolean
     */
    public function createElementTable($entity, $dbname){
        return $this->db->createTable("GEBEM_".$entity->contextElement->id, $dbname, [
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