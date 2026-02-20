<?php

namespace Tests\Unit\Services;

use App\Services\TranscriptCleaningService;
use Tests\TestCase;

class TranscriptCleaningServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'transcript_cleaning.text_replacements' => [
                'teh' => 'the',
                'recieve' => 'receive',
            ],
            'transcript_cleaning.ignored_entries' => [
                '[music]',
                '[applause]',
            ],
            'transcript_cleaning.ignored_patterns' => [],
        ]);
    }

    public function test_clean_transcript_trims_whitespace()
    {
        $service = new TranscriptCleaningService;

        $result = $service->cleanTranscript('  Hello world  ');

        $this->assertEquals('Hello world', $result['text']);
        $this->assertTrue($result['keep']);
    }

    public function test_clean_transcript_applies_text_replacements()
    {
        $service = new TranscriptCleaningService;

        $result = $service->cleanTranscript('I recieve teh package');

        $this->assertEquals('I receive the package', $result['text']);
        $this->assertTrue($result['keep']);
    }

    public function test_clean_transcript_ignores_configured_entries()
    {
        $service = new TranscriptCleaningService;

        $result = $service->cleanTranscript('[music]');

        $this->assertFalse($result['keep']);
    }

    public function test_clean_transcript_ignores_entries_case_insensitive()
    {
        $service = new TranscriptCleaningService;

        $result = $service->cleanTranscript('[MUSIC]');

        $this->assertFalse($result['keep']);
    }

    public function test_clean_transcript_ignores_parenthetical_text()
    {
        $service = new TranscriptCleaningService;

        $result = $service->cleanTranscript('(laughing)');

        $this->assertFalse($result['keep']);
    }

    public function test_clean_transcript_ignores_bracket_text()
    {
        $service = new TranscriptCleaningService;

        $result = $service->cleanTranscript('[inaudible]');

        $this->assertFalse($result['keep']);
    }

    public function test_clean_transcript_ignores_whitespace_only()
    {
        $service = new TranscriptCleaningService;

        $result = $service->cleanTranscript('   ---   ');

        $this->assertFalse($result['keep']);
    }

    public function test_clean_transcript_keeps_normal_text()
    {
        $service = new TranscriptCleaningService;

        $result = $service->cleanTranscript('This is normal speech text');

        $this->assertEquals('This is normal speech text', $result['text']);
        $this->assertTrue($result['keep']);
    }

    public function test_add_text_replacement_works()
    {
        $service = new TranscriptCleaningService;
        $service->addTextReplacement('colour', 'color');

        $result = $service->cleanTranscript('The colour is red');

        $this->assertEquals('The color is red', $result['text']);
        $this->assertTrue($result['keep']);
    }

    public function test_add_ignored_entry_works()
    {
        $service = new TranscriptCleaningService;
        $service->addIgnoredEntry('skip me');

        $result = $service->cleanTranscript('skip me');

        $this->assertFalse($result['keep']);
    }

    public function test_add_ignored_pattern_works()
    {
        $service = new TranscriptCleaningService;
        $service->addIgnoredPattern('/^INTRO:/');

        $result = $service->cleanTranscript('INTRO: Welcome to the show');

        $this->assertFalse($result['keep']);
    }

    public function test_get_text_replacements_returns_configured_values()
    {
        $service = new TranscriptCleaningService;

        $replacements = $service->getTextReplacements();

        $this->assertArrayHasKey('teh', $replacements);
        $this->assertEquals('the', $replacements['teh']);
    }

    public function test_get_ignored_entries_returns_configured_values()
    {
        $service = new TranscriptCleaningService;

        $entries = $service->getIgnoredEntries();

        $this->assertContains('[music]', $entries);
        $this->assertContains('[applause]', $entries);
    }

    public function test_get_ignored_patterns_returns_default_patterns()
    {
        $service = new TranscriptCleaningService;

        $patterns = $service->getIgnoredPatterns();

        $this->assertNotEmpty($patterns);
    }

    public function test_clean_transcript_handles_valid_utf8()
    {
        $service = new TranscriptCleaningService;

        $result = $service->cleanTranscript('Hello, world! Café résumé');

        $this->assertEquals('Hello, world! Café résumé', $result['text']);
        $this->assertTrue($result['keep']);
    }

    public function test_text_replacement_uses_word_boundaries()
    {
        $service = new TranscriptCleaningService;
        $service->addTextReplacement('mat', 'Max');

        // Should replace whole word
        $result = $service->cleanTranscript('mat said hello');
        $this->assertEquals('Max said hello', $result['text']);
    }

    public function test_text_replacement_does_not_replace_partial_words()
    {
        $service = new TranscriptCleaningService;
        $service->addTextReplacement('mat', 'Max');

        // Should NOT replace partial matches
        $result = $service->cleanTranscript('matt said hello');
        $this->assertEquals('matt said hello', $result['text']);

        // Should NOT replace partial match in longer word
        $result = $service->cleanTranscript('doormat here');
        $this->assertEquals('doormat here', $result['text']);

        // Should NOT replace partial match in compound word
        $result = $service->cleanTranscript('material is good');
        $this->assertEquals('material is good', $result['text']);
    }

    public function test_text_replacement_respects_punctuation_boundaries()
    {
        $service = new TranscriptCleaningService;
        $service->addTextReplacement('mat', 'Max');

        // Should replace word followed by punctuation
        $result = $service->cleanTranscript('mat, are you there?');
        $this->assertEquals('Max, are you there?', $result['text']);

        // Should replace word followed by period
        $result = $service->cleanTranscript('Hello mat.');
        $this->assertEquals('Hello Max.', $result['text']);

        // Should replace word with apostrophe
        $result = $service->cleanTranscript("mat's opinion matters");
        $this->assertEquals("Max's opinion matters", $result['text']);
    }

    public function test_text_replacement_at_string_boundaries()
    {
        $service = new TranscriptCleaningService;
        $service->addTextReplacement('mat', 'Max');

        // Should replace at start of string
        $result = $service->cleanTranscript('mat is here');
        $this->assertEquals('Max is here', $result['text']);

        // Should replace at end of string
        $result = $service->cleanTranscript('Hello mat');
        $this->assertEquals('Hello Max', $result['text']);

        // Should replace when only word
        $result = $service->cleanTranscript('mat');
        $this->assertEquals('Max', $result['text']);
    }

    public function test_text_replacement_is_case_insensitive()
    {
        $service = new TranscriptCleaningService;
        $service->addTextReplacement('mat', 'Max');

        $result = $service->cleanTranscript('MAT said hello');
        $this->assertEquals('Max said hello', $result['text']);

        $result = $service->cleanTranscript('Mat said hello');
        $this->assertEquals('Max said hello', $result['text']);
    }

    // =================================================================
    // Config-Driven Behavior Tests
    // =================================================================
    // Note: Theme-specific config merging is now handled by
    // ThemeConfigServiceProvider. These tests verify the service
    // reads from config correctly (config values set directly in tests).

    public function test_service_reads_text_replacements_from_config()
    {
        config([
            'transcript_cleaning.text_replacements' => [
                'config_word' => 'config_replacement',
            ],
        ]);

        $service = new TranscriptCleaningService;
        $replacements = $service->getTextReplacements();

        $this->assertArrayHasKey('config_word', $replacements);
        $this->assertEquals('config_replacement', $replacements['config_word']);

        $result = $service->cleanTranscript('config_word is here');
        $this->assertEquals('config_replacement is here', $result['text']);
    }

    public function test_service_reads_ignored_entries_from_config()
    {
        config([
            'transcript_cleaning.ignored_entries' => ['skip this text'],
        ]);

        $service = new TranscriptCleaningService;

        $result = $service->cleanTranscript('skip this text');
        $this->assertFalse($result['keep']);

        $result = $service->cleanTranscript('keep this text');
        $this->assertTrue($result['keep']);
    }

    public function test_multiple_replacements_from_config()
    {
        config([
            'transcript_cleaning.text_replacements' => [
                'word_a' => 'replacement_a',
                'word_b' => 'replacement_b',
            ],
        ]);

        $service = new TranscriptCleaningService;

        $result = $service->cleanTranscript('word_a and word_b together');
        $this->assertEquals('replacement_a and replacement_b together', $result['text']);
    }
}
