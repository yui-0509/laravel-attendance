<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AdminLoginController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\AdminAttendanceController;
use App\Http\Controllers\ApplicationController;
use App\Http\Controllers\AdminStaffController;
use App\Http\Controllers\AdminApplicationController;
use App\Http\Requests\EmailVerificationRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\RegisteredUserController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::prefix('admin')->group(function () {
    Route::get('/login', [AdminLoginController::class, 'create'])
    ->name('admin.login');
    Route::post('/login', [AdminLoginController::class, 'store'])->name('admin.login.store');

    Route::middleware('auth:admin')->group(function () {
        Route::post('/logout',
        [AdminLoginController::class, 'destroy'])->name('admin.logout');
        Route::get('/attendance/list',[AdminAttendanceController::class, 'index'])->name('admin.attendance');
        Route::get('/staff/list', [AdminStaffController::class, 'index']);
        Route::get('/attendance/{attendance}', [AdminAttendanceController::class, 'show'])->name('admin.show');
        Route::patch('/attendance/correction/{attendance}', [AdminAttendanceController::class, 'update'])->name('admin.update');
        Route::get('/attendance/staff/{user}', [AdminAttendanceController::class, 'userIndex'])->name('admin.userIndex');
        Route::get('/attendance/staff/{user}/csv', [AdminAttendanceController::class, 'exportCsv'])->name('admin.attendance.csv');
    });
});

Route::get('/stamp_correction_request/list', function (Request $request) {
    if (Auth::guard('admin')->check()) {
        return app(AdminApplicationController::class)->index($request);
    }
    if (Auth::check()) {
        return app(ApplicationController::class)->index($request);
    }

    $as = $request->query('as');
    if ($as === 'admin') {
        return redirect()->route('admin.login');
    }
    return redirect()->route('login');
})->name('request.index');

Route::middleware('auth:admin')->group(function () {
    Route::get('/stamp_correction_request/approve/{attendance_correct_request}',[AdminApplicationController::class, 'show'])->name('admin.approve.show');
    Route::patch('/stamp_correction_request/approve/{attendance_correct_request}', [AdminApplicationController::class, 'approve'])
        ->name('admin.approve');
});

Route::middleware('auth','verified')->group(function () {
    Route::get('/attendance', [AttendanceController::class, 'create']);
    Route::get('/attendance/list', [AttendanceController::class, 'index'])->name('attendance.list');
    Route::get('/attendance/detail/{id}', [AttendanceController::class, 'show'])->name('attendance.show');
    Route::post('/attendance/detail/correction/{attendance}', [AttendanceController::class, 'correction'])->name('attendance.correction');
    Route::post('/attendance/clock-in', [AttendanceController::class, 'clockIn'])->name('attendance.clockin');
    Route::post('/attendance/clock-out', [AttendanceController::class, 'clockOut'])->name('attendance.clockout');
    Route::post('/attendance/start-break', [AttendanceController::class, 'startBreak'])->name('attendance.startBreak');
    Route::post('/attendance/end-break', [AttendanceController::class, 'endBreak'])->name('attendance.endBreak');
});

Route::post('/register', [RegisteredUserController::class, 'store']);

Route::get('/email/verify', function () {
    return view('user.auth.verify-email');
})->name('verification.notice');

Route::post('/email/verification-notification', function (Request $request) {
    session()->get('unauthenticated_user')->sendEmailVerificationNotification();
    session()->put('resent', true);
    return back()->with('status', 'verification-link-sent');
})->name('verification.send');

Route::get('/email/verify/{id}/{hash}', function (EmailVerificationRequest $request) {
    $request->fulfill();
    session()->forget('unauthenticated_user');
    return redirect('/attendance');
})->name('verification.verify');

Route::get('/email/verify/check', function () {
    $user = Auth::user();
    if (!$user) {
        $sess = session('unauthenticated_user');
        if ($sess instanceof User) {
            $user = User::find($sess->id);
        }
    }
    if ($user && $user->hasVerifiedEmail()) {
        if (!Auth::check()) {
            Auth::login($user);
        }
        return redirect()->intended('/attendance');
    }
    return redirect()
        ->route('verification.notice')
        ->with('status', 'まだ認証が完了していません。メールのリンクをクリックしてください。');
})->name('verification.check');