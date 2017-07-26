<?php 

use Phalcon\Db\Column;
use Phalcon\Db\Index;
use Phalcon\Db\Reference;
use Phalcon\Mvc\Model\Migration;

/**
 * Class OauthAuthorizationCodesMigration_101
 */
class OauthAuthorizationCodesMigration_101 extends Migration
{
    /**
     * Define the table structure
     *
     * @return void
     */
    public function morph()
    {
        $this->morphTable('oauth_authorization_codes', array(
                'columns' => array(
                    new Column(
                        'authorization_code',
                        array(
                            'type' => Column::TYPE_VARCHAR,
                            'notNull' => true,
                            'size' => 40,
                            'first' => true
                        )
                    ),
                    new Column(
                        'client_id',
                        array(
                            'type' => Column::TYPE_VARCHAR,
                            'notNull' => true,
                            'size' => 80,
                            'after' => 'authorization_code'
                        )
                    ),
                    new Column(
                        'user_id',
                        array(
                            'type' => Column::TYPE_VARCHAR,
                            'size' => 255,
                            'after' => 'client_id'
                        )
                    ),
                    new Column(
                        'redirect_uri',
                        array(
                            'type' => Column::TYPE_VARCHAR,
                            'size' => 2000,
                            'after' => 'user_id'
                        )
                    ),
                    new Column(
                        'expires',
                        array(
                            'type' => Column::TYPE_TIMESTAMP,
                            'default' => "CURRENT_TIMESTAMP",
                            'notNull' => true,
                            'size' => 1,
                            'after' => 'redirect_uri'
                        )
                    ),
                    new Column(
                        'scope',
                        array(
                            'type' => Column::TYPE_VARCHAR,
                            'size' => 2000,
                            'after' => 'expires'
                        )
                    )
                ),
                'indexes' => array(
                    new Index('PRIMARY', array('authorization_code'), 'PRIMARY')
                ),
                'options' => array(
                    'TABLE_TYPE' => 'BASE TABLE',
                    'AUTO_INCREMENT' => '',
                    'ENGINE' => 'InnoDB',
                    'TABLE_COLLATION' => 'utf8_general_ci'
                ),
            )
        );
    }

    /**
     * Run the migrations
     *
     * @return void
     */
    public function up()
    {

    }

    /**
     * Reverse the migrations
     *
     * @return void
     */
    public function down()
    {

    }

}
