{{--
    WDYDY-specific search tips with Popular Search Ideas.
--}}
<div class="bg-blue-50 p-4 rounded-lg text-sm">
    <h4 class="font-semibold text-blue-900 mb-2">How to Search:</h4>
    <ul class="space-y-1 text-blue-800">
        <li><strong>Simple search:</strong> Type any word or phrase</li>
        @if($searchConfig['show_episode_types'] ?? false)
        <li><strong>Filter by type:</strong> Choose between episode types</li>
        @endif
        <li><strong>Sort results:</strong> By relevance, newest, or oldest first</li>
        <li><strong>Longer queries:</strong> Multi-word queries tend to work better than single words</li>
        <li><strong>Browse episodes:</strong> Use the <a class="underline hover:font-bold cursor-pointer" href="/?view=episodes">episodes archive</a> to browse all episodes.</li>
    </ul>
</div>

@php
    $popularSearches = config('search_tips.popular_searches', []);
@endphp

@if (!empty($popularSearches))
<div class="bg-yellow-50 p-4 rounded-lg text-sm">
    <h4 class="font-semibold text-yellow-900 mb-2">Popular Search Ideas:</h4>
    <div class="flex flex-wrap gap-2">
        @foreach ($popularSearches as $term)
            <a href="/?q={{ urlencode($term) }}" class="bg-yellow-200 hover:bg-yellow-300 text-yellow-900 px-3 py-1 rounded-full text-xs transition-colors cursor-pointer mb-2">
                {{ $term }}
            </a>
        @endforeach
    </div>
</div>
@endif
