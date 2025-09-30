@extends('layouts.app')

@section('title','スタッフ一覧')

@section('css')
<link rel="stylesheet" href="{{ asset('css/application.css') }}">
@endsection

@section('content')

@include('components.admin-header')
<div class="page">
    <div class="sheet">
        <h1 class="sheet__title">スタッフ一覧</h1>

        <div class="list-card">
            <table class="req-table">
                <thead>
                    <tr>
                        <th>名前</th>
                        <th>メールアドレス</th>
                        <th>月次勤怠</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($users as $user)
                        <tr>
                            <td>{{ $user->name }}</td>
                            <td>{{ $user->email }}</td>
                            <td>
                                <a  class="link-detail"
                                    href="{{ route('admin.userIndex', [
                                        'user' => $user->id,
                                        'month' => now('Asia/Tokyo')->format('Y-m')]) }}">
                                    詳細
                                </a>
                            </td>
                        </tr>
                    @empty
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection