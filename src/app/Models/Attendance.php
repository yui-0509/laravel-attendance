<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Attendance extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'date',
        'clock_in',
        'clock_out',
        'remark',
    ];

    protected $casts = [
        'date' => 'date',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function breaks()
    {
        return $this->hasMany(\App\Models\BreakTime::class, 'attendance_id');
    }

    public function newAttendances()
    {
        return $this->hasMany(NewAttendance::class);
    }

    public function applications()
    {
        return $this->hasManyThrough(
            Application::class,
            NewAttendance::class,
            'attendance_id',
            'id',
            'id',
            'application_id'
        );
    }
}
