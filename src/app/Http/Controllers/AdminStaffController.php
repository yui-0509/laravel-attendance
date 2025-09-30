<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;

class AdminStaffController extends Controller
{
    public function index(Request $request) {
        $users = User::orderBy('name')->get();
        return view('admin.staff.staff-list', compact('users'));
    }
}
