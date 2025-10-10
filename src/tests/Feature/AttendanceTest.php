<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\BreakTime;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Tests\TestCase;

class AttendanceTest extends TestCase
{
    use DatabaseMigrations;

    protected function setUp(): void
    {
        parent::setUp();
        app()->setLocale('ja');
        config(['app.timezone' => 'Asia/Tokyo']);
        $this->seed(DatabaseSeeder::class);
    }

    // 勤怠打刻画面--日時情報出力確認
    public function test_attendance_page_shows_current_datetime()
    {
        $now = Carbon::create(2025, 10, 2, 9, 5, 0, 'Asia/Tokyo');
        Carbon::setTestNow($now);

        $user = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($user);

        $response = $this->get('/attendance');
        $response->assertOk();

        $expectedDate = $now->format('Y年n月j日');
        $expectedTime = $now->format('H:i');

        $response->assertSee($expectedDate);
        $response->assertSee($expectedTime);
    }

    // 勤怠打刻画面--ステータス「勤務外」
    public function test_attendance_status_is_none()
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($user);

        $response = $this->get('/attendance');
        $response->assertOk();

        $response->assertSee('勤務外');
    }

    // 勤怠打刻画面--ステータス「出勤中」
    public function test_attendance_status_is_working()
    {
        $user = User::factory()->create(['email_verified_at' => now()]);

        Attendance::factory()
            ->for($user)
            ->working()
            ->state(['date' => now()->toDateString()])
            ->create();

        $this->actingAs($user);

        $response = $this->get('/attendance');
        $response->assertOk();

        $response->assertSee('出勤中');
    }

    // 勤怠打刻画面--ステータス「退勤済」
    public function test_attendance_status_is_finished()
    {
        $user = User::factory()->create(['email_verified_at' => now()]);

        Attendance::factory()
            ->for($user)
            ->finished()
            ->state(['date' => now()->toDateString()])
            ->create();

        $this->actingAs($user);

        $response = $this->get('/attendance');
        $response->assertOk();

        $response->assertSee('退勤済');
    }

    // 勤怠打刻画面--ステータス「休憩中」
    public function test_attendance_status_is_on_break()
    {
        $user = User::factory()->create(['email_verified_at' => now()]);

        $attendance = Attendance::factory()
            ->for($user)
            ->working()
            ->state(['date' => now()->toDateString()])
            ->create();

        BreakTime::factory()
            ->for($attendance, 'attendance')
            ->onBreak()
            ->create();

        $this->actingAs($user);

        $response = $this->get('/attendance');
        $response->assertOk();

        $response->assertSee('休憩中');
    }

    // 勤怠打刻画面--出勤ボタン押下
    public function test_attendance_clock_in_button_works()
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($user);

        $response = $this->get('/attendance');
        $response->assertOk();
        $response->assertSee('勤務外');
        $response->assertSee('出勤');

        $this->post(route('attendance.clockIn'));

        $this->assertDatabaseHas('attendances', [
            'user_id' => $user->id,
            'date' => now()->toDateString(),
            'clock_in' => now()->format('H:i:s'),
        ]);

        $response = $this->get('/attendance');
        $response->assertSee('出勤中');
    }

    // 勤怠打刻画面--出勤可能回数１日１回
    public function test_attendance_attendance_button_once_a_day()
    {
        $user = User::factory()->create(['email_verified_at' => now()]);

        Attendance::factory()
            ->for($user)
            ->finished()
            ->state(['date' => now()->toDateString()])
            ->create();

        $this->actingAs($user);

        $response = $this->get('/attendance');
        $response->assertOk();

        $response->assertDontSee('id="clockInBtn"');
    }

    // 勤怠一覧画面--出勤時刻表示確認
    public function test_attendance_clock_in_time_is_visible_on_list()
    {
        $now = Carbon::create(2025, 10, 2, 9, 5, 0, 'Asia/Tokyo');
        Carbon::setTestNow($now);

        $user = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($user);

        $this->post(route('attendance.clockIn'))->assertStatus(200);

        $this->assertDatabaseHas('attendances', [
            'user_id' => $user->id,
            'date' => $now->toDateString(),
            'clock_in' => $now->format('H:i:s'),
        ]);

        $list = $this->get('attendance/list');
        $list->assertOk();
        $list->assertSee($now->format('H:i'));
        $list->assertSee($now->format('m/d'));
    }

    // 勤怠打刻画面--休憩入ボタン押下
    public function test_attendance_break_start_button()
    {
        $now = Carbon::create(2025, 10, 2, 9, 5, 0, 'Asia/Tokyo');
        Carbon::setTestNow($now);

        $user = User::factory()->create(['email_verified_at' => now()]);

        $attendance = Attendance::factory()
            ->for($user)
            ->working()
            ->state(['date' => now()->toDateString()])
            ->create();

        $this->actingAs($user);

        $response = $this->get('/attendance');
        $response->assertOk();
        $response->assertSee('出勤中');
        $response->assertSee('休憩入');

        $this->post(route('attendance.startBreak'))->assertOk();

        $this->assertDatabaseHas('break_times', [
            'attendance_id' => $attendance->id,
            'break_start' => $now->format('H:i:s'),
            'break_end' => null,
        ]);

        $response = $this->get('/attendance');
        $response->assertOk();
        $response->assertSee('休憩中');
    }

    // 勤怠打刻画面--休憩戻ボタン押下
    public function test_attendance_break_end_button()
    {
        $now = Carbon::create(2025, 10, 2, 9, 5, 0, 'Asia/Tokyo');
        Carbon::setTestNow($now);

        $user = User::factory()->create(['email_verified_at' => now()]);

        $attendance = Attendance::factory()
            ->for($user)
            ->working()
            ->state(['date' => now()->toDateString()])
            ->create();

        $this->actingAs($user);

        $response = $this->get('/attendance');
        $response->assertOk();
        $response->assertSee('出勤中');
        $response->assertSee('休憩入');

        $this->post(route('attendance.startBreak'))->assertOk();

        $this->assertDatabaseHas('break_times', [
            'attendance_id' => $attendance->id,
            'break_start' => $now->format('H:i:s'),
            'break_end' => null,
        ]);

        $response = $this->get('/attendance');
        $response->assertOk();
        $response->assertSee('休憩中');
        $response->assertSee('休憩戻');

        $endNow = $now->copy()->addMinutes(30);
        Carbon::setTestNow($endNow);

        $this->post(route('attendance.endBreak'))->assertOk();

        $this->assertDatabaseHas('break_times', [
            'attendance_id' => $attendance->id,
            'break_start' => $now->format('H:i:s'),
            'break_end' => $endNow->format('H:i:s'),
        ]);

        $response = $this->get('/attendance');
        $response->assertOk();
        $response->assertSee('出勤中');
    }

    // 勤怠打刻画面--休憩入ボタン複数回押下可能
    public function test_attendance_break_start_button_many_times()
    {
        $now = Carbon::create(2025, 10, 2, 9, 5, 0, 'Asia/Tokyo');
        Carbon::setTestNow($now);

        $user = User::factory()->create(['email_verified_at' => now()]);

        $attendance = Attendance::factory()
            ->for($user)
            ->working()
            ->state(['date' => now()->toDateString()])
            ->create();

        $this->actingAs($user);

        $response = $this->get('/attendance');
        $response->assertOk();
        $response->assertSee('休憩入');

        $this->post(route('attendance.startBreak'))->assertOk();

        $this->assertDatabaseHas('break_times', [
            'attendance_id' => $attendance->id,
            'break_start' => $now->format('H:i:s'),
            'break_end' => null,
        ]);

        $response = $this->get('/attendance');
        $response->assertOk();
        $response->assertSee('休憩戻');

        $endNow = $now->copy()->addMinutes(30);
        Carbon::setTestNow($endNow);

        $this->post(route('attendance.endBreak'))->assertOk();

        $this->assertDatabaseHas('break_times', [
            'attendance_id' => $attendance->id,
            'break_start' => $now->format('H:i:s'),
            'break_end' => $endNow->format('H:i:s'),
        ]);

        $response = $this->get('/attendance');
        $response->assertOk();
        $response->assertSee('休憩入');
    }

    // 勤怠打刻画面--休憩戻ボタン複数回押下可能
    public function test_attendance_break_end_button_many_times()
    {
        $now = Carbon::create(2025, 10, 2, 9, 5, 0, 'Asia/Tokyo');
        Carbon::setTestNow($now);

        $user = User::factory()->create(['email_verified_at' => now()]);

        $attendance = Attendance::factory()
            ->for($user)
            ->working()
            ->state(['date' => now()->toDateString()])
            ->create();

        $this->actingAs($user);

        $response = $this->get('/attendance');
        $response->assertOk();
        $response->assertSee('休憩入');

        $this->post(route('attendance.startBreak'))->assertOk();

        $this->assertDatabaseHas('break_times', [
            'attendance_id' => $attendance->id,
            'break_start' => $now->format('H:i:s'),
            'break_end' => null,
        ]);

        $response = $this->get('/attendance');
        $response->assertOk();
        $response->assertSee('休憩戻');

        $endNow = $now->copy()->addMinutes(30);
        Carbon::setTestNow($endNow);

        $this->post(route('attendance.endBreak'))->assertOk();

        $this->assertDatabaseHas('break_times', [
            'attendance_id' => $attendance->id,
            'break_start' => $now->format('H:i:s'),
            'break_end' => $endNow->format('H:i:s'),
        ]);

        $response = $this->get('/attendance');
        $response->assertOk();
        $response->assertSee('休憩入');

        $breakSecond = $endNow->copy()->addMinutes(30);
        Carbon::setTestNow($breakSecond);

        $this->post(route('attendance.startBreak'))->assertOk();

        $this->assertDatabaseHas('break_times', [
            'attendance_id' => $attendance->id,
            'break_start' => $breakSecond->format('H:i:s'),
            'break_end' => null,
        ]);

        $response = $this->get('/attendance');
        $response->assertOk();
        $response->assertSee('休憩戻');
    }

    // 勤怠一覧画面--休憩時刻表示確認
    public function test_attendance_break_time_is_visible_on_list()
    {
        $now = Carbon::create(2025, 10, 2, 9, 5, 0, 'Asia/Tokyo');
        Carbon::setTestNow($now);

        $user = User::factory()->create(['email_verified_at' => now()]);

        $attendance = Attendance::factory()
            ->for($user)
            ->working()
            ->state(['date' => now()->toDateString()])
            ->create();

        $this->actingAs($user);

        $response = $this->get('/attendance');
        $response->assertOk();
        $response->assertSee('休憩入');

        $this->post(route('attendance.startBreak'))->assertOk();

        $this->assertDatabaseHas('break_times', [
            'attendance_id' => $attendance->id,
            'break_start' => $now->format('H:i:s'),
            'break_end' => null,
        ]);

        $response = $this->get('/attendance');
        $response->assertOk();
        $response->assertSee('休憩戻');

        $endNow = $now->copy()->addMinutes(30);
        Carbon::setTestNow($endNow);

        $this->post(route('attendance.endBreak'))->assertOk();

        $this->assertDatabaseHas('break_times', [
            'attendance_id' => $attendance->id,
            'break_start' => $now->format('H:i:s'),
            'break_end' => $endNow->format('H:i:s'),
        ]);

        $list = $this->get(route('attendance.list'));
        $list->assertOk();

        $breakStart = $now;
        $breakEnd = $endNow;

        $breakDurationSeconds = $breakEnd->diffInSeconds($breakStart);

        $hours = floor($breakDurationSeconds / 3600);
        $minutes = floor(($breakDurationSeconds % 3600) / 60);

        $breakDuration = sprintf('%d:%02d', $hours, $minutes);
        $list->assertSee($breakDuration);
    }

    // 勤怠打刻画面--退勤ボタン押下
    public function test_attendance_clock_out_button()
    {
        $now = Carbon::create(2025, 10, 2, 9, 5, 0, 'Asia/Tokyo');
        Carbon::setTestNow($now);

        $user = User::factory()->create(['email_verified_at' => now()]);

        $attendance = Attendance::factory()
            ->for($user)
            ->working()
            ->state([
                'date' => $now->toDateString(),
                'clock_in' => $now->format('H:i:s'),
                'clock_out' => null,
            ])
            ->create();

        $this->actingAs($user);

        $response = $this->get('/attendance');
        $response->assertOk();
        $response->assertSee('出勤中');
        $response->assertSee('退勤');

        $endNow = $now->copy()->addMinutes(30);
        Carbon::setTestNow($endNow);

        $this->post(route('attendance.clockOut'))->assertOk();

        $this->assertDatabaseHas('attendances', [
            'user_id' => $user->id,
            'date' => $now->toDateString(),
            'clock_in' => $now->format('H:i:s'),
            'clock_out' => $endNow->format('H:i:s'),
        ]);

        $response = $this->get('/attendance');
        $response->assertOk();
        $response->assertSee('退勤済');
    }

    // 勤怠一覧画面--退勤時刻表示確認
    public function test_attendance_clock_out_time_is_visible_on_list()
    {
        $now = Carbon::create(2025, 10, 2, 9, 5, 0, 'Asia/Tokyo');
        Carbon::setTestNow($now);

        $user = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($user);

        $response = $this->get('/attendance');
        $response->assertOk();
        $response->assertSee('勤務外');

        $this->post(route('attendance.clockIn'))->assertStatus(200);

        $this->assertDatabaseHas('attendances', [
            'user_id' => $user->id,
            'date' => $now->toDateString(),
            'clock_in' => $now->format('H:i:s'),
            'clock_out' => null,
        ]);

        $response = $this->get('/attendance');
        $response->assertOk();
        $response->assertSee('退勤');

        $endNow = $now->copy()->addMinutes(30);
        Carbon::setTestNow($endNow);

        $this->post(route('attendance.clockOut'))->assertStatus(200);

        $this->assertDatabaseHas('attendances', [
            'user_id' => $user->id,
            'clock_in' => $now->format('H:i:s'),
            'clock_out' => $endNow->format('H:i:s'),
        ]);

        $list = $this->get(route('attendance.list'));
        $list->assertOk();
        $list->assertSee($now->format('H:i'));
        $list->assertSee($endNow->format('H:i'));
        $list->assertSee($now->format('m/d'));
    }
}
