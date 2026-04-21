<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddShuffle extends AbstractMigration
{
    public function change(): void
    {
        $this->table('feeds')
            ->addColumn('shuffle', 'boolean', [
                'signed' => false,
                'null' => false,
                'default' => 1,
                'after' => 'proxy_only',
            ])
            ->addIndex(['shuffle'], ['name' => 'idx_shuffle'])
            ->update();
    }
}
