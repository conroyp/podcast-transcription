# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

This is a Laravel 12-based podcast transcription and processing system that automatically ingests podcast RSS feeds, downloads episodes, transcribes them using Whisper, and prepares transcripts for semantic search using OpenAI embeddings with PostgreSQL pgvector.

**Primary Use Case**: Automated pipeline for "What Did You Do Yesterday" podcast and similar shows.

## Development Commands

### Running the Application

```bash
# Development mode (runs server, queue, logs, and Vite concurrently)
composer dev

# Queue worker with extended timeout for long transcriptions
php artisan queue:work --timeout=3600

# Automated processing script (queue + ingest episodes)
./process.sh
```

### Testing

```bash
# Run all tests
composer test
# Or directly:
php artisan test

# Run specific test
php artisan test --filter=DashboardTest
```

### Database

```bash
# Run migrations
php artisan migrate

# PostgreSQL setup with pgvector extension
psql -h 127.0.0.1 -U postgres -d podcast_archive -c "CREATE EXTENSION IF NOT EXISTS vector;"

# Reset database and storage (destructive)
php artisan podcast:reset
```

### Frontend

```bash
# Build assets
npm run build

# Development server
npm run dev
```

## Core Architecture

### Processing Pipeline

The system follows a multi-stage pipeline for each episode:

```
RSS Feed → Episode Discovery → Audio Download → Whisper Transcription →
Ad Removal → Transcript Cleaning → Content Chunking → Embedding Generation
```

### Key Models

- **Podcast**: RSS feed metadata and configuration
- **Episode**: Individual episode data with processing status tracking
- **TranscriptSegment**: Time-stamped transcript segments with embeddings (pgvector)
- **QueryEmbedding**: Cached query embedding vectors to avoid redundant OpenAI API calls
- **Speaker**: Speaker identification (future use)
- **Feed**: RSS feed management (for sync operations)

### Services Architecture

Located in `app/Services/`:

- **TranscriptionService**: Core transcription orchestrator. Supports two engines:
  - **Faster-Whisper** (default): Python-based via `scripts/transcribe.py` using `large-v3-turbo` model
  - **whisper.cpp**: Alternative C++ implementation (legacy)
  - Toggle via `$useFasterWhisper` property in app/Services/TranscriptionService.php:21

- **PodcastDownloadService**: Downloads MP3 files with progress tracking
- **RssFeedService**: RSS parsing and episode discovery
- **AdRemovalService**: Pattern-based ad detection (config-driven, supports theme overrides)
- **TranscriptCleaningService**: Text replacements and filtering (config-driven, supports theme overrides)
- **TranscriptChunkingService**: Intelligently segments transcripts for embedding-friendly chunks
- **EmbeddingService**: OpenAI API integration for generating vector embeddings, semantic similarity search, query embedding caching
- **HybridSearchService**: Combines keyword (PostgreSQL `~*` word-boundary regex) and semantic (pgvector cosine distance) scoring in a single query. Used by both the web UI and CLI.
- **ThemeService**: Theme detection, branding, and CSS management
- **SearchCacheService**: Caching layer for search queries
- **SeoService**: SEO metadata generation

### Jobs

Located in `app/Jobs/`:

- **ProcessEpisodeCompleteJob**: Unified pipeline job (download → transcribe → clean → chunk → embed)
  - Timeout: 3900 seconds (65 minutes)
  - Single retry attempt
  - Configurable options: `force_download`, `force_transcription`, `skip_ad_removal`, `skip_chunking`, `skip_embedding`
- **ReEvaluateEmbeddingsJob**: Regenerate embeddings for existing segments
- **RecalculateSegmentEmbeddingJob**: Recalculate embedding for a single segment
- **SyncEpisodeJob**: Sync episode data between machines

### Artisan Commands

**Feed Management:**
- `podcast:fetch [--process]` - Fetch new episodes from RSS feed
- `podcast:list` - List all episodes with status
- `podcast:debug {episode_id}` - Debug specific episode
- `podcast:ingest-complete --oldest-first --limit=N` - Complete ingestion pipeline (recommended)
- `podcast:ingest-all --oldest-first --limit=N` - Ingest all episodes

