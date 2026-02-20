{{--
    Base search tips partial.
    Override in themes/{theme}/partials/search-tips.blade.php for custom content.
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
