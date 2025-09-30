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
    @if(session('status'))
        <p class="verify-hint">{{ session('status') }}</p>
    @endif
    <form method="POST" action="{{ route('verification.send') }}">
    @csrf
        <button class="resend-link" type="submit">認証メールを再送する</button>
    </form>

    @if(session('status') === 'verification-link-sent')
        <p class="verify-hint">認証メールを再送しました。</p>
    @endif
</div>
@endsection