**Episode Processing:**
- `podcast:download {episode_id}` - Download audio
- `podcast:transcribe {episode_id}` - Transcribe audio
- `podcast:process-episode {episode_id}` - Full pipeline (download + transcribe + clean + chunk)

**Content Processing:**
- `podcast:remove-ads {episode_id} [--dry-run]` - Remove ads
- `podcast:clean-transcripts {episode_id|--all} [--dry-run]` - Clean formatting
- `podcast:chunk-transcripts {episode_id|--all} [--dry-run] [--stats]` - Chunk for embeddings

**Embeddings & Search:**
- `podcast:generate-embeddings [episode_id] [--batch-size=50] [--failed-only] [--stats]`
- `podcast:search "query" [--limit=10] [--threshold=0.7] [--detailed]` - Hybrid keyword + semantic search
- `podcast:test-embedding-setup` - Test infrastructure

**Export/Import (Multi-Machine Workflow):**
- `episode:status [--ready|--stats|--processing|--failed]` - Check episode status
- `episode:export {episode_id} [--compress] [--include-audio] [--output=/path]`
- `episode:export-all [--compress] [--include-audio] [--filter=failed] [--limit=N]`
- `episode:import /path/to/export.json.gz [--force] [--skip-audio] [--dry-run]`

**Sync Operations:**
- `sync:export {episode|feed|podcast} {id} --endpoint=https://remote.com/api/import-sync`

**Utilities:**
- `podcast:manage-cleaning` - Interactive transcript management
- `clear:search-cache` - Clear search cache

### Scheduled Tasks

Configured in `routes/console.php`:

```php
Schedule::command('podcast:fetch --process')
    ->hourly()
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/podcast-fetch.log'));
```

**Production Cron Setup:**
```bash
* * * * * cd <project-root> && php artisan schedule:run >> /dev/null 2>&1
```

## Transcription Engine Details

### Faster-Whisper (Default)

- Python script: `scripts/transcribe.py`
- Model: `large-v3-turbo`
- Compute: CPU with `int8` quantization
- Workers: Auto-detected CPU count
- Features: VAD filtering, word timestamps, beam_size=1 (greedy)
- Output: JSON compatible with whisper.cpp format + processing metrics (RTF, duration, etc.)

### Switching Engines

Edit `app/Services/TranscriptionService.php` line 21:
```php
private bool $useFasterWhisper = true; // false for whisper.cpp
```

### Updating Faster-Whisper

```bash
cd <project-root>
uv venv --python 3.12
source .venv/bin/activate
pip install faster-whisper
```

## Status Tracking

Episodes track processing through multiple status fields:
- `download_status`: pending → downloading → completed/failed
- `transcription_status`: pending → processing → completed/failed
- `diarization_status`: pending → processing → completed/failed
- `embedding_status` (on segments): pending → processing → completed/failed

## Storage Locations

- **Audio Files**: `storage/app/private/podcasts/audio/{episode_id}.mp3`
- **Transcripts**: `storage/app/private/podcasts/transcripts/{episode_id}.json`
- **Logs**: `storage/logs/laravel.log`
- **Scheduled Task Logs**: `storage/logs/podcast-fetch.log`

## Database Configuration

This project requires **PostgreSQL 17+ with pgvector extension** (not SQLite as shown in .env.example):

```env
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=podcast_archive
DB_USERNAME=postgres
DB_PASSWORD=
```

Key tables:
- `episodes` - Episode metadata and processing status
- `transcript_segments` - Segments with `embedding_vector(1536)` pgvector column (keyword search uses `~*` regex on `text` column directly, no tsvector)
- `query_embeddings` - Cached query embedding vectors with usage tracking
- `podcasts` - Podcast/RSS feed configuration

## Environment Variables

**Required:**
- `OPENAI_API_KEY` - For embedding generation
- `DB_*` - PostgreSQL connection (see above)

**Optional:**
- `SHARED_UPLOAD_SECRET` - For sync operations between machines
- `WHISPER_MODEL` - Model size (if using whisper.cpp)
- `EMBEDDING_MODEL` - Default: `text-embedding-3-small`
- `EMBEDDING_DIMENSIONS` - Default: `1536`

## Multi-Machine Workflow

**Typical Scenario**: Powerful transcription machine → Production hosting server

