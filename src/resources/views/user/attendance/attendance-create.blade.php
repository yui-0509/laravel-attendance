@extends('layouts.app')

@section('title','勤怠登録')

@section('css')
<link rel="stylesheet" href="{{ asset('/css/attendance.css')  }}">
@endsection

@section('content')

@include('components.header')
    <div class="attendance">
        <div class="attendance__status" aria-live="polite"></div>

        <div class="attendance__date">{{ now()->format('Y年n月j日(D)') }}</div>
        <div class="attendance__time">{{ now()->format('H:i') }}</div>

        <div class="attendance__buttons">
            <button class="attendance__btn" id="clockInBtn">出勤</button>
            <button class="attendance__btn" id="clockOutBtn" style="display:none;">退勤</button>
            <button class="attendance__btn attendance__btn--break" id="breakInBtn" style="display:none;">休憩入</button>
            <button class="attendance__btn attendance__btn--break" id="breakOutBtn" style="display:none;">休憩戻</button>
        </div>
    </div>

    <script>
        function updateClock() {
            const now = new Date();

            const options = {
                year: 'numeric', month: 'long', day: 'numeric',
                weekday: 'short'
            };
            document.querySelector('.attendance__date').textContent =
                now.toLocaleDateString('ja-JP', options);

            const hours = String(now.getHours()).padStart(2, '0');
            const minutes = String(now.getMinutes()).padStart(2, '0');
            document.querySelector('.attendance__time').textContent = `${hours}:${minutes}`;
        }

        updateClock();
        setInterval(updateClock, 1000);

        const statusEl = document.querySelector('.attendance__status');
        const buttonsEl = document.querySelector('.attendance__buttons');
        const clockInBtn = document.getElementById('clockInBtn');
        const breakInBtn = document.getElementById('breakInBtn');
        const breakOutBtn = document.getElementById('breakOutBtn');
        const clockOutBtn = document.getElementById('clockOutBtn');

        const initialState = @json($state);

        function applyState(state) {
            clockInBtn.style.display  = 'none';
            breakInBtn.style.display  = 'none';
            breakOutBtn.style.display = 'none';
            clockOutBtn.style.display = 'none';

            const oldMsg = buttonsEl.querySelector('.attendance__message');
            if (oldMsg) oldMsg.remove();

            if (state === 'none') {
                statusEl.textContent = '勤務外';
                clockInBtn.style.display = 'inline-block';
            } else if (state === 'working') {
                statusEl.textContent = '出勤中';
                breakInBtn.style.display = 'inline-block';
                clockOutBtn.style.display = 'inline-block';
            } else if (state === 'on_break') {
                statusEl.textContent = '休憩中';
                breakOutBtn.style.display = 'inline-block';
            } else if (state === 'done') {
                statusEl.textContent = '退勤済';
                const message = document.createElement('div');
                message.textContent = 'お疲れ様でした。';
                message.classList.add('attendance__message');
                buttonsEl.appendChild(message);
            }
        }

        applyState(initialState);

        async function postJSON(url, body = {}) {
            const res = await fetch(url, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                },
                body: JSON.stringify(body)
            });
            if (!res.ok) {
                let err;
                try { err = await res.json(); } catch { err = { message: 'エラーが発生しました'}; }
                throw err;
            }
            return res.json();
        }

        function toggleButtonsDisabled(disabled) {
            [clockInBtn, breakInBtn, breakOutBtn, clockOutBtn].forEach(btn => {
                if (!btn) return;
                btn.disabled = disabled;
                btn.style.opacity = disabled ? '0.7' : '1';
                btn.style.pointerEvents = disabled ? 'none' : 'auto';
            });
        }

        // 出勤
        clockInBtn.addEventListener('click', async () => {
            try {
                toggleButtonsDisabled(true);
                await postJSON('{{ route("attendance.clockIn") }}');
                applyState('working');
            } catch (e) {
                alert(e.message ?? '出勤に失敗しました。');
            } finally {
                toggleButtonsDisabled(false);
            }
        });

        // 休憩入
        breakInBtn.addEventListener('click', async () => {
            try {
                toggleButtonsDisabled(true);
                await postJSON('{{ route("attendance.startBreak") }}');
                applyState('on_break');
            } catch (e) {
                alert(e.message ?? '休憩開始に失敗しました。');
            } finally {
                toggleButtonsDisabled(false);
            }
        });

        // 休憩戻
        breakOutBtn.addEventListener('click', async () => {
            try {
                toggleButtonsDisabled(true);
                await postJSON('{{ route("attendance.endBreak") }}');
                applyState('working');
            } catch (e) {
                alert(e.message ?? '休憩終了に失敗しました。');
            } finally {
                toggleButtonsDisabled(false);
            }
        });

        // 退勤
        clockOutBtn.addEventListener('click', async () => {
            try {
                toggleButtonsDisabled(true);
                await postJSON('{{ route("attendance.clockOut") }}');
                applyState('done');
            } catch (e) {
                alert(e.message ?? '退勤に失敗しました。');
            } finally {
                toggleButtonsDisabled(false);
            }
        });
    </script>
@endsection