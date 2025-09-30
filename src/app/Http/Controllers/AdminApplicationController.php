<?php

namespace App\Http\Controllers;

use App\Models\Application;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Models\Attendance;
use App\Models\BreakTime;

class AdminApplicationController extends Controller
{
    public function index(Request $request) {
        $user = $request->user();

        $tab = $request->query('tab', 'pending');
        if (!in_array($tab, ['pending', 'approved'], true)) {
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
        // 申請1件＋関連を読み込む（必要な関連はあなたの設計に合わせて）
        $app = $attendance_correct_request->load([
            'user', // 申請者
            'newAttendance.attendance.breaks' => fn ($q) => $q->orderBy('break_start'),
            'newBreaks' => fn ($q) => $q->orderBy('new_break_start'),
            // もし「旧値」も別リレーションで持っていればここに追加
        ]);

        // 画面に必要な情報を渡す
        return view('admin.approval', [
            'app'        => $app,
            'attendance' => $app->newAttendance?->attendance, // 対象勤怠の現行レコード
            'user'       => $app->user,
        ]);
    }

    public function approve(Application $attendance_correct_request)
    {
        // すでに処理済みなら戻す
        if ($attendance_correct_request->status !== 'pending') {
            return back()->with('info', 'この申請は処理済みです。');
        }

        DB::transaction(function () use ($attendance_correct_request) {
            // 関連読み込み
            $attendance_correct_request->load([
                'newAttendance.attendance', // 対象勤怠
                'newBreaks',                // 申請休憩
            ]);

            // 1) 出退勤の反映
            $attendanceId = null;
            if ($na = $attendance_correct_request->newAttendance) {
                $attendanceId = $na->attendance_id;

                $att = Attendance::whereKey($na->attendance_id)->lockForUpdate()->first();
                if ($att) {
                    if (!is_null($na->new_clock_in)) {
                        $att->clock_in = $na->new_clock_in;   // 必要なら H:i:s に揃える
                    }
                    if (!is_null($na->new_clock_out)) {
                        $att->clock_out = $na->new_clock_out;
                    }
                    $att->save(); // ← これ必須！
                }
            }

            // newAttendance が無い（出退勤に差分なし）場合でも、
            // 新規休憩を追加するには attendance_id が必要。
            if (!$attendanceId) {
                // 既存休憩の修正が含まれていれば、その break_id 経由で attendance_id を得る
                $existingRef = $attendance_correct_request->newBreaks->firstWhere('break_id', '!=', null);
                if ($existingRef) {
                    $refBr = BreakTime::whereKey($existingRef->break_id)->first();
                    if ($refBr) {
                        $attendanceId = $refBr->attendance_id;
                    }
                }
            }

            // 2) 休憩の反映（既存更新 + 新規追加）
            foreach ($attendance_correct_request->newBreaks as $nb) {
                $start = $nb->new_break_start;
                $end   = $nb->new_break_end;

                // 予備の空行（両方null）はスキップ
                if (is_null($start) && is_null($end)) {
                    continue;
                }

                if (!empty($nb->break_id)) {
                    // 既存休憩の更新
                    $br = BreakTime::whereKey($nb->break_id)->lockForUpdate()->first();
                    if ($br) {
                        if (!is_null($start)) { $br->break_start = $start; }
                        if (!is_null($end))   { $br->break_end   = $end; }
                        $br->save();
                    }
                } else {
                    // 新規休憩の追加（attendance_id が取れたときのみ）
                    if ($attendanceId) {
                        BreakTime::create([
                            'attendance_id' => $attendanceId,
                            'break_start'   => $start,
                            'break_end'     => $end,
                        ]);
                    }
                }
            }

            // 3) applications の監査情報
            $attendance_correct_request->fill([
                'status'      => 'approved',
                'admin_id'    => Auth::guard('admin')->id(),
                'approved_at' => now(),
            ])->save();
        });

        return redirect()
            ->route('admin.approve.show', ['attendance_correct_request' => $attendance_correct_request->id,]);
    }
}
