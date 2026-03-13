@extends('layouts.search')

@section('meta')
    @if(isset($episodeMetadata) && $episodeMetadata)
        @php
            $cleanDescription = trim(preg_replace('/\n+/', ' ', $episodeMetadata['description']));
        @endphp
        <title>{{ $episodeMetadata['title'] }} - Everything Is Showbiz</title>
        <meta name="description" content="{{ $cleanDescription }}">
        <link rel="canonical" href="{{ url('/?view=episodes&episode=' . request('episode') . (request('segment') ? '&segment=' . request('segment') : '')) }}">

        <meta property="og:title" content="{{ $episodeMetadata['title'] }} - Everything Is Showbiz"/>
        <meta property="og:description" content="{{ $cleanDescription }}" />
        <meta property="og:url" content="{{ url('/?view=episodes&episode=' . request('episode') . (request('segment') ? '&segment=' . request('segment') : '')) }}" />
        <meta property="og:site_name" content="Everything Is Showbiz"/>
        <meta property="og:locale" content="en_US"/>
        <meta property="og:image" content="{{ asset('i/og.png') }}"/>
        <meta property="og:image:width" content="1200"/>
        <meta property="og:image:height" content="630"/>
        <meta property="og:image:alt" content="{{ $episodeMetadata['title'] }} - Everything Is Showbiz"/>
        <meta property="og:type" content="article"/>
        @if($episodeMetadata['published_at'])
        <meta property="article:published_time" content="{{ $episodeMetadata['published_at']->toISOString() }}"/>
        @endif

        <meta name="twitter:card" content="summary_large_image"/>
        <meta name="twitter:title" content="{{ $episodeMetadata['title'] }} - Everything Is Showbiz"/>
        <meta name="twitter:description" content="{{ $cleanDescription }}"/>
        <meta name="twitter:image" content="{{ asset('i/og.png') }}"/>
        <meta name="twitter:image:width" content="1200"/>
        <meta name="twitter:image:height" content="630"/>
        <meta name="twitter:image:alt" content="{{ $episodeMetadata['title'] }} - Everything Is Showbiz"/>

        <!-- Structured Data for Podcast Episode -->
        @php
            $episodeJsonLd = [
                '@context' => 'https://schema.org',
                '@type' => 'PodcastEpisode',
                'name' => $episodeMetadata['title'],
                'description' => $cleanDescription,
                'url' => url('/?view=episodes&episode=' . request('episode')),
            ];
            if ($episodeMetadata['published_at']) {
                $episodeJsonLd['datePublished'] = $episodeMetadata['published_at']->toISOString();
            }
            $episodeJsonLd['partOfSeries'] = [
                '@type' => 'PodcastSeries',
                'name' => 'What Did You Do Yesterday?',
                'description' => 'Everything Is Showbiz - Searchable archive of What Did You Do Yesterday podcast episodes',
                'url' => url('/'),
            ];
            $episodeJsonLd['associatedMedia'] = [
                '@type' => 'MediaObject',
                'contentUrl' => $episodeMetadata['audio_url'] ?: url('/episode/' . request('episode') . '/audio'),
                'encodingFormat' => 'audio/mpeg',
            ];
            $episodeJsonLd['publisher'] = [
                '@type' => 'Organization',
                'name' => 'Everything Is Showbiz',
                'url' => url('/'),
            ];
        @endphp
        <script type="application/ld+json">
        {!! json_encode($episodeJsonLd, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) !!}
        </script>
    @else
        @if(isset($view) && $view === 'episodes')
            <title>@if(isset($query) && !empty($query)){{ $query }} (Episodes) - @else Episode Archive - @endif Everything Is Showbiz</title>
            <meta name="description" content="@if(isset($query) && !empty($query))Search results for &quot;{{ $query }}&quot; in the episode archive. Browse episodes matching {{ $query }}.@else Browse the complete archive of What Did You Do Yesterday podcast episodes. Filter by type, sort by date, or search for specific episodes.@endif">
            <link rel="canonical" href="{{ url('/?view=episodes') }}">

            <meta property="og:title" content="@if(isset($query) && !empty($query)){{ $query }} (Episodes) - @else Episode Archive - @endif Everything Is Showbiz"/>
            <meta property="og:description" content="@if(isset($query) && !empty($query))Search results for &quot;{{ $query }}&quot; in the episode archive. Browse episodes matching {{ $query }}.@else Browse the complete archive of What Did You Do Yesterday podcast episodes. Filter by type, sort by date, or search for specific episodes.@endif" />
            <meta property="og:url" content="{{ url('/?view=episodes') }}" />
            <meta property="og:site_name" content="Everything Is Showbiz"/>
            <meta property="og:locale" content="en_US"/>
            <meta property="og:image" content="{{ asset('i/og.png') }}"/>
            <meta property="og:image:width" content="1200"/>
            <meta property="og:image:height" content="630"/>
            <meta property="og:image:alt" content="Episode Archive - Everything Is Showbiz"/>
            <meta property="og:type" content="website"/>

            <meta name="twitter:card" content="summary_large_image"/>
            <meta name="twitter:title" content="@if(isset($query) && !empty($query)){{ $query }} (Episodes) - @else Episode Archive - @endif Everything Is Showbiz"/>
            <meta name="twitter:description" content="@if(isset($query) && !empty($query))Search results for &quot;{{ $query }}&quot; in the episode archive. Browse episodes matching {{ $query }}.@else Browse the complete archive of What Did You Do Yesterday podcast episodes. Filter by type, sort by date, or search for specific episodes.@endif"/>
            <meta name="twitter:image" content="{{ asset('i/og.png') }}"/>
            <meta name="twitter:image:width" content="1200"/>
            <meta name="twitter:image:height" content="630"/>
            <meta name="twitter:image:alt" content="Episode Archive - Everything Is Showbiz"/>
        @else
            <title>{{ $seoTitle }}</title>
            <meta name="description" content="{{ $seoDescription }}">
            <link rel="canonical" href="{{ $seoCanonicalUrl }}">

            <meta property="og:title" content="{{ $seoTitle }}"/>
            <meta property="og:description" content="{{ $seoDescription }}" />
            <meta property="og:url" content="{{ $seoCanonicalUrl }}" />
            <meta property="og:site_name" content="Everything Is Showbiz"/>
            <meta property="og:locale" content="en_US"/>
            <meta property="og:image" content="{{ asset('i/og.png') }}"/>
            <meta property="og:image:width" content="1200"/>
            <meta property="og:image:height" content="630"/>
            <meta property="og:image:alt" content="{{ $seoTitle }}"/>
            <meta property="og:type" content="website"/>

            <meta name="twitter:card" content="summary_large_image"/>
            <meta name="twitter:title" content="{{ $seoTitle }}"/>
            <meta name="twitter:description" content="{{ $seoDescription }}"/>
            <meta name="twitter:image" content="{{ asset('i/og.png') }}"/>
            <meta name="twitter:image:width" content="1200"/>
            <meta name="twitter:image:height" content="630"/>
            <meta name="twitter:image:alt" content="{{ $seoTitle }}"/>
        @endif

        <!-- Structured Data for Website -->
        <script type="application/ld+json">
        {
            "@context": "https://schema.org",
            "@type": "WebSite",
            "name": "Everything Is Showbiz",
            "description": "Searchable archive of What Did You Do Yesterday podcast episodes. Find out how often your favourite comedians go for lunch at Pret, what exactly a Helen Copter is, or how Melbourne Bohemians are doing in the current season.",
            "url": "{{ url('/') }}",
            "potentialAction": {
                "@type": "SearchAction",
                "target": {
                    "@type": "EntryPoint",
                    "urlTemplate": "{{ url('/?q={search_term_string}') }}"
                },
                "query-input": "required name=search_term_string"
            },
            "mainEntity": {
                "@type": "PodcastSeries",
                "name": "What Did You Do Yesterday?",
                "description": "Everything Is Showbiz - Searchable archive of What Did You Do Yesterday podcast episodes",
                "url": "{{ url('/') }}"
            }
        }
        </script>
    @endif