**On Transcription Machine:**
```bash
php artisan episode:status --ready
php artisan episode:export-all --compress --output=/transfer/directory
# Transfer files to production
```

**On Production Machine:**
```bash
cd /transfer/directory
./import-all.sh  # Auto-generated import script
# Or: php artisan episode:import episode-123.json.gz --force
```

Export files include: episode metadata, transcript segments, embeddings, podcast info. Audio is optional (large files).

## Helper Scripts

- **process.sh**: Starts queue worker and runs `podcast:ingest-all`
- **upload.sh**: Database backup and SCP to remote server (pg_dump)

## Theme Configuration

The application supports multiple themes with automatic config merging. Themes control branding, styling, and podcast-specific configurations.

### How Themes Work

1. Set the active theme via `THEME_DEFAULT` in `.env` (e.g., `THEME_DEFAULT=wdydy`)
2. `ThemeConfigServiceProvider` automatically merges theme-specific configs on boot
3. Services read from `config('key')` and get the merged result transparently

### Theme Directory Structure

```
config/
├── ad_removal.php              # Base ad removal config
├── podcast.php                 # Base podcast config
├── transcript_cleaning.php     # Base transcript cleaning config
├── theme.php                   # Theme definitions and branding
└── wdydy/                     # Theme-specific overrides
    ├── ad_removal.php          # Theme ad removal patterns
    ├── podcast.php             # Theme podcast settings (RSS URL, etc.)
    └── transcript_cleaning.php # Theme text replacements
```

### Config Merging Behavior

- Theme configs are **deep merged** with base configs
- Theme values **override** base values for matching keys
- Base keys not in theme config are **preserved**
- Only `.php` files in `config/{theme}/` are processed

### Available Theme Overrides

| Config File | Purpose | Common Overrides |
|-------------|---------|------------------|
| `podcast.php` | Podcast metadata | `default_rss_url`, `name`, `hosts`, `transcription_context` |
| `ad_removal.php` | Ad detection patterns | `start_patterns`, `end_patterns`, `enabled` |
| `transcript_cleaning.php` | Text corrections | `text_replacements` (name spellings, etc.) |

### Creating a Custom Theme

1. **Create the theme directory:**
   ```bash
   mkdir config/mytheme
   ```

2. **Register the theme in `config/theme.php`:**
   ```php
   'themes' => [
       // ... existing themes
       'mytheme' => [
           'name' => 'My Theme',
           'css_file' => 'mytheme.css',
           'fonts' => [
               'https://fonts.bunny.net/css?family=my-font:400,700',
           ],
       ],
   ],
   ```

3. **Create theme CSS file:**
   ```bash
   touch resources/css/themes/mytheme.css
   ```

   Add custom CSS variables and styles (see `resources/css/themes/wdydy.css` for example).

4. **Add theme-specific configs** (only override what you need):

   **`config/mytheme/podcast.php`:**
   ```php
   <?php
   return [
       'default_rss_url' => 'https://example.com/feed.xml',
       'name' => 'My Podcast',
       'hosts' => 'Host Name',
       'transcription_context' => 'Context for Whisper transcription...',
   ];
   ```

   **`config/mytheme/transcript_cleaning.php`:**
   ```php
   <?php
   return [
       'text_replacements' => [
           'misspelling' => 'Correct Spelling',
           'host name typo' => 'Host Name',
       ],
   ];
   ```

   **`config/mytheme/ad_removal.php`:**
   ```php
   <?php
   return [
       'enabled' => true,
       'start_patterns' => [
           '/sponsor.*message/i',
       ],
       'end_patterns' => [
           '/back to the show/i',
       ],
   ];
   ```

5. **Activate the theme:**
   ```env
   THEME_DEFAULT=mytheme
   ```

6. **Clear config cache:**
   ```bash
   php artisan config:clear
   ```

### Theme Environment Variables

These can be set in `.env` to customize branding without code changes:

```env
THEME_DEFAULT=wdydy
THEME_SITE_NAME="My Podcast Search"
THEME_TAGLINE="Search transcripts"
THEME_SEARCH_PLACEHOLDER="What are you looking for?"
THEME_SEO_HOME_TITLE="My Podcast - Search"
THEME_COLOR_PRIMARY=#ff6b6b
THEME_COLOR_SECONDARY=#4ecdc4
```

