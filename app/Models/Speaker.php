<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Speaker extends Model
{
    protected $fillable = [
        'podcast_id',
        'name',
        'identifier',
        'description',
        'voice_characteristics',
        'is_host',
        'is_verified',
    ];

    protected function casts(): array
    {
        return [
            'is_host' => 'boolean',
            'is_verified' => 'boolean',
        ];
    }

    public function podcast(): BelongsTo
    {
        return $this->belongsTo(Podcast::class);
    }

    public function transcriptSegments(): HasMany
    {
        return $this->hasMany(TranscriptSegment::class);
    }

    public function getDisplayName(): string
    {
        return $this->name ?: $this->identifier;
    }

    public function getTotalSpeakingTime(): float
    {
        return $this->transcriptSegments()
            ->selectRaw('SUM(end_time - start_time) as total_time')
            ->value('total_time') ?? 0;
    }

    public function getEpisodeCount(): int
    {
        return $this->transcriptSegments()
            ->distinct('episode_id')
            ->count('episode_id');
    }
}
