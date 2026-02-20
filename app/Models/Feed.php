<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Feed extends Model
{
    protected $fillable = [
        'name',
        'url',
        'description',
        'image_url',
        'language',
        'last_checked_at',
        'last_episode_at',
        'is_active',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'last_checked_at' => 'datetime',
            'last_episode_at' => 'datetime',
            'is_active' => 'boolean',
            'metadata' => 'array',
        ];
    }

    public function episodes(): HasMany
    {
        return $this->hasMany(Episode::class);
    }

    public function latestEpisodesQuery(int $limit = 10): HasMany
    {
        return $this->episodes()->latest('published_at')->limit($limit);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
