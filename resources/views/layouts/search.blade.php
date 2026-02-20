<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" @themeDataAttribute>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    @yield('meta')

    <link rel="icon" type="image/png" sizes="64x64" href="/favicon.png">
    <link rel="shortcut icon" href="/favicon.ico">

    <!-- Base Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600" rel="stylesheet" />

    <!-- Theme-specific Fonts -->
    @themeFonts

    <!-- Stylesheets -->
    @vite(['resources/css/app.css', 'resources/css/search.css', 'resources/css/themes/base.css', 'resources/js/app.js', 'resources/js/search.js'])

    @if($currentTheme !== 'base')
        @vite(['resources/css/themes/' . $themeConfig['css_file']])
    @endif

    <!-- Theme Color Overrides from Environment -->
    @themeColorOverrides
</head>
<body class="bg-gray-50 text-gray-700 font-sans">
    @yield('content')

    @stack('scripts')
</body>
</html>
