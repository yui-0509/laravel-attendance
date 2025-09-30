@extends('layouts.app')

@section('title','勤怠詳細')

@section('css')
<link rel="stylesheet" href="{{ asset('css/detail.css') }}">
@endsection

@section('content')

@include('components.header')
@php
  $isPending = !empty($pendingApp ?? null);
  $newAtt    = $isPending ? $pendingApp->newAttendance : null;
  $newBrs    = $isPending ? $pendingApp->newBreaks : collect();
@endphp

<main class="page">
  <section class="sheet">
    <h1 class="sheet__title">勤怠詳細</h1>

    <div class="detail-card">
      <div class="detail-row">
        <div class="detail-label">名前</div>
        <div class="detail-value detail-value--center">
          {{ optional($attendance->user)->name ?? '' }}
        </div>
      </div>

      <div class="detail-row">
        <div class="detail-label">日付</div>
        <div class="detail-value">
          <div class="detail-split">
            <div class="detail-strong">
              {{ $attendance->date?->format('Y年') }}
            </div>
            <div class="detail-strong">
              {{ $attendance->date?->format('n月j日') }}
            </div>
          </div>
        </div>
      </div>

      <form id="correction-form" action="{{ route('attendance.correction', $attendance->id) }}" method="post">
        @csrf

        <div class="detail-row">
          <div class="detail-label">出勤・退勤</div>
          <div class="detail-value">
            <div class="detail-range">
              @if ($isPending)
                <span class="pill-static_left">
                  {{ $newAtt?->new_clock_in
                    ? \Carbon\Carbon::parse($newAtt->new_clock_in)->format('H:i')
                    : ($attendance->clock_in ? \Carbon\Carbon::parse($attendance->clock_in)->format('H:i') : '') }}
                </span>
                <span class="tilde">〜</span>
                <span class="pill-static_right">
                  {{ $newAtt?->new_clock_out
                      ? \Carbon\Carbon::parse($newAtt->new_clock_out)->format('H:i')
                      : ($attendance->clock_out ? \Carbon\Carbon::parse($attendance->clock_out)->format('H:i') : '') }}
                </span>
              @else
                <div class="range-col">
                  <input class="pill_left {{ $errors->has('new_clock_in') ? 'is-invalid' : '' }}"
                    type="text" name="new_clock_in"
                    value="{{ old('new_clock_in', $attendance->clock_in ? \Carbon\Carbon::parse($attendance->clock_in)->format('H:i') : '') }}"
                    pattern="^(?:[01][0-9]|2[0-3]):[0-5][0-9]$" maxlength="5">
                  <div class="form__error">
                    @error('new_clock_in') {{ $message }} @enderror
                  </div>
                </div>

                <span class="tilde">〜</span>

                <div class="range-col">
                  <input class="pill_right {{ $errors->has('new_clock_out') ? 'is-invalid' : '' }}"
                    type="text" name="new_clock_out"
                    value="{{ old('new_clock_out', $attendance->clock_out ? \Carbon\Carbon::parse($attendance->clock_out)->format('H:i') : '') }}"
                    pattern="^(?:[01][0-9]|2[0-3]):[0-5][0-9]$" maxlength="5">
                  <div class="form__error">
                    @error('new_clock_out') {{ $message }} @enderror
                  </div>
                </div>
              @endif
            </div>
          </div>
        </div>

        @if (!$isPending)
          <div id="breaks-rows">
            @php
              $breaks = $attendance->breaks ?? collect();
              $count  = $breaks->count();
            @endphp

            @foreach ($breaks as $i => $br)
              @php
                $startKey = "breaks.$i.new_break_start";
                $endKey   = "breaks.$i.new_break_end";
                $idKey    = "breaks.$i.break_id";
              @endphp

              <div class="detail-row break-row" data-index="{{ $i }}">
                <div class="detail-label">{{ $i === 0 ? '休憩' : '休憩'.($i+1) }}</div>
                <div class="detail-value">
                  <div class="detail-range">
                    <input type="hidden" name="breaks[{{ $i }}][break_id]" value="{{ $br->id }}">

                    <div class="range-col">
                      <input class="pill_left {{ $errors->has($startKey) ? 'is-invalid' : '' }}"
                        type="text"
                        name="breaks[{{ $i }}][new_break_start]"
                        value="{{ old('breaks.'.$i.'.new_break_start', $br->break_start ? \Carbon\Carbon::parse($br->break_start)->format('H:i') : '') }}"
                        pattern="^(?:[01][0-9]|2[0-3]):[0-5][0-9]$" maxlength="5">
                      <div class="form__error">
                        @foreach ($errors->get($startKey) as $msg)
                          <div>{{ $msg }}</div>
                        @endforeach
                      </div>
                    </div>

                    <span class="tilde">〜</span>

                    <div class="range-col">
                      <input class="pill_right {{ $errors->has($endKey) ? 'is-invalid' : '' }}"
                        type="text"
                        name="breaks[{{ $i }}][new_break_end]"
                        value="{{ old('breaks.'.$i.'.new_break_end', $br->break_end ? \Carbon\Carbon::parse($br->break_end)->format('H:i') : '') }}"
                        pattern="^(?:[01][0-9]|2[0-3]):[0-5][0-9]$" maxlength="5">
                      <div class="form__error">
                        @foreach ($errors->get($endKey) as $msg)
                          <div>{{ $msg }}</div>
                        @endforeach
                      </div>
                    </div>

                    @if ($errors->has($idKey))
                      <div class="form__error form__error--full">
                        @foreach ($errors->get($idKey) as $msg)
                          <div>{{ $msg }}</div>
                        @endforeach
                      </div>
                    @endif
                  </div>
                </div>
              </div>
            @endforeach

            {{-- 末尾に必ず空の1行（新規追加用）） --}}
            @php
              $j = $count;
              $startKey = "breaks.$j.new_break_start";
              $endKey   = "breaks.$j.new_break_end";
            @endphp

            <div class="detail-row break-row" data-index="{{ $j }}">
              <div class="detail-label">{{ $j === 0 ? '休憩' : '休憩'.($j+1) }}</div>
              <div class="detail-value">
                <div class="detail-range">
                  <input type="hidden" name="breaks[{{ $j }}][break_id]" value="">

                  <div class="range-col">
                    <input class="pill_left {{ $errors->has($startKey) ? 'is-invalid' : '' }}"
                      type="text" name="breaks[{{ $j }}][new_break_start]"
                      value="{{ old('breaks.'.$j.'.new_break_start') }}"
                      pattern="^(?:[01][0-9]|2[0-3]):[0-5][0-9]$" maxlength="5">
                    <div class="form__error">
                      @foreach ($errors->get($startKey) as $msg)
                        <div>{{ $msg }}</div>
                      @endforeach
                    </div>
                  </div>

                  <span class="tilde">〜</span>

                  <div class="range-col">
                    <input class="pill_right {{ $errors->has($endKey) ? 'is-invalid' : '' }}"
                      type="text" name="breaks[{{ $j }}][new_break_end]"
                      value="{{ old('breaks.'.$j.'.new_break_end') }}"
                      pattern="^(?:[01][0-9]|2[0-3]):[0-5][0-9]$" maxlength="5">
                    <div class="form__error">
                      @foreach ($errors->get($endKey) as $msg)
                        <div>{{ $msg }}</div>
                      @endforeach
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          {{-- テンプレート（1行まるごと） --}}
          <template id="break-row-template">
            <div class="detail-row break-row" data-index="__INDEX__">
              <div class="detail-label">__LABEL__</div>
              <div class="detail-value">
                <div class="detail-range">
                  <input type="hidden" name="breaks[__INDEX__][break_id]" value="">
                  <input class="pill_left" type="text" name="breaks[__INDEX__][new_break_start]" value="" pattern="^(?:[01][0-9]|2[0-3]):[0-5][0-9]$" maxlength="5">

                  <span class="tilde">〜</span>

                  <input class="pill_right" type="text" name="breaks[__INDEX__][new_break_end]" value="" pattern="^(?:[01][0-9]|2[0-3]):[0-5][0-9]$" maxlength="5">
                </div>
              </div>
            </div>
          </template>
        @else
          <!-- 休憩（承認待ち・読取専用） -->
          @php
            $current = ($attendance->breaks ?? collect())->values();
            $nbById  = ($newBrs ?? collect())->whereNotNull('break_id')->keyBy('break_id');
            $merged  = collect();

            foreach ($current as $br) {
              $nb    = $nbById->get($br->id);
              $start = $nb && isset($nb->new_break_start) ? $nb->new_break_start : $br->break_start;
              $end   = $nb && isset($nb->new_break_end)   ? $nb->new_break_end   : $br->break_end;

              $merged->push((object)[
                'break_id' => $br->id,
                'start'    => $start,
                'end'      => $end,
                'sort_key' => $br->break_start ?? '99:99:99',
              ]);
            }

            foreach (($newBrs ?? collect())->whereNull('break_id') as $nb) {
              $merged->push((object)[
                'break_id' => null,
                'start'    => $nb->new_break_start ?? null,
                'end'      => $nb->new_break_end   ?? null,
                // sort_order を持っていれば優先。無ければ後ろへ。
                'sort_key' => $nb->sort_order ?? '99:99:99',
              ]);
            }

            $rows = $merged->sortBy('sort_key')->values();
          @endphp

          @foreach ($rows as $i => $row)
            <div class="detail-row break-row" data-index="{{ $i }}">
              <div class="detail-label">{{ $i === 0 ? '休憩' : '休憩'.($i+1) }}</div>
              <div class="detail-value">
                <div class="detail-range">
                  <span class="pill-static_left">
                    {{ $row->start ? \Carbon\Carbon::parse($row->start)->format('H:i') : '' }}
                  </span>
                  <span class="tilde">〜</span>
                  <span class="pill-static_right">
                    {{ $row->end ? \Carbon\Carbon::parse($row->end)->format('H:i') : '' }}
                  </span>
                </div>
              </div>
            </div>
          @endforeach
        @endif

        <div class="detail-row">
          <div class="detail-label">備考</div>
          <div class="detail-value">
            @if ($isPending)
              <div class="textarea-static">
                {{ $pendingApp->remark ?? '' }}
              </div>
            @else
              <textarea class="textarea-like {{ $errors->has('remark') ? 'is-invalid' : '' }}"
                name="remark">
                {{ old('remark', $pendingApp->remark ?? '') }}
              </textarea>
              <div class="form__error">
                @error('remark') {{ $message }} @enderror
              </div>
            @endif
          </div>
        </div>
      </form>
    </div>

    @if (!$isPending)
      <div class="detail-actions">
        <button type="submit" form="correction-form" class="btn btn-primary">修正</button>
      </div>
    @else
      <p class="pending-note">*承認待ちのため修正はできません。</p>
    @endif
  </section>
</main>
@if (!$isPending)
  <script>
  document.addEventListener('DOMContentLoaded', () => {
    const container = document.getElementById('breaks-rows');
    const tmpl = document.getElementById('break-row-template').innerHTML;

    let idx = Math.max(-1, ...[...container.querySelectorAll('.break-row')].map(r => +r.dataset.index)) + 1;

    const isRowEmpty = (row) => {
      const ins = row.querySelectorAll('input[type="text"].pill_left, input[type="text"].pill_right');
      return [...ins].every(inp => !inp.value.trim());
    };

    const addEmptyRow = () => {
      const label = (idx === 0) ? '休憩' : `休憩${idx + 1}`;
      const html = tmpl.replaceAll('__INDEX__', idx).replaceAll('__LABEL__', label);
      container.insertAdjacentHTML('beforeend', html);
      idx++;
    };

    container.addEventListener('input', (e) => {
      if (!e.target.matches('input[type="text"].pill_left, input[type="text"].pill_right')) return;

      const rows = container.querySelectorAll('.break-row');
      const last = rows[rows.length - 1];
      if (!isRowEmpty(last)) {
        addEmptyRow();
      }
    });
  });
  </script>
@endif
@endsection