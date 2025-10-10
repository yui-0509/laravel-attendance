<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Attendance;
use App\Models\BreakTime;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Tests\TestCase;

class AdminAttendanceListTest extends TestCase
{
    use DatabaseMigrations;

    protected function setUp(): void
    {
        parent::setUp();
        app()->setLocale('ja');
        config(['app.timezone' => 'Asia/Tokyo']);
        $this->seed(DatabaseSeeder::class);
    }

    // 管理者勤怠一覧画面--ユーザー勤怠情報表示確認
    public function test_admin_daily_list_shows_all_users_for_the_day()
    {
        $date = Carbon::create(2025, 10, 10, 9, 0, 0, 'Asia/Tokyo');
        Carbon::setTestNow($date);

        $users = collect([
            User::factory()->create(['name' => 'ユーザーA', 'email_verified_at' => now()]),
            User::factory()->create(['name' => 'ユーザーB', 'email_verified_at' => now()]),
            User::factory()->create(['name' => 'ユーザーC', 'email_verified_at' => now()]),
        ]);

        foreach ($users as $user) {
            $attendance = Attendance::factory()->create([
                'user_id' => $user->id,
                'date' => $date->toDateString(),
                'clock_in' => '09:00:00',
                'clock_out' => '18:00:00',
            ]);
            BreakTime::factory()->create([
                'attendance_id' => $attendance->id,
                'break_start' => '12:00:00',
                'break_end' => '13:00:00',
            ]);
        }

        $admin = Admin::where('email', 'admin@example.com')->firstOrFail();
        $this->actingAs($admin, 'admin');

        $response = $this->get(route('admin.attendance', ['date' => $date->toDateString()]))->assertOk();

        $response->assertSee($date->format('Y/m/d'));
        $response->assertSee('ユーザーA');
        $response->assertSee('ユーザーB');
        $response->assertSee('ユーザーC');
        $response->assertSee('09:00');
        $response->assertSee('18:00');
        $response->assertSee('1:00');
        $response->assertSee('8:00');
    }

    // 管理者勤怠一覧画面--日付表示確認
    public function test_admin_daily_list_shows_date()
    {
        $date = Carbon::create(2025, 10, 10, 9, 0, 0, 'Asia/Tokyo');
        Carbon::setTestNow($date);

        $admin = Admin::where('email', 'admin@example.com')->firstOrFail();
        $this->actingAs($admin, 'admin');

        $response = $this->get(route('admin.attendance', ['date' => $date->toDateString()]))->assertOk();

        $response->assertSee($date->format('Y/m/d'));
    }

    // 管理者勤怠一覧画面--前日ボタン押下
    public function test_previous_day_button_shows_previous_day_records()
    {
        $now = Carbon::create(2025, 10, 10, 9, 0, 0, 'Asia/Tokyo');
        Carbon::setTestNow($now);

        $prevDay = $now->copy()->subDay();

        $user = User::factory()->create(['name' => 'テストユーザー', 'email_verified_at' => now()]);

        $attendance = Attendance::factory()->create([
            'user_id' => $user->id,
            'date' => $prevDay->toDateString(),
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
        ]);

        BreakTime::factory()->create([
            'attendance_id' => $attendance->id,
            'break_start' => '12:00:00',
            'break_end' => '13:00:00',
        ]);

        $admin = Admin::where('email', 'admin@example.com')->firstOrFail();
        $this->actingAs($admin, 'admin');

        $response = $this->get(route('admin.attendance', ['date' => $prevDay->toDateString()]))->assertOk();

        $response->assertSee($prevDay->format('Y/m/d'));
        $response->assertSee($user->name);
        $response->assertSee('09:00');
        $response->assertSee('18:00');
        $response->assertSee('1:00');
        $response->assertSee('8:00');
    }

    // 管理者勤怠一覧画面--翌日ボタン押下
    public function test_next_day_button_shows_next_day_records()
    {
        $now = Carbon::create(2025, 10, 6, 9, 0, 0, 'Asia/Tokyo');
        Carbon::setTestNow($now);

        $nextDay = $now->copy()->addDay();

        $users = collect([
            User::factory()->create(['name' => 'ユーザーA', 'email_verified_at' => now()]),
            User::factory()->create(['name' => 'ユーザーB', 'email_verified_at' => now()]),
        ]);

        foreach ($users as $user) {
            $attendance = Attendance::factory()->create([
                'user_id' => $user->id,
                'date' => $nextDay->toDateString(),
                'clock_in' => '09:00:00',
                'clock_out' => '18:00:00',
            ]);
            BreakTime::factory()->create([
                'attendance_id' => $attendance->id,
                'break_start' => '12:00:00',
                'break_end' => '13:00:00',
            ]);
        }

        $admin = Admin::where('email', 'admin@example.com')->firstOrFail();
        $this->actingAs($admin, 'admin');

        $response = $this->get(route('admin.attendance', ['date' => $nextDay->toDateString()]))->assertOk();

        $response->assertSee($nextDay->format('Y/m/d'));
        $response->assertSee('ユーザーA');
        $response->assertSee('ユーザーB');
        $response->assertSee('09:00');
        $response->assertSee('18:00');
        $response->assertSee('1:00');
        $response->assertSee('8:00');
    }
}
