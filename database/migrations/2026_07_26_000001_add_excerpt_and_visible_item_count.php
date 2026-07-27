<?php

use App\Support\Excerpt;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('feed_items', 'excerpt')) {
            Schema::table('feed_items', function (Blueprint $table): void {
                $table->string('excerpt', Excerpt::STORAGE_LENGTH)->nullable()->after('content');
            });
        }

        if (! Schema::hasColumn('feeds', 'visible_item_count')) {
            Schema::table('feeds', function (Blueprint $table): void {
                $table->unsignedInteger('visible_item_count')->default(0)->after('item_count');
            });
        }

        DB::statement(
            'UPDATE feeds f SET f.visible_item_count = (
                SELECT COUNT(*) FROM feed_items WHERE feed_id = f.id AND is_visible = 1
            )'
        );

        DB::table('feed_items')
            ->select(['id', 'content'])
            ->whereNull('excerpt')
            ->orderBy('id')
            ->chunkById(500, function ($items): void {
                foreach ($items as $item) {
                    DB::table('feed_items')->where('id', $item->id)->update([
                        'excerpt' => Excerpt::forStorage($item->content),
                    ]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('feed_items', function (Blueprint $table): void {
            $table->dropColumn('excerpt');
        });

        Schema::table('feeds', function (Blueprint $table): void {
            $table->dropColumn('visible_item_count');
        });
    }
};
