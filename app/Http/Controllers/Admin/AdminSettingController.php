<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class AdminSettingController extends Controller
{
    // ── Settings Page ─────────────────────────────────────
    public function index()
    {
        return view('admin.settings');
    }

    // ── Change Password ───────────────────────────────────
    public function changePassword(Request $request)
    {
        $request->validate([
            'current_password' => 'required|string',
            'new_password'     => 'required|string|min:6|confirmed',
        ], [
            'current_password.required' => app()->getLocale()=='km' ? 'សូមបញ្ចូលលេខសំងាត់បច្ចុប្បន្ន' : 'Current password is required.',
            'new_password.required'     => app()->getLocale()=='km' ? 'សូមបញ្ចូលលេខសំងាត់ថ្មី' : 'New password is required.',
            'new_password.min'          => app()->getLocale()=='km' ? 'លេខសំងាត់ថ្មីត្រូវការយ៉ាងហោចណាស់ 6 តួ' : 'New password must be at least 6 characters.',
            'new_password.confirmed'    => app()->getLocale()=='km' ? 'លេខសំងាត់ថ្មីមិនត្រូវគ្នា' : 'New passwords do not match.',
        ]);

        // Check current password
        if (!Hash::check($request->current_password, Auth::user()->password)) {
            return back()->with('password_error',
                app()->getLocale()=='km'
                    ? 'លេខសំងាត់បច្ចុប្បន្នមិនត្រឹមត្រូវ!'
                    : 'Current password is incorrect!'
            );
        }

        // Update password
        Auth::user()->update([
            'password' => Hash::make($request->new_password),
        ]);

        return back()->with('password_success',
            app()->getLocale()=='km'
                ? 'បានផ្លាស់ប្តូរលេខសំងាត់ដោយជោគជ័យ!'
                : 'Password changed successfully!'
        );
    }
}