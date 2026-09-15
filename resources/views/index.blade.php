@extends('layouts.site')

@section('content')
    <h1 class="card__title">@lang('text.welcome'), {{ $firstName }}</h1>
    <p class="card__text">@lang('text.authInstruction')</p>

    <form method="GET" action="{{ route('auth/discord') }}">
        <label class="consent">
            <input type="checkbox" required>
            <span>
                @lang('text.consentmentDeclaration1')
                <button type="button" onclick="document.getElementById('consent-dialog').showModal()">@lang('text.consent')</button>
                @lang('text.consentmentDeclaration2')
                <a href="https://wiki.ivao.aero/en/home/ivao/privacypolicy" target="_blank" rel="noopener">@lang('text.privacyPolicy')</a>.
            </span>
        </label>

        <button type="submit" class="button button--discord button--block">
            <svg aria-hidden="true"><use href="#icon-discord"/></svg>
            @lang('text.loginBtn')
        </button>
    </form>

    <dialog class="dialog" id="consent-dialog" aria-labelledby="consent-dialog-title">
        <div class="dialog__bar">
            <h2 class="dialog__title" id="consent-dialog-title">@lang('text.consentTitle')</h2>
            <form method="dialog"><button class="text-button text-button--bold">@lang('text.close')</button></form>
        </div>
        @include('partials.consent')
    </dialog>
@endsection

@section('footer')
    <span>@lang('text.noAccount') <a href="https://discord.com/register" target="_blank" rel="noopener">@lang('text.createAccount')</a></span>
    <span>@lang('text.revoke') <a href="{{ route('revoke') }}">@lang('text.revokeAccount')</a></span>
@endsection
