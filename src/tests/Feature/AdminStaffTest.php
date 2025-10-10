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

class AdminStaffTest extends TestCase
{
    use DatabaseMigrations;

    protected function setUp(): void
    {
        parent::setUp();
        app()->setLocale('ja');
        config(['app.timezone' => 'Asia/Tokyo']);
        $this->seed(DatabaseSeeder::class);
    }

    // 管理者スタッフ一覧ページ--一般ユーザー情報表示確認
    public function test_admin_can_see_all_users_name_and_email()
    {
        $admin = Admin::where('email', 'admin@example.com')->firstOrFail();
        $this->actingAs($admin, 'admin');

        $seededEmails = [
            'reina.n@coachtech.com',
            'taro.y@coachtech.com',
            'issei.m@coachtech.com',
            'keikichi.y@coachtech.com',
            'tomomi.a@coachtech.com',
            'norio.n@coachtech.com',
        ];

        $users = User::whereIn('email', $seededEmails)->get();

        $response = $this->get('admin/staff/list')->assertOk();

        foreach ($users as $user) {
            $response->assertSee($user->name);
            $response->assertSee($user->email);
        }
    }

    // 管理者画面--選択ユーザー勤怠一覧画面表示確認
    public function test_admin_can_view_user_monthly_attendance_list()
    {
        $month = '2025-10';
        $user = User::factory()->create([
            'name' => 'ユーザーA',
            'email_verified_at' => now(),
        ]);

        $attendances = [
            ['day' => 1, 'in' => '09:00:00', 'out' => '18:00:00'],
            ['day' => 2, 'in' => '09:30:00', 'out' => '18:15:00'],
            ['day' => 3, 'in' => '10:00:00', 'out' => '19:00:00'],
        ];

        foreach ($attendances as $attendance) {
            Attendance::factory()->create([
                'user_id' => $user->id,
                'date' => "{$month}-".str_pad($attendance['day'], 2, '0', STR_PAD_LEFT),
                'clock_in' => $attendance['in'],
                'clock_out' => $attendance['out'],
            ]);
        }

        $admin = Admin::where('email', 'admin@example.com')->firstOrFail();
        $this->actingAs($admin, 'admin');

        $response = $this->get(route('admin.userIndex', [
            'user' => $user->id,
            'month' => $month,
        ]))->assertOk();

        $response->assertSee($user->name);
        foreach ($attendances as $attendance) {
            $response->assertSee(sprintf('%02d/%02d', 10, $attendance['day']));
            $response->assertSee(substr($attendance['in'], 0, 5));
            $response->assertSee(substr($attendance['out'], 0, 5));
        }
    }

    // 管理者画面--ユーザー別勤怠一覧前月画面表示
    public function test_admin_monthly_list_previous_month_button_shows_correct_data()
    {
        $now = Carbon::create(2025, 10, 1, 9, 0, 0, 'Asia/Tokyo');
        Carbon::setTestNow($now);

        $user = User::factory()->create(['email_verified_at' => now(), 'name' => 'ユーザーA']);

        $previousMonth = $now->copy()->subMonth()->format('Y-m');
        $attendance = Attendance::factory()->create([
            'user_id' => $user->id,
            'date' => "{$previousMonth}-15",
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

        $response = $this->get(route('admin.userIndex', [
            'user' => $user->id,
            'month' => $previousMonth,
        ]))->assertOk();

        $response->assertSee('ユーザーA');
        $response->assertSee('09:00');
        $response->assertSee('18:00');
        $response->assertSee('1:00');
        $response->assertSee('8:00');
        $response->assertSee('09/15');
    }

    // 管理者画面--ユーザー別勤怠一覧翌月画面表示
    public function test_admin_monthly_list_next_month_button_shows_correct_data()
    {
        $now = Carbon::create(2025, 10, 1, 9, 0, 0, 'Asia/Tokyo');
        Carbon::setTestNow($now);

        $user = User::factory()->create(['email_verified_at' => now(), 'name' => 'ユーザーA']);

        $nextMonth = $now->copy()->addMonth()->format('Y-m');
        $attendance = Attendance::factory()->create([
            'user_id' => $user->id,
            'date' => "{$nextMonth}-15",
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

        $response = $this->get(route('admin.userIndex', [
            'user' => $user->id,
            'month' => $nextMonth,
        ]))->assertOk();

        $response->assertSee('ユーザーA');
        $response->assertSee('09:00');
        $response->assertSee('18:00');
        $response->assertSee('1:00');
        $response->assertSee('8:00');
        $response->assertSee('11/15');
    }

    // 管理者画面--勤怠一覧画面詳細ボタン押下
    public function test_admin_list_detail_button_navigates_to_detail_page()
    {
        $date = Carbon::create(2025, 10, 15, 9, 0, 0, 'Asia/Tokyo');
        Carbon::setTestNow($date);

        $user = User::factory()->create([
            'name' => 'ユーザーA',
            'email_verified_at' => now(),
        ]);

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

        $admin = Admin::where('email', 'admin@example.com')->firstOrFail();
        $this->actingAs($admin, 'admin');

        $list = $this->get(route('admin.attendance', ['date' => $date->toDateString()]))->assertOk();

        $detailUrl = route('admin.show', ['attendance' => $attendance->id]);
        $list->assertSee($detailUrl);

        $detail = $this->get($detailUrl)->assertOk();
        $detail->assertSee('ユーザーA');
        $detail->assertSee($date->format('Y年'));
        $detail->assertSee($date->format('n月j日'));
        $detail->assertSee('09:00');
        $detail->assertSee('18:00');
        $detail->assertSee('12:00');
        $detail->assertSee('13:00');
    }
}
