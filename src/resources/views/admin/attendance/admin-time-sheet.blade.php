@extends('layouts.app')

@section('title','勤怠一覧')

@section('css')
<link rel="stylesheet" href="{{ asset('css/time-sheet.css') }}">
@endsection

@section('content')

@include('components.admin-header')
<main class="page">
    <section class="sheet">
        <h1 class="sheet__title">
            {{ $date->format('Y年n月j日') }}の勤怠一覧
        </h1>

        <div class="day-nav" aria-label="日切替">
            <a class="day-nav__btn" href="{{ route('admin.attendance', ['date' => $prevDay]) }}" aria-label="前日">
                <span class="chev">
                    <img src="{{ asset('images/arrow.png') }}" alt="<-" class="arrow">
                </span> 前日
            </a>

            <div class="day-nav__current" role="heading" aria-level="2">
                <span class="cal-icn" aria-hidden="true">
                    <img src="{{ asset('images/calendar.png') }}" alt="<-">
                </span>
                <span class="day-nav__text">{{ $date->format('Y/m/d ') }}</span>
            </div>

            <a class="day-nav__btn day-nav__btn--right"
            href="{{ route('admin.attendance', ['date' => $nextDay]) }}" aria-label="翌日">翌日
                <span class="chev">
                    <img src="{{ asset('images/arrow.png') }}" alt="->" class="arrow">
                </span>
            </a>
        </div>

        <div class="table-wrap">
            <table class="attendance">
                <thead>
                    <tr>
                        <th>名前</th>
                        <th>出勤</th>
                        <th>退勤</th>
                        <th>休憩</th>
                        <th>合計</th>
                        <th>詳細</th>
                    </tr>
                </thead>
                <tbody>
                    @php
                        $dash  = '';
                        $fmt   = fn($t) => $t ? \Carbon\Carbon::parse($t)->format('H:i') : $dash;
                        $fmtHM = fn($min) => is_null($min) ? $dash : sprintf('%d:%02d', intdiv($min, 60), $min % 60);
                    @endphp

                    @forelse ($attendances as $att)
                        @php
                            $breakMin = 0;
                            foreach ($att->breaks as $br) {
                                if ($br->break_start && $br->break_end) {
                                    $breakMin += \Carbon\Carbon::parse($br->break_start)->diffInMinutes(\Carbon\Carbon::parse($br->break_end));
                                }
                            }
                            $totalMin = null;
                            if ($att->clock_in && $att->clock_out) {
                                $span = \Carbon\Carbon::parse($att->clock_in)->diffInMinutes(\Carbon\Carbon::parse($att->clock_out));
                                $totalMin = max(0, $span - $breakMin);
                            }
                        @endphp

                        <tr>
                            <td>{{ optional($att->user)->name ?? $dash }}</td>
                            <td>{{ $fmt($att->clock_in) }}</td>
                            <td>{{ $fmt($att->clock_out) }}</td>
                            <td>{{ $fmtHM($breakMin) }}</td>
                            <td>{{ $fmtHM($totalMin) }}</td>
                            <td>
                                <a href="{{ route('admin.show', ['attendance' => $att->id]) }}">詳細</a>
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