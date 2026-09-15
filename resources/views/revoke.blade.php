@extends('layouts.site')

@section('content')
    <h1 class="card__title">@lang('text.revokeConfirmTitle')</h1>
    <p class="card__text">@lang('text.revokeConfirmBody')</p>

    <form method="POST" action="{{ route('revoke') }}" class="card__actions">
        @csrf
        <button type="submit" class="button button--danger">@lang('text.revokeConfirmButton')</button>
        <a href="{{ route('home') }}" class="button button--secondary">@lang('text.cancel')</a>
    </form>
@endsection
