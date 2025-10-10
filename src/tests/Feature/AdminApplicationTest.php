<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Application;
use App\Models\Attendance;
use App\Models\BreakTime;
use App\Models\NewAttendance;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Tests\TestCase;

class AdminApplicationTest extends TestCase
{
    use DatabaseMigrations;

    protected function setUp(): void
    {
        parent::setUp();
        app()->setLocale('ja');
        config(['app.timezone' => 'Asia/Tokyo']);
        $this->seed(DatabaseSeeder::class);
    }

    // 管理者修正申請一覧画面--承認待ち一覧表示確認
    public function test_admin_can_view_pending_applications_list()
    {
        $now = Carbon::create(2025, 10, 10, 9, 0, 0, 'Asia/Tokyo');
        Carbon::setTestNow($now);

        $admin = Admin::where('email', 'admin@example.com')->firstOrFail();
        $this->actingAs($admin, 'admin');

        $users = collect([
            User::factory()->create(['name' => 'ユーザーA', 'email_verified_at' => now()]),
            User::factory()->create(['name' => 'ユーザーB', 'email_verified_at' => now()]),
            User::factory()->create(['name' => 'ユーザーC', 'email_verified_at' => now()]),
        ]);

        $expectedRows = [];

        foreach ($users as $user) {
            $attendance = Attendance::factory()->create([
                'user_id' => $user->id,
                'date' => $now->toDateString(),
                'clock_in' => '09:00:00',
                'clock_out' => '18:00:00',
            ]);

            $application = Application::factory()->create([
                'user_id' => $user->id,
                'remark' => '遅延のため',
                'status' => 'pending',
            ]);

            NewAttendance::factory()->create([
                'application_id' => $application->id,
                'attendance_id' => $attendance->id,
                'new_clock_in' => '10:00:00',
                'new_clock_out' => '18:00:00',
            ]);

            $expectedRows[] = [
                'name' => $user->name,
                'att_date' => Carbon::parse($attendance->date)->format('Y/m/d'),
                'remark' => $application->remark,
                'applied_at' => Carbon::parse($application->created_at)->format('Y/m/d'),
            ];
        }

        $response = $this->get(route('request.index', ['tab' => 'pending']))->assertOk();

        $response->assertSee('承認待ち');
        foreach ($expectedRows as $row) {
            $response->assertSee($row['name']);
            $response->assertSee($row['att_date']);
            $response->assertSee($row['remark']);
            $response->assertSee($row['applied_at']);
        }
    }

    // 管理者修正申請一覧画面--承認済み一覧表示確認
    public function test_admin_can_view_approved_applications_list()
    {
        $now = Carbon::create(2025, 10, 10, 9, 0, 0, 'Asia/Tokyo');
        Carbon::setTestNow($now);

        $admin = Admin::where('email', 'admin@example.com')->firstOrFail();
        $this->actingAs($admin, 'admin');

        $users = collect([
            User::factory()->create(['name' => 'ユーザーA', 'email_verified_at' => now()]),
            User::factory()->create(['name' => 'ユーザーB', 'email_verified_at' => now()]),
            User::factory()->create(['name' => 'ユーザーC', 'email_verified_at' => now()]),
        ]);

        $expectedRows = [];

        foreach ($users as $user) {
            $attendance = Attendance::factory()->create([
                'user_id' => $user->id,
                'date' => $now->toDateString(),
                'clock_in' => '09:00:00',
                'clock_out' => '18:00:00',
            ]);

            $application = Application::factory()->create([
                'user_id' => $user->id,
                'remark' => '遅延のため',
                'status' => 'approved',
                'approved_at' => now(),
            ]);

            NewAttendance::factory()->create([
                'application_id' => $application->id,
                'attendance_id' => $attendance->id,
                'new_clock_in' => '10:00:00',
                'new_clock_out' => '18:00:00',
            ]);

            $expectedRows[] = [
                'name' => $user->name,
                'att_date' => Carbon::parse($attendance->date)->format('Y/m/d'),
                'remark' => $application->remark,
                'applied_at' => Carbon::parse($application->created_at)->format('Y/m/d'),
            ];
        }

        $response = $this->get(route('request.index', ['tab' => 'approved']))->assertOk();

        $response->assertSee('承認済み');
        foreach ($expectedRows as $row) {
            $response->assertSee($row['name']);
            $response->assertSee($row['att_date']);
            $response->assertSee($row['remark']);
            $response->assertSee($row['applied_at']);
        }
    }

