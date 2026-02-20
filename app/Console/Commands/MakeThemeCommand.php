<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class MakeThemeCommand extends Command
{
    protected $signature = 'podcast:make-theme
                            {name : Theme identifier (lowercase letters, numbers, hyphens, underscores)}';

    protected $description = 'Scaffold a new podcast theme with config stubs, view partials, and CSS';

    public function handle(): int
    {
        $name = strtolower($this->argument('name'));

        if (! preg_match('/^[a-z][a-z0-9_-]*$/', $name)) {
            $this->error('Theme name must start with a letter and contain only lowercase letters, numbers, hyphens, and underscores.');

            return self::FAILURE;
        }

        if ($name === 'base') {
            $this->error("'base' is reserved. Choose a different name.");

            return self::FAILURE;
        }

        if (File::isDirectory(config_path($name))) {
            $this->warn("Config directory config/{$name}/ already exists.");

            if (! $this->confirm('Continue and overwrite existing files?')) {
                return self::FAILURE;
            }
        }

        $this->info("Scaffolding theme: <fg=cyan>{$name}</>");
        $this->newLine();

        $this->createConfigFiles($name);
        $this->createViewPartials($name);
        $this->createCssFile($name);
        $this->printNextSteps($name);

        return self::SUCCESS;
    }

    private function createConfigFiles(string $name): void
    {
        $configDir = config_path($name);
        File::ensureDirectoryExists($configDir);

        File::put("{$configDir}/podcast.php", $this->podcastConfigStub($name));
        $this->line("  <fg=green>✓</> config/{$name}/podcast.php");

        File::put("{$configDir}/ad_removal.php", $this->adRemovalConfigStub());
        $this->line("  <fg=green>✓</> config/{$name}/ad_removal.php");

        File::put("{$configDir}/transcript_cleaning.php", $this->transcriptCleaningConfigStub());
        $this->line("  <fg=green>✓</> config/{$name}/transcript_cleaning.php");
    }

    private function createViewPartials(string $name): void
    {
        $viewDir = resource_path("views/themes/{$name}/partials");
        File::ensureDirectoryExists($viewDir);

        $partials = [
            'header-title' => $this->headerTitlePartialStub(),
            'footer-links' => $this->footerLinksPartialStub(),
            'social-links' => $this->socialLinksPartialStub(),
            'search-tips' => $this->searchTipsPartialStub(),
            'about-content' => $this->aboutContentPartialStub(),
        ];

        foreach ($partials as $partial => $content) {
            File::put("{$viewDir}/{$partial}.blade.php", $content);
            $this->line("  <fg=green>✓</> resources/views/themes/{$name}/partials/{$partial}.blade.php");
        }
    }

    private function createCssFile(string $name): void
    {
        $cssPath = resource_path("css/themes/{$name}.css");
        File::put($cssPath, $this->cssStub($name));
        $this->line("  <fg=green>✓</> resources/css/themes/{$name}.css");
    }

    private function podcastConfigStub(string $name): string
    {
        $rssUrl = config('podcast.default_rss_url', '');
        $podcastName = config('podcast.name', 'My Podcast');
        $hosts = config('podcast.hosts', 'Host Name');
        $transcriptionContext = config('podcast.transcription_context', 'Podcast transcript.');

        return <<<PHP
<?php

/**
 * Podcast configuration for the '{$name}' theme.
 * These values are merged with and override the base config/podcast.php.
 *
 * Set the corresponding environment variables in your .env file:
 *   PODCAST_DEFAULT_RSS_URL=https://your-feed-url.com/feed
 *   PODCAST_NAME="Your Podcast Name"
 *   PODCAST_HOSTS="Host One, Host Two"
 */
return [
    'default_rss_url' => '{$rssUrl}',

    'name' => '{$podcastName}',

    'hosts' => '{$hosts}',

    /**
     * Context provided to the Whisper transcription engine.
     * Include host names, proper nouns, and domain-specific terms.
     * More context = better transcription accuracy for specialist vocabulary.
     */
    'transcription_context' => '{$transcriptionContext}',

    /**
     * Keywords used to classify episode types from title patterns.
     * Map episode type slug => array of title keywords (case-insensitive).
     */
    'episode_type_keywords' => [
        // 'bonus' => ['bonus episode', 'extra'],
    ],
];
PHP;
    }

    private function adRemovalConfigStub(): string
    {
        return <<<'PHP'
<?php

/**
 * Ad removal pattern configuration for this theme.
 *
 * Add regex patterns that match the start and end of advertising segments
 * in your podcast's transcripts. The service will remove transcript segments
 * between a matching start and end pattern.
 *
 * Patterns use PHP regex syntax and are matched case-insensitively.
 */
return [
    'enabled' => true,

    /**
     * Patterns that mark the start of an ad break.
     * Example: '/this episode is brought to you by/i'
     */
    'start_patterns' => [
        // '/your ad intro phrase/i',
    ],

    /**
     * Patterns that mark the end of an ad break.
     * Example: '/now back to the show/i'
     */
    'end_patterns' => [
        // '/your ad outro phrase/i',
    ],
];
PHP;
    }

    private function transcriptCleaningConfigStub(): string
    {
        return <<<'PHP'
<?php

/**
 * Transcript cleaning configuration for this theme.
 *
 * Add text replacements to fix common transcription errors specific to
 * your podcast — host name spellings, recurring terms, etc.
 *
 * Keys are matched as whole words (case-insensitive).
 * Values replace the matched text exactly as written.
 */
return [
    'text_replacements' => [
        // 'whisper missspelling' => 'Correct Spelling',
        // 'misheard term' => 'What Was Actually Said',
    ],
];
PHP;
    }

    private function headerTitlePartialStub(): string
    {
        return <<<'BLADE'
{{--
    Header title partial — customise this to match your podcast's branding.
    The $branding variable is available from ThemeService::getBranding().
    The $podcastName variable is also available in all views.
--}}
<a href="/">
    <h1 class="text-3xl font-bold" style="color: var(--brand-primary);">
        {{ $branding['site_name'] ?? $podcastName }}
    </h1>
</a>
BLADE;
    }

    private function footerLinksPartialStub(): string
    {
        return <<<'BLADE'
{{--
    Footer links partial — add links to your podcast website, social profiles,
    or related projects. Remove this file entirely to hide the footer links section.
--}}
<div class="flex flex-wrap gap-4 text-sm text-gray-500 dark:text-gray-400">
    {{-- <a href="https://yourpodcast.com" class="hover:underline">Podcast Website</a> --}}
</div>
BLADE;
    }

    private function socialLinksPartialStub(): string
    {
        return <<<'BLADE'
{{--
    Social links partial — add your podcast's social media links.
    Remove this file entirely to hide the social links section.
--}}
<div class="flex gap-3">
    {{-- <a href="https://twitter.com/yourpodcast" target="_blank" rel="noopener" class="hover:underline text-sm text-gray-500 dark:text-gray-400">Twitter / X</a> --}}
    {{-- <a href="https://bsky.app/profile/yourpodcast.bsky.social" target="_blank" rel="noopener" class="hover:underline text-sm text-gray-500 dark:text-gray-400">Bluesky</a> --}}
</div>
BLADE;
    }

    private function searchTipsPartialStub(): string
    {
        return <<<'BLADE'
{{--
    Search tips partial — shown to help users get better results.
    Customise the example queries with memorable moments from your podcast.

    Available variables: $searchConfig, $branding, $podcastName
--}}
<div class="bg-blue-50 p-4 rounded-lg text-sm dark:bg-blue-950">
    <h4 class="font-semibold text-blue-900 dark:text-blue-100 mb-2">How to Search:</h4>
    <ul class="space-y-1 text-blue-800 dark:text-blue-200">
        <li><strong>Simple search:</strong> Type any word or phrase</li>
        @if($searchConfig['show_episode_types'] ?? false)
            <li><strong>Filter by type:</strong> Choose between episode types</li>
        @endif
        <li><strong>Sort results:</strong> By relevance, newest, or oldest first</li>
        <li><strong>Longer queries:</strong> Multi-word queries tend to work better than single words</li>
        <li><strong>Browse episodes:</strong> Use the <a class="underline hover:font-bold" href="/?view=episodes">episodes archive</a> to browse all episodes.</li>
    </ul>
</div>
BLADE;
    }

    private function aboutContentPartialStub(): string
    {
        return <<<'BLADE'
{{--
    About content partial — shown in the info/about modal.
    Describe your podcast, what the search covers, and any caveats.

    Available variables: $branding, $podcastName
--}}
<div class="prose dark:prose-invert max-w-none">
    <h2>About {{ $branding['site_name'] ?? $podcastName }}</h2>
    <p>
        Search the full transcript archive of {{ $podcastName }}.
        Find any moment, quote, or topic discussed across all episodes.
    </p>
    <p class="text-sm text-gray-500 dark:text-gray-400 mt-4">
        Transcripts are generated automatically using Whisper and may contain errors.
    </p>
</div>
BLADE;
    }

    private function cssStub(string $name): string
    {
        $displayName = ucwords(str_replace(['-', '_'], ' ', $name));

        return <<<CSS
/**
 * {$displayName} Theme
 *
 * Override CSS custom properties from base.css to match your podcast's brand.
 * Only include variables you want to change — all others fall back to base.css.
 *
 * See resources/css/themes/base.css for the full list of available variables.
 *
 * Apply this theme by setting:  THEME_DEFAULT={$name}
 */

[data-theme="{$name}"] {
    /* --- Brand colours --- */
    --brand-primary: #3b82f6;          /* Change to your brand colour */
    --brand-secondary: #2563eb;        /* Hover state */
    --brand-accent: #1d4ed8;           /* Active state */

    --header-gradient-from: #3b82f6;
    --header-gradient-to: #1d4ed8;

    /* --- Typography --- */
    /* --font-heading: 'Your Font', system-ui, sans-serif; */
    /* --font-body: 'Your Font', system-ui, sans-serif; */
}

[data-theme="{$name}"].dark {
    /* Dark mode overrides — add any colour adjustments for dark mode here */
}
CSS;
    }

    private function printNextSteps(string $name): void
    {
        $this->newLine();
        $this->line('<fg=yellow;options=bold>Next steps:</>');
        $this->newLine();

        $this->line('<fg=yellow>1. Register the theme in config/theme.php</>');
        $this->line("   Add this entry to the <fg=cyan>'themes'</> array:");
        $this->newLine();
        $this->line("   <fg=cyan>'{$name}'</> => [");
        $this->line("       <fg=cyan>'name'</> => 'My Podcast Name',");
        $this->line("       <fg=cyan>'css_file'</> => '{$name}.css',");
        $this->line("       <fg=cyan>'fonts'</> => [");
        $this->line("           // 'https://fonts.bunny.net/css?family=my-font:400,700',");
        $this->line('       ],');
        $this->line('   ],');
        $this->newLine();

        $this->line('<fg=yellow>2. Configure your .env file</>');
        $this->line("   <fg=cyan>THEME_DEFAULT={$name}</>");
        $this->line('   <fg=cyan>PODCAST_DEFAULT_RSS_URL=https://your-feed-url.com/feed</>');
        $this->line('   <fg=cyan>PODCAST_NAME="Your Podcast Name"</>');
        $this->line('   <fg=cyan>PODCAST_HOSTS="Host One, Host Two"</>');
        $this->line('   <fg=cyan>THEME_SITE_NAME="Your Podcast Search"</>');
        $this->line('   <fg=cyan>THEME_TAGLINE="Search every episode transcript"</>');
        $this->newLine();

        $this->line('<fg=yellow>3. Customise the generated files</>');
        $this->table(
            ['File', 'What to edit'],
            [
                ["config/{$name}/podcast.php", 'RSS feed URL, podcast name, Whisper transcription context'],
                ["config/{$name}/ad_removal.php", 'Regex patterns to remove ad segments from transcripts'],
                ["config/{$name}/transcript_cleaning.php", 'Text replacements for transcription errors (host names, etc.)'],
                ["resources/css/themes/{$name}.css", 'Brand colours and CSS variable overrides'],
                ["resources/views/themes/{$name}/partials/header-title.blade.php", 'Site title styling in the header'],
                ["resources/views/themes/{$name}/partials/about-content.blade.php", 'About modal description of your podcast'],
                ["resources/views/themes/{$name}/partials/search-tips.blade.php", 'Help text and example searches shown to users'],
                ["resources/views/themes/{$name}/partials/social-links.blade.php", 'Social media profile links'],
                ["resources/views/themes/{$name}/partials/footer-links.blade.php", 'Footer navigation links'],
            ]
        );

        $this->line('<fg=yellow>4. Clear the config cache and fetch your first episodes</>');
        $this->line('   php artisan config:clear');
        $this->line('   php artisan podcast:fetch --process');
        $this->newLine();

        $this->info('See CLAUDE.md for full documentation on the theme system.');
    }
}
