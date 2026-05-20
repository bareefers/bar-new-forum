<?php

/*!
 * Copyright 2017 Tapatalk.com
 */

namespace Tapatalk;

use XF\AddOn\AbstractSetup;
use XF\Db\Schema\Create;
use XF\Db\Schema\Alter;

class Setup extends AbstractSetup
{
    public function install(array $stepParams = [])
    {
        $this->schemaManager()->createTable(
            'xf_tapatalk_users',
            function (Create $table) {
                $table->addColumn('userid','INT', 10)->primaryKey();
                $table->addColumn('announcement', 'SMALLINT', 5)->setDefault(1);
                $table->addColumn('pm', 'SMALLINT', 5)->setDefault(1);
                $table->addColumn('subscribe', 'SMALLINT', 5)->setDefault(1);
                $table->addColumn('quote', 'SMALLINT', 5)->setDefault(1);
                $table->addColumn('liked', 'SMALLINT', 5)->setDefault(1);
                $table->addColumn('tag', 'SMALLINT', 5)->setDefault(1);
                $table->addColumn('updated', 'TIMESTAMP');
            }
        );
    }

    public function upgrade(array $stepParams = [])
    {
        // TODO: Implement upgrade() method.
    }

    public function uninstall(array $stepParams = [])
    {
         $this->schemaManager()->dropTable('xf_tapatalk_users');
    }


}