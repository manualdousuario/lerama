<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class InitialSchema extends AbstractMigration
{
    public function change(): void
    {
        $this->table('feeds', [
            'id' => false,
            'primary_key' => ['id'],
            'engine' => 'InnoDB',
            'encoding' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'row_format' => 'DYNAMIC',
        ])
            ->addColumn('id', 'integer', ['signed' => false, 'identity' => true])
            ->addColumn('title', 'string', ['limit' => 255])
            ->addColumn('feed_url', 'string', ['limit' => 512])
            ->addColumn('site_url', 'string', ['limit' => 512])
            ->addColumn('feed_type', 'enum', ['values' => ['rss1', 'rss2', 'atom', 'rdf', 'csv', 'json', 'xml']])
            ->addColumn('last_post_id', 'string', ['limit' => 255, 'null' => true, 'default' => null])
            ->addColumn('last_checked', 'datetime', ['null' => true, 'default' => null])
            ->addColumn('last_updated', 'datetime', ['null' => true, 'default' => null])
            ->addColumn('status', 'enum', [
                'values' => ['online', 'offline', 'paused', 'pending', 'rejected'],
                'null' => true,
                'default' => 'online',
            ])
            ->addColumn('created_at', 'datetime', ['null' => true, 'default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('updated_at', 'datetime', [
                'null' => true,
                'default' => 'CURRENT_TIMESTAMP',
                'update' => 'CURRENT_TIMESTAMP',
            ])
            ->addColumn('language', 'string', ['limit' => 5, 'null' => true, 'default' => null])
            ->addIndex(['feed_url'], ['unique' => true, 'limit' => ['feed_url' => 255], 'name' => 'feed_url'])
            ->addIndex(['status'], ['name' => 'idx_feed_status'])
            ->addIndex(['feed_type'], ['name' => 'idx_feed_type'])
            ->addIndex(['last_checked'], ['name' => 'idx_last_checked'])
            ->addIndex(['last_updated'], ['name' => 'idx_last_updated'])
            ->addIndex(['status', 'last_checked'], ['name' => 'idx_status_checked'])
            ->addIndex(['language'], ['name' => 'idx_language'])
            ->addIndex(['title'], ['limit' => ['title' => 100], 'name' => 'idx_title'])
            ->create();

        $this->table('feed_items', [
            'id' => false,
            'primary_key' => ['id'],
            'engine' => 'InnoDB',
            'encoding' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'row_format' => 'DYNAMIC',
        ])
            ->addColumn('id', 'integer', ['signed' => false, 'identity' => true])
            ->addColumn('feed_id', 'integer', ['signed' => false])
            ->addColumn('title', 'string', ['limit' => 512])
            ->addColumn('author', 'string', ['limit' => 255, 'null' => true, 'default' => null])
            ->addColumn('content', 'text', ['limit' => \Phinx\Db\Adapter\MysqlAdapter::TEXT_MEDIUM, 'null' => true, 'default' => null])
            ->addColumn('url', 'string', ['limit' => 512])
            ->addColumn('image_url', 'string', ['limit' => 512, 'null' => true, 'default' => null])
            ->addColumn('guid', 'string', ['limit' => 255])
            ->addColumn('published_at', 'datetime', ['null' => true, 'default' => null])
            ->addColumn('is_visible', 'boolean', ['null' => true, 'default' => 1])
            ->addColumn('created_at', 'datetime', ['null' => true, 'default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('updated_at', 'datetime', [
                'null' => true,
                'default' => 'CURRENT_TIMESTAMP',
                'update' => 'CURRENT_TIMESTAMP',
            ])
            ->addIndex(['feed_id', 'guid'], ['unique' => true, 'name' => 'unique_item'])
            ->addIndex(['title', 'content'], ['type' => 'fulltext', 'name' => 'idx_title_content'])
            ->addIndex(['published_at'], ['name' => 'idx_published_at'])
            ->addIndex(['is_visible'], ['name' => 'idx_is_visible'])
            ->addIndex(['feed_id', 'published_at'], ['name' => 'idx_feed_published'])
            ->addIndex(['feed_id', 'is_visible'], ['name' => 'idx_feed_visible'])
            ->addIndex(['is_visible', 'published_at'], ['name' => 'idx_visible_published'])
            ->addIndex(['feed_id', 'is_visible', 'published_at'], ['name' => 'idx_feed_visible_published'])
            ->addForeignKey('feed_id', 'feeds', 'id', [
                'constraint' => 'feed_items_ibfk_1',
                'delete' => 'CASCADE',
                'update' => 'RESTRICT',
            ])
            ->create();
    }
}
