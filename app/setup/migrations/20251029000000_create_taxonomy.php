<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateTaxonomy extends AbstractMigration
{
    public function change(): void
    {
        $this->table('categories', [
            'id' => false,
            'primary_key' => ['id'],
            'engine' => 'InnoDB',
            'encoding' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'row_format' => 'DYNAMIC',
        ])
            ->addColumn('id', 'integer', ['signed' => false, 'identity' => true])
            ->addColumn('name', 'string', ['limit' => 100])
            ->addColumn('slug', 'string', ['limit' => 100])
            ->addColumn('created_at', 'datetime', ['null' => true, 'default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('updated_at', 'datetime', [
                'null' => true,
                'default' => 'CURRENT_TIMESTAMP',
                'update' => 'CURRENT_TIMESTAMP',
            ])
            ->addIndex(['slug'], ['unique' => true, 'name' => 'slug'])
            ->addIndex(['slug'], ['name' => 'idx_slug'])
            ->create();

        $this->table('tags', [
            'id' => false,
            'primary_key' => ['id'],
            'engine' => 'InnoDB',
            'encoding' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'row_format' => 'DYNAMIC',
        ])
            ->addColumn('id', 'integer', ['signed' => false, 'identity' => true])
            ->addColumn('name', 'string', ['limit' => 100])
            ->addColumn('slug', 'string', ['limit' => 100])
            ->addColumn('created_at', 'datetime', ['null' => true, 'default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('updated_at', 'datetime', [
                'null' => true,
                'default' => 'CURRENT_TIMESTAMP',
                'update' => 'CURRENT_TIMESTAMP',
            ])
            ->addIndex(['slug'], ['unique' => true, 'name' => 'slug'])
            ->addIndex(['slug'], ['name' => 'idx_slug'])
            ->create();

        $this->table('feed_categories', [
            'id' => false,
            'primary_key' => ['feed_id', 'category_id'],
            'engine' => 'InnoDB',
            'encoding' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'row_format' => 'DYNAMIC',
        ])
            ->addColumn('feed_id', 'integer', ['signed' => false])
            ->addColumn('category_id', 'integer', ['signed' => false])
            ->addColumn('created_at', 'datetime', ['null' => true, 'default' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['category_id'], ['name' => 'idx_category_id'])
            ->addIndex(['category_id', 'feed_id'], ['name' => 'idx_feed_category_lookup'])
            ->addForeignKey('feed_id', 'feeds', 'id', [
                'constraint' => 'feed_categories_ibfk_1',
                'delete' => 'CASCADE',
                'update' => 'RESTRICT',
            ])
            ->addForeignKey('category_id', 'categories', 'id', [
                'constraint' => 'feed_categories_ibfk_2',
                'delete' => 'CASCADE',
                'update' => 'RESTRICT',
            ])
            ->create();

        $this->table('feed_tags', [
            'id' => false,
            'primary_key' => ['feed_id', 'tag_id'],
            'engine' => 'InnoDB',
            'encoding' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'row_format' => 'DYNAMIC',
        ])
            ->addColumn('feed_id', 'integer', ['signed' => false])
            ->addColumn('tag_id', 'integer', ['signed' => false])
            ->addColumn('created_at', 'datetime', ['null' => true, 'default' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['tag_id'], ['name' => 'idx_tag_id'])
            ->addIndex(['tag_id', 'feed_id'], ['name' => 'idx_feed_tag_lookup'])
            ->addForeignKey('feed_id', 'feeds', 'id', [
                'constraint' => 'feed_tags_ibfk_1',
                'delete' => 'CASCADE',
                'update' => 'RESTRICT',
            ])
            ->addForeignKey('tag_id', 'tags', 'id', [
                'constraint' => 'feed_tags_ibfk_2',
                'delete' => 'CASCADE',
                'update' => 'RESTRICT',
            ])
            ->create();

        $this->table('feeds')
            ->changeColumn('status', 'enum', [
                'values' => ['online', 'offline', 'paused', 'pending', 'rejected'],
                'null' => true,
                'default' => 'online',
            ])
            ->addColumn('submitter_email', 'string', [
                'limit' => 255,
                'null' => true,
                'default' => null,
                'after' => 'language',
            ])
            ->update();
    }
}
