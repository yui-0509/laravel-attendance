@extends('layouts.app')

@section('title','勤怠詳細')

@section('css')
<link rel="stylesheet" href="{{ asset('css/detail.css') }}">
@endsection

@section('content')

@include('components.admin-header')
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

            <form id="correction-form" action="{{ route('admin.update', ['attendance' => $attendance->id]) }}" method="post">
                @csrf
                @method('PATCH')

                <div class="detail-row">
                    <div class="detail-label">出勤・退勤</div>
                    <div class="detail-value">
                        <div class="detail-range">
                            <div class="range-col">
                                <input class="pill_left {{ $errors->has('clock_in') ? 'is-invalid' : '' }}"
                                type="text" name="clock_in"
                                value="{{ old('clock_in', $attendance->clock_in ? \Carbon\Carbon::parse($attendance->clock_in)->format('H:i') : '') }}"
                                pattern="^(?:[01][0-9]|2[0-3]):[0-5][0-9]$" maxlength="5">
                                <div class="form__error">
                                    @error('clock_in')
                                        {{ $message }}
                                    @enderror
                                </div>
                            </div>

                            <span class="tilde">〜</span>

                            <div class="range-col">
                                <input class="pill_right {{ $errors->has('clock_out') ? 'is-invalid' : '' }}"
                                type="text" name="clock_out"
                                value="{{ old('clock_out', $attendance->clock_out ? \Carbon\Carbon::parse($attendance->clock_out)->format('H:i') : '') }}"
                                pattern="^(?:[01][0-9]|2[0-3]):[0-5][0-9]$" maxlength="5">
                                <div class="form__error">
                                    @error('clock_out')
                                        {{ $message }}
                                    @enderror
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div id="breaks-rows">
                    @php
                        $breaks = $attendance->breaks ?? collect();
                        $count  = $breaks->count();
                    @endphp

                    @foreach ($breaks as $i => $br)
                        @php
                            $startKey = "breaks.$i.break_start";
                            $endKey   = "breaks.$i.break_end";
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
                                        name="breaks[{{ $i }}][break_start]"
                                        value="{{ old('breaks.'.$i.'.break_start', $br->break_start ? \Carbon\Carbon::parse($br->break_start)->format('H:i') : '') }}"
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
                                        name="breaks[{{ $i }}][break_end]"
                                        value="{{ old('breaks.'.$i.'.break_end', $br->break_end ? \Carbon\Carbon::parse($br->break_end)->format('H:i') : '') }}"
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
                        $startKey = "breaks.$j.break_start";
                        $endKey   = "breaks.$j.break_end";
                        $valStart = old("breaks.$j.break_start");
                        $valEnd   = old("breaks.$j.break_end");
                    @endphp

                    <div class="detail-row break-row" data-index="{{ $j }}">
                        <div class="detail-label">{{ $j === 0 ? '休憩' : '休憩'.($j+1) }}</div>
                        <div class="detail-value">
                            <div class="detail-range">
                                <input type="hidden" name="breaks[{{ $j }}][break_id]" value="">

                                <div class="range-col">
                                    <input class="pill_left {{ $errors->has($startKey) ? 'is-invalid' : '' }}"
                                    type="text"
                                    name="breaks[{{ $j }}][break_start]"
                                    value="{{ $valStart }}"
                                    pattern="^(?:[01][0-9]|2[0-3]):[0-5][0-9]$"
                                    maxlength="5">
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
                                    name="breaks[{{ $j }}][break_end]"
                                    value="{{ $valEnd }}"
                                    pattern="^(?:[01][0-9]|2[0-3]):[0-5][0-9]$"
                                    maxlength="5">
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

                                <input class="pill_left"
                                    type="text"
                                    name="breaks[__INDEX__][break_start]"
                                    value=""
                                    pattern="^(?:[01][0-9]|2[0-3]):[0-5][0-9]$"
                                    maxlength="5">

                                <span class="tilde">〜</span>

                                <input class="pill_right"
                                    type="text"
                                    name="breaks[__INDEX__][break_end]"
                                    value=""
                                    pattern="^(?:[01][0-9]|2[0-3]):[0-5][0-9]$"
                                    maxlength="5">
                            </div>
                        </div>
                    </div>
                </template>

                <div class="detail-row">
                    <div class="detail-label">備考</div>
                    <div class="detail-value">
                        <textarea class="textarea-like {{ $errors->has('remark') ? 'is-invalid' : '' }}" name="remark">{{ old('remark', $attendance->remark ?? '') }}</textarea>
                        <div class="form__error">
                            @error('remark') {{ $message }} @enderror
                        </div>
                    </div>
                </div>
            </form>
        </div>

        <div class="detail-actions">
            <button type="submit" form="correction-form" class="btn btn-primary">修正</button>
        </div>
    </section>
</main>
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

    // 末尾の行に値が入ったら、空の新行を追加
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
@endsection