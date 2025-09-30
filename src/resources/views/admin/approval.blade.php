@extends('layouts.app')

@section('title','修正申請')

@section('css')
<link rel="stylesheet" href="{{ asset('css/detail.css') }}">
@endsection

@section('content')

@include('components.admin-header')
@php
    $pendingApp = $app;
    $newAtt     = $app->newAttendance;
    $newBrs     = $app->newBreaks ?? collect();
    $attendance = $attendance ?? $newAtt?->attendance;
    $isPending  = ($app->status ?? 'pending') === 'pending';
@endphp

<main class="page">
    <section class="sheet">
        <h1 class="sheet__title">勤怠詳細</h1>

        <div class="detail-card">
            <div class="detail-row">
                <div class="detail-label">名前</div>
                <div class="detail-value detail-value--center">
                    {{ $app->user?->name ?? $attendance?->user?->name ?? '—' }}
                </div>
            </div>

            <div class="detail-row">
                <div class="detail-label">日付</div>
                <div class="detail-value">
                    <div class="detail-split">
                        <div class="detail-strong">
                            {{ $attendance?->date?->format('Y年') ?? '—' }}
                        </div>
                        <div class="detail-strong">
                            {{ $attendance?->date?->format('n月j日') ?? '—' }}
                        </div>
                    </div>
                </div>
            </div>

            <div class="detail-row">
                <div class="detail-label">出勤・退勤</div>
                <div class="detail-value">
                    <div class="detail-range">
                        @php
                            $cin  = $newAtt?->new_clock_in  ?? $attendance?->clock_in;
                            $cout = $newAtt?->new_clock_out ?? $attendance?->clock_out;
                        @endphp
                        <span class="pill-static_left">
                            {{ $cin  ? \Carbon\Carbon::parse($cin)->format('H:i')  : '' }}
                        </span>
                        <span class="tilde">〜</span>
                        <span class="pill-static_right">
                            {{ $cout ? \Carbon\Carbon::parse($cout)->format('H:i') : '' }}
                        </span>
                    </div>
                </div>
            </div>

            <div id="breaks-rows">
                @php
                    $rows = $newBrs->count() ? $newBrs :
                        ($attendance->breaks ?? collect());
                    $rows = $rows->push(null);
                @endphp
                @foreach ($rows as $i => $row)
                    @php
                        $start = $row->new_break_start ??
                            $row->break_start ?? null;
                        $end   = $row->new_break_end   ??
                            $row->break_end   ?? null;
                    @endphp

                    <div class="detail-row break-row" data-index="{{ $i }}">
                        <div class="detail-label">{{ $i === 0 ? '休憩' : '休憩'.($i+1) }}</div>
                        <div class="detail-value">
                            <div class="detail-range">
                                <span class="pill-static_left">
                                    {{ $start ? \Carbon\Carbon::parse($start)->format('H:i') : '' }}
                                </span>

                                @if($start || $end)
                                    <span class="tilde">〜</span>
                                @endif

                                <span class="pill-static_right">
                                    {{ $end ? \Carbon\Carbon::parse($end)->format('H:i') : '' }}
                                </span>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="detail-row">
                <div class="detail-label">備考</div>
                <div class="detail-value">
                    <div class="textarea-static">
                        {{ $pendingApp->remark ?? '' }}
                    </div>
                </div>
            </div>
        </div>

        @if ($app->status === 'pending')
            <form action="{{ route('admin.approve', ['attendance_correct_request' => $app->id]) }}" method="post" class="detail-actions">
                @csrf
                @method('PATCH')
                <button type="submit" id="approve-btn" class="btn btn-primary">承認</button>
            </form>

            <script>
                document.addEventListener('DOMContentLoaded', function () {
                    const form = document.getElementById('approve-form');
                    const btn  = document.getElementById('approve-btn');
                    if (!form || !btn) return;

                    form.addEventListener('submit', function () {
                        btn.textContent = '承認済み';
                        btn.disabled = true;          // 二重送信防止
                    });
                });
            </script>
        @else
            <div class="detail-actions">
                <button type="button" class="btn btn-secondary" disabled>承認済み</button>
            </div>
        @endif
    </section>
</main>
@endsection