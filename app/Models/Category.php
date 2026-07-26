<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Category extends Model
{
    protected $table = 'categories';

    protected $fillable = ['name', 'slug'];

    protected function casts(): array
    {
        return [
            'item_count' => 'integer',
        ];
    }

    public function feeds(): BelongsToMany
    {
        return $this->belongsToMany(Feed::class, 'feed_categories', 'category_id', 'feed_id');
    }
}
