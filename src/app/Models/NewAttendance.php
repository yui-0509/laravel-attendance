<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NewAttendance extends Model
{
    use HasFactory;

    protected $fillable = ['application_id', 'attendance_id', 'new_clock_in', 'new_clock_out'];

    public function application()
    {
        return $this->belongsTo(Application::class);
    }

    public function attendance()
    {
        return $this->belongsTo(Attendance::class);
    }
}
