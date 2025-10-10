<?php

namespace App\Http\Controllers;

use App\Models\Application;
use Illuminate\Http\Request;

class ApplicationController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $tab = $request->query('tab', 'pending');
        if (! in_array($tab, ['pending', 'approved'], true)) {
            $tab = 'pending';
        }

        $applications = Application::with([
            'user',
            'newAttendance.attendance',
        ])
            ->where('user_id', $user->id)
            ->where('status', $tab)
            ->orderByDesc('created_at')
            ->get();

        return view('user.user-applications', compact('applications'));
    }
}
