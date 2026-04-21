<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddRetryFieldsToFeeds extends AbstractMigration
{
    public function change(): void
    {
        $this->table('feeds')
            ->addColumn('retry_count', 'integer', ['signed' => false, 'default' => 0])
            ->addColumn('retry_proxy', 'boolean', ['default' => 0])
            ->addColumn('paused_at', 'datetime', ['null' => true, 'default' => null])
            ->update();
    }
}
