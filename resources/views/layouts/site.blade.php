<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#F2F2F7" media="(prefers-color-scheme: light)">
    <meta name="theme-color" content="#000000" media="(prefers-color-scheme: dark)">
    <title>{{ config('brauth.title') }}</title>
    <link rel="icon" href="{{ asset('assets/imgs/ivao-br.svg') }}">
    <link rel="stylesheet" href="@versioned('css/app.css')">
    <link rel="stylesheet" href="@versioned('css/site.css')">
</head>
<body>
@include('partials.icons')

<main class="site">
    <div class="site__brand" aria-hidden="true">
        <img src="{{ asset('assets/imgs/ivao-br.svg') }}" alt="" width="44" height="44">
        <span class="site__link"></span>
        <svg class="site__discord"><use href="#icon-discord"/></svg>
    </div>

    <div class="card">
        @yield('content')
    </div>

    @hasSection('footer')
        <div class="site__footer">
            @yield('footer')
        </div>
    @endif
</main>
</body>
</html>