## Livewire Components

The application uses Laravel Livewire with Flux UI components for the frontend. Key Livewire pages are in `app/Livewire/` and views in `resources/views/`.

## Ad Removal

Ad removal is config-driven and supports theme overrides. Base patterns are in `config/ad_removal.php`, theme-specific patterns in `config/{theme}/ad_removal.php`.

Built-in patterns include sponsor mentions and podcast network promos. Add custom patterns by creating or editing theme config files (see Theme Configuration section).

## Common Issues

**UTF-8 Encoding**: Fixed in TranscriptionService with `mb_convert_encoding` for malformed Whisper JSON output.

**NOT NULL Constraint Errors**: Resolved in migration `2025_06_30_192049_update_transcript_segments_decimal_to_double_fixed.php` for proper decimal handling.

**Long Processing Times**: Transcription takes ~2-3x audio duration. Ensure queue workers have sufficient timeout (3600+ seconds).

**Memory Usage**: Peak ~2GB during transcription with base model. Monitor with `top`.

## Performance Notes

- **Transcription**: ~2-3x audio duration depending on hardware
- **Storage**: ~50MB per hour of audio + ~2MB per transcript
- **Chunking**: Reduces segment count by ~80% (e.g., 2000→400 segments)
- **Export Files**: 1-10MB without audio, 50-200MB with audio (compressed)

## Testing Commands

Always use `--dry-run` flags when testing bulk operations:

```bash
php artisan podcast:remove-ads 1 --dry-run
php artisan podcast:chunk-transcripts --all --dry-run --stats
```

## Important Notes

- Queue workers MUST be running for processing to occur (`php artisan queue:work`)
- Use `--oldest-first` flag for historical backfill
- Use `--compress` for exports to reduce file size by ~80%
- Jobs have extended timeouts due to long transcription times
- The system uses `withoutOverlapping()` to prevent concurrent scheduled runs

===

<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

The Laravel Boost guidelines are specifically curated by Laravel maintainers for this application. These guidelines should be followed closely to enhance the user's satisfaction building Laravel applications.

## Foundational Context
This application is a Laravel application and its main Laravel ecosystems package & versions are below. You are an expert with them all. Ensure you abide by these specific packages & versions.

- php - 8.4.3
- laravel/framework (LARAVEL) - v12
- laravel/prompts (PROMPTS) - v0
- livewire/flux (FLUXUI_FREE) - v2
- livewire/livewire (LIVEWIRE) - v3
- livewire/volt (VOLT) - v1
- laravel/mcp (MCP) - v0
- laravel/pint (PINT) - v1
- laravel/sail (SAIL) - v1
- pestphp/pest (PEST) - v3
- phpunit/phpunit (PHPUNIT) - v11
- tailwindcss (TAILWINDCSS) - v4


## Conventions
- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts
- Do not create verification scripts or tinker when tests cover that functionality and prove it works. Unit and feature tests are more important.

## Application Structure & Architecture
- Stick to existing directory structure - don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling
- If the user doesn't see a frontend change reflected in the UI, it could mean they need to run `npm run build`, `npm run dev`, or `composer run dev`. Ask them.

## Replies
- Be concise in your explanations - focus on what's important rather than explaining obvious details.

## Documentation Files
- You must only create documentation files if explicitly requested by the user.


=== boost rules ===

## Laravel Boost
- Laravel Boost is an MCP server that comes with powerful tools designed specifically for this application. Use them.

## Artisan
- Use the `list-artisan-commands` tool when you need to call an Artisan command to double check the available parameters.

## URLs
- Whenever you share a project URL with the user you should use the `get-absolute-url` tool to ensure you're using the correct scheme, domain / IP, and port.

## Tinker / Debugging
- You should use the `tinker` tool when you need to execute PHP to debug code or query Eloquent models directly.
- Use the `database-query` tool when you only need to read from the database.

## Reading Browser Logs With the `browser-logs` Tool
- You can read browser logs, errors, and exceptions using the `browser-logs` tool from Boost.
- Only recent browser logs will be useful - ignore old logs.

