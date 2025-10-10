<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\BreakTime;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Tests\TestCase;

class AttendanceListTest extends TestCase
{
    use DatabaseMigrations;

    protected function setUp(): void
    {
        parent::setUp();
        app()->setLocale('ja');
        config(['app.timezone' => 'Asia/Tokyo']);
        $this->seed(DatabaseSeeder::class);
    }

    // 勤怠一覧画面--勤怠情報表示確認
    public function test_attendance_list_shows_user_attendances()
    {
        $now = Carbon::create(2025, 10, 6, 9, 0, 0, 'Asia/Tokyo');
        Carbon::setTestNow($now);

        $user = User::where('email', 'reina.n@coachtech.com')->firstOrFail();
        $this->actingAs($user);

        $response = $this->get(route('attendance.list'));
        $response->assertOk();

        $clockIn = Carbon::create(2025, 10, 6, 9, 0, 0);
        $clockOut = Carbon::create(2025, 10, 6, 18, 0, 0);
        $breakStart = Carbon::create(2025, 10, 6, 12, 0, 0);
        $breakEnd = Carbon::create(2025, 10, 6, 13, 0, 0);

        $totalWorkSeconds = $clockOut->diffInSeconds($clockIn);

        $breakSeconds = $breakEnd->diffInSeconds($breakStart);

        $workSeconds = $totalWorkSeconds - $breakSeconds;

        $workHours = floor($workSeconds / 3600);
        $workMinutes = floor(($workSeconds % 3600) / 60);
        $workDuration = sprintf('%d:%02d', $workHours, $workMinutes);

        $response->assertSee('09:00');
        $response->assertSee('18:00');
        $response->assertSee('1:00');
        $response->assertSee($workDuration);
        $response->assertSee($now->format('m/d'));
    }

    // 勤怠一覧画面--現在月表示
    public function test_attendance_list_shows_current_month()
    {
        $now = Carbon::create(2025, 10, 6, 9, 0, 0, 'Asia/Tokyo');
        Carbon::setTestNow($now);

        $user = User::where('email', 'reina.n@coachtech.com')->firstOrFail();
        $this->actingAs($user);

        $response = $this->get(route('attendance.list'));
        $response->assertOk();

        $response->assertSee($now->format('Y/n'));
    }

    // 勤怠一覧画面--前月ボタン押下
    public function test_prev_month_button_shows_previous_month_records()
    {
        $now = Carbon::create(2025, 10, 6, 9, 0, 0, 'Asia/Tokyo');
        Carbon::setTestNow($now);

        $prevKey = $now->copy()->subMonth()->format('Y-m');
        $prevDay = $now->copy()->subMonth()->day(15);

        $user = User::factory()->create(['email_verified_at' => now()]);
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

        $this->actingAs($user);

        $response = $this->get(route('attendance.list', ['month' => $prevKey]))->assertOk();

        $response->assertSee($prevDay->format('Y/m'));

        $response->assertSee($prevDay->format('m/d'));
        $response->assertSee('09:00');
        $response->assertSee('18:00');
        $response->assertSee('1:00');
        $response->assertSee('8:00');
    }

    // 勤怠一覧画面--翌月ボタン押下
    public function test_next_month_button_shows_next_month_records()
    {
        $now = Carbon::create(2025, 10, 6, 9, 0, 0, 'Asia/Tokyo');
        Carbon::setTestNow($now);

        $user = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($user);

        $nextKey = $now->copy()->addMonth()->format('Y-m');
        $nextYm = $now->copy()->addMonth()->format('Y/n');

        $response = $this->get(route('attendance.list', ['month' => $nextKey]))->assertOk();

        $response->assertSee($nextYm);
    }

    // 勤怠一覧画面--詳細ボタン押下
    public function test_attendance_list_detail_button_navigates_to_detail_page()
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

        $list = $this->get(route('attendance.list'))->assertOk();

        $detailUrl = route('attendance.show', [
            'id' => $attendance->id,
            'date' => $now->toDateString(),
        ]);

        $detail = $this->get($detailUrl)->assertOk();

        $detail->assertSee($now->format('Y年'));
        $detail->assertSee($now->format('n月j日'));
        $detail->assertSee('09:00');
        $detail->assertSee('18:00');
    }
}
