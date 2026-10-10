<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? config('app.name') }}</title>
    @isset($description)
        <meta name="description" content="{{ $description }}">
    @endisset
    <style>[x-cloak]{display:none!important}</style>
    @filamentStyles
    @vite('resources/css/filament/app/theme.css')
    @stack('head')
</head>
<body class="ghl-public {{ $bodyClass ?? '' }}">
    {{ $slot }}

    @livewire('notifications')
    @filamentScripts
</body>
</html>
