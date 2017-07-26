<?php 

use Phalcon\Db\Column;
use Phalcon\Db\Index;
use Phalcon\Db\Reference;
use Phalcon\Mvc\Model\Migration;

/**
 * Class OauthScopesMigration_101
 */
class OauthScopesMigration_101 extends Migration
{
    /**
     * Define the table structure
     *
     * @return void
     */
    public function morph()
    {
        $this->morphTable('oauth_scopes', array(
                'columns' => array(
                    new Column(
                        'scope',
                        array(
                            'type' => Column::TYPE_TEXT,
                            'size' => 1,
                            'first' => true
                        )
                    ),
                    new Column(
                        'is_default',
                        array(
                            'type' => Column::TYPE_INTEGER,
                            'size' => 1,
                            'after' => 'scope'
                        )
                    )
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
