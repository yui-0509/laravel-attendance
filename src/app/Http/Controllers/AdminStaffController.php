<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;

class AdminStaffController extends Controller
{
    public function index(Request $request)
    {
        $users = User::orderBy('name')->get();

        return view('admin.staff.staff-list', compact('users'));
    }
}
