@extends('layouts.app')

@section('title','勤怠登録')

@section('css')
<link rel="stylesheet" href="{{ asset('/css/attendance.css')  }}">
@endsection

@section('content')

@include('components.header')
    <div class="attendance">
        <div class="attendance__status" aria-live="polite"></div>

        <div class="attendance__date"></div>
        <div class="attendance__time"></div>

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

            // 日付の表示
            const options = {
                year: 'numeric', month: 'long', day: 'numeric',
                weekday: 'short'
            };
            document.querySelector('.attendance__date').textContent =
                now.toLocaleDateString('ja-JP', options);

            // 時刻の表示（ゼロ埋め）
            const hours = String(now.getHours()).padStart(2, '0');
            const minutes = String(now.getMinutes()).padStart(2, '0');
            document.querySelector('.attendance__time').textContent = `${hours}:${minutes}`;
        }

        // 最初に実行
        updateClock();
        // 1秒ごとに更新
        setInterval(updateClock, 1000);

        const statusEl = document.querySelector('.attendance__status');
        const buttonsEl = document.querySelector('.attendance__buttons');
        const clockInBtn = document.getElementById('clockInBtn');
        const breakInBtn = document.getElementById('breakInBtn');
        const breakOutBtn = document.getElementById('breakOutBtn');
        const clockOutBtn = document.getElementById('clockOutBtn');

        const initialState = @json($state); // 'none' | 'working' | 'on_break' | 'done'

        function applyState(state) {
            // 全ボタン一旦隠す
            clockInBtn.style.display  = 'none';
            breakInBtn.style.display  = 'none';
            breakOutBtn.style.display = 'none';
            clockOutBtn.style.display = 'none';

            // 「お疲れ様でした。」の重複防止
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

        // ページロード時にサーバーの真実で初期化
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
                // エラーメッセージを拾って投げ返す
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
                toggleButtonsDisabled(true); // 二度押し防止
                await postJSON('{{ route("attendance.clockin") }}');
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
                await postJSON('{{ route("attendance.clockout") }}');
                applyState('done');
            } catch (e) {
                alert(e.message ?? '退勤に失敗しました。');
            } finally {
                toggleButtonsDisabled(false);
            }
        });
    </script>
@endsection