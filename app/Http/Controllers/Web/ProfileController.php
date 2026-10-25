<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Support\ActivityLogger;
use App\Support\Actor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class ProfileController extends Controller
{
    public function show()
    {
        return view('profile.show', [
            'pageTitle' => 'Profil',
        ]);
    }

    public function updatePassword(Request $request)
    {
        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'confirmed', Password::min(12)],
        ]);

        $user = Actor::from($request);

        if (! Hash::check($validated['current_password'], $user->password)) {
            return back()->withErrors([
                'current_password' => 'Kata sandi sekarang tidak cocok.',
            ]);
        }

        $user->password = $validated['password'];
        $user->save();

        ActivityLogger::log('user.password_changed', $user, $user);

        return redirect()->route('profile')->with('info', 'Kata sandi berhasil diubah.');
    }
}
