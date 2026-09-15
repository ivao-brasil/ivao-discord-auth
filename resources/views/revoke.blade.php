<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('brauth.title') }}</title>
    <style>
        html {
            font-family: Verdana, sans-serif;
        }

        body {
            margin: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 0 16px;
        }

        .revoke {
            max-width: 480px;
            text-align: center;
        }

        .revoke__title {
            color: #7289DA;
            font-size: 4vh;
            margin: 0 0 16px;
        }

        .revoke__body {
            color: #6F7275;
            line-height: 1.5;
            margin: 0 0 32px;
        }

        .revoke__actions {
            display: flex;
            gap: 12px;
            justify-content: center;
            flex-wrap: wrap;
        }

        .revoke__button {
            border: none;
            border-radius: 3px;
            cursor: pointer;
            font-size: 14px;
            padding: 14px 28px;
            text-decoration: none;
        }

        .revoke__button--danger {
            background: #D83C3E;
            color: #fff;
        }

        .revoke__button--danger:hover {
            background: #B93234;
        }

        .revoke__button--secondary {
            background: #E3E5E8;
            color: #2E3338;
        }
    </style>
</head>
<body>
<div class="revoke">
    <h1 class="revoke__title">@lang('text.revokeConfirmTitle')</h1>
    <p class="revoke__body">@lang('text.revokeConfirmBody')</p>

    <form method="POST" action="{{ route('revoke') }}" class="revoke__actions">
        @csrf
        <a href="{{ route('home') }}" class="revoke__button revoke__button--secondary">@lang('text.cancel')</a>
        <button type="submit" class="revoke__button revoke__button--danger">@lang('text.revokeConfirmButton')</button>
    </form>
</div>
</body>
</html>
