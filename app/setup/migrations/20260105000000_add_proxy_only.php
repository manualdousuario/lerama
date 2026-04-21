<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddProxyOnly extends AbstractMigration
{
    public function change(): void
    {
        $this->table('feeds')
            ->addColumn('proxy_only', 'boolean', ['default' => 0, 'after' => 'retry_proxy'])
            ->addIndex(['proxy_only'], ['name' => 'idx_proxy_only'])
            ->update();
    }
}
