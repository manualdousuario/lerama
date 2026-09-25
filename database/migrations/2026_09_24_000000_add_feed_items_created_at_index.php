<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasIndex('feed_items', 'idx_created_at')) {
            Schema::table('feed_items', function (Blueprint $table): void {
                $table->index('created_at', 'idx_created_at');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasIndex('feed_items', 'idx_created_at')) {
            Schema::table('feed_items', function (Blueprint $table): void {
                $table->dropIndex('idx_created_at');
            });
        }
    }
};
