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

class AdminAttendanceDetailTest extends TestCase
{
    use DatabaseMigrations;

    protected function setUp(): void
    {
        parent::setUp();
        app()->setLocale('ja');
        config(['app.timezone' => 'Asia/Tokyo']);
        $this->seed(DatabaseSeeder::class);
    }

    // 管理者勤怠詳細画面--表示確認
    public function test_admin_detail_page()
    {
        $now = Carbon::create(2025, 10, 6, 9, 0, 0, 'Asia/Tokyo');
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

        $admin = Admin::where('email', 'admin@example.com')->firstOrFail();
        $this->actingAs($admin, 'admin');

        $detail = $this->get(route('admin.show', ['attendance' => $attendance->id]))->assertOk();

        $detail->assertSee('ユーザーA');
        $detail->assertSee(Carbon::parse($attendance->date)->format('Y年'));
        $detail->assertSee(Carbon::parse($attendance->date)->format('n月j日'));
        $detail->assertSee('09:00');
        $detail->assertSee('18:00');
        $detail->assertSee('12:00');
        $detail->assertSee('13:00');
    }

    // 管理者勤怠詳細画面--出退勤エラー表示
    public function test_admin_detail_validate_attendance_time()
    {
        $now = Carbon::create(2025, 10, 6, 9, 0, 0, 'Asia/Tokyo');
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

        $admin = Admin::where('email', 'admin@example.com')->firstOrFail();
        $this->actingAs($admin, 'admin');

        $fromUrl = route('admin.show', ['attendance' => $attendance->id]);

        $response = $this->from($fromUrl)->patch(
            route('admin.update', $attendance->id),
            [
                'clock_in' => '19:00',
                'clock_out' => '18:00',
            ]
        );

        $response->assertRedirect($fromUrl);

        $response->assertSessionHasErrors([
            'clock_in' => '出勤時間もしくは退勤時間が不適切な値です',
        ]);

        $this->assertDatabaseMissing('attendances', [
            'id' => $attendance->id,
            'clock_in' => '19:00:00',
            'clock_out' => '18:00:00',
        ]);
    }

    // 管理者勤怠詳細画面--休憩開始エラー表示
    public function test_admin_detail_validate_break_time()
    {
        $now = Carbon::create(2025, 10, 6, 9, 0, 0, 'Asia/Tokyo');
        Carbon::setTestNow($now);

        $user = User::factory()->create(['email_verified_at' => now(), 'name' => 'ユーザーA']);
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

        $admin = Admin::where('email', 'admin@example.com')->firstOrFail();
        $this->actingAs($admin, 'admin');

        $fromUrl = route('admin.show', ['attendance' => $attendance->id]);

        $response = $this->from($fromUrl)->patch(
            route('admin.update', $attendance->id),
            [
                'clock_in' => '09:00',
                'clock_out' => '18:00',

                'breaks' => [
                    [
                        'id' => $breakTime->id,
                        'break_start' => '19:00',
                        'break_end' => '13:00',
                    ],
                ],
            ]
        );

        $response->assertRedirect($fromUrl);

        $response->assertSessionHasErrors([
            'breaks.0.break_start' => '休憩時間が不適切な値です',
        ]);

        $this->assertDatabaseHas('break_times', [
            'id' => $breakTime->id,
            'break_start' => '12:00:00',
            'break_end' => '13:00:00',
        ]);

        $this->assertDatabaseHas('attendances', [
            'id' => $attendance->id,
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
        ]);
    }

    // 管理者勤怠詳細画面--休憩終了エラー表示
    public function test_admin_detail_validate_break_end_time()
    {
        $now = Carbon::create(2025, 10, 6, 9, 0, 0, 'Asia/Tokyo');
        Carbon::setTestNow($now);

        $user = User::factory()->create(['email_verified_at' => now(), 'name' => 'ユーザーA']);
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

        $admin = Admin::where('email', 'admin@example.com')->firstOrFail();
        $this->actingAs($admin, 'admin');

        $fromUrl = route('admin.show', ['attendance' => $attendance->id]);

        $response = $this->from($fromUrl)->patch(
            route('admin.update', $attendance->id),
            [
                'clock_in' => '09:00',
                'clock_out' => '18:00',

                'breaks' => [
                    [
                        'id' => $breakTime->id,
                        'break_start' => '12:00',
                        'break_end' => '20:00',
                    ],
                ],
            ]
        );

        $response->assertRedirect($fromUrl);

        $response->assertSessionHasErrors([
            'breaks.0.break_end' => '休憩時間もしくは退勤時間が不適切な値です',
        ]);

        $this->assertDatabaseHas('break_times', [
            'id' => $breakTime->id,
            'break_start' => '12:00:00',
            'break_end' => '13:00:00',
        ]);

        $this->assertDatabaseHas('attendances', [
            'id' => $attendance->id,
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
        ]);
    }

    // 管理者勤怠詳細画面--備考欄未入力
    public function test_admin_detail_validate_remark()
    {
        $now = Carbon::create(2025, 10, 6, 9, 0, 0, 'Asia/Tokyo');
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

        $admin = Admin::where('email', 'admin@example.com')->firstOrFail();
        $this->actingAs($admin, 'admin');

        $fromUrl = route('admin.show', ['attendance' => $attendance->id]);

        $response = $this->from($fromUrl)->patch(
            route('admin.update', $attendance->id),
            [
                'clock_in' => '10:00',
                'clock_out' => '18:00',
                'remark' => '',
            ]
        );

        $response->assertRedirect($fromUrl);

        $response->assertSessionHasErrors([
            'remark' => '備考を記入してください',
        ]);
    }

    // 管理者勤怠詳細画面--勤怠修正処理
    public function test_admin_detail()
    {
        $now = Carbon::create(2025, 10, 6, 9, 0, 0, 'Asia/Tokyo');
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

        $admin = Admin::where('email', 'admin@example.com')->firstOrFail();
        $this->actingAs($admin, 'admin');

        $fromUrl = route('admin.show', ['attendance' => $attendance->id]);

        $response = $this->from($fromUrl)->patch(
            route('admin.update', $attendance->id),
            [
                'clock_in' => '10:00',
                'clock_out' => '18:00',
                'remark' => '遅延のため',
            ]
        );

        $response->assertRedirect($fromUrl);

        $this->assertDatabaseHas('attendances', [
            'id' => $attendance->id,
            'clock_in' => '10:00:00',
            'clock_out' => '18:00:00',
            'remark' => '遅延のため',
        ]);
    }
}
