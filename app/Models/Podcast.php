<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class Podcast extends Model
{
    use HasFactory;

    protected $fillable = [
        'id',
        'title',
        'description',
        'rss_url',
        'website_url',
        'author',
        'language',
        'category',
        'image_url',
        'is_active',
        'last_checked_at',
        'last_episode_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'last_checked_at' => 'datetime',
            'last_episode_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function episodes(): HasMany
    {
        return $this->hasMany(Episode::class);
    }

    public function speakers(): HasMany
    {
        return $this->hasMany(Speaker::class);
    }

    public function transcriptSegments(): HasManyThrough
    {
        return $this->hasManyThrough(TranscriptSegment::class, Episode::class);
    }
}
