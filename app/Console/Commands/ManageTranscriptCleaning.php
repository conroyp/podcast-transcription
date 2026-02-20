<?php

namespace App\Console\Commands;

use App\Services\TranscriptCleaningService;
use Illuminate\Console\Command;

class ManageTranscriptCleaning extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'transcript:cleaning
                            {action : The action to perform (list|test|add-replacement|add-ignore)}
                            {--search= : Text to search for (for add-replacement)}
                            {--replace= : Text to replace with (for add-replacement)}
                            {--entry= : Entry to ignore (for add-ignore)}
                            {--text= : Text to test cleaning on (for test)}';

    /**
     * The console command description.
     */
    protected $description = 'Manage transcript cleaning rules and test cleaning functionality';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $action = $this->argument('action');
        $cleaningService = app(TranscriptCleaningService::class);

        switch ($action) {
            case 'list':
                return $this->listRules($cleaningService);

            case 'test':
                return $this->testCleaning($cleaningService);

            case 'add-replacement':
                return $this->addReplacement($cleaningService);

            case 'add-ignore':
                return $this->addIgnore($cleaningService);

            default:
                $this->error("Unknown action: {$action}");
                $this->info('Available actions: list, test, add-replacement, add-ignore');

                return 1;
        }
    }

    private function listRules(TranscriptCleaningService $service): int
    {
        $this->info('Current Transcript Cleaning Rules:');
        $this->newLine();

        // Text replacements
        $replacements = $service->getTextReplacements();
        if (! empty($replacements)) {
            $this->info('📝 Text Replacements:');
            foreach ($replacements as $search => $replace) {
                $this->line("  '{$search}' → '{$replace}'");
            }
        } else {
            $this->info('📝 Text Replacements: (none)');
        }

        $this->newLine();

        // Ignored entries
        $ignored = $service->getIgnoredEntries();
        if (! empty($ignored)) {
            $this->info('🚫 Ignored Entries:');
            foreach ($ignored as $entry) {
                $this->line("  '{$entry}'");
            }
        } else {
            $this->info('🚫 Ignored Entries: (none)');
        }

        $this->newLine();

        // Ignored patterns
        $patterns = $service->getIgnoredPatterns();
        if (! empty($patterns)) {
            $this->info('🎯 Ignored Patterns:');
            foreach ($patterns as $pattern) {
                $this->line("  {$pattern}");
            }
        } else {
            $this->info('🎯 Ignored Patterns: (none)');
        }

        return 0;
    }

    private function testCleaning(TranscriptCleaningService $service): int
    {
        $text = $this->option('text');

        if (! $text) {
            $text = $this->ask('Enter text to test cleaning on:');
        }

        if (! $text) {
            $this->error('No text provided to test');

            return 1;
        }

        $this->info("Original text: '{$text}'");

        $result = $service->cleanTranscript($text);

        $this->info("Cleaned text: '{$result['text']}'");
        $this->info('Keep segment: '.($result['keep'] ? 'YES' : 'NO'));

        if ($result['text'] !== $text) {
            $this->info('✅ Text was modified during cleaning');
        } else {
            $this->info('ℹ️  Text was not modified');
        }

        return 0;
    }

    private function addReplacement(TranscriptCleaningService $service): int
    {
        $search = $this->option('search') ?: $this->ask('Enter text to search for:');
        $replace = $this->option('replace') ?: $this->ask('Enter replacement text:');

        if (! $search || ! $replace) {
            $this->error('Both search and replace text are required');

            return 1;
        }

        $this->info('Note: This command shows how to add rules. To permanently add rules,');
        $this->info('edit the config/transcript_cleaning.php file.');
        $this->newLine();

        $this->info('Add this to your config/transcript_cleaning.php file:');
        $this->line("'text_replacements' => [");
        $this->line('    // ...existing rules...');
        $this->line("    '{$search}' => '{$replace}',");
        $this->line('],');

        return 0;
    }

    private function addIgnore(TranscriptCleaningService $service): int
    {
        $entry = $this->option('entry') ?: $this->ask('Enter text entry to ignore:');

        if (! $entry) {
            $this->error('Entry text is required');

            return 1;
        }

        $this->info('Note: This command shows how to add rules. To permanently add rules,');
        $this->info('edit the config/transcript_cleaning.php file.');
        $this->newLine();

        $this->info('Add this to your config/transcript_cleaning.php file:');
        $this->line("'ignored_entries' => [");
        $this->line('    // ...existing rules...');
        $this->line("    '{$entry}',");
        $this->line('],');

        return 0;
    }
}
