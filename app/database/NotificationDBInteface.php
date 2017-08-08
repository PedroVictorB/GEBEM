<?php
/**
 * Created by PhpStorm.
 * User: pedro
 * Date: 08/08/2017
 * Time: 14:37
 */

namespace GEBEM\Database;


interface NotificationDBInteface
{
    public function insertEntity($tableName, $values, $values_names_db);
    public function checkTableExists($tableName, $config);
    public function createElementTable($entity);
}