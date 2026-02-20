# Podcast Transcription & Processing System

A robust Laravel-based podcast ingestion, transcription, and processing pipeline designed to automatically download, transcribe, clean, and prepare podcast episodes for semantic search using embeddings.

## 🎯 Overview

This system provides an automated pipeline for:
- **RSS Feed Ingestion**: Fetch and parse podcast RSS feeds
- **Audio Download**: Download MP3/audio files from podcast episodes
- **Whisper Transcription**: Generate accurate transcripts using OpenAI Whisper
- **Ad Removal**: Automatically detect and remove advertising content
- **Transcript Cleaning**: Remove filler words, fix formatting, and standardize text
- **Content Chunking**: Split transcripts into embedding-friendly segments
- **Hybrid Search**: Combined keyword + semantic search using OpenAI embeddings and pgvector
- **Episode Export/Import**: Transfer processed episodes between machines

## 🏗️ Architecture

### Core Models
- **Podcast**: RSS feed metadata and configuration
- **Episode**: Individual episode data (title, description, audio URL, etc.)
- **TranscriptSegment**: Time-stamped transcript segments with text content and embedding vectors
- **QueryEmbedding**: Cached query embedding vectors to avoid redundant API calls

### Key Services
- **RssFeedService**: RSS parsing and episode discovery
- **PodcastDownloadService**: Audio file downloading with progress tracking
- **TranscriptionService**: Whisper integration and pipeline orchestration
- **AdRemovalService**: Pattern-based ad detection (config-driven, supports theme overrides)
- **TranscriptCleaningService**: Text cleaning and standardization (config-driven, supports theme overrides)
- **TranscriptChunkingService**: Intelligent transcript segmentation
- **ThemeService**: Theme detection, branding, and CSS management
- **EmbeddingService**: OpenAI API integration for vector embeddings and query embedding caching
- **HybridSearchService**: Combines keyword (word-boundary regex) and semantic (cosine distance) scoring in a single query

### Processing Pipeline
```
RSS Feed → Episode Discovery → Audio Download → Whisper Transcription →
Ad Removal → Transcript Cleaning → Content Chunking → [Embeddings/Search]
```

## 🎨 Theme Configuration

The application is designed to be deployed for **any podcast**, not just the included example. Themes control branding, CSS, and podcast-specific behaviour — RSS feeds, ad removal patterns, transcript cleaning rules, social links, and more — all without touching core application code.

### How Themes Work

1. Set the active theme via `THEME_DEFAULT` in `.env` (e.g., `THEME_DEFAULT=mypodcast`)
2. `ThemeConfigServiceProvider` automatically merges theme-specific configs on boot
3. Services read from `config('key')` and get the merged result transparently

### Reference Theme

