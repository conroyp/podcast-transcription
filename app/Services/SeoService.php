<?php

namespace App\Services;

use Illuminate\Support\Str;

class SeoService
{
    /**
     * Get the special case descriptions from config (merged with built-in defaults).
     *
     * @return array<string, string>
     */
    protected function specialCases(): array
    {
        $configCases = config('theme.seo.special_case_descriptions', []);

        return array_merge($configCases);
    }

    /**
     * Get the site name from config.
     */
    protected function siteName(): string
    {
        return config('theme.branding.site_name', 'Podcast Search');
    }

    /**
     * Get the podcast name from config.
     */
    protected function podcastName(): string
    {
        return config('podcast.name', 'Podcast');
    }

    /**
     * Generate the meta description based on the search query and view type.
     */
    public function generateDescription(?string $query, string $viewType = 'search'): string
    {
        // Default description if no query
        if (empty($query)) {
            if ($viewType === 'episodes') {
                return 'Browse the complete archive of What Did You Do Yesterday podcast episodes. Filter by type, sort by date, or search for specific episodes.';
            }

            return 'Are they just normal cheeses? How do you make a good 3/4 flat white? Search the What Did You Do Yesterday archive to find out!';
        }

        // Remove all spaces and lowercase for matching
        $normalizedQuery = Str::lower(str_replace(' ', '', trim($query)));

        $specialCases = $this->specialCases();

        // Check for exact matches in special cases
        if (array_key_exists($normalizedQuery, $specialCases)) {
            return $specialCases[$normalizedQuery];
        }

        // Check for partial matches
        foreach ($specialCases as $key => $description) {
            if (str_contains($normalizedQuery, $key)) {
                return $description;
            }
        }

        // Default dynamic description
        if ($viewType === 'episodes') {
            return "Search results for \"{$query}\" in the episode archive. Browse episodes matching {$query}.";
        }

        return "Search results for \"{$query}\" in the What Did You Do Yesterday podcast archive. Find every mention of {$query} across all episodes.";
    }

    /**
     * Generate the meta title based on the search query and view type.
     */
    public function generateTitle(?string $query, string $viewType = 'search'): string
    {
        $siteName = $this->siteName();
        $podcastName = $this->podcastName();

        if (empty($query)) {
            if ($viewType === 'episodes') {
                return "Episode Archive - {$siteName}";
            }

            return "{$siteName} - {$podcastName}";
        }

        if ($viewType === 'episodes') {
            return "{$query} (Episodes) - {$siteName}";
        }

        return "{$query} - {$siteName}";
    }

    /**
     * Generate the canonical URL based on the search query and view type.
     */
    public function generateCanonicalUrl(?string $query, string $viewType = 'search'): string
    {
        if ($viewType === 'episodes') {
            return url('/?view=episodes');
        }

        if (! empty($query)) {
            return url('/?q='.urlencode(Str::lower($query)));
        }

        return url('/');
    }
}