## Searching Documentation (Critically Important)
- Boost comes with a powerful `search-docs` tool you should use before any other approaches. This tool automatically passes a list of installed packages and their versions to the remote Boost API, so it returns only version-specific documentation specific for the user's circumstance. You should pass an array of packages to filter on if you know you need docs for particular packages.
- The 'search-docs' tool is perfect for all Laravel related packages, including Laravel, Inertia, Livewire, Filament, Tailwind, Pest, Nova, Nightwatch, etc.
- You must use this tool to search for Laravel-ecosystem documentation before falling back to other approaches.
- Search the documentation before making code changes to ensure we are taking the correct approach.
- Use multiple, broad, simple, topic based queries to start. For example: `['rate limiting', 'routing rate limiting', 'routing']`.
- Do not add package names to queries - package information is already shared. For example, use `test resource table`, not `filament 4 test resource table`.

### Available Search Syntax
- You can and should pass multiple queries at once. The most relevant results will be returned first.

1. Simple Word Searches with auto-stemming - query=authentication - finds 'authenticate' and 'auth'
2. Multiple Words (AND Logic) - query=rate limit - finds knowledge containing both "rate" AND "limit"
3. Quoted Phrases (Exact Position) - query="infinite scroll" - Words must be adjacent and in that order
4. Mixed Queries - query=middleware "rate limit" - "middleware" AND exact phrase "rate limit"
5. Multiple Queries - queries=["authentication", "middleware"] - ANY of these terms


=== php rules ===

## PHP

- Always use curly braces for control structures, even if it has one line.

### Constructors
- Use PHP 8 constructor property promotion in `__construct()`.
    - <code-snippet>public function __construct(public GitHub $github) { }</code-snippet>
- Do not allow empty `__construct()` methods with zero parameters.

### Type Declarations
- Always use explicit return type declarations for methods and functions.
- Use appropriate PHP type hints for method parameters.

<code-snippet name="Explicit Return Types and Method Params" lang="php">
protected function isAccessible(User $user, ?string $path = null): bool
{
    ...
}
</code-snippet>

## Comments
- Prefer PHPDoc blocks over comments. Never use comments within the code itself unless there is something _very_ complex going on.

## PHPDoc Blocks
- Add useful array shape type definitions for arrays when appropriate.

## Enums
- Typically, keys in an Enum should be TitleCase. For example: `FavoritePerson`, `BestLake`, `Monthly`.


=== laravel/core rules ===

## Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using the `list-artisan-commands` tool.
- If you're creating a generic PHP class, use `artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Database
- Always use proper Eloquent relationship methods with return type hints. Prefer relationship methods over raw queries or manual joins.
- Use Eloquent models and relationships before suggesting raw database queries
- Avoid `DB::`; prefer `Model::query()`. Generate code that leverages Laravel's ORM capabilities rather than bypassing them.
- Generate code that prevents N+1 query problems by using eager loading.
- Use Laravel's query builder for very complex database operations.

### Model Creation
- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `list-artisan-commands` to check the available options to `php artisan make:model`.

### APIs & Eloquent Resources
- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

### Controllers & Validation
- Always create Form Request classes for validation rather than inline validation in controllers. Include both validation rules and custom error messages.
- Check sibling Form Requests to see if the application uses array or string based validation rules.

### Queues
- Use queued jobs for time-consuming operations with the `ShouldQueue` interface.

### Authentication & Authorization
- Use Laravel's built-in authentication and authorization features (gates, policies, Sanctum, etc.).

### URL Generation
- When generating links to other pages, prefer named routes and the `route()` function.

### Configuration
- Use environment variables only in configuration files - never use the `env()` function directly outside of config files. Always use `config('app.name')`, not `env('APP_NAME')`.

