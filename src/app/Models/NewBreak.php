<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NewBreak extends Model
{
    use HasFactory;

    protected $fillable = ['application_id','break_id','new_break_start','new_break_end'];

    public function application()
    {
        return $this->belongsTo(Application::class);
    }

    public function originalBreak()
    {
        // 既存休憩に紐づく場合のみ。新規追加は null
        return $this->belongsTo(BreakTime::class, 'break_id');
    }
}
