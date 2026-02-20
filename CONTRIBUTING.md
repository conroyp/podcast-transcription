# Contributing to Podcast Transcription System

Thank you for your interest in contributing! This document provides guidelines for contributing to the project.

## Development Setup

### Prerequisites

- PHP 8.4+
- Composer
- Node.js 18+ and npm
- PostgreSQL 17+ with pgvector extension
- Python 3.10+ with faster-whisper (for transcription)

### Installation

1. Clone the repository:
   ```bash
   git clone https://github.com/conroyp/podcast-transcription.git
   cd podcast-transcription
   ```

2. Install PHP dependencies:
   ```bash
   composer install
   ```

3. Install JavaScript dependencies:
   ```bash
   npm install
   ```

4. Copy environment file and configure:
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

5. Set up PostgreSQL with pgvector:
   ```bash
   createdb podcast_archive
   psql -d podcast_archive -c "CREATE EXTENSION IF NOT EXISTS vector;"
   ```

6. Run migrations:
   ```bash
   php artisan migrate
   ```

7. Build frontend assets:
   ```bash
   npm run build
   ```

### Running the Application

```bash
# Development mode (server + queue + vite)
composer dev

# Or run components separately:
php artisan serve
php artisan queue:work --timeout=3600
npm run dev
```

## Code Style

### PHP Standards

- Follow PSR-12 coding standard
- Use type hints for parameters and return types
- Add docblocks to public methods
- Use Laravel facades consistently (e.g., `Log::` not `logger()`)

### JavaScript

- Follow existing Livewire/Flux and Alpine.js patterns
- Avoid inline `onclick` handlers — use `wire:click` or Alpine `@click`

### Running Code Style Checks

```bash
# PHP code style (Laravel Pint)
./vendor/bin/pint

# Check without fixing
./vendor/bin/pint --test
```

## Testing

### Running Tests

```bash
# Run all tests
composer test
# or
php artisan test

# Run specific test
php artisan test --filter=SearchControllerTest

# Run with coverage
php artisan test --coverage
```

### Test Database

Tests use a separate PostgreSQL database. Ensure it's configured in `phpunit.xml`:

```xml
<env name="DB_DATABASE" value="podcast_archive_test"/>
```

Create the test database with pgvector:
```bash
createdb podcast_archive_test
psql -d podcast_archive_test -c "CREATE EXTENSION IF NOT EXISTS vector;"
```

### Writing Tests

- Add feature tests for new endpoints in `tests/Feature/`
- Add unit tests for service methods in `tests/Unit/`
- Mock external APIs (OpenAI, HTTP requests) in tests
- Test both success and error paths

## Pull Request Process

1. **Fork and branch**: Create a feature branch from `main`
   ```bash
   git checkout -b feature/your-feature-name
   ```

2. **Make changes**: Implement your feature or fix

3. **Add tests**: Include tests for new functionality

4. **Check code style**: Run Pint and fix any issues
   ```bash
   ./vendor/bin/pint
   ```

5. **Run tests**: Ensure all tests pass
   ```bash
   composer test
   ```

6. **Commit**: Use clear, descriptive commit messages
   ```
   Add semantic search highlighting for matched phrases

   - Implement text highlighting in SearchController
   - Add CSS classes for highlighted matches
   - Escape content before applying highlight markup
   ```

7. **Push and PR**: Push your branch and create a pull request

### PR Guidelines

- Keep PRs focused on a single feature or fix
- Include a clear description of what the PR does
- Reference any related issues
- Ensure CI checks pass
- Be responsive to review feedback

## Architecture Overview

### Processing Pipeline

```
RSS Feed -> Episode Discovery -> Audio Download -> Whisper Transcription ->
Ad Removal -> Transcript Cleaning -> Content Chunking -> Embedding Generation
```

### Key Components

- **Services** (`app/Services/`): Core business logic
  - `TranscriptionService`: Whisper integration
  - `EmbeddingService`: OpenAI embeddings and semantic search
  - `RssFeedService`: RSS parsing and episode discovery
  - `AdRemovalService`: Ad content detection and removal

- **Jobs** (`app/Jobs/`): Queue-processed tasks
  - `ProcessEpisodeCompleteJob`: Full pipeline for an episode

- **Commands** (`app/Console/Commands/`): CLI tools
  - `podcast:*`: Main podcast processing commands
  - `episode:*`: Import/export functionality

- **Livewire** (`app/Livewire/`): Frontend components
  - Uses Flux UI component library

### Database

- PostgreSQL with pgvector extension
- Key tables: `podcasts`, `episodes`, `transcript_segments`
- Embedding vectors stored in `transcript_segments.embedding_vector`

## Configuration

The system is configured through:

- `config/podcast.php`: Podcast-specific settings
- `config/embeddings.php`: OpenAI model settings
- `config/ad_removal.php`: Ad detection patterns
- `config/transcript_cleaning.php`: Text cleaning rules
- `config/theme.php`: Theme definitions and branding
- `config/{theme}/`: Theme-specific overrides (merged automatically on boot)

To scaffold a new theme: `php artisan podcast:make-theme {name}`

See each config file for detailed documentation.

## Getting Help

- Open an issue for bugs or feature requests
- Check existing issues before creating new ones
- For questions, use GitHub Discussions (if enabled)

## License

By contributing, you agree that your contributions will be licensed under the project's license.
