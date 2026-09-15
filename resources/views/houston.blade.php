@extends('layouts.site')

@section('content')
    @if ($image ?? false)
        <img class="card__image" src="{{ asset('assets/imgs/houston-we-have-a-problem.jpeg') }}" alt="">
    @else
        <div class="card__icon card__icon--danger">
            <svg aria-hidden="true" viewBox="0 0 24 24"><path d="M12 7v6m0 4h.01" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"/></svg>
        </div>
    @endif

    <h1 class="card__title" style="margin-bottom: 24px">{{ $text }}</h1>

    <div class="card__actions">
        <a class="button button--secondary" href="{{ route('home') }}">@lang('text.backHome')</a>
    </div>
@endsection