### Testing
- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] <name>` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

### Vite Error
- If you receive an "Illuminate\Foundation\ViteException: Unable to locate file in Vite manifest" error, you can run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.


=== laravel/v12 rules ===

## Laravel 12

- Use the `search-docs` tool to get version specific documentation.
- Since Laravel 11, Laravel has a new streamlined file structure which this project uses.

### Laravel 12 Structure
- No middleware files in `app/Http/Middleware/`.
- `bootstrap/app.php` is the file to register middleware, exceptions, and routing files.
- `bootstrap/providers.php` contains application specific service providers.
- **No app\Console\Kernel.php** - use `bootstrap/app.php` or `routes/console.php` for console configuration.
- **Commands auto-register** - files in `app/Console/Commands/` are automatically available and do not require manual registration.

### Database
- When modifying a column, the migration must include all of the attributes that were previously defined on the column. Otherwise, they will be dropped and lost.
- Laravel 11 allows limiting eagerly loaded records natively, without external packages: `$query->latest()->limit(10);`.

### Models
- Casts can and likely should be set in a `casts()` method on a model rather than the `$casts` property. Follow existing conventions from other models.


=== fluxui-free/core rules ===

## Flux UI Free

- This project is using the free edition of Flux UI. It has full access to the free components and variants, but does not have access to the Pro components.
- Flux UI is a component library for Livewire. Flux is a robust, hand-crafted, UI component library for your Livewire applications. It's built using Tailwind CSS and provides a set of components that are easy to use and customize.
- You should use Flux UI components when available.
- Fallback to standard Blade components if Flux is unavailable.
- If available, use Laravel Boost's `search-docs` tool to get the exact documentation and code snippets available for this project.
- Flux UI components look like this:

<code-snippet name="Flux UI Component Usage Example" lang="blade">
    <flux:button variant="primary"/>
</code-snippet>


### Available Components
This is correct as of Boost installation, but there may be additional components within the codebase.

<available-flux-components>
avatar, badge, brand, breadcrumbs, button, callout, checkbox, dropdown, field, heading, icon, input, modal, navbar, profile, radio, select, separator, switch, text, textarea, tooltip
</available-flux-components>


=== livewire/core rules ===

## Livewire Core
- Use the `search-docs` tool to find exact version specific documentation for how to write Livewire & Livewire tests.
- Use the `php artisan make:livewire [Posts\\CreatePost]` artisan command to create new components
- State should live on the server, with the UI reflecting it.
- All Livewire requests hit the Laravel backend, they're like regular HTTP requests. Always validate form data, and run authorization checks in Livewire actions.

## Livewire Best Practices
- Livewire components require a single root element.
- Use `wire:loading` and `wire:dirty` for delightful loading states.
- Add `wire:key` in loops:

    ```blade
    @foreach ($items as $item)
        <div wire:key="item-{{ $item->id }}">
            {{ $item->name }}
        </div>
    @endforeach
    ```

- Prefer lifecycle hooks like `mount()`, `updatedFoo()` for initialization and reactive side effects:

<code-snippet name="Lifecycle hook examples" lang="php">
    public function mount(User $user) { $this->user = $user; }
    public function updatedSearch() { $this->resetPage(); }
</code-snippet>


## Testing Livewire

<code-snippet name="Example Livewire component test" lang="php">
    Livewire::test(Counter::class)
        ->assertSet('count', 0)
        ->call('increment')
        ->assertSet('count', 1)
        ->assertSee(1)
        ->assertStatus(200);
</code-snippet>


    <code-snippet name="Testing a Livewire component exists within a page" lang="php">
        $this->get('/posts/create')
        ->assertSeeLivewire(CreatePost::class);
    </code-snippet>


=== livewire/v3 rules ===

## Livewire 3

### Key Changes From Livewire 2
- These things changed in Livewire 2, but may not have been updated in this application. Verify this application's setup to ensure you conform with application conventions.
    - Use `wire:model.live` for real-time updates, `wire:model` is now deferred by default.
    - Components now use the `App\Livewire` namespace (not `App\Http\Livewire`).
    - Use `$this->dispatch()` to dispatch events (not `emit` or `dispatchBrowserEvent`).
    - Use the `components.layouts.app` view as the typical layout path (not `layouts.app`).

### New Directives
- `wire:show`, `wire:transition`, `wire:cloak`, `wire:offline`, `wire:target` are available for use. Use the documentation to find usage examples.

### Alpine
- Alpine is now included with Livewire, don't manually include Alpine.js.
- Plugins included with Alpine: persist, intersect, collapse, and focus.

### Lifecycle Hooks
- You can listen for `livewire:init` to hook into Livewire initialization, and `fail.status === 419` for the page expiring:

<code-snippet name="livewire:load example" lang="js">
document.addEventListener('livewire:init', function () {
    Livewire.hook('request', ({ fail }) => {
        if (fail && fail.status === 419) {
            alert('Your session expired');
        }
    });

    Livewire.hook('message.failed', (message, component) => {
        console.error(message);
    });
});
</code-snippet>


=== volt/core rules ===

## Livewire Volt

- This project uses Livewire Volt for interactivity within its pages. New pages requiring interactivity must also use Livewire Volt. There is documentation available for it.
- Make new Volt components using `php artisan make:volt [name] [--test] [--pest]`
- Volt is a **class-based** and **functional** API for Livewire that supports single-file components, allowing a component's PHP logic and Blade templates to co-exist in the same file
- Livewire Volt allows PHP logic and Blade templates in one file. Components use the `@livewire("volt-anonymous-fragment-eyJuYW1lIjoidm9sdC1hbm9ueW1vdXMtZnJhZ21lbnQtYmQ5YWJiNTE3YWMyMTgwOTA1ZmUxMzAxODk0MGJiZmIiLCJwYXRoIjoic3RvcmFnZVwvZnJhbWV3b3JrXC92aWV3c1wvMTUxYWRjZWRjMzBhMzllOWIxNzQ0ZDRiMWRjY2FjYWIuYmxhZGUucGhwIn0=", Livewire\Volt\Precompilers\ExtractFragments::componentArguments([...get_defined_vars(), ...array (
)]))
</code-snippet>


