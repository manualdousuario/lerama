<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Baseline schema, mirroring the 17 legacy Phinx migrations.
 *
 * Idempotent: on databases already migrated by Phinx every table exists and
 * this is a no-op; on fresh installs it creates the whole schema.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('feeds')) {
            Schema::create('feeds', function (Blueprint $table): void {
                $table->increments('id');
                $table->string('title');
                $table->string('feed_url', 512);
                $table->string('site_url', 512);
                $table->string('slug')->nullable();
                $table->enum('feed_type', ['rss1', 'rss2', 'atom', 'rdf', 'csv', 'json', 'xml']);
                $table->string('last_post_id')->nullable();
                $table->dateTime('last_checked')->nullable();
                $table->dateTime('last_updated')->nullable();
                $table->unsignedBigInteger('next_fetch_at')->default(0);
                $table->string('etag')->nullable();
                $table->string('last_modified', 64)->nullable();
                $table->enum('status', ['online', 'offline', 'paused', 'pending', 'rejected'])->nullable()->default('online');
                $table->text('last_error')->nullable();
                $table->dateTime('created_at')->nullable()->useCurrent();
                $table->dateTime('updated_at')->nullable()->useCurrent()->useCurrentOnUpdate();
                $table->string('language', 5)->nullable();
                $table->string('submitter_email')->nullable();
                $table->unsignedInteger('retry_count')->default(0);
                $table->boolean('retry_proxy')->default(false);
                $table->dateTime('paused_at')->nullable();
                $table->boolean('proxy_only')->default(false);
                $table->boolean('shuffle')->default(true);
                $table->unsignedInteger('last_feed_item_id')->nullable();
                $table->unsignedInteger('item_count')->default(0);

                $table->unique('slug', 'idx_feed_slug_unique');
                $table->index('status', 'idx_feed_status');
                $table->index('feed_type', 'idx_feed_type');
                $table->index('last_checked', 'idx_last_checked');
                $table->index('last_updated', 'idx_last_updated');
                $table->index(['status', 'last_checked'], 'idx_status_checked');
                $table->index('language', 'idx_language');
                $table->index(['status', 'next_fetch_at'], 'idx_status_next_fetch');
                $table->index('shuffle', 'idx_shuffle');
                $table->index(['status', 'shuffle'], 'idx_status_shuffle');
                $table->index('last_feed_item_id', 'idx_last_feed_item_id');
            });

            // Prefixed indexes, matching the legacy schema.
            DB::statement('CREATE UNIQUE INDEX feed_url ON feeds (feed_url(255))');
            DB::statement('CREATE INDEX idx_title ON feeds (title(100))');
        }

        if (! Schema::hasTable('feed_items')) {
            Schema::create('feed_items', function (Blueprint $table): void {
                $table->increments('id');
                $table->unsignedInteger('feed_id');
                $table->string('title', 512);
                $table->string('author')->nullable();
                $table->mediumText('content')->nullable();
                $table->string('url', 512);
                $table->string('image_url', 512)->nullable();
                $table->dateTime('image_fetched_at')->nullable();
                $table->string('guid');
                $table->dateTime('published_at')->nullable();
                $table->boolean('is_visible')->nullable()->default(true);
                $table->dateTime('created_at')->nullable()->useCurrent();
                $table->dateTime('updated_at')->nullable()->useCurrent()->useCurrentOnUpdate();

                $table->unique(['feed_id', 'guid'], 'unique_item');
                $table->fullText(['title', 'content'], 'idx_title_content');
                $table->index('published_at', 'idx_published_at');
                $table->index('is_visible', 'idx_is_visible');
                $table->index(['feed_id', 'published_at'], 'idx_feed_published');
                $table->index(['feed_id', 'is_visible'], 'idx_feed_visible');
                $table->index(['is_visible', 'published_at'], 'idx_visible_published');
                $table->index(['feed_id', 'is_visible', 'published_at'], 'idx_feed_visible_published');
                $table->index('guid', 'idx_guid');
                $table->index('image_fetched_at', 'idx_image_fetched_at');

                $table->foreign('feed_id', 'feed_items_ibfk_1')
                    ->references('id')->on('feeds')
                    ->cascadeOnDelete()->restrictOnUpdate();
            });
        }

        if (! Schema::hasTable('categories')) {
            Schema::create('categories', function (Blueprint $table): void {
                $table->increments('id');
                $table->string('name', 100);
                $table->string('slug', 100);
                $table->unsignedInteger('item_count')->default(0);
                $table->dateTime('created_at')->nullable()->useCurrent();
                $table->dateTime('updated_at')->nullable()->useCurrent()->useCurrentOnUpdate();

                $table->unique('slug', 'slug');
            });
        }

        if (! Schema::hasTable('tags')) {
            Schema::create('tags', function (Blueprint $table): void {
                $table->increments('id');
                $table->string('name', 100);
                $table->string('slug', 100);
                $table->unsignedInteger('item_count')->default(0);
                $table->dateTime('created_at')->nullable()->useCurrent();
                $table->dateTime('updated_at')->nullable()->useCurrent()->useCurrentOnUpdate();

                $table->unique('slug', 'slug');
            });
        }

        if (! Schema::hasTable('feed_categories')) {
            Schema::create('feed_categories', function (Blueprint $table): void {
                $table->unsignedInteger('feed_id');
                $table->unsignedInteger('category_id');
                $table->dateTime('created_at')->nullable()->useCurrent();

                $table->primary(['feed_id', 'category_id']);
                $table->index('category_id', 'idx_category_id');
                $table->index(['category_id', 'feed_id'], 'idx_feed_category_lookup');

                $table->foreign('feed_id', 'feed_categories_ibfk_1')
                    ->references('id')->on('feeds')
                    ->cascadeOnDelete()->restrictOnUpdate();
                $table->foreign('category_id', 'feed_categories_ibfk_2')
                    ->references('id')->on('categories')
                    ->cascadeOnDelete()->restrictOnUpdate();
            });
        }

        if (! Schema::hasTable('feed_tags')) {
            Schema::create('feed_tags', function (Blueprint $table): void {
                $table->unsignedInteger('feed_id');
                $table->unsignedInteger('tag_id');
                $table->dateTime('created_at')->nullable()->useCurrent();

                $table->primary(['feed_id', 'tag_id']);
                $table->index('tag_id', 'idx_tag_id');
                $table->index(['tag_id', 'feed_id'], 'idx_feed_tag_lookup');

                $table->foreign('feed_id', 'feed_tags_ibfk_1')
                    ->references('id')->on('feeds')
                    ->cascadeOnDelete()->restrictOnUpdate();
                $table->foreign('tag_id', 'feed_tags_ibfk_2')
                    ->references('id')->on('tags')
                    ->cascadeOnDelete()->restrictOnUpdate();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('feed_items');
        Schema::dropIfExists('feed_tags');
        Schema::dropIfExists('feed_categories');
        Schema::dropIfExists('tags');
        Schema::dropIfExists('categories');
        Schema::dropIfExists('feeds');
    }
};
