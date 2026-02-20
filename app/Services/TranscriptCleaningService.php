<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class TranscriptCleaningService
{
    /**
     * Text replacements to apply during transcript ingestion
     * Key = incorrect text, Value = correct text
     */
    private array $textReplacements = [
    ];

    /**
     * Full text entries to ignore (case-insensitive)
     * These will cause the entire transcript segment to be skipped
     */
    private array $ignoredEntries = [
    ];

    /**
     * Patterns to ignore (regex patterns)
     * For more complex filtering
     */
    private array $ignoredPatterns = [
        '/^\([^)]*\)$/',           // Any text in parentheses like (whatever)
        '/^\[[^\]]*\]$/',          // Any text in brackets like [whatever]
        '/^[\s\-_\.]*$/',          // Just whitespace, dashes, underscores, dots
    ];

    public function __construct()
    {
        $this->loadFromConfig();
    }

    /**
     * Clean transcript text and determine if it should be kept
     *
     * @param  string  $text  The raw transcript text
     * @return array ['text' => cleaned_text, 'keep' => boolean]
     */
    public function cleanTranscript(string $text): array
    {
        $originalText = $text;

        // Trim whitespace
        $text = trim($text);

        // Check if the entire entry should be ignored (case-insensitive)
        if ($this->shouldIgnoreEntry($text)) {
            Log::debug("Ignoring transcript entry: '{$originalText}'");

            return ['text' => $text, 'keep' => false];
        }

        // Apply text replacements
        $text = $this->applyTextReplacements($text);

        // Ensure valid UTF-8 encoding
        $text = $this->ensureValidUtf8($text);

        // Log if we made changes
        if ($text !== trim($originalText)) {
            Log::info('Cleaned transcript text', [
                'original' => $originalText,
                'cleaned' => $text,
            ]);
        }

        return ['text' => $text, 'keep' => true];
    }

    /**
     * Check if an entry should be completely ignored
     */
    private function shouldIgnoreEntry(string $text): bool
    {
        // Check exact matches (case-insensitive)
        foreach ($this->ignoredEntries as $ignoredEntry) {
            if (strcasecmp(trim($text), $ignoredEntry) === 0) {
                return true;
            }
        }

        // Check regex patterns
        foreach ($this->ignoredPatterns as $pattern) {
            if (preg_match($pattern, $text)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Apply text replacements using word boundary matching.
     *
     * Only replaces whole words, not partial matches within larger words.
     * For example, "mat" -> "Max" will replace "mat" but not "matt" or "material".
     */
    private function applyTextReplacements(string $text): string
    {
        foreach ($this->textReplacements as $search => $replace) {
            // Use word boundary matching to only replace whole words
            // \b matches word boundaries (spaces, punctuation, start/end of string)
            // The 'i' flag makes it case-insensitive, 'u' enables Unicode support
            $pattern = '/\b'.preg_quote($search, '/').'\b/iu';
            $text = preg_replace($pattern, $replace, $text);
        }

        return $text;
    }

    /**
     * Ensure the text contains valid UTF-8 characters
     */
    private function ensureValidUtf8(string $text): string
    {
        // Check if the string is valid UTF-8
        if (! mb_check_encoding($text, 'UTF-8')) {
            // Try to convert from common encodings
            $encodings = ['ISO-8859-1', 'Windows-1252', 'UTF-8'];

            foreach ($encodings as $encoding) {
                $converted = mb_convert_encoding($text, 'UTF-8', $encoding);
                if (mb_check_encoding($converted, 'UTF-8')) {
                    Log::warning("Fixed text encoding from {$encoding} to UTF-8", [
                        'original' => $text,
                        'converted' => $converted,
                    ]);

                    return $converted;
                }
            }

            // As a last resort, remove/replace invalid UTF-8 sequences
            $cleaned = mb_convert_encoding($text, 'UTF-8', 'UTF-8');
            Log::warning('Removed invalid UTF-8 sequences from text', [
                'original' => $text,
                'cleaned' => $cleaned,
            ]);

            return $cleaned;
        }

        return $text;
    }

    /**
     * Add a new text replacement rule
     */
    public function addTextReplacement(string $search, string $replace): void
    {
        $this->textReplacements[$search] = $replace;
    }

    /**
     * Add a new entry to ignore
     */
    public function addIgnoredEntry(string $entry): void
    {
        $this->ignoredEntries[] = $entry;
    }

    /**
     * Add a new pattern to ignore
     */
    public function addIgnoredPattern(string $pattern): void
    {
        $this->ignoredPatterns[] = $pattern;
    }

    /**
     * Get current text replacements
     */
    public function getTextReplacements(): array
    {
        return $this->textReplacements;
    }

    /**
     * Get current ignored entries
     */
    public function getIgnoredEntries(): array
    {
        return $this->ignoredEntries;
    }

    /**
     * Get current ignored patterns
     */
    public function getIgnoredPatterns(): array
    {
        return $this->ignoredPatterns;
    }

    /**
     * Load rules from configuration file.
     *
     * Theme-specific config merging is handled automatically by
     * ThemeConfigServiceProvider, so we just read from config('transcript_cleaning').
     */
    public function loadFromConfig(): void
    {
        $config = config('transcript_cleaning', []);

        if (isset($config['text_replacements'])) {
            $this->textReplacements = array_merge(
                $this->textReplacements,
                $config['text_replacements']
            );
        }

        if (isset($config['ignored_entries'])) {
            $this->ignoredEntries = array_merge(
                $this->ignoredEntries,
                $config['ignored_entries']
            );
        }

        if (isset($config['ignored_patterns'])) {
            $this->ignoredPatterns = array_merge(
                $this->ignoredPatterns,
                $config['ignored_patterns']
            );
        }
    }
}
