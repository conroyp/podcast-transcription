{{--
    WDYDY-specific footer links with related project links.
--}}
@php
    $footerLinks = config('footer_links.links', []);
@endphp

@if (!empty($footerLinks))
<hr class="border-gray-200 mt-6 mb-4">

<div class="text-center text-xs text-gray-500">
    <p>Time to kill?
        @foreach ($footerLinks as $index => $link)
            @if ($index > 0)<span class="mx-1">&bull;</span>@endif
            <a href="{{ $link['url'] }}" target="_blank" rel="noopener" class="{{ $index === 0 ? 'ml-1 ' : '' }}underline hover:text-gray-800">{{ $link['label'] }}</a>
        @endforeach
    </p>
</div>
@endif
