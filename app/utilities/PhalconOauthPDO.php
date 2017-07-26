<?php
/**
 * Created by PhpStorm.
 * User: pedro
 * Date: 26/07/2017
 * Time: 08:35
 *
 * This is an extension of OAuth2\Storage\Pdo to overwrite security methods
 * The Oath2 used default was to save passwords as plain text.
 * Instead of saving as plain text we are going to save using phalcon security.
 * Phalcon security uses BCRYPT hash and makes breaking passwords
 * very difficult if not impossible depending on the workfactor used.
 * The workfactor of the hash can be changed in app/config/services.php
 *
 */

namespace GEBEM\Utilities;

use OAuth2\Storage\Pdo as OPdo;
use Phalcon\Di as Di;

class PhalconOauthPDO extends OPdo
{
    //We need to pass the phalcon di for access to security
    private $di;

    public function checkClientCredentials($client_id, $client_secret = null)
    {
        //This function is not extensible so we just copy and paste from source and change somethings
        $stmt = $this->db->prepare(sprintf('SELECT * from %s where client_id = :client_id', $this->config['client_table']));
        $stmt->execute(compact('client_id'));
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        // make this extensible
        return $result && $this->di->getShared("security")->checkHash($client_secret, $result['client_secret']);
    }

    protected function hashPassword($password)
    {
        return $this->di->getShared("security")->hash($password);
    }

    //Changed SQL to support bigger hashed passwords
    public function getBuildSql($dbName = 'oauth2_server_php')
    {
        $sql = "
        CREATE TABLE {$this->config['client_table']} (
          client_id             VARCHAR(80)   NOT NULL,
          client_secret         VARCHAR(500),
          redirect_uri          VARCHAR(2000),
          grant_types           VARCHAR(80),
          scope                 VARCHAR(4000),
          user_id               VARCHAR(80),
          PRIMARY KEY (client_id)
        );

            CREATE TABLE {$this->config['access_token_table']} (
              access_token         VARCHAR(40)    NOT NULL,
              client_id            VARCHAR(80)    NOT NULL,
              user_id              VARCHAR(80),
              expires              TIMESTAMP      NOT NULL,
              scope                VARCHAR(4000),
              PRIMARY KEY (access_token)
            );

            CREATE TABLE {$this->config['code_table']} (
              authorization_code  VARCHAR(40)    NOT NULL,
              client_id           VARCHAR(80)    NOT NULL,
              user_id             VARCHAR(80),
              redirect_uri        VARCHAR(2000),
              expires             TIMESTAMP      NOT NULL,
              scope               VARCHAR(4000),
              id_token            VARCHAR(1000),
              PRIMARY KEY (authorization_code)
            );

            CREATE TABLE {$this->config['refresh_token_table']} (
              refresh_token       VARCHAR(40)    NOT NULL,
              client_id           VARCHAR(80)    NOT NULL,
              user_id             VARCHAR(80),
              expires             TIMESTAMP      NOT NULL,
              scope               VARCHAR(4000),
              PRIMARY KEY (refresh_token)
            );

            CREATE TABLE {$this->config['user_table']} (
              username            VARCHAR(80),
              password            VARCHAR(500),
              first_name          VARCHAR(80),
              last_name           VARCHAR(80),
              email               VARCHAR(80),
              email_verified      BOOLEAN,
              scope               VARCHAR(4000)
            );

            CREATE TABLE {$this->config['scope_table']} (
              scope               VARCHAR(80)  NOT NULL,
              is_default          BOOLEAN,
              PRIMARY KEY (scope)
            );

            CREATE TABLE {$this->config['jwt_table']} (
              client_id           VARCHAR(80)   NOT NULL,
              subject             VARCHAR(80),
              public_key          VARCHAR(2000) NOT NULL
            );

            CREATE TABLE {$this->config['jti_table']} (
              issuer              VARCHAR(80)   NOT NULL,
              subject             VARCHAR(80),
              audiance            VARCHAR(80),
              expires             TIMESTAMP     NOT NULL,
              jti                 VARCHAR(2000) NOT NULL
            );

            CREATE TABLE {$this->config['public_key_table']} (
              client_id            VARCHAR(80),
              public_key           VARCHAR(2000),
              private_key          VARCHAR(2000),
              encryption_algorithm VARCHAR(100) DEFAULT 'RS256'
            )
        ";

        return $sql;
    }

    public function setDi(Di $di)
    {
        $this->di = $di;
    }

    public function getDi()
    {
        return $this->di;
    }
}