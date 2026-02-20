<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TranscriptSegment extends Model
{
    use HasFactory;

    protected $fillable = [
        'id',
        'episode_id',
        'speaker_id',
        'start_time',
        'end_time',
        'text',
        'confidence',
        'segment_index',
        'word_count',
        'embedding_status',
        'chunk_group',
        'embedding_metadata',
        'embedding_created_at',
    ];

    protected function casts(): array
    {
        return [
            'start_time' => 'float',
            'end_time' => 'float',
            'confidence' => 'float',
            'embedding_metadata' => 'array',
            'embedding_created_at' => 'datetime',
        ];
    }

    public function episode(): BelongsTo
    {
        return $this->belongsTo(Episode::class);
    }

    public function speaker(): BelongsTo
    {
        return $this->belongsTo(Speaker::class);
    }

    public function getFormattedTimestamp(): string
    {
        $startTime = (float) $this->start_time;
        $minutes = intval($startTime / 60);
        $seconds = intval($startTime - ($minutes * 60));

        return sprintf('%d:%02d', $minutes, $seconds);
    }

    public function getDuration(): float
    {
        return (float) $this->end_time - (float) $this->start_time;
    }

    public function getTimestampUrl(): string
    {
        // Format: #t=120 for 2 minutes
        return '#t='.floor((float) $this->start_time);
    }

    protected static function booted()
    {
        static::creating(function ($segment) {
            if (! $segment->word_count) {
                $segment->word_count = str_word_count($segment->text);
            }
        });

        static::updating(function ($segment) {
            if ($segment->isDirty('text')) {
                $segment->word_count = str_word_count($segment->text);
                $segment->embedding_status = 'pending';
                $segment->embedding_vector = null;
                $segment->embedding_metadata = null;
                $segment->embedding_created_at = null;
            }
        });
    }
}
