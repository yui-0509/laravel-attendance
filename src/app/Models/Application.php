<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Application extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'admin_id', 'remark', 'status', 'approved_at'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function admin()
    {
        return $this->belongsTo(Admin::class);
    }

    public function newAttendance()
    {
        return $this->hasOne(NewAttendance::class);
    }

    public function newBreaks()
    {
        return $this->hasMany(NewBreak::class)->orderBy('new_break_start');
    }

    public function attendance()
    {
        return $this->hasOneThrough(
            Attendance::class,
            NewAttendance::class,
            'application_id',
            'id',
            'id',
            'attendance_id'
        );
    }

    public function scopePending($q)
    {
        return $q->where('status', 'pending');
    }

    public function scopeApproved($q)
    {
        return $q->where('status', 'approved');
    }
}