@endsection

@section('content')
    <header class="theme-header-gradient px-2 py-4 md:p-5 shadow-lg text-white sticky top-0 z-30">
        <div class="container mx-auto max-w-4xl md:px-3">
            <div class="flex flex-wrap items-center justify-between gap-y-4">
                <!-- Title and Info Icon -->
                <div class="flex items-center">
                    @themeInclude('header-title')
                    <!-- Hide on mobile -->
                    <svg onclick="openInfoModal()" title="About this site" viewBox="0 0 15 15" fill="none" xmlns="http://www.w3.org/2000/svg" class="ml-1 md:ml-3 w-7 md:w-8 h-7 md:h-8 p-1.5 text-white rounded-full cursor-pointer transition-all duration-200 hover:bg-white/20 hover:scale-110 align-middle">
                        <path d="M7.49991 0.876892C3.84222 0.876892 0.877075 3.84204 0.877075 7.49972C0.877075 11.1574 3.84222 14.1226 7.49991 14.1226C11.1576 14.1226 14.1227 11.1574 14.1227 7.49972C14.1227 3.84204 11.1576 0.876892 7.49991 0.876892ZM1.82707 7.49972C1.82707 4.36671 4.36689 1.82689 7.49991 1.82689C10.6329 1.82689 13.1727 4.36671 13.1727 7.49972C13.1727 10.6327 10.6329 13.1726 7.49991 13.1726C4.36689 13.1726 1.82707 10.6327 1.82707 7.49972ZM8.24992 4.49999C8.24992 4.9142 7.91413 5.24999 7.49992 5.24999C7.08571 5.24999 6.74992 4.9142 6.74992 4.49999C6.74992 4.08577 7.08571 3.74999 7.49992 3.74999C7.91413 3.74999 8.24992 4.08577 8.24992 4.49999ZM6.00003 5.99999H6.50003H7.50003C7.77618 5.99999 8.00003 6.22384 8.00003 6.49999V9.99999H8.50003H9.00003V11H8.50003H7.50003H6.50003H6.00003V9.99999H6.50003H7.00003V6.99999H6.50003H6.00003V5.99999Z" fill="currentColor" fill-rule="evenodd" clip-rule="evenodd"></path>
                    </svg>
                </div>

                <!-- Search Form -->
                <form id="searchForm" method="GET" action="/" class="w-full md:flex-1 md:max-w-md">
                    <div class="relative">
                        <input type="text" id="searchInput" name="q" placeholder="{{ $searchConfig['placeholder'] ?? 'Search for topics, quotes, or themes...' }}" class="bg-white w-full pl-5 pr-14 py-3 text-base text-gray-800 border-2 border-transparent rounded-full focus:ring-2 focus:ring-pink-300 focus:border-white outline-none transition-all shadow-inner" autofocus>
                        <input type="hidden" id="formEpisodeType" name="episode_type" value="all">
                        <input type="hidden" id="formSort" name="sort" value="relevance">
                        <button type="submit" class="absolute right-0 top-1/2 transform -translate-y-1/2 text-gray-400 hover:text-pink-500 px-5 py-3 transition-colors rounded-full">
                            <span class="search-text">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                            </span>
                            <div class="loading hidden"></div>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </header>

    <!-- New Sub-Header for Filters and Results Count -->
    <div class="bg-white border-b border-gray-200 py-3">
        <div class="container mx-auto px-4 max-w-4xl">
            <div class="flex flex-wrap items-center justify-between gap-x-6 gap-y-2 text-sm">
                <div class="flex items-center gap-x-4 gap-y-2 flex-wrap">
                    @if($searchConfig['show_episode_types'] ?? false)
                    <label class="flex items-center">
                        <span class="mr-2 text-xs font-semibold text-gray-600">Type:</span>
                        <select name="episode_type" class="text-xs text-gray-800 border border-gray-300 rounded-md px-2 py-1 bg-gray-50 focus:ring-1 focus:ring-blue-500 focus:border-blue-500">
                            <option value="all" selected>All Episodes</option>
                            <option value="interview">Interviews Only</option>
                            <option value="midweek_mayhem">Midweek Mayhem Only</option>
                        </select>
                    </label>
                    @endif

                    <label class="flex items-center">
                        <span class="mr-2 text-xs font-semibold text-gray-600">Sort:</span>
                        <select name="sort" class="text-xs text-gray-800 border border-gray-300 rounded-md px-2 py-1 bg-gray-50 focus:ring-1 focus:ring-blue-500 focus:border-blue-500">
                            <option value="relevance" selected>Most Relevant</option>
                            <option value="newest">Newest First</option>
                            <option value="oldest">Oldest First</option>
                        </select>
                    </label>
                </div>

                <!-- View Mode Toggle -->
                <div class="flex items-center">
                    <div class="view-toggle-wrapper">
                        <div class="view-toggle" id="viewToggle">
                            <input type="radio" name="view_mode" id="transcripts" value="transcripts" checked>
                            <input type="radio" name="view_mode" id="episodes" value="episodes">
                            <div class="toggle-slider">
                                <div class="toggle-handle"></div>
                            </div>
                            <label for="transcripts" class="toggle-label toggle-label-left">Transcripts</label>
                            <label for="episodes" class="toggle-label toggle-label-right">Episode List</label>
                        </div>
                    </div>
                </div>
            </div>
            <div id="resultsHeader" class="mt-3 pt-3 border-t border-gray-200 text-gray-600">
                @if(isset($searchData))
                    @php
                        $total = $searchData['total'] ?? 0;
                        $shown = count($searchData['results'] ?? []);
                        $page = max(1, intval($searchData['page'] ?? 1));
                        $limit = intval($searchData['limit'] ?? 10);
                        $isEstimated = $searchData['is_estimated'] ?? false;

                        // Format total with "500+" for estimated large result sets
                        $totalDisplay = $isEstimated ? '500+' : number_format($total);

                        $headerText = '';
                        if ($total <= 0) {
                            $headerText = 'Found 0 results';
                        } elseif ($total <= $limit && $page === 1) {
                            $headerText = "Found " . $totalDisplay . " results";
                        } elseif ($shown <= 0) {
                            $headerText = "Found " . $totalDisplay . " results";
                        } else {
                            $first = (($page - 1) * $limit) + 1;
                            $last = $first + $shown - 1;
                            $headerText = "Showing " . number_format($first) . " - " . number_format($last) . " of " . $totalDisplay . " results";
                        }

                        $sortLabels = \App\Http\Controllers\SearchController::SORT_LABELS;
                        $typeLabels = \App\Http\Controllers\SearchController::TYPE_LABELS;

                        $sortKey = strtolower($searchData['sort'] ?? 'relevance');
                        $episodeTypeKey = strtolower($searchData['episode_type'] ?? 'all');

                        $summaryParts = [];
                        if (isset($typeLabels[$episodeTypeKey])) {
                            $summaryParts[] = $typeLabels[$episodeTypeKey];
                        }
                        if (isset($sortLabels[$sortKey])) {
                            $summaryParts[] = $sortLabels[$sortKey];
                        }
                        $metaText = implode(' • ', $summaryParts);
                    @endphp
                    <h2 class="text-sm font-semibold">{{ $headerText }}</h2>
                    <p class="text-xs text-gray-500 mt-1">{{ $metaText }}</p>
                @else
                    <h2 class="text-sm font-semibold"></h2>
                    <p class="text-xs text-gray-500 mt-1"></p>
                @endif
            </div>
        </div>
    </div>
    <!-- Main Content -->
    <div class="flex-1 transition-all duration-300" id="mainContent">
        <div class="container mx-auto px-4 py-8 max-w-4xl">

        <!-- Results Container -->
        <div class="">
            <!-- Search Results -->
            <div id="results" class="{{ isset($searchData) && ($view ?? 'search') !== 'episodes' ? '' : 'hidden' }}">
                <div id="resultsList" class="space-y-4">
                    @if(isset($searchData) && isset($searchData['results']))
                        @foreach($searchData['results'] as $result)
                            <div class="result-card clickable-result bg-white rounded-lg border border-gray-200 p-6 relative hover:shadow-md transition-shadow"
                                 onclick="openEpisodeSidebar({{ $result['episode_id'] }}, {{ $result['id'] }})"
                                 data-episode-id="{{ $result['episode_id'] }}"
                                 data-segment-id="{{ $result['id'] }}">

                                <!-- Main Quote (Primary Focus) -->
                                <div class="mb-4">
                                    <div class="text-gray-900 text-lg leading-relaxed font-medium">
                                        {!! $result['highlighted_text'] !!}
                                    </div>
                                </div>

                                <!-- Date & Timestamp (Secondary) -->
                                <div class="mb-3 text-sm text-gray-700">
                                    <span>
                                        {{ $result['formatted_date'] }}
                                    </span>
                                    <span class="mx-2">•</span>
                                    <span>
                                        {{ $result['formatted_time'] }} - {{ \App\Models\Episode::formatDuration($result['end_time']) }}
                                    </span>
                                </div>

                                <!-- Episode Title & Type (Tertiary) -->
                                <div class="text-sm text-gray-700">
                                    <span class="italic font-medium">
                                        {{ $result['episode_title'] }}
                                    </span>
                                    <span class="mx-2">•</span>
                                    <span class="text-gray-500 italic">
                                        {{ ($result['episode_type'] ?? '') === 'midweek_mayhem' ? 'Midweek Mayhem' : 'Interview' }}
                                    </span>
                                </div>
                            </div>
                        @endforeach
                    @endif
                </div>

                <!-- Pagination -->
                <div id="pagination" class="mt-8 flex justify-center">
                    @if(isset($searchData) && isset($searchData['total']) && $searchData['total'] > ($searchData['limit'] ?? 10))
                        @php
                            $currentPage = max(1, intval($searchData['page'] ?? 1));
                            $limit = intval($searchData['limit'] ?? 10);
                            $total = intval($searchData['total']);
                            $totalPages = ceil($total / $limit);

                            $buildSearchUrl = function($page) use ($searchData) {
                                $params = [];
                                if ($page > 1) $params['page'] = $page;

                                if (($searchData['episode_type'] ?? 'all') !== 'all') $params['episode_type'] = $searchData['episode_type'];
                                if (($searchData['sort'] ?? 'relevance') !== 'relevance') $params['sort'] = $searchData['sort'];
                                if (!empty($searchData['query'])) $params['q'] = $searchData['query'];

                                return '/?' . http_build_query($params);
                            };
                        @endphp

                        @if($totalPages > 1)
                            <nav class="flex items-center space-x-2">
                                @if($currentPage > 1)
                                    <a href="{{ $buildSearchUrl($currentPage - 1) }}"
                                       class="pagination-button"
                                       data-page="{{ $currentPage - 1 }}">
                                        Previous
                                    </a>
                                @else
                                    <span class="pagination-button pagination-button-disabled">
                                        Previous
                                    </span>
                                @endif

                                @php
                                    $startPage = max(1, $currentPage - 2);
                                    $endPage = min($totalPages, $currentPage + 2);
                                @endphp

                                @if($startPage > 1)
                                    <a href="{{ $buildSearchUrl(1) }}"
                                       class="pagination-button"
                                       data-page="1">
                                        1
                                    </a>
                                    @if($startPage > 2)
                                        <span class="pagination-ellipsis">…</span>
                                    @endif
                                @endif

                                @for($i = $startPage; $i <= $endPage; $i++)
                                    @if($i === $currentPage)
                                        <span class="pagination-button pagination-button-active">
                                            {{ $i }}
                                        </span>
                                    @else
                                        <a href="{{ $buildSearchUrl($i) }}"
                                           class="pagination-button"
                                           data-page="{{ $i }}">
                                            {{ $i }}
                                        </a>
                                    @endif
                                @endfor

                                @if($endPage < $totalPages)
                                    @if($endPage < $totalPages - 1)
                                        <span class="pagination-ellipsis">…</span>
                                    @endif
                                    <a href="{{ $buildSearchUrl($totalPages) }}"
                                       class="pagination-button"
                                       data-page="{{ $totalPages }}">
                                        {{ $totalPages }}
                                    </a>
                                @endif

                                @if($currentPage < $totalPages)
                                    <a href="{{ $buildSearchUrl($currentPage + 1) }}"
                                       class="pagination-button"
                                       data-page="{{ $currentPage + 1 }}">
                                        Next
                                    </a>
                                @else
                                    <span class="pagination-button pagination-button-disabled">
                                        Next
                                    </span>
                                @endif
                            </nav>
                        @endif
                    @endif
                </div>
            </div>

            <!-- Episode Cards Container -->
            <div id="episodeCards" class="{{ ($view ?? 'search') === 'episodes' ? '' : 'hidden' }}">
                <div id="episodeCardsList" class="grid gap-6 md:grid-cols-2">
                    @if(isset($episodeData) && isset($episodeData['episodes']))
                        @foreach($episodeData['episodes'] as $episode)
                            <a href="/?view=episodes&episode={{ $episode['id'] }}"
                               class="block bg-white rounded-lg shadow-sm border border-gray-200 p-6 hover:shadow-md transition-shadow cursor-pointer text-left"
                               onclick="event.preventDefault(); openEpisodeFromCard({id: {{ $episode['id'] }}})">
                                <div class="mb-3">
                                    <h3 class="text-lg font-semibold text-gray-900 mb-2 line-clamp-2">{{ $episode['title'] }}</h3>
                                    <div class="flex items-center text-sm text-gray-500 mb-2">
                                        <span>{{ $episode['formatted_date'] }}</span>
                                        @if(($episode['episode_type'] ?? '') === 'midweek_mayhem')
                                            <span class="mx-2">•</span><span>Midweek Mayhem</span>
                                        @endif
                                    </div>
                                </div>
                                @if(!empty($episode['short_description']))
                                    <p class="text-gray-700 text-sm line-clamp-3 mb-4">{{ $episode['short_description'] }}</p>
                                @endif
                                <div class="flex items-center justify-between">
                                    <span class="text-sm text-blue-600 font-medium">Click to view transcript</span>
                                    <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                                    </svg>
                                </div>
                            </a>
                        @endforeach
                    @else
                        <!-- Episode cards will be inserted here -->
                    @endif
                </div>

                <!-- Episode Pagination -->
                <div id="episodePagination" class="mt-8 flex justify-center">
                    @if(isset($episodeData) && isset($episodeData['pagination']) && $episodeData['pagination']['last_page'] > 1)
                        @php
                            $buildUrl = function($page) use ($episodeData) {
                                $params = ['view' => 'episodes'];
                                if ($page > 1) $params['page'] = $page;

                                $filters = $episodeData['filters'] ?? [];
                                if (($filters['episode_type'] ?? 'all') !== 'all') $params['episode_type'] = $filters['episode_type'];
                                if (($filters['sort'] ?? 'newest') !== 'newest') $params['sort'] = $filters['sort'];
                                if (!empty($filters['q'])) $params['q'] = $filters['q'];

                                return '/?' . http_build_query($params);
                            };
                        @endphp
                        <nav class="flex items-center justify-between">
                            <div class="flex space-x-2">
                                @if($episodeData['pagination']['current_page'] > 1)
                                    <a href="{{ $buildUrl($episodeData['pagination']['current_page'] - 1) }}"
                                       class="pagination-button"
                                       onclick="event.preventDefault(); loadEpisodes({{ $episodeData['pagination']['current_page'] - 1 }})">
                                        Previous
                                    </a>
                                @else
                                    <span class="pagination-button pagination-button-disabled">
                                        Previous
                                    </span>
                                @endif

                                @php
                                    $startPage = max(1, $episodeData['pagination']['current_page'] - 2);
                                    $endPage = min($episodeData['pagination']['last_page'], $episodeData['pagination']['current_page'] + 2);
                                @endphp

                                @if($startPage > 1)
                                    <a href="{{ $buildUrl(1) }}"
                                       class="pagination-button"
                                       onclick="event.preventDefault(); loadEpisodes(1)">
                                        1
                                    </a>
                                    @if($startPage > 2)
                                        <span class="pagination-ellipsis">…</span>
                                    @endif
                                @endif

                                @for($i = $startPage; $i <= $endPage; $i++)
                                    @if($i === $episodeData['pagination']['current_page'])
                                        <span class="pagination-button pagination-button-active">
                                            {{ $i }}
                                        </span>
                                    @else
                                        <a href="{{ $buildUrl($i) }}"
                                           class="pagination-button"
                                           onclick="event.preventDefault(); loadEpisodes({{ $i }})">
                                            {{ $i }}
                                        </a>
                                    @endif
                                @endfor

                                @if($endPage < $episodeData['pagination']['last_page'])
                                    @if($endPage < $episodeData['pagination']['last_page'] - 1)
                                        <span class="pagination-ellipsis">…</span>
                                    @endif
                                    <a href="{{ $buildUrl($episodeData['pagination']['last_page']) }}"
                                       class="pagination-button"
                                       onclick="event.preventDefault(); loadEpisodes({{ $episodeData['pagination']['last_page'] }})">
                                        {{ $episodeData['pagination']['last_page'] }}
                                    </a>
                                @endif

                                @if($episodeData['pagination']['current_page'] < $episodeData['pagination']['last_page'])
                                    <a href="{{ $buildUrl($episodeData['pagination']['current_page'] + 1) }}"
                                       class="pagination-button"
                                       onclick="event.preventDefault(); loadEpisodes({{ $episodeData['pagination']['current_page'] + 1 }})">
                                        Next
                                    </a>
                                @else
                                    <span class="pagination-button pagination-button-disabled">
                                        Next
                                    </span>
                                @endif
                            </div>
                        </nav>
                    @else
                        <!-- Pagination will be inserted here -->
                    @endif
                </div>
            </div>

            <!-- Loading State -->
            <div id="loadingState" class="hidden text-center py-12 bg-white rounded-lg shadow-sm border border-gray-200 ">
                <div class="loading mx-auto mb-4"></div>
                <p class="text-gray-600">Searching yesterdays...</p>
            </div>

            <!-- No Results -->
            <div id="noResults" class="hidden text-center py-12 bg-white rounded-lg shadow-sm border border-gray-200 ">
                <div class="text-6xl mb-4">🔍</div>
                <h3 class="text-xl font-semibold text-gray-900 mb-2">No results found</h3>
                <p class="text-gray-600 px-4">Try adjusting your search terms or adding quotes to your query for a closer match.</p>
            </div>

            <!-- Error State -->
            <div id="errorState" class="hidden text-center py-12 bg-white rounded-lg shadow-sm border border-gray-200 ">
                <div class="text-6xl mb-4">⚠️</div>
                <h3 class="text-xl font-semibold text-red-600 mb-2">Search Error</h3>
                <p class="text-gray-600" id="errorMessage"></p>
            </div>

            <!-- Default Welcome State -->
            <div id="welcomeState" class="{{ (($view ?? 'search') === 'episodes' || isset($searchData)) ? 'hidden' : '' }} text-center py-8 md:py-16 bg-white rounded-lg shadow-sm border border-gray-200">
                <div class="max-w-2xl mx-auto px-4 md:px-6">
                    <!-- Main Icon -->
                    <div class="text-6xl mb-6">🎙️</div>

                    <!-- Welcome Title -->
                    <h2 class="text-3xl font-bold text-gray-900 mb-4">
                        Everything Is Showbiz
                    </h2>

                    <!-- Subtitle -->
                    <p class="text-xl text-gray-700 mb-8">
                        Search the "What Did You Do Yesterday?" archives
                    </p>

                    <!-- Description -->
                    <div class="text-left space-y-4 text-gray-700 mb-8">
                        <p>
                            Find out how often your favourite comedians go for lunch at Pret, what exactly a Helen Copter is, how many ways making a 3/4 flat white can go wrong, or unusual suggestions for filling a bath tub.
                        </p>

                        <p>
                            Click on any result to open the episode sidebar with full transcript segments and audio playback.
                        </p>

                        @themeInclude('search-tips')
                    </div>

                    <p class="text-gray-500 text-sm">
                        Start typing in the box above to explore the archive!
                    </p>

                    @themeInclude('social-links')

                    @themeInclude('timestamp-note')
                </div>
            </div>
        </div>
        </div>
    </div>

    <!-- Info Modal Backdrop -->
    <div id="infoModalBackdrop" class="info-modal-backdrop fixed inset-0 bg-black bg-opacity-40 z-40" onclick="closeInfoModal()"></div>

    <!-- Info Modal -->
    <div id="infoModal" class="info-modal fixed inset-0 z-50 flex items-center justify-center p-4" onclick="closeInfoModal()">
        <div class="bg-white rounded-lg shadow-2xl max-w-md w-full mx-4 max-h-[90vh] flex flex-col" onclick="event.stopPropagation()">
            <!-- Modal Header -->
            <div class="border-b border-gray-200 p-4 flex-shrink-0">
                <div class="flex items-center justify-between">
                    <h3 class="text-lg font-semibold text-gray-900">Everything Is Showbiz</h3>
                    <button onclick="closeInfoModal()" class="text-gray-400 hover:text-gray-600 cursor-pointer transition-colors">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>
            </div>

            <!-- Modal Content -->
            <div class="p-4 overflow-y-auto flex-1">
                <div class="space-y-4 text-sm text-gray-700">
                    @themeInclude('about-content')

                    @themeInclude('social-links')

                    @themeInclude('footer-links')
                </div>
            </div>
        </div>
    </div>

    <!-- Episode Info Modal Backdrop -->
    <div id="episodeInfoModalBackdrop" class="info-modal-backdrop fixed inset-0 bg-black bg-opacity-40" style="z-index: 60;" onclick="closeEpisodeInfoModal()"></div>

    <!-- Episode Info Modal -->
    <div id="episodeInfoModal" class="info-modal fixed inset-0 flex items-center justify-center p-4" style="z-index: 60;" onclick="closeEpisodeInfoModal()">
        <div class="bg-white rounded-lg shadow-2xl max-w-md w-full mx-4 max-h-[90vh] flex flex-col" onclick="event.stopPropagation()">
            <!-- Modal Header -->
            <div class="border-b border-gray-200 p-6 flex-shrink-0">
                <div class="flex items-center justify-between">
                    <h3 id="episodeInfoModalTitle" class="text-lg font-semibold text-gray-900">
                        @if(isset($sidebarEpisodeData))
                            {{ $sidebarEpisodeData['episode']['title'] }}
                        @endif
                    </h3>
                    <button onclick="closeEpisodeInfoModal()" class="text-gray-400 hover:text-gray-600 transition-colors cursor-pointer">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>
            </div>

            <!-- Modal Content -->
            <div class="p-6 overflow-y-auto flex-1">
                <div id="episodeInfoModalContent" class="text-sm text-gray-700 leading-relaxed">
                    @if(isset($sidebarEpisodeData))
                        {!! nl2br(e($sidebarEpisodeData['episode']['description'] ?: 'No description available.')) !!}
                    @endif
                </div>
            </div>
        </div>
    </div>

    <!-- Sidebar Backdrop -->
    <div id="sidebarBackdrop" class="sidebar-backdrop fixed inset-0 bg-black bg-opacity-50 z-40" onclick="closeSidebar()"></div>

    <!-- Sidebar Panel -->
    <div id="episodeSidebar" class="sidebar fixed top-0 right-0 h-full w-4/5 md:w-1/2 bg-white shadow-2xl z-50 flex flex-col" data-current-episode-id="{{ request('episode') ?? '' }}">
        <!-- Sidebar Header -->
        <div class="border-b border-gray-200 p-4 bg-gray-50 flex-shrink-0">
            <!-- Close Button (Positioned Absolutely) -->
            <button onclick="closeSidebar()" class="absolute top-4 right-4 text-gray-400 hover:text-gray-600 transition-colors cursor-pointer">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>

            <!-- Stack vertically with explicit spacing -->
            <div class="space-y-4 pr-10">
                <!-- Line 1: Episode Title -->
                <div class="w-full flex items-center">
                    <h2 id="sidebarEpisodeTitle" class="text-xl font-bold text-gray-900 flex-1">
                        @if(isset($sidebarEpisodeData))
                            {{ $sidebarEpisodeData['episode']['title'] }}
                        @endif
                    </h2>
                </div>
            </div>
            <div class="space-y-4 mt-4">
                <!-- Line 2: Metadata Container -->
                <div class="w-full">
                    <div class="text-sm text-gray-700">
                        <div id="sidebarEpisodeMeta" class="inline-block">
                            @if(isset($sidebarEpisodeData))
                                @php
                                    $episodeType = $sidebarEpisodeData['episode']['episode_type'] === 'midweek_mayhem'
                                        ? 'Midweek Mayhem'
                                        : 'Interview';
                                @endphp
                                {{ $sidebarEpisodeData['episode']['formatted_date'] }} • {{ $episodeType }}
                            @endif
                        </div>
                        <svg onclick="openEpisodeInfoModal()" title="Episode details" viewBox="0 0 15 15" fill="none" xmlns="http://www.w3.org/2000/svg" class="inline-block w-7 h-7 ml-1 p-1.5 bg-gray-100 text-gray-600 rounded-full cursor-pointer transition-all duration-200 hover:bg-gray-200 hover:scale-110 flex-shrink-0">
                            <path d="M7.49991 0.876892C3.84222 0.876892 0.877075 3.84204 0.877075 7.49972C0.877075 11.1574 3.84222 14.1226 7.49991 14.1226C11.1576 14.1226 14.1227 11.1574 14.1227 7.49972C14.1227 3.84204 11.1576 0.876892 7.49991 0.876892ZM1.82707 7.49972C1.82707 4.36671 4.36689 1.82689 7.49991 1.82689C10.6329 1.82689 13.1727 4.36671 13.1727 7.49972C13.1727 10.6327 10.6329 13.1726 7.49991 13.1726C4.36689 13.1726 1.82707 10.6327 1.82707 7.49972ZM8.24992 4.49999C8.24992 4.9142 7.91413 5.24999 7.49992 5.24999C7.08571 5.24999 6.74992 4.9142 6.74992 4.49999C6.74992 4.08577 7.08571 3.74999 7.49992 3.74999C7.91413 3.74999 8.24992 4.08577 8.24992 4.49999ZM6.00003 5.99999H6.50003H7.50003C7.77618 5.99999 8.00003 6.22384 8.00003 6.49999V9.99999H8.50003H9.00003V11H8.50003H7.50003H6.50003H6.00003V9.99999H6.50003H7.00003V6.99999H6.50003H6.00003V5.99999Z" fill="currentColor" fill-rule="evenodd" clip-rule="evenodd"></path>
                        </svg>

                        <svg onclick="shareSidebarUrl()" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="inline-block w-7 h-7 ml-1 p-1.5 bg-gray-100 text-gray-600 rounded-full cursor-pointer transition-all duration-200 hover:bg-gray-200 hover:scale-110 flex-shrink-0">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M7.217 10.907a2.25 2.25 0 1 0 0 2.186m0-2.186c.18.324.283.696.283 1.093s-.103.77-.283 1.093m0-2.186 9.566-5.314m-9.566 7.5 9.566 5.314m0 0a2.25 2.25 0 1 0 3.935 2.186 2.25 2.25 0 0 0-3.935-2.186Zm0-12.814a2.25 2.25 0 1 0 3.933-2.185 2.25 2.25 0 0 0-3.933 2.185Z" />
                        </svg>
                    </div>
                </div>

                <!-- Line 3: Audio Player -->
                <div class="w-full">
                    <div class="bg-white rounded-lg border border-gray-200 p-4">
                        <audio id="episodeAudio" class="w-full" controls preload="metadata">
                            <source id="audioSource" src="{{ isset($sidebarEpisodeData) ? '/episode/' . $sidebarEpisodeData['episode']['id'] . '/audio' : '' }}" type="audio/mpeg">
                            Your browser does not support the audio element.
                        </audio>
                    </div>
                </div>
            </div>
        </div>
        <!-- Transcript Segments -->
        <div class="flex-1 overflow-hidden flex flex-col">
            <div id="transcriptSegments" class="flex-1 overflow-y-auto">
                @if(isset($sidebarEpisodeData) && count($sidebarEpisodeData['segments']) > 0)
                    @foreach($sidebarEpisodeData['segments'] as $segment)
                        <div class="segment-item p-4 border-b border-gray-100"
                             data-segment-id="{{ $segment['id'] }}"
                             data-start-time="{{ $segment['start_time'] }}"
                             onclick="jumpToSegment({{ $segment['id'] }}, {{ $segment['start_time'] }})">
                            <div class="flex justify-between items-start mb-2">
                                <span class="text-xs text-blue-600 font-mono">
                                    {{ $segment['formatted_time'] }} - {{ gmdate('H:i:s', (int)$segment['end_time']) }}
                                </span>
                            </div>
                            <div class="text-sm text-gray-700 leading-relaxed">{{ $segment['text'] }}</div>
                        </div>
                    @endforeach
                @else
                    <!-- Segments will be loaded here by JavaScript if not pre-populated -->
                @endif
            </div>
        </div>

        <!-- Loading State -->
        <div id="sidebarLoading" class="flex-1 flex items-center justify-center">
            <div class="text-center">
                <div class="loading mx-auto mb-4"></div>
                <p class="text-gray-600">Loading episode details...</p>
            </div>
        </div>

        <!-- Toast for share confirmation (fixed, global, bottom right) -->
        <div id="shareToast"
            class="share-toast fixed bottom-6 right-6 z-[9999] bg-black text-white px-6 py-3 rounded-lg shadow-2xl text-base opacity-0 pointer-events-none transition-opacity duration-300"
            ></div>
    </div>
@endsection


@push('scripts')
<script>
    // Server-side data injection for JavaScript modules
    // These globals are used by resources/js/modules/*.js
    window.serverSearchData = @json($searchData ?? null);
    window.serverEpisodeData = {!! $episodeDataJson ?? 'null' !!};
    window.currentView = @json($view ?? 'search');
</script>
@endpush
