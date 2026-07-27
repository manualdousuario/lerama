<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FeedItem extends Model
{
    protected $table = 'feed_items';

    protected $fillable = [
        'feed_id',
        'title',
        'author',
        'content',
        'excerpt',
        'url',
        'image_url',
        'image_fetched_at',
        'guid',
        'published_at',
        'is_visible',
    ];

    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
            'image_fetched_at' => 'datetime',
            'is_visible' => 'boolean',
        ];
    }

    public function feed(): BelongsTo
    {
        return $this->belongsTo(Feed::class, 'feed_id');
    }
}
