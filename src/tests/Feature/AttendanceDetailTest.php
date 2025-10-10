<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Attendance;
use App\Models\BreakTime;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AttendanceDetailTest extends TestCase
{
    use DatabaseMigrations;

    protected function setUp(): void
    {
        parent::setUp();
        app()->setLocale('ja');
        config(['app.timezone' => 'Asia/Tokyo']);
        $this->seed(DatabaseSeeder::class);
    }

    // 勤怠詳細画面--名前表示確認
    public function test_detail_user_name()
    {
        $now = Carbon::create(2025, 10, 6, 9, 0, 0, 'Asia/Tokyo');
        Carbon::setTestNow($now);

        $user = User::factory()->create(['email_verified_at' => now()]);

        $attendance = Attendance::factory()->create([
            'user_id' => $user->id,
            'date' => $now->toDateString(),
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
        ]);

        $this->actingAs($user);

        $detailUrl = route('attendance.show', [
            'id' => $attendance->id,
            'date' => $now->toDateString(),
        ]);

        $detail = $this->get($detailUrl)->assertOk();

        $detail->assertSee($user->name);
    }

    // 勤怠詳細画面--日付表示確認
    public function test_detail_date_is_selected_date()
    {
        $now = Carbon::create(2025, 10, 6, 9, 0, 0, 'Asia/Tokyo');
        Carbon::setTestNow($now);

        $user = User::factory()->create(['email_verified_at' => now()]);

        $attendance = Attendance::factory()->create([
            'user_id' => $user->id,
            'date' => $now->toDateString(),
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
        ]);

        $this->actingAs($user);

        $detailUrl = route('attendance.show', [
            'id' => $attendance->id,
            'date' => $now->toDateString(),
        ]);

        $detail = $this->get($detailUrl)->assertOk();

        $detail->assertSee($now->format('Y年'));
        $detail->assertSee($now->format('n月j日'));
    }

    // 勤怠詳細画面--出退勤時刻表示確認
    public function test_detail_attendance_time()
    {
        $now = Carbon::create(2025, 10, 6, 9, 0, 0, 'Asia/Tokyo');
        Carbon::setTestNow($now);

        $user = User::factory()->create(['email_verified_at' => now()]);

        $attendance = Attendance::factory()->create([
            'user_id' => $user->id,
            'date' => $now->toDateString(),
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
        ]);

        $this->actingAs($user);

        $detailUrl = route('attendance.show', [
            'id' => $attendance->id,
            'date' => $now->toDateString(),
        ]);

        $detail = $this->get($detailUrl)->assertOk();

        $detail->assertSee(Carbon::parse($attendance->clock_in)->format('H:i'));
        $detail->assertSee(Carbon::parse($attendance->clock_out)->format('H:i'));
    }

    // 勤怠詳細画面--休憩時刻表示確認
    public function test_detail_break_time()
    {
        $now = Carbon::create(2025, 10, 6, 9, 0, 0, 'Asia/Tokyo');
        Carbon::setTestNow($now);

        $user = User::factory()->create(['email_verified_at' => now()]);

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

        $this->actingAs($user);

        $detailUrl = route('attendance.show', [
            'id' => $attendance->id,
            'date' => $now->toDateString(),
        ]);

        $detail = $this->get($detailUrl)->assertOk();

        $detail->assertSee(Carbon::parse($breakTime->break_start)->format('H:i'));
        $detail->assertSee(Carbon::parse($breakTime->break_end)->format('H:i'));
    }

    // 勤怠詳細画面--出退勤エラー確認
    public function test_detail_validate_attendance_time()
    {
        $now = Carbon::create(2025, 10, 6, 9, 0, 0, 'Asia/Tokyo');
        Carbon::setTestNow($now);

        $user = User::factory()->create(['email_verified_at' => now()]);

        $attendance = Attendance::factory()->create([
            'user_id' => $user->id,
            'date' => $now->toDateString(),
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
        ]);

        $this->actingAs($user);

        $fromUrl = route('attendance.show', [
            'id' => $attendance->id,
            'date' => $now->toDateString(),
        ]);

        $response = $this->from($fromUrl)->post(
            route('attendance.correction', $attendance),
            [
                'new_clock_in' => '19:00',
                'new_clock_out' => '18:00',
            ]
        );

        $response->assertRedirect($fromUrl);

        $response->assertSessionHasErrors([
            'new_clock_in' => '出勤時間もしくは退勤時間が不適切な値です',
        ]);

        $this->assertDatabaseMissing('new_attendances', [
            'attendance_id' => $attendance->id,
            'new_clock_in' => '19:00:00',
            'new_clock_out' => '18:00:00',
        ]);

        $this->assertDatabaseHas('attendances', [
            'id' => $attendance->id,
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
        ]);
    }

    // 勤怠詳細画面--休憩開始エラー表示
    public function test_detail_validate_break_time()
    {
        $now = Carbon::create(2025, 10, 6, 9, 0, 0, 'Asia/Tokyo');
        Carbon::setTestNow($now);

        $user = User::factory()->create(['email_verified_at' => now()]);

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

        $this->actingAs($user);

        $fromUrl = route('attendance.show', [
            'id' => $attendance->id,
            'date' => $now->toDateString(),
        ]);

        $response = $this->from($fromUrl)->post(
            route('attendance.correction', $attendance),
            [
                'new_clock_in' => '09:00',
                'new_clock_out' => '18:00',

                'breaks' => [
                    [
                        'id' => $breakTime->id,
                        'new_break_start' => '19:00',
                        'new_break_end' => '13:00',
                    ],
                ],
            ]
        );

        $response->assertRedirect($fromUrl);

        $response->assertSessionHasErrors([
            'breaks.0.new_break_start' => '休憩時間が不適切な値です',
        ]);

        $this->assertDatabaseMissing('new_attendances', [
            'attendance_id' => $attendance->id,
        ]);

        $this->assertDatabaseMissing('new_breaks', [
            'break_id' => $breakTime->id,
        ]);

        $this->assertDatabaseHas('attendances', [
            'id' => $attendance->id,
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
        ]);

        $this->assertDatabaseHas('break_times', [
            'id' => $breakTime->id,
            'break_start' => '12:00:00',
            'break_end' => '13:00:00',
        ]);
    }

    // 勤怠詳細画面--休憩終了エラー表示
    public function test_detail_validate_break_end_time()
    {
        $now = Carbon::create(2025, 10, 6, 9, 0, 0, 'Asia/Tokyo');
        Carbon::setTestNow($now);

        $user = User::factory()->create(['email_verified_at' => now()]);

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

        $this->actingAs($user);

        $fromUrl = route('attendance.show', [
            'id' => $attendance->id,
            'date' => $now->toDateString(),
        ]);

        $response = $this->from($fromUrl)->post(
            route('attendance.correction', $attendance),
            [
                'new_clock_in' => '09:00',
                'new_clock_out' => '18:00',

                'breaks' => [
                    [
                        'id' => $breakTime->id,
                        'new_break_start' => '12:00',
                        'new_break_end' => '20:00',
                    ],
                ],
            ]
        );

        $response->assertRedirect($fromUrl);

        $response->assertSessionHasErrors([
            'breaks.0.new_break_end' => '休憩時間もしくは退勤時間が不適切な値です',
        ]);

        $this->assertDatabaseMissing('new_attendances', [
            'attendance_id' => $attendance->id,
        ]);

        $this->assertDatabaseMissing('new_breaks', [
            'break_id' => $breakTime->id,
        ]);

        $this->assertDatabaseHas('attendances', [
            'id' => $attendance->id,
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
        ]);

        $this->assertDatabaseHas('break_times', [
            'id' => $breakTime->id,
            'break_start' => '12:00:00',
            'break_end' => '13:00:00',
        ]);
    }

    // 勤怠詳細画面--備考欄未入力
    public function test_detail_validate_remark()
    {
        $now = Carbon::create(2025, 10, 6, 9, 0, 0, 'Asia/Tokyo');
        Carbon::setTestNow($now);

        $user = User::factory()->create(['email_verified_at' => now()]);

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

        $this->actingAs($user);

        $fromUrl = route('attendance.show', [
            'id' => $attendance->id,
            'date' => $now->toDateString(),
        ]);

        $response = $this->from($fromUrl)->post(
            route('attendance.correction', $attendance),
            [
                'new_clock_in' => '10:00',
                'new_clock_out' => '18:00',
                'remark' => '',
            ]
        );

        $response->assertRedirect($fromUrl);

        $response->assertSessionHasErrors([
            'remark' => '備考を記入してください',
        ]);

        $this->assertDatabaseMissing('new_attendances', [
            'attendance_id' => $attendance->id,
        ]);

        $this->assertDatabaseHas('attendances', [
            'id' => $attendance->id,
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
        ]);
    }

    // 勤怠詳細画面--修正申請処理
    public function test_detail_correction()
    {
        $now = Carbon::create(2025, 10, 6, 9, 0, 0, 'Asia/Tokyo');
        Carbon::setTestNow($now);

        $user = User::factory()->create(['email_verified_at' => now()]);
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

        $this->actingAs($user);

        $fromUrl = route('attendance.show', [
            'id' => $attendance->id,
            'date' => $attendance->date,
        ]);

        $response = $this->from($fromUrl)->post(
            route('attendance.correction', $attendance),
            [
                'new_clock_in' => '10:00',
                'new_clock_out' => '18:00',
                'remark' => '遅延のため',
            ]
        );

        $response->assertRedirect($fromUrl);

        $this->assertDatabaseHas('applications', [
            'user_id' => $user->id,
            'remark' => '遅延のため',
            'status' => 'pending',
        ]);

        $applicationId = DB::table('applications')
            ->where('user_id', $user->id)
            ->where('remark', '遅延のため')
            ->orderByDesc('id')
            ->value('id');

        $this->assertDatabaseHas('new_attendances', [
            'application_id' => $applicationId,
            'attendance_id' => $attendance->id,
            'new_clock_in' => '10:00:00',
        ]);

        $this->post('/logout')->assertStatus(302);

        $admin = Admin::where('email', 'admin@example.com')->firstOrFail();
        $this->actingAs($admin, 'admin');

        $index = $this->get(route('request.index'))->assertOk();

        $application = DB::table('applications')->find($applicationId);

        $index->assertSee('承認待ち');
        $index->assertSee($user->name);
        $index->assertSee(Carbon::parse($attendance->date)->format('Y/m/d'));
        $index->assertSee($application->remark);
        $index->assertSee(Carbon::parse($application->created_at)->format('Y/m/d'));

        $show = $this->get(route('admin.approve.show', ['attendance_correct_request' => $applicationId]))->assertOk();

        $show->assertSee($user->name);
        $show->assertSee(Carbon::parse($attendance->date)->format('Y年'));
        $show->assertSee(Carbon::parse($attendance->date)->format('n月j日'));
        $show->assertSee('10:00');
        $show->assertSee('18:00');
        $show->assertSee('12:00');
        $show->assertSee('13:00');
        $show->assertSee('遅延のため');
    }

    // 勤怠詳細画面--申請一覧画面「承認待ち」表示確認
    public function test_detail_application_list()
    {
        $now = Carbon::create(2025, 10, 6, 9, 0, 0, 'Asia/Tokyo');
        Carbon::setTestNow($now);

        $user = User::factory()->create(['email_verified_at' => now()]);
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

        $this->actingAs($user);

        $fromUrl = route('attendance.show', [
            'id' => $attendance->id,
            'date' => $attendance->date,
        ]);

        $response = $this->from($fromUrl)->post(
            route('attendance.correction', $attendance),
            [
                'new_clock_in' => '10:00',
                'new_clock_out' => '18:00',
                'remark' => '遅延のため',
            ]
        );

        $response->assertRedirect($fromUrl);

        $this->assertDatabaseHas('applications', [
            'user_id' => $user->id,
            'remark' => '遅延のため',
            'status' => 'pending',
        ]);

        $applicationId = DB::table('applications')
            ->where('user_id', $user->id)
            ->where('remark', '遅延のため')
            ->orderByDesc('id')
            ->value('id');

        $this->assertDatabaseHas('new_attendances', [
            'application_id' => $applicationId,
            'attendance_id' => $attendance->id,
            'new_clock_in' => '10:00:00',
        ]);

        $index = $this->get(route('request.index'))->assertOk();

        $application = DB::table('applications')->find($applicationId);

        $index->assertSee('承認待ち');
        $index->assertSee($user->name);
        $index->assertSee(Carbon::parse($attendance->date)->format('Y/m/d'));
        $index->assertSee($application->remark);
        $index->assertSee(Carbon::parse($application->created_at)->format('Y/m/d'));
    }

    // 勤怠詳細画面--申請一覧画面「承認済み」表示確認
    public function test_detail_approval_list()
    {
        $now = Carbon::create(2025, 10, 6, 9, 0, 0, 'Asia/Tokyo');
        Carbon::setTestNow($now);

        $user = User::factory()->create(['email_verified_at' => now()]);
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

        $this->actingAs($user);

        $fromUrl = route('attendance.show', [
            'id' => $attendance->id,
            'date' => $attendance->date,
        ]);

        $response = $this->from($fromUrl)->post(
            route('attendance.correction', $attendance),
            [
                'new_clock_in' => '10:00',
                'new_clock_out' => '18:00',
                'remark' => '遅延のため',
            ]
        );

        $response->assertRedirect($fromUrl);

        $this->assertDatabaseHas('applications', [
            'user_id' => $user->id,
            'remark' => '遅延のため',
            'status' => 'pending',
        ]);

        $applicationId = DB::table('applications')
            ->where('user_id', $user->id)
            ->where('remark', '遅延のため')
            ->orderByDesc('id')
            ->value('id');

        $this->assertDatabaseHas('new_attendances', [
            'application_id' => $applicationId,
            'attendance_id' => $attendance->id,
            'new_clock_in' => '10:00:00',
        ]);

        $this->post('/logout')->assertStatus(302);

        $admin = Admin::where('email', 'admin@example.com')->firstOrFail();
        $this->actingAs($admin, 'admin');

        $this->get(route('admin.approve.show', ['attendance_correct_request' => $applicationId]))->assertOk();

        $this->patch(route('admin.approve', ['attendance_correct_request' => $applicationId]))->assertRedirect();

        $this->assertDatabaseHas('applications', [
            'id' => $applicationId,
            'status' => 'approved',
        ]);

        $this->post(route('admin.logout'))->assertStatus(302);

        $this->actingAs($user);

        $index = $this->get(route('request.index', ['tab' => 'approved']))->assertOk();

        $application = DB::table('applications')->find($applicationId);

        $index->assertSee('承認済み');
        $index->assertSee($user->name);
        $index->assertSee(Carbon::parse($attendance->date)->format('Y/m/d'));
        $index->assertSee($application->remark);
        $index->assertSee(Carbon::parse($application->created_at)->format('Y/m/d'));
    }

    // 勤怠一覧画面--詳細ボタン押下
    public function test_application_list_detail_button()
    {
        $now = Carbon::create(2025, 10, 6, 9, 0, 0, 'Asia/Tokyo');
        Carbon::setTestNow($now);

        $user = User::factory()->create(['email_verified_at' => now()]);
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

        $this->actingAs($user);

        $fromUrl = route('attendance.show', [
            'id' => $attendance->id,
            'date' => $attendance->date,
        ]);

        $response = $this->from($fromUrl)->post(
            route('attendance.correction', $attendance),
            [
                'new_clock_in' => '10:00',
                'new_clock_out' => '18:00',
                'remark' => '遅延のため',
            ]
        )->assertRedirect($fromUrl);

        $this->get(route('request.index'))->assertOk();

        $detailUrl = route('attendance.show', [
            'id' => $attendance->id,
            'date' => $attendance->date,
        ]);

        $detail = $this->get($detailUrl)->assertOk();

        $detail->assertSee($user->name);
        $detail->assertSee(Carbon::parse($attendance->date)->format('Y年'));
        $detail->assertSee(Carbon::parse($attendance->date)->format('n月j日'));
    }
}
