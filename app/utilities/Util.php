<?php

/**
 * Created by PhpStorm.
 * User: pedro
 * Date: 13/06/2017
 * Time: 13:19
 */

namespace GEBEM\Utilities;

class Util
{

    /**
     * @param String $key
     * @param String $defaultValue
     * @param array $config
     * @param array $get
     *
     * Find the best value for the given key
     * Ranking: GET > CONFIG > defaultValue
     *
     * @return String
     */
    public static function getBestParamValue(String $key, String $defaultValue, array $config = null, array $get){
        if(!empty($get[$key])){
            return $get[$key];
        }
        else if (!empty($config[$key])){
            return $config[$key];
        }
        else {
            return $defaultValue;
        }
    }

}