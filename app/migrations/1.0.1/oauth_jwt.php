<?php 

use Phalcon\Db\Column;
use Phalcon\Db\Index;
use Phalcon\Db\Reference;
use Phalcon\Mvc\Model\Migration;

/**
 * Class OauthJwtMigration_101
 */
class OauthJwtMigration_101 extends Migration
{
    /**
     * Define the table structure
     *
     * @return void
     */
    public function morph()
    {
        $this->morphTable('oauth_jwt', array(
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
                        'subject',
                        array(
                            'type' => Column::TYPE_VARCHAR,
                            'size' => 80,
                            'after' => 'client_id'
                        )
                    ),
                    new Column(
                        'public_key',
                        array(
                            'type' => Column::TYPE_VARCHAR,
                            'size' => 2000,
                            'after' => 'subject'
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
