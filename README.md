# laravel-attendance

## アプリ概要
出退勤登録や休憩時間の登録、勤怠の修正や申請ができるアプリです。
管理者は一般ユーザーの勤怠管理や修正、申請の承認ができます。

## 環境構築

### Dockerビルド

1.`git clone git@github.com:yui-0509/laravel-attendance.git` 

2.docker-compose.ymlのmysqlとphpmyadminに`platform:linux/x86_64`を追加

3.DockerDesktopアプリを立ち上げる

4.`docker-compose up -d --build` 

### Laravel環境構築

1.`docker-compose exec php bash` 

2.`composer install` 

3..env.exampleファイルを基に.envファイルを作成し、下記環境変数を変更 

```bash
cp .env.example .env
```

```env
DB_CONNECTION=mysql
DB_HOST=mysql
DB_PORT=3306
DB_DATABASE=laravel_db
DB_USERNAME=laravel_user
DB_PASSWORD=laravel_pass
```

4.アプリケーションキーの作成 

   `php artisan key:generate` 

5.マイグレーションの実行 

   `php artisan migrate` 
