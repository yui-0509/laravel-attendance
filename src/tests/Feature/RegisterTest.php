<?php

namespace Tests\Feature;

use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class RegisterTest extends TestCase
{
    use DatabaseMigrations;

    protected function setUp(): void
    {
        parent::setUp();
        app()->setLocale('ja');
        $this->seed(DatabaseSeeder::class);
    }

    // 会員情報登録
    public function test_register_user()
    {
        $response = $this->post('/register', [
            'name' => 'テストユーザ',
            'email' => 'test@gmail.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertRedirect('/email/verify');
        $this->assertDatabaseHas(User::class, [
            'name' => 'テストユーザ',
            'email' => 'test@gmail.com',
        ]);
    }

    // 会員情報登録--名前未入力
    public function test_register_user_validate_name()
    {
        $response = $this->post('/register', [
            'name' => '',
            'email' => 'test@gmail.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(302);
        $response->assertSessionHasErrors('name');

        $errors = session('errors');
        $this->assertEquals('お名前を入力してください', $errors->first('name'));
    }

    // 会員情報登録--メールアドレス未入力
    public function test_register_user_validate_email()
    {
        $response = $this->post('/register', [
            'name' => 'テストユーザ',
            'email' => '',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(302);
        $response->assertSessionHasErrors('email');

        $errors = session('errors');
        $this->assertEquals('メールアドレスを入力してください', $errors->first('email'));
    }

    // 会員情報登録--パスワード８文字未満
    public function test_register_user_validate_password_under7()
    {
        $response = $this->post('/register', [
            'name' => 'テストユーザ',
            'email' => 'test@gmail.com',
            'password' => 'passwor',
            'password_confirmation' => 'passwor',
        ]);

        $response->assertStatus(302);
        $response->assertSessionHasErrors('password');

        $errors = session('errors');
        $this->assertEquals('パスワードは8文字以上で入力してください', $errors->first('password'));
    }

    // 会員登録時--パスワード不一致
    public function test_register_user_validate_confirm_password()
    {
        $response = $this->post('/register', [
            'name' => 'テストユーザ',
            'email' => 'test@gmail.com',
            'password' => 'password',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(302);
        $response->assertSessionHasErrors('password');

        $errors = session('errors');
        $this->assertEquals('パスワードと一致しません', $errors->first('password'));
    }

    // 会員登録時--パスワード未入力
    public function test_register_user_validate_password()
    {
        $response = $this->post('/register', [
            'name' => 'テストユーザ',
            'email' => 'test@gmail.com',
            'password' => '',
            'password_confirmation' => 'password',
        ]);

        $response->assertStatus(302);
        $response->assertSessionHasErrors('password');

        $errors = session('errors');
        $this->assertEquals('パスワードを入力してください', $errors->first('password'));
    }

    // メール認証--認証メール送信
    public function test_verification_email_is_sent_after_registration()
    {
        Notification::fake();

        $payload = [
            'name' => 'テストユーザー',
            'email' => 'verify_test@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ];

        $response = $this->post('/register', $payload);
        $response->assertRedirect(route('verification.notice'));

        $user = User::where('email', $payload['email'])->firstOrFail();
        $this->assertNull($user->email_verified_at);

        Notification::assertSentTo($user, VerifyEmail::class);
    }

    // メール認証--認証ボタン押下時の遷移確認
    public function test_verification_check_redirects_to_mailtrap_when_unverified()
    {
        $now = Carbon::create(2025, 10, 10, 9, 0, 0, 'Asia/Tokyo');
        Carbon::setTestNow($now);

        $user = User::factory()->create([
            'email_verified_at' => null,
            'email' => 'testuser@example.com',
        ]);

        $this->actingAs($user);

        $response = $this->get(route('verification.check'));

        $response->assertRedirect('https://mailtrap.io/');
    }

    // メール認証--認証完了後のリダイレクト確認
    public function test_user_is_redirected_to_attendance_after_email_verification()
    {
        $user = User::factory()->create([
            'email' => 'testuser@example.com',
            'email_verified_at' => null,
        ]);

        $this->withSession(['unauthenticated_user' => $user]);

        $verificationUrl = route('verification.verify', [
            'id' => $user->id,
            'hash' => sha1($user->email),
        ]);

        $response = $this->get($verificationUrl);

        $response->assertRedirect('/attendance');

        $this->assertNotNull($user->fresh()->email_verified_at);
    }
}
