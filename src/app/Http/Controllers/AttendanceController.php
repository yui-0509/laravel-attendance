<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Carbon\Carbon;
use App\Models\Attendance;
use App\Models\BreakTime;
use App\Models\Application;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Arr;
use App\Models\NewAttendance;
use App\Models\NewBreak;
use App\Http\Requests\AttendanceRequest;


class AttendanceController extends Controller
{
    public function create(Request $request) {
        $user  = $request->user();
        $today = now()->toDateString();

        $attendance = \App\Models\Attendance::where('user_id', $user->id)
            ->where('date', $today)
            ->first();

        $state = 'none'; // 勤務外(未出勤)
        $openBreak = false;

        if ($attendance) {
            if ($attendance->clock_out) {
                $state = 'done'; // 退勤済
            } else {
                // 退勤前
                $openBreak = \App\Models\BreakTime::where('attendance_id', $attendance->id)
                    ->whereNull('break_end')
                    ->exists();

                $state = $openBreak ? 'on_break' : 'working';
            }
        }

        return view('user.attendance.attendance-create', [
            'state' => $state,
        ]);
    }

    public function index(Request $request)
    {
        $monthStr = $request->query('month') ?? now()->format('Y-m');
        $month    = Carbon::createFromFormat('Y-m', $monthStr)->startOfMonth();
        $start    = $month->copy()->startOfMonth();
        $end      = $month->copy()->endOfMonth();

        $days = collect();
        for ($d = $start->copy(); $d->lte($end); $d->addDay()) {
            $days->push([
                'date'        => $d->copy(),
                'clock_in'    => null,
                'clock_out'   => null,
                'break_min'   => null,
                'total_min'   => null,
                'record'      => null,
            ]);
        }

        $records = Attendance::query()
            ->with('breaks')
            ->where('user_id', auth()->id())
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->orderBy('date')
            ->get()
            ->keyBy(fn ($r) => $r->date->format('Y-m-d'));

        $days = $days->map(function ($row) use ($records) {
            $key = $row['date']->format('Y-m-d');
            if ($records->has($key)) {
                $r = $records[$key];

                $dayDate  = $r->date->format('Y-m-d');

                $clockIn   = $r->clock_in ? \Carbon\Carbon::parse("$dayDate {$r->clock_in}")  : null;
                $clockOut  = $r->clock_out ? \Carbon\Carbon::parse("$dayDate {$r->clock_out}") : null;

                $breakMin  = $r->breaks->sum(function ($b) use ($dayDate) {
                    if (empty($b->break_start) || empty($b->break_end)) return 0;
                    $bs = \Carbon\Carbon::parse("$dayDate {$b->break_start}");
                    $be = \Carbon\Carbon::parse("$dayDate {$b->break_end}");
                    return $be->greaterThan($bs) ? $be->diffInMinutes($bs) : 0;
                });

                $totalMin = null;
                if ($clockIn && $clockOut) {
                    $totalMin = max(0, $clockOut->diffInMinutes($clockIn) - $breakMin);
                }

                $row['clock_in']  = $clockIn;
                $row['clock_out'] = $clockOut;
                $row['break_min'] = ($breakMin > 0) ? $breakMin : null;
                $row['total_min'] = $totalMin;
                $row['record']    = $r;
            }
            return $row;
        });

        $prevMonth = $month->copy()->subMonth()->format('Y-m');
        $nextMonth = $month->copy()->addMonth()->format('Y-m');

        return view('user.attendance.user-time-sheet', [
            'month'     => $month,
            'days'      => $days,
            'prevMonth' => $prevMonth,
            'nextMonth' => $nextMonth,
        ]);
    }

