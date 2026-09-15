@extends('layouts.site')

@section('content')
    <div class="card__icon card__icon--success">
        <svg aria-hidden="true"><use href="#icon-check"/></svg>
    </div>
    <h1 class="card__title">@lang('text.successTitle')</h1>
    <p class="card__text">@lang('text.successBody')</p>

    <div class="card__actions">
        <a class="button button--discord" href="https://discord.com/channels/{{ config('services.discord.guild_id') }}">
            <svg aria-hidden="true"><use href="#icon-discord"/></svg>
            @lang('text.openDiscord')
        </a>
    </div>
@endsection
