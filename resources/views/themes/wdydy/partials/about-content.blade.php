{{--
    WDYDY-specific about content with feedback links and timestamp note.
--}}
<p>
    This is a searchable transcript archive for the <strong>What Did You Do Yesterday</strong> podcast. Use the search box to find specific quotes, topics, or discussions.
</p>
<p>
    Find out how often your favourite comedians go for lunch at Pret, what exactly a Helen Copter is, or how Melbourne Bohemians are doing in the current season.
</p>
<p>
    Click on any result to open the episode sidebar with full transcript segments and audio playback.
</p>

@php
    $aboutSocialLinks = config('social_links.links', []);
@endphp
<p>
    Leave a review, if you liked it, on your preferred podcast platform.@if (!empty($aboutSocialLinks)) And if you didn't, send all feedback
        @foreach ($aboutSocialLinks as $index => $link)
            @if ($index > 0), @endif<a class="underline font-bold" href="{{ $link['url'] }}" target="_blank" rel="noopener">here</a>
        @endforeach.
    @endif
</p>
<div class="bg-blue-50 p-3 rounded-lg">
    <p class="text-blue-800 text-xs">
        <strong>Tip:</strong> If you don't find what you're looking for, try putting "quotes" around your search query for exact matches.
    </p>
</div>
<div class="bg-yellow-50 p-3 rounded-lg">
    <p class="text-yellow-800 text-xs">
        Sometimes the timestamps are a bit out of sync with the audio you're listening to. This happens when the AI has been served an episode with a different number of ads to those in your region. If you find a segment that is particularly out of sync, please let me know via the links above.
    </p>
</div>