    public function show($id, Request $request)
    {
        if ((int) $id === 0) {
            $date = $request->query('date');

            if (!$date || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            abort(404);
            }

            $attendance = Attendance::firstOrCreate(
                ['user_id' => auth()->id(), 'date' => $date],
                ['clock_in' => null, 'clock_out' => null]
            );

            return redirect()->route('attendance.show', ['id' => $attendance->id]);
        }

        $attendance = Attendance::with([
            'breaks' => fn($q) => $q->orderBy('break_start'),
        ])
        ->where('id', $id)
        ->where('user_id', auth()->id())
        ->firstOrFail();

        $pendingApp = Application::with([
                'newAttendance',
                'newBreaks' => fn($q) => $q->orderBy('new_break_start'),
            ])
            ->where('user_id', $attendance->user_id)
            ->where('status', 'pending')
            ->whereHas('newAttendance', fn($q) => $q->where('attendance_id', $attendance->id))
            ->latest()
            ->first();

        return view('user.attendance.user-detail', [
            'attendance' => $attendance,
            'pendingApp' => $pendingApp,
            'day'        => $attendance->date,
            ]);
    }

    public function correction(AttendanceRequest $request, Attendance $attendance)
    {
        // 1) 権限ガード：自分の勤怠のみ
        if ((int)$attendance->user_id !== (int)auth()->id()) {
            abort(403);
        }

        // 2) 現行データを先にロード（N+1防止）
        $attendance->load(['breaks' => fn($q) => $q->orderBy('break_start')]);

        $dateStr   = optional($attendance->date)->format('Y-m-d');
        $validated = $request->validated();

        // helper: 空文字を null に
        $toNull = fn($v) => ($v === '' ? null : $v);

        // 3) 現行値を H:i に正規化（比較用）
        $currentIn  = $attendance->clock_in  ? Carbon::parse($attendance->clock_in)->format('H:i')  : null;
        $currentOut = $attendance->clock_out ? Carbon::parse($attendance->clock_out)->format('H:i') : null;

        $in  = $toNull(Arr::get($validated, 'new_clock_in'));   // "HH:MM" or null
        $out = $toNull(Arr::get($validated, 'new_clock_out'));  // "HH:MM" or null

        // 4) 出退勤の差分だけ保持
        $attChanges = [];
        if ($in  !== $currentIn)  { $attChanges['new_clock_in']  = $in; }
        if ($out !== $currentOut) { $attChanges['new_clock_out'] = $out; }

        // 5) 休憩の差分抽出（末尾の空行は除外）
        $breakChanges = [];
        foreach ((array)Arr::get($validated, 'breaks', []) as $i => $b) {
            $id    = Arr::get($b, 'break_id');
            $start = $toNull(Arr::get($b, 'new_break_start')); // "HH:MM" or null
            $end   = $toNull(Arr::get($b, 'new_break_end'));   // "HH:MM" or null

            // 空行はスキップ（両方null/空）
            if (!$start && !$end) { continue; }

            // 既存休憩の現行値を H:i で取得
            $current = $id ? $attendance->breaks->firstWhere('id', (int)$id) : null;
            $currStart = $current && $current->break_start ? Carbon::parse($current->break_start)->format('H:i') : null;
            $currEnd   = $current && $current->break_end   ? Carbon::parse($current->break_end)->format('H:i')   : null;

            $row = ['break_id' => $id ?: null]; // null なら新規
            $hasDiff = false;

            if ($start !== $currStart) { $row['new_break_start'] = $start; $hasDiff = true; }
            if ($end   !== $currEnd)   { $row['new_break_end']   = $end;   $hasDiff = true; }

            // 新規行（idなし）は start/end どちらかが入っていれば採用
            if ($hasDiff || $row['break_id'] === null) {
                $breakChanges[] = $row + ['sort_order' => $i]; // ソート順持つなら一緒に
            }
        }

        $remark = Arr::get($validated, 'remark');

        if (empty($attChanges) && empty($breakChanges)) {
            return back()->withErrors(['remark' => '変更点がありません。'])->withInput();
        }

        DB::transaction(function () use ($attendance, $dateStr, $attChanges, $breakChanges, $remark) {
            // 6) 申請ヘッダ作成
            $app = Application::create([
                'user_id' => auth()->id(),
                'status'  => 'pending',
                'remark'  => $remark,
            ]);

            // helper: "H:i" -> "H:i:s" へ
            $toHis = fn($t) => $t ? Carbon::parse("$dateStr $t")->format('H:i:s') : null;

            // 7) 出退勤（差分がある時だけ）
            if (!empty($attChanges) || !empty($breakChanges)) {
                NewAttendance::create([
                    'application_id' => $app->id,
                    'attendance_id'  => $attendance->id,
                    'new_clock_in'   => array_key_exists('new_clock_in',  $attChanges) ? $toHis($attChanges['new_clock_in'])  : null,
                    'new_clock_out'  => array_key_exists('new_clock_out', $attChanges) ? $toHis($attChanges['new_clock_out']) : null,
                ]);
            }

            // 8) 休憩（既存更新 & 新規追加）
            foreach ($breakChanges as $row) {
                NewBreak::create([
                    'application_id'  => $app->id,
                    'break_id'        => $row['break_id'], // null なら新規
                    'new_break_start' => isset($row['new_break_start']) ? $toHis($row['new_break_start']) : null,
                    'new_break_end'   => isset($row['new_break_end'])   ? $toHis($row['new_break_end'])   : null,
                    'sort_order'      => $row['sort_order'] ?? 0,
                ]);
            }
        });

        return redirect()
            ->route('attendance.show', $attendance->id)
            ->with('status', 'applied');
    }

