@extends('layouts.app')

@section('title','スタッフ別勤怠一覧')

@section('css')
<link rel="stylesheet" href="{{ asset('css/time-sheet.css') }}">
@endsection

@section('content')

@include('components.admin-header')
<main class="page">
  <section class="sheet">
    <h1 class="sheet__title">{{ $user->name }}さんの勤怠</h1>

    <div class="month-nav" aria-label="月切替">
      <a class="month-nav__btn" href="{{ route('admin.userIndex', ['user' => $user->id, 'month' => $prevMonth]) }}" aria-label="前月">
        <span class="chev">
          <img src="{{ asset('images/arrow.png') }}" alt="<-" class="arrow">
        </span> 前月
      </a>

      <div class="month-nav__current" role="heading" aria-level="2">
        <span class="cal-icn" aria-hidden="true">
          <img src="{{ asset('images/calendar.png') }}" alt="🗓️">
        </span>
        <span class="month-nav__text">
          {{ $month->format('Y/m') }}
        </span>
      </div>

      <a class="month-nav__btn month-nav__btn--right"
        href="{{ route('admin.userIndex', ['user' => $user->id, 'month' => $nextMonth]) }}" aria-label="翌月">翌月
        <span class="chev">
          <img src="{{ asset('images/arrow.png') }}" alt="->" class="arrow">
        </span>
      </a>
    </div>

    <div class="table-wrap">
      <table class="attendance">
        <thead>
          <tr>
            <th>日付</th>
            <th>出勤</th>
            <th>退勤</th>
            <th>休憩</th>
            <th>合計</th>
            <th>詳細</th>
          </tr>
        </thead>
        <tbody>
          @foreach ($days as $day)
            @php
              $w = ['日','月','火','水','木','金','土'][$day['date']->dayOfWeek];
              $isOff = is_null($day['clock_in']) && is_null($day['clock_out']);
              $dash  = '';
              $fmt   = fn($t) => $t ? $t->format('H:i') : $dash;
              $fmtHM = fn($min) => is_null($min) ? $dash : sprintf('%d:%02d', intdiv($min,60), $min%60);
            @endphp

            <tr class="{{ $isOff ? 'row--off' : '' }}">
              <td>{{ $day['date']->format('m/d') }}({{ $w }})</td>
              <td>{{ $fmt($day['clock_in']) }}</td>
              <td>{{ $fmt($day['clock_out']) }}</td>
              <td>{{ $fmtHM($day['break_min']) }}</td>
              <td>{{ $fmtHM($day['total_min']) }}</td>
              <td>
                <a href="{{ route('admin.show', [
                    'attendance' => $day['record']->id ?? 0,
                    'date' => $day['date']->format('Y-m-d')
                    ]) }}">詳細</a>
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
    <div class="actions">
      <a class="btn btn-primary" href="{{ route('admin.attendance.csv', [
        'user' => $user->id,
        'month' => request('month', now()->format('Y-m')),
        ]) }}">CSV出力</a>
    </div>
  </section>
</main>
@endsection