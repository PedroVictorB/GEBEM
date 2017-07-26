<?php

namespace GEBEM\Models;

use Phalcon\Mvc\Model as Model;

class OauthClients extends Model
{

    /**
     *
     * @var string
     */
    public $client_id;

    /**
     *
     * @var string
     */
    public $client_secret;

    /**
     *
     * @var string
     */
    public $redirect_uri;

    /**
     *
     * @var string
     */
    public $grant_types;

    /**
     *
     * @var string
     */
    public $scope;

    /**
     *
     * @var string
     */
    public $user_id;

    /**
     * Returns table name mapped in the model.
     *
     * @return string
     */
    public function getSource()
    {
        return 'oauth_clients';
    }

    /**
     * Allows to query a set of records that match the specified conditions
     *
     * @param mixed $parameters
     * @return OauthClients[]
     */
    public static function find($parameters = null)
    {
        return parent::find($parameters);
    }

    /**
     * Allows to query the first record that match the specified conditions
     *
     * @param mixed $parameters
     * @return OauthClients
     */
    public static function findFirst($parameters = null)
    {
        return parent::findFirst($parameters);
    }

}