### Volt Class Based Component Example
To get started, define an anonymous class that extends Livewire\Volt\Component. Within the class, you may utilize all of the features of Livewire using traditional Livewire syntax:


<code-snippet name="Volt Class-based Volt Component Example" lang="php">
use Livewire\Volt\Component;

new class extends Component {
    public $count = 0;

    public function increment()
    {
        $this->count++;
    }
} ?>

<div>
    <h1>{{ $count }}</h1>
    <button wire:click="increment">+</button>
</div>
</code-snippet>


### Testing Volt & Volt Components
- Use the existing directory for tests if it already exists. Otherwise, fallback to `tests/Feature/Volt`.

<code-snippet name="Livewire Test Example" lang="php">
use Livewire\Volt\Volt;

test('counter increments', function () {
    Volt::test('counter')
        ->assertSee('Count: 0')
        ->call('increment')
        ->assertSee('Count: 1');
});
</code-snippet>


<code-snippet name="Volt Component Test Using Pest" lang="php">
declare(strict_types=1);

use App\Models\{User, Product};
use Livewire\Volt\Volt;

test('product form creates product', function () {
    $user = User::factory()->create();

    Volt::test('pages.products.create')
        ->actingAs($user)
        ->set('form.name', 'Test Product')
        ->set('form.description', 'Test Description')
        ->set('form.price', 99.99)
        ->call('create')
        ->assertHasNoErrors();

    expect(Product::where('name', 'Test Product')->exists())->toBeTrue();
});
</code-snippet>


### Common Patterns


<code-snippet name="CRUD With Volt" lang="php">
<?php

use App\Models\Product;
use function Livewire\Volt\{state, computed};

state(['editing' => null, 'search' => '']);

$products = computed(fn() => Product::when($this->search,
    fn($q) => $q->where('name', 'like', "%{$this->search}%")
)->get());

$edit = fn(Product $product) => $this->editing = $product->id;
$delete = fn(Product $product) => $product->delete();

?>

<!-- HTML / UI Here -->
</code-snippet>

<code-snippet name="Real-Time Search With Volt" lang="php">
    <flux:input
        wire:model.live.debounce.300ms="search"
        placeholder="Search..."
    />
</code-snippet>

<code-snippet name="Loading States With Volt" lang="php">
    <flux:button wire:click="save" wire:loading.attr="disabled">
        <span wire:loading.remove>Save</span>
        <span wire:loading>Saving...</span>
    </flux:button>
</code-snippet>


=== pint/core rules ===

## Laravel Pint Code Formatter

