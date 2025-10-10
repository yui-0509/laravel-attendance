<?php

namespace Database\Seeders;

use App\Models\Attendance;
use App\Models\BreakTime;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AttendanceDummySeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // 🔒 外部キー制約を一時的にオフ
        Schema::disableForeignKeyConstraints();
        // 先に子テーブル → 親テーブルの順で
        DB::table('break_times')->truncate();
        DB::table('attendances')->truncate();
        // 🔓 元に戻す
        Schema::enableForeignKeyConstraints();

        $users = User::whereIn('email', [
            'reina.n@coachtech.com',
            'taro.y@coachtech.com',
            'issei.m@coachtech.com',
            'keikichi.y@coachtech.com',
            'tomomi.a@coachtech.com',
            'norio.n@coachtech.com',
        ])->get();

        $month = now()->startOfMonth();
        $start = $month->copy()->subMonths(2)->startOfMonth();
        $end = $month->copy()->endOfMonth();

        $period = CarbonPeriod::create($start, $end);
        $weeks = [];
        foreach ($period as $d) {
            $key = $d->isoWeekYear.'-W'.str_pad($d->isoWeek, 2, '0', STR_PAD_LEFT);
            $weeks[$key][] = $d->copy();
        }

        $inTime = '09:00:00';
        $outTime = '18:00:00';
        $restFrom = '12:00:00';
        $restTo = '13:00:00';

        DB::transaction(function () use ($users, $weeks, $inTime, $outTime, $restFrom, $restTo) {
            foreach ($users as $user) {
                foreach ($weeks as $key => $dates) {
                    $candidates = collect($dates)
                        ->reject(fn (Carbon $d) => $d->isSunday());

                    if ($candidates->isEmpty()) {
                        continue;
                    }

                    $workdays = $candidates->shuffle()->take(5)->sortBy(fn ($d) => $d->toDateString())->values();

                    foreach ($workdays as $date) {
                        $attendance = Attendance::firstOrCreate(
                            [
                                'user_id' => $user->id,
                                'date' => $date->toDateString(),
                            ],
                            [
                                'clock_in' => $inTime,
                                'clock_out' => $outTime,
                            ]
                        );

                        BreakTime::firstOrCreate(
                            [
                                'attendance_id' => $attendance->id, 'break_start' => $restFrom,
                                'break_end' => $restTo,
                            ]
                        );
                    }
                }
            }
        });
    }
}
