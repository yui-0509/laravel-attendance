<?php

namespace App\Http\Controllers;

use App\Models\Application;
use App\Models\Attendance;
use App\Models\BreakTime;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class AdminApplicationController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $tab = $request->query('tab', 'pending');
        if (! in_array($tab, ['pending', 'approved'], true)) {
            $tab = 'pending';
        }

        $applications = Application::with([
            'user',
            'newAttendance.attendance',
        ])
            ->where('status', $tab)
            ->orderByDesc('created_at')
            ->get();

        return view('admin.admin-applications', compact('applications'));
    }

    public function show(Application $attendance_correct_request)
    {
        $app = $attendance_correct_request->load([
            'user',
            'newAttendance.attendance.breaks' => fn ($q) => $q->orderBy('break_start'),
            'newBreaks' => fn ($q) => $q->orderBy('new_break_start'),
        ]);

        return view('admin.approval', [
            'app' => $app,
            'attendance' => $app->newAttendance?->attendance,
            'user' => $app->user,
        ]);
    }

    public function approve(Application $attendance_correct_request)
    {
        if ($attendance_correct_request->status !== 'pending') {
            return back()->with('info', 'この申請は処理済みです。');
        }

        DB::transaction(function () use ($attendance_correct_request) {
            $attendance_correct_request->load([
                'newAttendance.attendance',
                'newBreaks',
            ]);

            $attendanceId = null;
            if ($na = $attendance_correct_request->newAttendance) {
                $attendanceId = $na->attendance_id;

                $att = Attendance::whereKey($na->attendance_id)->lockForUpdate()->first();
                if ($att) {
                    if (! is_null($na->new_clock_in)) {
                        $att->clock_in = $na->new_clock_in;
                    }
                    if (! is_null($na->new_clock_out)) {
                        $att->clock_out = $na->new_clock_out;
                    }
                    $att->save();
                }
            }

            if (! $attendanceId) {
                $existingRef = $attendance_correct_request->newBreaks->firstWhere('break_id', '!=', null);
                if ($existingRef) {
                    $refBr = BreakTime::whereKey($existingRef->break_id)->first();
                    if ($refBr) {
                        $attendanceId = $refBr->attendance_id;
                    }
                }
            }

            foreach ($attendance_correct_request->newBreaks as $nb) {
                $start = $nb->new_break_start;
                $end = $nb->new_break_end;

                if (is_null($start) && is_null($end)) {
                    continue;
                }

                if (! empty($nb->break_id)) {
                    $br = BreakTime::whereKey($nb->break_id)->lockForUpdate()->first();
                    if ($br) {
                        if (! is_null($start)) {
                            $br->break_start = $start;
                        }
                        if (! is_null($end)) {
                            $br->break_end = $end;
                        }
                        $br->save();
                    }
                } else {
                    if ($attendanceId) {
                        BreakTime::create([
                            'attendance_id' => $attendanceId,
                            'break_start' => $start,
                            'break_end' => $end,
                        ]);
                    }
                }
            }

            $attendance_correct_request->fill([
                'status' => 'approved',
                'admin_id' => Auth::guard('admin')->id(),
                'approved_at' => now(),
            ])->save();
        });

        return redirect()
            ->route('admin.approve.show', ['attendance_correct_request' => $attendance_correct_request->id]);
    }
}
