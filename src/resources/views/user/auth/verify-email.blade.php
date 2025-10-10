@extends('layouts.app')

@section('title','メール認証')

@section('css')
<link rel="stylesheet" href="{{ asset('css/verify.css')  }}">
@endsection

@section('content')

@include('components.header')
<div class="verify-container">
    <p class="verify-message">
        登録していただいたメールアドレスに認証メールを送付しました。<br>
        メール認証を完了してください。
    </p>

    <a href="{{ route('verification.check') }}" class="verify-button">認証はこちらから</a>

    @php($status = session('status'))
    @if($status)
        <p class="verify-hint" aria-live="polite" role="status">
            @if($status === 'verification-link-sent')
                認証メールを再送しました。
            @else
                {{ $status }}
            @endif
        </p>
    @endif

    <form method="POST" action="{{ route('verification.send') }}">
    @csrf
        <button class="resend-link" type="submit">認証メールを再送する</button>
    </form>
</div>
@endsection