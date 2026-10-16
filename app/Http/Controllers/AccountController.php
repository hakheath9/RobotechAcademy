<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class AccountController extends Controller
{
    public function show()
    {
        return view('account.show', ['user' => Auth::user()]);
    }

    public function edit()
    {
        return view('account.edit', ['user' => Auth::user()]);
    }

  public function update(Request $request)
{
    $user = Auth::user();

    $request->validate([
        'name'             => 'required|string|max:255',
        'email'            => 'required|email|unique:users,email,' . $user->id,
        'current_password' => 'nullable|string',
        'password'         => 'nullable|min:8|confirmed',
    ]);

    // If changing password, verify current password first
    if ($request->filled('password')) {
        if (!Hash::check($request->current_password, $user->password)) {
            return back()
                ->withErrors(['current_password' => 'Current password is incorrect.'])
                ->withInput();
        }
        $user->password = Hash::make($request->password);
    }

    $user->name  = $request->name;
    $user->email = $request->email;
    $user->save();

    return redirect()->route('account.show')->with('success', 'Account updated successfully!');
}
}