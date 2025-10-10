<?php

namespace App\Http\Controllers;

use App\Actions\Fortify\CreateNewUser;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\Request;

class RegisteredUserController extends Controller
{
    public function store(Request $request, CreateNewUser $creator)
    {
        event(new Registered($user = $creator->create($request->only('name', 'email', 'password', 'password_confirmation'))));
        session()->put('unauthenticated_user', $user);

        return redirect()->route('verification.notice');
    }
}
