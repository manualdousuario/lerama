<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class MarkLegacyAsMigrated extends AbstractMigration
{
    private const LEGACY_MIGRATIONS = [
        '20250101000000' => 'InitialSchema',
        '20250527000000' => 'AddRetryFieldsToFeeds',
        '20251029000000' => 'CreateTaxonomy',
        '20251103000000' => 'AddItemCountAndTriggers',
        '20260105000000' => 'AddProxyOnly',
        '20260128000000' => 'AddLastFeedItemId',
        '20260407000000' => 'AddShuffle',
        '20260421000000' => 'AddFeedItemCount',
    ];

    public function up(): void
    {
        if (!$this->hasTable('feeds')) {
            return;
        }

        $now = date('Y-m-d H:i:s');
        foreach (self::LEGACY_MIGRATIONS as $version => $name) {
            $this->execute(sprintf(
                "INSERT IGNORE INTO `phinxlog` (version, migration_name, start_time, end_time, breakpoint) "
                . "VALUES ('%s', '%s', '%s', '%s', 1)",
                $version,
                $name,
                $now,
                $now
            ));
        }
    }

    public function down(): void
    {
        $versions = implode("','", array_keys(self::LEGACY_MIGRATIONS));
        $this->execute(sprintf(
            "DELETE FROM `phinxlog` WHERE version IN ('%s')",
            $versions
        ));
    }
}
