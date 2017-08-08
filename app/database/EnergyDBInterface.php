<?php

/**
 * Created by PhpStorm.
 * User: pedro
 * Date: 08/08/2017
 * Time: 13:53
 */

namespace GEBEM\Database;

interface EnergyDBInterface
{
    public function getModuleData($id_m, $tableName, $attrn, $datef, $datet);
    public function getModuleEnergySumTotal($id_m, $tableName, $attrn, $datef, $datet);
}