@extends('layouts.app')

@section('title','申請一覧')

@section('css')
<link rel="stylesheet" href="{{ asset('css/application.css') }}">
@endsection

@section('content')

@include('components.admin-header')
@php
    $activeTab = request('tab', 'pending');
@endphp

<main class="page">
    <section class="sheet">
        <h1 class="sheet__title">申請一覧</h1>

        <nav class="tabs">
            <a href="{{ route('request.index', ['tab' => 'pending']) }}"
            class="tab {{ $activeTab === 'pending' ? 'is-active' : '' }}">承認待ち</a>
            <a href="{{ route('request.index', ['tab' => 'approved']) }}"
            class="tab {{ $activeTab === 'approved' ? 'is-active' : '' }}">承認済み</a>
        </nav>

        <!-- 一覧カード -->
        <div class="list-card">
            <table class="req-table">
                <thead>
                    <tr>
                        <th>状態</th>
                        <th>名前</th>
                        <th>対象日時</th>
                        <th>申請理由</th>
                        <th>申請日時</th>
                        <th>詳細</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($applications as $app)
                        <tr>
                            <td>
                                @if ($app->status === 'pending')
                                    <span class="badge badge--pending">承認待ち</span>
                                    @elseif ($app->status === 'approved')
                                        <span class="badge badge--approved">
                                            承認済み
                                        </span>
                                    @else
                                        <span class="badge">
                                            {{ $app->status }}
                                        </span>
                                @endif
                            </td>
                            <td>
                                {{ optional($app->user)->name ?? '—' }}
                            </td>
                            <td>
                                {{($app->newAttendance?->attendance?->date)?->format('Y/m/d')?? '—'}}
                            </td>
                            <td class="reason">
                                {{ $app->remark ?? '—' }}
                            </td>
                            <td>
                                {{ $app->created_at?->format('Y/m/d') ?? '—' }}
                            </td>
                            <td>
                                <a class="link-detail"
                                    href="{{ route('admin.approve.show', ['attendance_correct_request' => $app->id]) }}">詳細
                                </a>
                            </td>
                        </tr>
                        @empty
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</main>
@endsection