A complete reference theme — `wdydy` — is included, powering [everythingisshowbiz.com](https://everythingisshowbiz.com). It demonstrates the full theming stack: custom branding, CSS variables, ad removal patterns, transcript cleaning rules, social links, and search tips. Use it as a starting point or copy individual files as needed.

### Creating a Theme

Use the scaffold command to generate all required files in one step:

```bash
php artisan podcast:make-theme mypodcast
```

This creates:
- `config/mypodcast/` — podcast, ad removal, transcript cleaning config stubs
- `resources/views/themes/mypodcast/partials/` — header, footer, social links, search tips, and about content partials
- `resources/css/themes/mypodcast.css` — CSS variable overrides with brand colour stubs

The command prints the exact next steps on completion, including the snippet to add to `config/theme.php` to register the theme and the `.env` variables to set.

After scaffolding, register the theme in `config/theme.php`:

```php
'themes' => [
    // ... existing themes
    'mypodcast' => [
        'name' => 'My Podcast',
        'css_file' => 'mypodcast.css',
        'fonts' => [
            // 'https://fonts.bunny.net/css?family=my-font:400,700',
        ],
    ],
],
```

Then activate and clear the cache:

```bash
# .env
THEME_DEFAULT=mypodcast

php artisan config:clear
```

### Theme Directory Structure

```
config/
├── ad_removal.php              # Base ad removal config
├── podcast.php                 # Base podcast config
├── social_links.php            # Base social links config
├── search_tips.php             # Base search tips config
├── transcript_cleaning.php     # Base transcript cleaning config
├── theme.php                   # Theme definitions and branding
└── mypodcast/                  # Theme-specific overrides (only what you need)
    ├── ad_removal.php          # Ad detection patterns
    ├── podcast.php             # RSS URL, podcast name, Whisper context
    ├── social_links.php        # Social profile links
    ├── search_tips.php         # Example search queries shown to users
    └── transcript_cleaning.php # Text replacements for transcription errors

resources/css/themes/
├── base.css                    # Base theme styles and CSS variables
└── mypodcast.css               # Brand colour and font overrides
```

### Config Merging Behaviour

- Theme configs are **deep merged** with base configs
- Theme values **override** base values for matching keys
- Base keys not in the theme config are **preserved**
- Only `.php` files in `config/{theme}/` are processed

### Available Theme Overrides

| Config File | Purpose | Common Overrides |
|-------------|---------|------------------|
| `podcast.php` | Podcast metadata | `default_rss_url`, `name`, `hosts`, `transcription_context` |
| `ad_removal.php` | Ad detection patterns | `start_patterns`, `end_patterns`, `enabled` |
| `transcript_cleaning.php` | Text corrections | `text_replacements` (name spellings, etc.) |
| `social_links.php` | Social profile links | `links` array of platform/URL pairs |
| `search_tips.php` | Search UI help text | `popular_searches` example queries |

### Theme Environment Variables

Branding can also be controlled via `.env` without code changes:

```env
THEME_DEFAULT=mypodcast
THEME_SITE_NAME="My Podcast Search"
THEME_TAGLINE="Search every episode transcript"
THEME_SEARCH_PLACEHOLDER="What are you looking for?"
THEME_SEO_HOME_TITLE="My Podcast - Search"
```

---

## 🚀 Quick Start

### Prerequisites

- PHP 8.4+
- Laravel 12.x
- PostgreSQL 17+ with pgvector extension
- Python 3.10+ (3.12 recommended) if using faster-whisper, **or** a C++ compiler if building whisper.cpp from source
- Sufficient storage for audio files and transcripts (~50MB per hour of audio)

### Installation

1. **Clone and Setup**
   ```bash
   git clone https://github.com/conroyp/podcast-transcription.git
   cd podcast-transcription
   composer install
   npm install
   ```

2. **Environment Configuration**
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

3. **Database Setup**
   ```bash
   # Ensure PostgreSQL is running with pgvector extension
   psql -h 127.0.0.1 -U postgres -d podcast_archive -c "CREATE EXTENSION IF NOT EXISTS vector;"

   # Run migrations
   php artisan migrate
   ```

4. **Transcription Engine Setup**

   The system supports two transcription engines. You only need to install one.

   ### Option A: Faster-Whisper (Recommended)

   [faster-whisper](https://github.com/SYSTRAN/faster-whisper) is a CTranslate2-based implementation that's 4x faster than OpenAI's whisper with lower memory usage.

   **Using uv (Recommended)**

   [uv](https://docs.astral.sh/uv/) is a fast Python package manager. Install it first if you don't have it:
   ```bash
   # macOS/Linux
   curl -LsSf https://astral.sh/uv/install.sh | sh

   # Or via Homebrew
   brew install uv
   ```

   Then create the Python environment:
   ```bash
   # Create virtual environment with Python 3.12
   uv venv --python 3.12

   # Activate the environment
   source .venv/bin/activate

   # Install faster-whisper
   pip install faster-whisper

   # Verify installation
   python -c "from faster_whisper import WhisperModel; print('faster-whisper installed successfully')"
   ```

   **Using standard venv**

   ```bash
   # Create virtual environment (requires Python 3.10+)
   python3 -m venv .venv

   # Activate the environment
   source .venv/bin/activate  # Linux/macOS
   # Or: .venv\Scripts\activate  # Windows

   # Install faster-whisper
   pip install faster-whisper

   # Verify installation
   python -c "from faster_whisper import WhisperModel; print('faster-whisper installed successfully')"
   ```

   **Important Notes:**
   - The `.venv` directory is git-ignored and should be in your project root
   - The transcription service automatically activates this environment when running
   - Model files (~3GB for large-v3-turbo) are downloaded on first use to `~/.cache/huggingface/`
   - For GPU acceleration, install CUDA toolkit and `pip install faster-whisper[cuda]`

   ### Option B: whisper.cpp

   [whisper.cpp](https://github.com/ggerganov/whisper.cpp) is a C++ port of OpenAI's Whisper model. It runs natively without Python and can be useful on systems where a Python environment is not desirable.

   **Build from source:**
   ```bash
   # Clone the repository
   git clone https://github.com/ggerganov/whisper.cpp.git
   cd whisper.cpp

   # Build
   cmake -B build
   cmake --build build --config Release

   # Download a model (e.g., large-v3)
   bash models/download-ggml-model.sh large-v3
   ```

   **Or install via Homebrew (macOS):**
   ```bash
   brew install whisper-cpp

   # Download a model
   whisper-cpp-download-ggml-model large-v3
   ```

   **Configure the paths** in your `.env`:
   ```env
   WHISPER_BIN_PATH=/path/to/whisper.cpp/build/bin/whisper-cli
   WHISPER_MODEL_PATH=/path/to/whisper.cpp/models/ggml-large-v3.bin
   ```

   If installed via Homebrew, the paths are typically:
   ```env
   WHISPER_BIN_PATH=/opt/homebrew/bin/whisper-cpp
   WHISPER_MODEL_PATH=/opt/homebrew/share/whisper-cpp/models/ggml-large-v3.bin
   ```

   ### Switching Between Engines

   Toggle the engine in `app/Services/TranscriptionService.php` (line 18):
   ```php
   private bool $useFasterWhisper = true;  // true for faster-whisper, false for whisper.cpp
   ```

5. **Storage Setup**
   ```bash
   php artisan storage:link
   mkdir -p storage/app/private/podcasts/{audio,transcripts}
   ```

6. **Queue Configuration**
   ```bash
   # Start the queue worker with extended timeout for long transcriptions
   php artisan queue:work --timeout=3600

   # For production, use a process manager like Supervisor
   # Add to your supervisord.conf:
   [program:laravel-worker]
   command=php /path/to/your/project/artisan queue:work --timeout=3600
   autostart=true
   autorestart=true
   user=www-data
   ```

## 🎮 Common Commands

### Feed Management
```bash
# Fetch new episodes from RSS feed
php artisan podcast:fetch

# List all episodes with status
php artisan podcast:list

# Debug specific episode information
php artisan podcast:debug {episode_id}

# Complete ingestion pipeline (recommended)
php artisan podcast:ingest-complete --oldest-first --limit=10

# Alternative: Ingest all episodes with full processing
php artisan podcast:ingest-all --oldest-first --limit=10

# Reset database and storage for fresh start
php artisan podcast:reset
```

### Audio & Transcription
```bash
# Download audio for specific episode
php artisan podcast:download {episode_id}

# Transcribe specific episode
php artisan podcast:transcribe {episode_id}

# Full pipeline processing (download + transcribe + clean + chunk)
php artisan podcast:process-episode {episode_id}
```

### Content Processing
```bash
# Remove ads from specific episode
php artisan podcast:remove-ads {episode_id} [--dry-run]

# Clean transcript formatting
php artisan podcast:clean-transcripts {episode_id} [--dry-run]

# Chunk transcripts for embeddings
php artisan podcast:chunk-transcripts {episode_id} [--dry-run]

# Bulk operations on all episodes
php artisan podcast:clean-transcripts --all [--dry-run]
php artisan podcast:chunk-transcripts --all [--dry-run]
```

### Embedding & Search Operations
```bash
# Generate embeddings for specific episode
php artisan podcast:generate-embeddings {episode_id} [--batch-size=50]

# Generate embeddings for all pending segments
php artisan podcast:generate-embeddings [--batch-size=50]

# Retry failed embeddings
php artisan podcast:generate-embeddings --failed-only

# Check embedding statistics
php artisan podcast:generate-embeddings --stats

# Hybrid keyword + semantic search through transcripts
php artisan podcast:search "your search query" [--limit=10] [--threshold=0.7] [--detailed]

# Test embedding infrastructure
php artisan podcast:test-embedding-setup
```

### Episode Sync to Production

The system includes a **batched sync system** to transfer processed episodes from your local transcription machine to a remote production server. Episodes are synced via HTTP in compressed batches to work within typical hosting limits.

#### Prerequisites

**Environment Configuration:**

On **both local and remote** servers, add to `.env`:
```env
SHARED_UPLOAD_SECRET=your-secure-random-string-here
```

On **local machine only**, add to `.env`:
```env
SYNC_UPLOAD_ENDPOINT=https://your-production-server.com/api/import-sync
```

#### Healthcheck (Test Your Configuration)

Before syncing, verify your configuration is working correctly:

```bash
# Test connection and authentication
php artisan sync:healthcheck

# Or test with a specific endpoint
php artisan sync:healthcheck --endpoint=https://yourserver.com/api/import-sync
```

The healthcheck will:
- ✅ Validate endpoint URL format
- ✅ Test network connectivity to remote server
- ✅ Verify authentication secret matches
- ✅ Display remote server information
- ✅ Provide troubleshooting guidance if issues are found

**Note:** The healthcheck uses a POST request to bypass CDN/Cloudflare caching and ensure real-time authentication validation.

**Example successful output:**
```
🔍 Testing sync connection...

┌──────────────────┬──────────────────────────────────────────┐
│ Setting          │ Value                                    │
├──────────────────┼──────────────────────────────────────────┤
│ Endpoint         │ https://yourserver.com/api/import-sync   │
│ Healthcheck URL  │ https://yourserver.com/api/sync-healthcheck│
│ Secret Configured│ ✓ Yes                                    │
│ Secret Preview   │ abc123xyz...                             │
└──────────────────┴──────────────────────────────────────────┘

📡 Connecting to remote server...
✅ Connection successful!

┌──────────────┬────────────────────────────────────────────┐
│ Property     │ Value                                      │
├──────────────┼────────────────────────────────────────────┤
│ Status       │ ok                                         │
│ Message      │ Sync endpoint is healthy and auth successful│
│ Server Time  │ 2025-01-15T10:30:45Z                       │
│ Environment  │ production                                 │
└──────────────┴────────────────────────────────────────────┘

🎉 Your sync configuration is working correctly!
```

#### Sync via Dashboard (Recommended)

**Prerequisites:** Ensure your queue worker is running:
```bash
php artisan queue:work --timeout=3600
```

**Steps:**
1. Process your episode locally (download + transcribe + embeddings)
2. Go to the episode page in your browser
3. Click the **"Sync to Server"** button
4. The sync job will be queued and run in the background
5. Monitor progress in `storage/logs/laravel.log`

**Note:** Dashboard sync runs as a background job to avoid PHP execution time limits. The sync typically takes 30-60 seconds for episodes with 1000+ segments.

#### Sync via Command Line

```bash
# Sync a specific episode
php artisan sync:export episode {episode_id} --endpoint=https://yourserver.com/api/import-sync
```

#### How It Works

The sync happens in batches to handle hosting limitations:

1. **Episode Metadata**: Sends episode + podcast data (first request)
2. **Segment Batches**: Sends transcript segments in batches of 100 (multiple requests)
   - Each batch includes full embeddings (1536-dimensional vectors)
   - Gzip compressed (~80-90% size reduction)
   - Typical batch size: 1-2MB compressed
3. **Cache Clearing**: Automatically clears search cache when complete

For a 1000-segment episode:
- 10 batches total
- ~20 seconds sync time
- All with preserved IDs (database identical on both machines)

#### Batch Details

- **Compression**: Gzip (not zip) for better JSON compression
- **Batch Size**: 100 segments per request
- **Timeout**: 30 seconds per batch
- **Memory**: Works within 128M PHP memory limit
- **File Size**: Each batch ~1-2MB compressed
- **Embeddings**: Full pgvector embeddings included

#### What Gets Synced

✅ Episode metadata (title, description, status, etc.)
✅ Podcast information
✅ All transcript segments with text
✅ Full vector embeddings (1536 dimensions)
✅ Processing metadata
✅ Preserved database IDs

❌ Audio files (not synced - too large)

#### Security

- Protected by `SHARED_UPLOAD_SECRET` header
- 401 Unauthorized if secret doesn't match
- Transaction safety (rollback on error)
- Idempotent (safe to run multiple times)

#### Troubleshooting

**First step: Run the healthcheck**
```bash
php artisan sync:healthcheck
```
The healthcheck will identify most common configuration issues and provide specific guidance.

**"Episode not fully processed" error:**
- Ensure download, transcription, and embeddings are complete
- Check episode status in dashboard

**"Sync not configured" error:**
- Add `SYNC_UPLOAD_ENDPOINT` and `SHARED_UPLOAD_SECRET` to local `.env`
- Run `php artisan sync:healthcheck` to verify configuration

**"Unauthorized" on remote:**
- Ensure `SHARED_UPLOAD_SECRET` matches on both servers
- Check for extra spaces or hidden characters in the secret
- Run `php artisan sync:healthcheck` to test authentication

**Network timeout:**
- Check remote server PHP `max_execution_time` (30s+ recommended)
- Verify network connectivity with `php artisan sync:healthcheck`

**"Sync has been queued" but nothing happens:**
- Ensure queue worker is running: `php artisan queue:work --timeout=3600`
- Check queue logs: `tail -f storage/logs/laravel.log`
- Verify job is in queue: `php artisan queue:failed`

### Utility Commands
```bash
# View processing statistics
php artisan podcast:chunk-transcripts --all --dry-run --stats

# Interactive transcript management
php artisan podcast:manage-cleaning

# Clear search result cache
php artisan clear:search-cache

# Scaffold a new theme
php artisan podcast:make-theme {name}

# Laravel tinker for debugging
php artisan tinker
```

## 🤖 Automated Episode Ingestion

### Scheduled Command Setup

The system includes automated checking for new episodes using Laravel's task scheduler. This is configured in `routes/console.php`:

```php
// Schedule podcast feed checking for new episodes
Schedule::command('podcast:fetch --process')
    ->hourly()
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/podcast-fetch.log'));
```

### Production Cron Setup

To activate the scheduler in production, add this single cron job to your server:

```bash
* * * * * cd /path/to/your/project && php artisan schedule:run >> /dev/null 2>&1
```

### What the Scheduled Command Does

1. **Checks RSS feed** for new episodes from your active podcast
2. **Creates database records** for any new episodes found
3. **Dispatches ProcessEpisodeCompleteJob** for each new episode (full pipeline)
4. **Logs output** to `storage/logs/podcast-fetch.log`

### Schedule Frequency Options

You can modify the frequency in `routes/console.php`:

```php
// Check every hour (default)
->hourly()

// Check twice daily
->twiceDaily()

// Check daily at specific time
->dailyAt('09:00')

// Check weekly
->weekly()
```

### Manual Testing

Test the automated command manually:

```bash
# Test the exact command that runs on schedule
php artisan podcast:fetch --process

# Check the logs
tail -f storage/logs/podcast-fetch.log

# Check queue jobs were dispatched
php artisan queue:work
```

### Monitoring

- **Logs**: Check `storage/logs/podcast-fetch.log` for scheduling output
- **Queue**: Ensure `php artisan queue:work` is running to process jobs
- **Database**: New episodes will appear in the episodes table
- **Status**: Episode processing status tracked through pipeline

### Production Considerations

1. **Queue Worker**: Ensure queue workers are running with process managers like Supervisor
2. **Timeouts**: Episode processing can take hours - configure appropriate timeouts
3. **Storage**: Monitor disk space for downloaded audio files
4. **Overlap Prevention**: `withoutOverlapping()` prevents concurrent runs

## 📁 File Structure

### Key Directories
```
app/
├── Console/Commands/          # Artisan commands
├── Models/                   # Eloquent models
├── Services/                 # Business logic services
├── Providers/                # Service providers (inc. ThemeConfigServiceProvider)
└── Http/Controllers/         # Web controllers

config/
├── *.php                     # Base configuration files
└── {theme}/                  # Theme-specific config overrides
    ├── podcast.php           # RSS URL, podcast metadata
    ├── ad_removal.php        # Ad detection patterns
    └── transcript_cleaning.php # Text replacements

resources/css/themes/         # Theme CSS files
storage/app/private/podcasts/
├── audio/                    # Downloaded MP3 files
└── transcripts/              # Whisper JSON output

resources/views/episodes/     # Episode management UI
database/migrations/          # Database schema
tests/                       # Test suite
```

### Storage Locations
- **Audio Files**: `storage/app/private/podcasts/audio/{episode_id}.mp3`
- **Transcripts**: `storage/app/private/podcasts/transcripts/{episode_id}.json`
- **Backups**: `storage/backups/` (created before bulk operations)
- **Logs**: `storage/logs/laravel.log`

## 🔧 Configuration

### Environment Variables
```bash
# Application
APP_NAME="Podcast Transcription"
APP_ENV=local
APP_DEBUG=true

# Database (PostgreSQL with pgvector)
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=podcast_archive
DB_USERNAME=postgres
DB_PASSWORD=

# Logging
LOG_CHANNEL=stack
LOG_LEVEL=debug

# Transcription - Faster-Whisper (default)
# Model is configured in scripts/transcribe.py (default: large-v3-turbo)
# Models are cached in ~/.cache/huggingface/

# Transcription - whisper.cpp (alternative)
# WHISPER_BIN_PATH=/path/to/whisper-cli
# WHISPER_MODEL_PATH=/path/to/models/ggml-large-v3.bin

# OpenAI for embeddings
OPENAI_API_KEY=your_openai_api_key_here
EMBEDDING_MODEL=text-embedding-3-small  # OpenAI embedding model
EMBEDDING_DIMENSIONS=1536      # Embedding vector dimensions

# Theming (optional)
THEME_DEFAULT=base             # Theme to use: base, wdydy, or custom
THEME_SITE_NAME="Podcast Search"
THEME_TAGLINE="Search your favorite podcast transcripts"
```

### Ad Removal Patterns

Ad detection patterns are config-driven and support theme overrides. Base patterns are in `config/ad_removal.php`, with theme-specific patterns in `config/{theme}/ad_removal.php`.

The system uses pattern-based detection to identify ad segments:
- **Start patterns**: Phrases that indicate ad content is beginning
- **End patterns**: Phrases that indicate ad content is ending
- **Direct patterns**: Self-contained ad segment markers

See `config/ad_removal.php` for base configuration and the Theme Configuration section for adding theme-specific patterns.

## 🎵 Podcast Configuration

The system is designed to work with any podcast. Configure your podcast in `config/podcast.php` or use theme-specific overrides (recommended for multi-podcast setups).

**Base configuration** (`config/podcast.php`):
```php
'default_rss_url' => env('PODCAST_DEFAULT_RSS_URL'),
'name' => env('PODCAST_NAME'),
'transcription_context' => env('PODCAST_TRANSCRIPTION_CONTEXT'),
```

**Theme-specific configuration** (`config/{theme}/podcast.php`):
```php
<?php
return [
    'default_rss_url' => 'https://feeds.example.com/podcast.rss',
    'name' => 'My Podcast',
    'hosts' => 'Host Name',
    'transcription_context' => 'Context for Whisper transcription...',
];
```

Theme configs are automatically merged on boot - see Theme Configuration below.

## 🔍 Processing Status Tracking

Each episode tracks processing stages through status fields:
- `download_status`: pending → downloading → completed/failed
- `transcription_status`: pending → processing → completed/failed
- `diarization_status`: pending → processing → completed/failed
- `embedding_status` (segments): pending → processing → completed/failed

## 🐛 Troubleshooting

### Common Issues

**Transcription Failures**
```bash
# Verify Python environment is set up correctly
source .venv/bin/activate
python -c "from faster_whisper import WhisperModel; print('OK')"

# Test the transcription script directly
python scripts/transcribe.py --help

# Verify audio file exists
ls -la storage/app/private/podcasts/audio/

# Check logs for errors
tail -f storage/logs/laravel.log
```

**Python Environment Issues (faster-whisper)**
```bash
# If faster-whisper import fails, rebuild the environment
rm -rf .venv
uv venv --python 3.12  # or: python3 -m venv .venv
source .venv/bin/activate
pip install faster-whisper

# Check Python version (must be 3.10+)
python --version
```

**whisper.cpp Issues**
```bash
# Verify the binary exists and runs
/path/to/whisper-cli --help

# Verify the model file exists
ls -la /path/to/models/ggml-large-v3.bin

# Check your .env paths match
grep WHISPER_ .env
```

**UTF-8 Encoding Issues**
- Fixed in TranscriptionService with `mb_convert_encoding`
- Handles malformed Whisper JSON output

**NOT NULL Constraint Errors**
- Resolved in migration `2025_06_30_192049_update_transcript_segments_decimal_to_double.php`
- Ensures proper decimal handling for timestamps

**Large File Processing**
```bash
# Check available disk space
df -h

# Monitor memory usage during transcription
top -p $(pgrep whisper)
```

### Debug Commands
```bash
# Test episode processing pipeline
php artisan podcast:debug 1

# Check segment count before/after chunking
php artisan tinker
>>> Episode::find(1)->transcriptSegments()->count()

# Verify ad removal results
php artisan podcast:remove-ads 1 --dry-run
```

## 🧪 Testing

```bash
# Run test suite
php artisan test

# Run specific feature tests
php artisan test --filter=DashboardTest

# Test transcript processing
php artisan test tests/Feature/
```

## 📈 Performance Notes

- **Transcription**: ~2-3x audio duration (depends on hardware)
- **Storage**: ~50MB per hour of audio + ~2MB per transcript
- **Chunking**: Reduces segment count by ~80% (typical 2000→400 segments)
- **Memory**: Peak usage during Whisper processing (~2GB for base model)

## 🤝 Contributing

We welcome contributions! Please see [CONTRIBUTING.md](CONTRIBUTING.md) for detailed guidelines.

Quick summary:
1. Follow PSR-12 coding standards (use `./vendor/bin/pint`)
2. Add tests for new features
3. Update documentation
4. Use `--dry-run` flags for testing bulk operations
5. Create database backups before schema changes

## 📝 License

MIT License - see LICENSE file for details.

---

## 📞 Support

For issues or questions:
1. Check the troubleshooting section above
2. Review logs in `storage/logs/laravel.log`
3. Use `php artisan tinker` for debugging
4. Test with `--dry-run` flags before bulk operations

**Last Updated**: February 2026
**System Status**: Production-ready core pipeline, hybrid search, theming, and multi-machine sync

