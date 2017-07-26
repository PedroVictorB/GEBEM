<?php 

use Phalcon\Db\Column;
use Phalcon\Db\Index;
use Phalcon\Db\Reference;
use Phalcon\Mvc\Model\Migration;

/**
 * Class OauthClientsMigration_101
 */
class OauthClientsMigration_101 extends Migration
{
    /**
     * Define the table structure
     *
     * @return void
     */
    public function morph()
    {
        $this->morphTable('oauth_clients', array(
                'columns' => array(
                    new Column(
                        'client_id',
                        array(
                            'type' => Column::TYPE_VARCHAR,
                            'notNull' => true,
                            'size' => 80,
                            'first' => true
                        )
                    ),
                    new Column(
                        'client_secret',
                        array(
                            'type' => Column::TYPE_VARCHAR,
                            'size' => 500,
                            'after' => 'client_id'
                        )
                    ),
                    new Column(
                        'redirect_uri',
                        array(
                            'type' => Column::TYPE_VARCHAR,
                            'notNull' => true,
                            'size' => 2000,
                            'after' => 'client_secret'
                        )
                    ),
                    new Column(
                        'grant_types',
                        array(
                            'type' => Column::TYPE_VARCHAR,
                            'size' => 80,
                            'after' => 'redirect_uri'
                        )
                    ),
                    new Column(
                        'scope',
                        array(
                            'type' => Column::TYPE_VARCHAR,
                            'size' => 100,
                            'after' => 'grant_types'
                        )
                    ),
                    new Column(
                        'user_id',
                        array(
                            'type' => Column::TYPE_VARCHAR,
                            'size' => 80,
                            'after' => 'scope'
                        )
                    )
                ),
                'indexes' => array(
                    new Index('PRIMARY', array('client_id'), 'PRIMARY')
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
