<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Tests\TestCase;

class UserLoginTest extends TestCase
{
    use DatabaseMigrations;

    protected function setUp(): void
    {
        parent::setUp();
        app()->setLocale('ja');
        $this->seed(DatabaseSeeder::class);
    }

    // 一般ユーザーログイン
    public function test_login_user()
    {
        $user = User::find(1);

        $response = $this->post('/login', [
            'email' => 'reina.n@coachtech.com',
            'password' => 'password123',
        ]);

        $response->assertRedirect('/attendance');
        $this->assertAuthenticatedAs($user);
    }

    // 一般ユーザーログイン--メールアドレス未入力
    public function test_login_user_validate_email()
    {
        $response = $this->post('/login', [
            'email' => '',
            'password' => 'password123',
        ]);

        $response->assertStatus(302);
        $response->assertSessionHasErrors('email');

        $errors = session('errors');
        $this->assertEquals('メールアドレスを入力してください', $errors->first('email'));
    }

    // 一般ユーザーログイン--パスワード未入力
    public function test_login_user_validate_password()
    {
        $response = $this->post('/login', [
            'email' => 'reina.n@coachtech.com',
            'password' => '',
        ]);

        $response->assertStatus(302);
        $response->assertSessionHasErrors('password');

        $errors = session('errors');
        $this->assertEquals('パスワードを入力してください', $errors->first('password'));
    }

    // 一般ユーザーログイン--登録内容不一致
    public function test_login_user_validate_user()
    {
        $response = $this->post('/login', [
            'email' => 'rein.n@coachtech.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(302);
        $response->assertSessionHasErrors('email');

        $errors = session('errors');
        $this->assertEquals('ログイン情報が登録されていません。', $errors->first('email'));
    }
}
