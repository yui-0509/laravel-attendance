<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Application extends Model
{
    use HasFactory;

    protected $fillable = ['user_id','admin_id','remark','status','approved_at'];

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
            Attendance::class,     // 行き先
            NewAttendance::class,  // 経由
            'application_id',      // 経由テーブルのFK
            'id',                  // 行き先のキー
            'id',                  // 自分のキー
            'attendance_id'        // 経由→行き先のFK
        );
    }

    public function scopePending($q){ return $q->where('status','pending'); }
    public function scopeApproved($q){ return $q->where('status','approved'); }
}
