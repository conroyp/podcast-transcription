{{--
    WDYDY-specific social sharing links.
--}}
@php
    $socialLinks = config('social_links.links', []);
@endphp

@if (!empty($socialLinks))
<div class="social-share">
    <div class="mt-6 flex gap-6 justify-center">
        @foreach ($socialLinks as $link)
            <a class="group -m-1 p-1" aria-label="Share on {{ $link['platform'] }}"
               href="{{ $link['url'] }}"
               {{ ($link['icon'] === 'bluesky') ? 'target="_new"' : '' }}>
                @if ($link['icon'] === 'twitter')
                    <svg viewBox="0 0 24 24" aria-hidden="true" class="h-6 w-6 fill-zinc-500 transition group-hover:fill-zinc-600 dark:fill-zinc-400 dark:group-hover:fill-zinc-300"><path d="M13.3174 10.7749L19.1457 4H17.7646L12.7039 9.88256L8.66193 4H4L10.1122 12.8955L4 20H5.38119L10.7254 13.7878L14.994 20H19.656L13.3171 10.7749H13.3174ZM11.4257 12.9738L10.8064 12.0881L5.87886 5.03974H8.00029L11.9769 10.728L12.5962 11.6137L17.7652 19.0075H15.6438L11.4257 12.9742V12.9738Z"></path></svg>
                @elseif ($link['icon'] === 'bluesky')
                    <svg viewBox="0 0 568 501" aria-hidden="true" class="h-6 w-6 fill-zinc-500 transition group-hover:fill-zinc-600 dark:fill-zinc-400 dark:group-hover:fill-zinc-300"><path d="M123.121 33.6637C188.241 82.5526 258.281 181.681 284 234.873C309.719 181.681 379.759 82.5526 444.879 33.6637C491.866 -1.61183 568 -28.9064 568 57.9464C568 75.2916 558.055 203.659 552.222 224.501C531.947 296.954 458.067 315.434 392.347 304.249C507.222 323.8 536.444 388.56 473.333 453.32C353.473 576.312 301.061 422.461 287.631 383.039C285.169 375.812 284.017 372.431 284 375.306C283.983 372.431 282.831 375.812 280.369 383.039C266.939 422.461 214.527 576.312 94.6667 453.32C31.5556 388.56 60.7778 323.8 175.653 304.249C109.933 315.434 36.0535 296.954 15.7778 224.501C9.94525 203.659 0 75.2916 0 57.9464C0 -28.9064 76.1345 -1.61183 123.121 33.6637Z"></path></svg>
                @elseif ($link['icon'] === 'facebook')
                    <svg viewBox="0 0 24 24" aria-hidden="true" class="h-6 w-6 fill-zinc-500 transition group-hover:fill-zinc-600 dark:fill-zinc-400 dark:group-hover:fill-zinc-300"><path d="M22.675 0H1.325C0.593 0 0 0.593 0 1.325v21.35C0 23.407 0.593 24 1.325 24h11.494V14.706H9.692v-3.62h3.127V8.412c0-3.1 1.892-4.788 4.646-4.788c1.325 0 2.465 0.098 2.798 0.142v3.243l-1.922 0c-1.505 0-1.794 0.715-1.794 1.764v2.31h3.588l-0.467 3.62h-3.121V24h6.116C23.407 24 24 23.407 24 22.675V1.325C24 0.593 23.407 0 22.675 0z"></path></svg>
                @endif
            </a>
        @endforeach
    </div>
</div>
@endif
