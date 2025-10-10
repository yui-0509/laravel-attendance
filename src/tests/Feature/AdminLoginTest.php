<?php

namespace Tests\Feature;

use App\Models\Admin;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Tests\TestCase;

class AdminLoginTest extends TestCase
{
    use DatabaseMigrations;

    protected function setUp(): void
    {
        parent::setUp();
        app()->setLocale('ja');
        $this->seed(DatabaseSeeder::class);
    }

    // 管理者ログイン
    public function test_login_admin()
    {
        $admin = Admin::find(1);

        $response = $this->post('/admin/login', [
            'email' => 'admin@example.com',
            'password' => 'password123',
        ]);

        $response->assertRedirect('/admin/attendance/list');
        $this->assertAuthenticatedAs($admin, 'admin');
    }

    // 管理者ログイン--メールアドレス未入力
    public function test_login_admin_validate_email()
    {
        $response = $this->post('/admin/login', [
            'email' => '',
            'password' => 'password123',
        ]);

        $response->assertStatus(302);
        $response->assertSessionHasErrors('email');

        $errors = session('errors');
        $this->assertEquals('メールアドレスを入力してください', $errors->first('email'));
    }

    // 管理者ログイン--パスワード未入力
    public function test_login_admin_validate_password()
    {
        $response = $this->post('/admin/login', [
            'email' => 'admin@example.com',
            'password' => '',
        ]);

        $response->assertStatus(302);
        $response->assertSessionHasErrors('password');

        $errors = session('errors');
        $this->assertEquals('パスワードを入力してください', $errors->first('password'));
    }

    // 管理者ログイン--登録内容不一致
    public function test_login_admin_validate_admin()
    {
        $response = $this->post('/admin/login', [
            'email' => 'admi@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(302);
        $response->assertSessionHasErrors('email');

        $errors = session('errors');
        $this->assertEquals('ログイン情報が登録されていません', $errors->first('email'));
    }
}
