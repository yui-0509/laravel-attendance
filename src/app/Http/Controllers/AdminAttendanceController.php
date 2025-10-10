<?php

namespace App\Http\Controllers;

use App\Http\Requests\AdminAttendanceUpdateRequest;
use App\Models\Application;
use App\Models\Attendance;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;

class AdminAttendanceController extends Controller
{
    public function index(Request $request)
    {
        $tz = 'Asia/Tokyo';
        $dateStr = $request->query('date', Carbon::now($tz)->toDateString());
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateStr)) {
            $dateStr = Carbon::now($tz)->toDateString();
        }
        $date = Carbon::parse($dateStr, $tz)->startOfDay();

        $prevDay = $date->copy()->subDay()->toDateString();
        $nextDay = $date->copy()->addDay()->toDateString();

        $attendances = Attendance::with(['user', 'breaks'])
            ->whereDate('date', $date->toDateString())
            ->get()
            ->sortBy(fn ($a) => optional($a->user)->name ?? '');

        return view('admin.attendance.admin-time-sheet', [
            'date' => $date,
            'prevDay' => $prevDay,
            'nextDay' => $nextDay,
            'attendances' => $attendances,
        ]);
    }

    public function show(Attendance $attendance)
    {
        $attendance->load(['user', 'breaks' => fn ($q) => $q->orderBy('break_start')]);

        $pendingApp = Application::with([
            'newAttendance',
            'newBreaks' => fn ($q) => $q->orderBy('new_break_start'),
        ])
            ->where('status', 'pending')
            ->whereHas('newAttendance', fn ($q) => $q->where('attendance_id', $attendance->id))
            ->latest()
            ->first();

        return view('admin.attendance.admin-detail', [
            'attendance' => $attendance,
            'pendingApp' => $pendingApp,
            'day' => $attendance->date,
        ]);
    }

    public function userIndex(Request $request, User $user)
    {
        $monthStr = $request->query('month') ?? now('Asia/Tokyo')->format('Y-m');
        $month = Carbon::createFromFormat('Y-m', $monthStr)->startOfMonth();
        $start = $month->copy()->startOfMonth();
        $end = $month->copy()->endOfMonth();

        $days = collect();
        for ($d = $start->copy(); $d->lte($end); $d->addDay()) {
            $days->push([
                'date' => $d->copy(),
                'clock_in' => null,
                'clock_out' => null,
                'break_min' => null,
                'total_min' => null,
                'record' => null,
            ]);
        }

        $records = Attendance::query()
            ->with('breaks')
            ->where('user_id', $user->id)
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->orderBy('date')
            ->get()
            ->keyBy(fn ($r) => $r->date->format('Y-m-d'));

        $days = $days->map(function ($row) use ($records) {
            $key = $row['date']->format('Y-m-d');

            if ($records->has($key)) {
                $r = $records[$key];

                $dayDate = $r->date->format('Y-m-d');

                $clockIn = $r->clock_in ? \Carbon\Carbon::parse("$dayDate {$r->clock_in}") : null;
                $clockOut = $r->clock_out ? \Carbon\Carbon::parse("$dayDate {$r->clock_out}") : null;

                $breakMin = $r->breaks->sum(function ($b) use ($dayDate) {
                    if (empty($b->break_start) || empty($b->break_end)) {
                        return 0;
                    }
                    $bs = \Carbon\Carbon::parse("$dayDate {$b->break_start}");
                    $be = \Carbon\Carbon::parse("$dayDate {$b->break_end}");

                    return $be->greaterThan($bs) ? $be->diffInMinutes($bs) : 0;
                });

                $totalMin = null;
                if ($clockIn && $clockOut) {
                    $totalMin = max(0, $clockOut->diffInMinutes($clockIn) - $breakMin);
                }

                $row['clock_in'] = $clockIn;
                $row['clock_out'] = $clockOut;
                $row['break_min'] = ($breakMin > 0) ? $breakMin : null;
                $row['total_min'] = $totalMin;
                $row['record'] = $r;
            }

            return $row;
        });

        $prevMonth = $month->copy()->subMonth()->format('Y-m');
        $nextMonth = $month->copy()->addMonth()->format('Y-m');

        return view('admin.staff.staff-time-sheet', [
            'user' => $user,
            'month' => $month,
            'days' => $days,
            'prevMonth' => $prevMonth,
            'nextMonth' => $nextMonth,
        ]);
    }

    public function update(AdminAttendanceUpdateRequest $request, Attendance $attendance)
    {
        $validated = $request->validated();

        $date = $attendance->date?->format('Y-m-d');
        $toHis = fn ($t) => $t ? \Carbon\Carbon::parse("$date $t")->format('H:i:s') : null;

        $attendance->clock_in = array_key_exists('clock_in',
            $validated) ? $toHis($validated['clock_in']) : $attendance->clock_in;
        $attendance->clock_out = array_key_exists('clock_out',
            $validated) ? $toHis($validated['clock_out']) : $attendance->clock_out;
        if (array_key_exists('remark', $validated)) {
            $attendance->remark = $validated['remark'];
        }
        $attendance->save();

        foreach (($validated['breaks'] ?? []) as $row) {
            $start = $row['break_start'] ?? null;
            $end = $row['break_end'] ?? null;

            if (! $start && ! $end) {
                continue;
            }

            if (! empty($row['break_id'])) {
                $br = $attendance->breaks()->whereKey($row['break_id'])->first();
                if ($br) {
                    if (! is_null($start)) {
                        $br->break_start = $toHis($start);
                    }
                    if (! is_null($end)) {
                        $br->break_end = $toHis($end);
                    }
                    $br->save();
                }
            } else {
                $attendance->breaks()->create([
                    'break_start' => $toHis($start),
                    'break_end' => $toHis($end),
                ]);
            }
        }

        return back();
    }

    public function exportCsv(Request $request, User $user)
    {
        $month = $request->query('month', now()->format('Y-m'));
        try {
            $start = Carbon::createFromFormat('Y-m', $month)->startOfMonth();
        } catch (\Throwable $e) {
            abort(400, 'monthはYYYY-MM形式で指定してください');
        }
        $end = (clone $start)->endOfMonth();

        $attendances = Attendance::with(['breaks' => fn ($q) => $q->orderBy('break_start')])
            ->where('user_id', $user->id)
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->get()
            ->keyBy(fn ($a) => $a->date->toDateString());

        $toMinutes = function ($time) {
            if ($time === null || $time === '') {
                return null;
            }
            try {
                $c = Carbon::parse($time);
            } catch (\Throwable $e) {
                return null;
            }

            return $c->hour * 60 + $c->minute;
        };

        $fmtHM = function ($min) {
            if ($min === null) {
                return '';
            }

            return sprintf('%d:%02d', intdiv($min, 60), $min % 60);
        };

        $rows = [];
        $rows[] = ['日付', '出勤', '退勤', '休憩', '合計'];

        $cursor = $start->copy();
        while ($cursor->lte($end)) {
            $dateKey = $cursor->toDateString();
            $w = ['日', '月', '火', '水', '木', '金', '土'][$cursor->dayOfWeek];

            $att = $attendances->get($dateKey);
            $clockIn = $att?->clock_in ? Carbon::parse($att->clock_in) : null;
            $clockOut = $att?->clock_out ? Carbon::parse($att->clock_out) : null;

            $clockInStr = $clockIn ? $clockIn->format('H:i') : '';
            $clockOutStr = $clockOut ? $clockOut->format('H:i') : '';

            $breakMin = null;
            if ($att && $att->breaks?->count()) {
                $sum = 0;
                foreach ($att->breaks as $br) {
                    if ($br->break_start && $br->break_end) {
                        $sum += max(0, $toMinutes($br->break_end) - $toMinutes($br->break_start));
                    }
                }
                $breakMin = $sum;
            } else {
                $breakMin = 0;
            }

            $totalMin = null;
            if ($clockIn && $clockOut) {
                $span = max(0, $toMinutes($clockOutStr) - $toMinutes($clockInStr));
                $totalMin = max(0, $span - ($breakMin ?? 0));
            }

            $rows[] = [
                $cursor->format('Y-m-d')."($w)",
                $clockInStr,
                $clockOutStr,
                $fmtHM($breakMin),
                $fmtHM($totalMin),
            ];

            $cursor->addDay();
        }

        $filename = sprintf('attendance_%s_%s.csv', $user->id, $start->format('Y-m'));

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));
            foreach ($rows as $r) {
                fputcsv($out, $r);
            }
            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'no-store, no-cache',
            'Pragma' => 'no-cache',
        ]);
    }
}