- You must run `vendor/bin/pint --dirty` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test`, simply run `vendor/bin/pint` to fix any formatting issues.


=== pest/core rules ===

## Pest

### Testing
- If you need to verify a feature is working, write or update a Unit / Feature test.

### Pest Tests
- All tests must be written using Pest. Use `php artisan make:test --pest <name>`.
- You must not remove any tests or test files from the tests directory without approval. These are not temporary or helper files - these are core to the application.
- Tests should test all of the happy paths, failure paths, and weird paths.
- Tests live in the `tests/Feature` and `tests/Unit` directories.
- Pest tests look and behave like this:
<code-snippet name="Basic Pest Test Example" lang="php">
it('is true', function () {
    expect(true)->toBeTrue();
});
</code-snippet>

### Running Tests
- Run the minimal number of tests using an appropriate filter before finalizing code edits.
- To run all tests: `php artisan test`.
- To run all tests in a file: `php artisan test tests/Feature/ExampleTest.php`.
- To filter on a particular test name: `php artisan test --filter=testName` (recommended after making a change to a related file).
- When the tests relating to your changes are passing, ask the user if they would like to run the entire test suite to ensure everything is still passing.

### Pest Assertions
- When asserting status codes on a response, use the specific method like `assertForbidden` and `assertNotFound` instead of using `assertStatus(403)` or similar, e.g.:
<code-snippet name="Pest Example Asserting postJson Response" lang="php">
it('returns all', function () {
    $response = $this->postJson('/api/docs', []);

    $response->assertSuccessful();
});
</code-snippet>

### Mocking
- Mocking can be very helpful when appropriate.
- When mocking, you can use the `Pest\Laravel\mock` Pest function, but always import it via `use function Pest\Laravel\mock;` before using it. Alternatively, you can use `$this->mock()` if existing tests do.
- You can also create partial mocks using the same import or self method.

### Datasets
- Use datasets in Pest to simplify tests which have a lot of duplicated data. This is often the case when testing validation rules, so consider going with this solution when writing tests for validation rules.

<code-snippet name="Pest Dataset Example" lang="php">
it('has emails', function (string $email) {
    expect($email)->not->toBeEmpty();
})->with([
    'james' => 'james@laravel.com',
    'taylor' => 'taylor@laravel.com',
]);
</code-snippet>


=== tailwindcss/core rules ===

## Tailwind Core

- Use Tailwind CSS classes to style HTML, check and use existing tailwind conventions within the project before writing your own.
- Offer to extract repeated patterns into components that match the project's conventions (i.e. Blade, JSX, Vue, etc..)
- Think through class placement, order, priority, and defaults - remove redundant classes, add classes to parent or child carefully to limit repetition, group elements logically
- You can use the `search-docs` tool to get exact examples from the official documentation when needed.

### Spacing
- When listing items, use gap utilities for spacing, don't use margins.

    <code-snippet name="Valid Flex Gap Spacing Example" lang="html">
        <div class="flex gap-8">
            <div>Superior</div>
            <div>Michigan</div>
            <div>Erie</div>
        </div>
    </code-snippet>


### Dark Mode
- If existing pages and components support dark mode, new pages and components must support dark mode in a similar way, typically using `dark:`.


=== tailwindcss/v4 rules ===

## Tailwind 4

- Always use Tailwind CSS v4 - do not use the deprecated utilities.
- `corePlugins` is not supported in Tailwind v4.
- In Tailwind v4, you import Tailwind using a regular CSS `@import` statement, not using the `@tailwind` directives used in v3:

<code-snippet name="Tailwind v4 Import Tailwind Diff" lang="diff">
   - @tailwind base;
   - @tailwind components;
   - @tailwind utilities;
   + @import "tailwindcss";
</code-snippet>


### Replaced Utilities
- Tailwind v4 removed deprecated utilities. Do not use the deprecated option - use the replacement.
- Opacity values are still numeric.

| Deprecated |	Replacement |
|------------+--------------|
| bg-opacity-* | bg-black/* |
| text-opacity-* | text-black/* |
| border-opacity-* | border-black/* |
| divide-opacity-* | divide-black/* |
| ring-opacity-* | ring-black/* |
| placeholder-opacity-* | placeholder-black/* |
| flex-shrink-* | shrink-* |
| flex-grow-* | grow-* |
| overflow-ellipsis | text-ellipsis |
| decoration-slice | box-decoration-slice |
| decoration-clone | box-decoration-clone |


=== tests rules ===

## Test Enforcement

- Every change must be programmatically tested. Write a new test or update an existing test, then run the affected tests to make sure they pass.
- Run the minimum number of tests needed to ensure code quality and speed. Use `php artisan test` with a specific filename or filter.
</laravel-boost-guidelines>