<?php

namespace App\Models;

use App\Services\Storage\PodcastFileManager;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Episode extends Model
{
    use HasFactory;

    protected $fillable = [
        'id',
        'podcast_id',
        'feed_id',
        'title',
        'episode_type',
        'description',
        'guid',
        'audio_url',
        'download_status',
        'local_audio_path',
        'file_size_bytes',
        'duration_seconds',
        'audio_format',
        'transcription_status',
        'embedding_status',
        'processing_metadata',
        'published_at',
        'episode_number',
        'season_number',
        'diarization_status',
        'sync_status',
        'sync_progress',
        'sync_hash',
        'last_synced_at',
        'sync_attempts',
        'sync_error',
    ];

    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
            'last_synced_at' => 'datetime',
            'processing_metadata' => 'array',
            'sync_progress' => 'array',
            'file_size_bytes' => 'integer',
            'duration_seconds' => 'float',
            'sync_attempts' => 'integer',
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

    public function isDownloaded(): bool
    {
        if ($this->download_status !== 'completed' || ! $this->local_audio_path) {
            return false;
        }

        return PodcastFileManager::episodeAudioExists($this);
    }

    public function isTranscribed(): bool
    {
        return $this->transcription_status === 'completed';
    }

    /**
     * @warning Fires a SQL query on every call. Do not call in a loop.
     *          For bulk checks, use withCount(['transcriptSegments as embeddings_count' => fn($q) => $q->where('embedding_status', 'completed')])
     *          on the parent query and check $episode->embeddings_count > 0 instead.
     */
    public function hasEmbeddings(): bool
    {
        return $this->transcriptSegments()
            ->where('embedding_status', 'completed')
            ->exists();
    }

    public function isFullyProcessed(): bool
    {
        return $this->isDownloaded() &&
               $this->isTranscribed() &&
               $this->hasEmbeddings();
    }

    public static function formatDuration(?float $seconds): ?string
    {
        if ($seconds === null) {
            return null;
        }

        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        $remainingSeconds = $seconds % 60;

        if ($hours > 0) {
            return sprintf('%d:%02d:%02d', (int) $hours, (int) $minutes, (int) $remainingSeconds);
        } else {
            return sprintf('%d:%02d', (int) $minutes, (int) $remainingSeconds);
        }
    }

    public function getFormattedDurationAttribute(): ?string
    {
        return self::formatDuration($this->duration_seconds);
    }

    /**
     * @warning Fires a SQL query on every call. Do not call in a loop.
     *          For bulk use, add ->withCount('transcriptSegments') to the parent query
     *          and use $episode->transcript_segments_count instead.
     */
    public function getTranscriptSegmentCount(): int
    {
        return $this->transcriptSegments()->count();
    }

    /**
     * @warning Fires a SQL query on every call. Do not call in a loop.
     *          For bulk use, add ->loadAvg('transcriptSegments', 'confidence') on the collection
     *          and access $episode->transcript_segments_avg_confidence instead.
     */
    public function getAverageConfidenceScore(): ?float
    {
        $avgConfidence = $this->transcriptSegments()
            ->whereNotNull('confidence')
            ->avg('confidence');

        return $avgConfidence ? round((float) $avgConfidence, 3) : null;
    }

    /**
     * @warning Fires multiple SQL queries on every call (loads all segments + avg confidence).
     *          Do not call in a loop. This method is intended for single-episode detail views only.
     */
    public function getTranscriptSummary(): array
    {
        $segments = $this->transcriptSegments()
            ->orderBy('start_time')
            ->get();

        return [
            'total_segments' => $segments->count(),
            'total_text_length' => $segments->sum(fn ($s) => strlen($s->text)),
            'average_confidence' => $this->getAverageConfidenceScore(),
            'duration_covered' => $segments->max('end_time'),
        ];
    }

    /**
     * Detect episode type based on title patterns
     */
    public static function detectEpisodeType(string $title): string
    {
        // Check for Midweek Mayhem patterns
        if (preg_match('/WDWDY #\d+/i', $title) ||
            stripos($title, 'midweek mayhem') !== false ||
            stripos($title, 'midweek') !== false) {
            return 'midweek_mayhem';
        }

        // Default to interview for regular episodes (S2E3: format, etc.)
        return 'interview';
    }

    /**
     * Set episode type based on title
     */
    public function setEpisodeTypeFromTitle(): void
    {
        $this->episode_type = self::detectEpisodeType($this->title);
        $this->save();
    }

    /**
     * Check if this is a Midweek Mayhem episode
     */
    public function isMidweekMayhem(): bool
    {
        return $this->episode_type === 'midweek_mayhem';
    }

    /**
     * Check if this is an interview episode
     */
    public function isInterview(): bool
    {
        return $this->episode_type === 'interview';
    }
}