    public function clockIn(Request $request) {
        $user = $request->user();
        $today = now()->toDateString();

        $attendance = Attendance::firstOrCreate(
            ['user_id' => $user->id, 'date' => $today],
            ['clock_in' => now()->format('H:i:s')]
        );

        return response()->json([
            'ok' => true,
            'status' => 'working',
            'attendance_id' => $attendance->id,
            'clock_in' => $attendance->clock_in,
        ]);
    }

    public function clockOut(Request $request)
    {
        $user = $request->user();
        $today = now()->toDateString();

        $attendance = Attendance::where('user_id', $user->id)
            ->where('date', $today)
            ->first();

        if (!$attendance) {
            return response()->json(['ok' => false, 'message' => '出勤していません。'], 422);
        }

        if (!$attendance->clock_out) {
            $attendance->update(['clock_out' => now()->format('H:i:s')]);
        }

        return response()->json([
            'ok' => true,
            'status' => 'done',
            'clock_out' => $attendance->clock_out,
        ]);
    }

    public function startBreak(Request $request)
    {
        $user = $request->user();
        $today = now()->toDateString();

        $attendance = Attendance::where('user_id', $user->id)
            ->where('date', $today)
            ->first();

        if (!$attendance) {
            return response()->json(['ok' => false, 'message' => '出勤していません。'], 422);
        }

        if ($attendance->clock_out) {
            return response()->json(['ok' => false, 'message' => 'すでに退勤済みです。'], 422);
        }

        // すでに未終了の休憩があればブロック
        $openBreak = BreakTime::where('attendance_id', $attendance->id)
            ->whereNull('break_end')
            ->exists();

        if ($openBreak) {
            return response()->json(['ok' => false, 'message' => '進行中の休憩があります。'], 422);
        }

        $break = BreakTime::create([
            'attendance_id' => $attendance->id,
            'break_start'   => now()->format('H:i:s'),
        ]);

        return response()->json([
            'ok' => true,
            'status' => 'on_break',
            'break_id' => $break->id,
            'break_start' => $break->break_start,
        ]);
    }

    public function endBreak(Request $request)
    {
        $user = $request->user();
        $today = now()->toDateString();

        $attendance = Attendance::where('user_id', $user->id)
            ->where('date', $today)
            ->first();

        if (!$attendance) {
            return response()->json(['ok' => false, 'message' => '出勤していません。'], 422);
        }

        $break = BreakTime::where('attendance_id', $attendance->id)
            ->whereNull('break_end')
            ->latest('id')
            ->first();

        if (!$break) {
            return response()->json(['ok' => false, 'message' => '進行中の休憩がありません。'], 422);
        }

        $break->update(['break_end' => now()->format('H:i:s')]);

        return response()->json([
            'ok' => true,
            'status' => 'working',
            'break_end' => $break->break_end,
        ]);
    }
}
