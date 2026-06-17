<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddSlugToFeeds extends AbstractMigration
{
    public function up(): void
    {
        $this->table('feeds')
            ->addColumn('slug', 'string', [
                'limit' => 255,
                'null' => true,
                'default' => null,
                'after' => 'site_url',
            ])
            ->addIndex(['slug'], ['unique' => true, 'name' => 'idx_feed_slug_unique'])
            ->update();

        // Populate slugs for existing feeds based on their site_url.
        $rows = $this->fetchAll('SELECT id, site_url FROM feeds WHERE slug IS NULL OR slug = \'\'');
        foreach ($rows as $row) {
            $slug = $this->generateSlug($row['site_url']);
            $uniqueSlug = $this->makeUniqueSlug($slug, (int)$row['id']);

            $this->execute(sprintf(
                "UPDATE feeds SET slug = %s WHERE id = %d",
                $this->getAdapter()->getConnection()->quote($uniqueSlug),
                (int)$row['id']
            ));
        }
    }

    public function down(): void
    {
        $this->table('feeds')
            ->removeIndexByName('idx_feed_slug_unique')
            ->removeColumn('slug')
            ->update();
    }

    private function generateSlug(string $url): string
    {
        $parsed = parse_url($url);
        if ($parsed === false) {
            return 'feed';
        }

        $parts = [];
        if (!empty($parsed['host'])) {
            $parts[] = $parsed['host'];
        }
        if (!empty($parsed['path']) && $parsed['path'] !== '/') {
            $parts[] = $parsed['path'];
        }
        if (!empty($parsed['query'])) {
            $parts[] = $parsed['query'];
        }

        $raw = implode('/', $parts);
        $raw = mb_strtolower($raw, 'UTF-8');

        $transliterated = @iconv('UTF-8', 'ASCII//TRANSLIT', $raw);
        if ($transliterated !== false) {
            $raw = $transliterated;
        }

        $slug = preg_replace('/[^a-z0-9]+/', '-', $raw);
        $slug = trim($slug, '-');

        return $slug === '' ? 'feed' : $slug;
    }

    private function makeUniqueSlug(string $baseSlug, int $feedId): string
    {
        $slug = $baseSlug;
        $counter = 1;

        while (true) {
            $existing = $this->fetchRow(
                'SELECT id FROM feeds WHERE slug = ' . $this->getAdapter()->getConnection()->quote($slug) . ' AND id != ' . $feedId
            );
            if (!$existing) {
                return $slug;
            }

            $counter++;
            $slug = $baseSlug . '-' . $counter;
        }
    }
}
