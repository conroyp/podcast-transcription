<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Transcript Cleaning Configuration
    |--------------------------------------------------------------------------
    |
    | This file contains rules for cleaning transcripts during ingestion.
    | Rules are applied to each transcript segment as it's processed.
    |
    | For podcast-specific rules, see config/transcript_cleaning_wdydy.php
    | as an example of a fully configured cleaning ruleset.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Text Replacements
    |--------------------------------------------------------------------------
    |
    | Key = incorrect text, Value = correct text
    | These are applied as case-insensitive string replacements.
    |
    | Common uses:
    | - Correcting speech-to-text misrecognitions of proper nouns
    | - Standardizing spellings of names, places, and terms
    | - Fixing common transcription errors specific to your content
    |
    | Example:
    | 'Jon Snow' => 'John Snow',
    | 'Westeros' => 'Westros',
    |
    */

    'text_replacements' => [
    ],

    /*
    |--------------------------------------------------------------------------
    | Ignored Entries
    |--------------------------------------------------------------------------
    |
    | Full text entries to completely skip (case-insensitive exact match).
    | These cause the entire transcript segment to be discarded.
    |
    | Common uses:
    | - Removing segments that are just music cues
    | - Removing transcription artifacts like "[INAUDIBLE]"
    | - Removing repetitive sound effects or non-speech content
    |
    | Example:
    | '[music]',
    | '[inaudible]',
    | 'thanks for watching',
    |
    */

    'ignored_entries' => [
        // Add entries to completely skip here
        // '[music]',
    ],

    /*
    |--------------------------------------------------------------------------
    | Ignored Patterns (Regex)
    |--------------------------------------------------------------------------
    |
    | Regular expression patterns for filtering out transcript segments.
    | If a segment matches any pattern, it will be discarded.
    |
    | Note: These are applied AFTER the built-in patterns:
    | - /^\([^)]*\)$/  - Text entirely in parentheses
    | - /^\[[^\]]*\]$/ - Text entirely in brackets
    | - /^[\s\-_\.]*$/ - Only whitespace/punctuation
    |
    | Example:
    | '/^\[.*\]$/',           // Any text in brackets
    | '/^♪.*♪$/',             // Music notation
    | '/^[A-Z]{2,}:/',        // Speaker labels like "HOST:"
    |
    */

    'ignored_patterns' => [
        // Add regex patterns for segments to skip
        // '/pattern/',
    ],
];
