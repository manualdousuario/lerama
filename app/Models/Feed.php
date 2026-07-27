<?php

namespace App\Models;

use App\Enums\FeedStatus;
use App\Enums\FeedType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Feed extends Model
{
    protected $table = 'feeds';

    protected $fillable = [
        'title',
        'feed_url',
        'site_url',
        'slug',
        'feed_type',
        'language',
        'status',
        'submitter_email',
        'proxy_only',
        'shuffle',
        'last_post_id',
        'last_feed_item_id',
        'last_checked',
        'last_updated',
        'next_fetch_at',
        'etag',
        'last_modified',
        'retry_count',
        'retry_proxy',
        'paused_at',
        'last_error',
        'item_count',
        'visible_item_count',
    ];

    protected function casts(): array
    {
        return [
            'feed_type' => FeedType::class,
            'status' => FeedStatus::class,
            'last_checked' => 'datetime',
            'last_updated' => 'datetime',
            'paused_at' => 'datetime',
            'retry_proxy' => 'boolean',
            'proxy_only' => 'boolean',
            'shuffle' => 'boolean',
            'next_fetch_at' => 'integer',
            'last_feed_item_id' => 'integer',
            'item_count' => 'integer',
            'visible_item_count' => 'integer',
            'retry_count' => 'integer',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(FeedItem::class, 'feed_id');
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'feed_categories', 'feed_id', 'category_id');
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'feed_tags', 'feed_id', 'tag_id');
    }

    public function scopeOnline(Builder $query): Builder
    {
        return $query->where('status', FeedStatus::Online->value);
    }

    public function scopePaused(Builder $query): Builder
    {
        return $query->where('status', FeedStatus::Paused->value);
    }

    public function scopeDueForFetch(Builder $query): Builder
    {
        return $query->where('status', FeedStatus::Online->value)
            ->where('next_fetch_at', '<=', time())
            ->orderBy('next_fetch_at');
    }

    public function scopeShuffleable(Builder $query): Builder
    {
        return $query->where('status', FeedStatus::Online->value)
            ->where('shuffle', true);
    }
}
