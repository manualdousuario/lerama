<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Drops the 12 legacy MySQL triggers that maintained item_count. From here on
 * the application owns those counters, via FeedItemObserver plus aggregate
 * updates in the feed processor. See also the `lerama:recount` command.
 */
return new class extends Migration
{
    private const TRIGGERS = [
        'update_feed_item_count_on_insert',
        'update_feed_item_count_on_delete',
        'update_category_count_on_insert',
        'update_category_count_on_delete',
        'update_category_count_on_update',
        'update_tag_count_on_insert',
        'update_tag_count_on_delete',
        'update_tag_count_on_update',
        'update_category_count_on_feed_category_insert',
        'update_category_count_on_feed_category_delete',
        'update_tag_count_on_feed_tag_insert',
        'update_tag_count_on_feed_tag_delete',
    ];

    public function up(): void
    {
        foreach (self::TRIGGERS as $trigger) {
            DB::statement("DROP TRIGGER IF EXISTS `{$trigger}`");
        }
    }

    public function down(): void
    {
        // Deliberately not recreated: the observers own the counters now.
        // Run `lerama:recount` if they need reconciling.
    }
};