    // 管理者修正申請画面--申請内容確認
    public function test_admin_application_detail_shows_correct_contents()
    {
        $now = Carbon::create(2025, 10, 10, 9, 0, 0, 'Asia/Tokyo');
        Carbon::setTestNow($now);

        $user = User::factory()->create([
            'name' => 'ユーザーA',
            'email_verified_at' => now(),
        ]);
        $attendance = Attendance::factory()->create([
            'user_id' => $user->id,
            'date' => $now->toDateString(),
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
        ]);
        $breakTime = BreakTime::factory()->create([
            'attendance_id' => $attendance->id,
            'break_start' => '12:00:00',
            'break_end' => '13:00:00',
        ]);

        $application = Application::factory()->create([
            'user_id' => $user->id,
            'remark' => '遅延のため',
            'status' => 'pending',
        ]);
        NewAttendance::factory()->create([
            'application_id' => $application->id,
            'attendance_id' => $attendance->id,
            'new_clock_in' => '10:00:00',
            'new_clock_out' => '19:00:00',
        ]);

        $admin = Admin::where('email', 'admin@example.com')->firstOrFail();
        $this->actingAs($admin, 'admin');

        $detail = $this->get(route('admin.approve.show', [
            'attendance_correct_request' => $application->id,
        ]))->assertOk();

        $detail->assertSee('ユーザーA');
        $detail->assertSee(Carbon::parse($attendance->date)->format('Y年'));
        $detail->assertSee(Carbon::parse($attendance->date)->format('n月j日'));

        $detail->assertSee('10:00');
        $detail->assertSee('19:00');
        $detail->assertSee('12:00');
        $detail->assertSee('13:00');

        $detail->assertSee('遅延のため');
    }

    // 管理者修正申請画面--承認処理
    public function test_admin_can_approve_correction_and_apply_changes()
    {
        $now = Carbon::create(2025, 10, 10, 9, 0, 0, 'Asia/Tokyo');
        Carbon::setTestNow($now);

        $user = User::factory()->create(['email_verified_at' => now(), 'name' => 'ユーザーA']);
        $attendance = Attendance::factory()->create([
            'user_id' => $user->id,
            'date' => $now->toDateString(),
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
        ]);
        BreakTime::factory()->create([
            'attendance_id' => $attendance->id,
            'break_start' => '12:00:00',
            'break_end' => '13:00:00',
        ]);

        $application = Application::factory()->create([
            'user_id' => $user->id,
            'remark' => '遅延のため',
            'status' => 'pending',
        ]);
        NewAttendance::factory()->create([
            'application_id' => $application->id,
            'attendance_id' => $attendance->id,
            'new_clock_in' => '10:00:00',
            'new_clock_out' => '19:00:00',
        ]);

        $admin = Admin::where('email', 'admin@example.com')->firstOrFail();
        $this->actingAs($admin, 'admin');

        $this->get(route('admin.approve.show', [
            'attendance_correct_request' => $application->id,
        ]))->assertOk();

        $response = $this->patch(route('admin.approve', [
            'attendance_correct_request' => $application->id,
        ]));

        $response->assertRedirect(route('admin.approve.show', [
            'attendance_correct_request' => $application->id,
        ]));

        $this->assertDatabaseHas('applications', [
            'id' => $application->id,
            'status' => 'approved',
            'approved_at' => $now->toDateTimeString(),
        ]);

        $this->assertDatabaseHas('attendances', [
            'id' => $attendance->id,
            'clock_in' => '10:00:00',
            'clock_out' => '19:00:00',
        ]);

        $this->assertDatabaseHas('break_times', [
            'attendance_id' => $attendance->id,
            'break_start' => '12:00:00',
            'break_end' => '13:00:00',
        ]);
    }
}
