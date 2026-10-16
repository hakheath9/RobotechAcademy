<?php

namespace App\Http\Controllers;

use App\Mail\OtpMail;
use App\Models\OtpCode;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;

class AuthController extends Controller
{
    // ── Show login form ────────────────────────────────────
    public function showLogin()
    {
        return view('auth.login');
    }

    // ── Handle login ───────────────────────────────────────
    public function login(Request $request)
    {
        $request->validate([
            'email'    => 'required|email',
            'password' => 'required|string',
        ]);

        if (Auth::attempt($request->only('email', 'password'), $request->boolean('remember'))) {
            $request->session()->regenerate();
            $user = Auth::user();

            if (!$user->is_active) {
                Auth::logout();
                return back()->withErrors([
                    'email' => app()->getLocale()=='km'
                        ? 'គណនីរបស់អ្នកត្រូវបានបិទ! សូមទាក់ទង Admin។'
                        : 'Your account has been deactivated. Please contact admin.'
                ])->withInput($request->only('email'));
            }

            if ($user->canAccessAdmin()) {
                return redirect()->route('admin.dashboard');
            }

            return redirect()->intended(route('home'));
        }

        return back()
            ->withErrors(['email' => app()->getLocale()=='km'
                ? 'អ៊ីមែល ឬ លេខសំងាត់មិនត្រឹមត្រូវ'
                : 'Invalid email or password.'])
            ->withInput($request->only('email'));
    }

    // ── Show register form ─────────────────────────────────
    public function showRegister()
    {
        return view('auth.register');
    }

    // ── Step 1: Send OTP ───────────────────────────────────
    public function sendOtp(Request $request)
    {
        $request->validate([
            'name'     => 'required|string|max:100',
            'email'    => 'required|email|unique:users,email',
            'password' => 'required|string|min:6|confirmed',
        ], [
            'name.required'      => app()->getLocale()=='km' ? 'សូមបញ្ចូលឈ្មោះ' : 'Name is required.',
            'email.required'     => app()->getLocale()=='km' ? 'សូមបញ្ចូលអ៊ីមែល' : 'Email is required.',
            'email.email'        => app()->getLocale()=='km' ? 'អ៊ីមែលមិនត្រឹមត្រូវ' : 'Please enter a valid email.',
            'email.unique'       => app()->getLocale()=='km' ? 'អ៊ីមែលនេះត្រូវបានប្រើហើយ' : 'This email is already registered.',
            'password.required'  => app()->getLocale()=='km' ? 'សូមបញ្ចូលលេខសំងាត់' : 'Password is required.',
            'password.min'       => app()->getLocale()=='km' ? 'លេខសំងាត់ត្រូវការ 6 តួ' : 'Password must be at least 6 characters.',
            'password.confirmed' => app()->getLocale()=='km' ? 'លេខសំងាត់មិនត្រូវគ្នា' : 'Passwords do not match.',
        ]);

        // Delete old OTP for this email
        OtpCode::where('email', $request->email)->delete();

        // Generate 6-digit OTP
        $otp = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        // Save OTP
        OtpCode::create([
            'email'      => $request->email,
            'code'       => $otp,
            'expires_at' => now()->addMinutes(10),
            'is_used'    => false,
        ]);

        // Send email
        try {
            Mail::to($request->email)->send(new OtpMail($otp, $request->name));
        } catch (\Exception $e) {
            return back()->withErrors([
                'email' => app()->getLocale()=='km'
                    ? 'មិនអាចផ្ញើអ៊ីមែលបាន! សូមពិនិត្យ Email ឡើងវិញ។'
                    : 'Failed to send email! Please check your email address.'
            ])->withInput();
        }

        // Store registration data in session
        session([
            'otp_register' => [
                'name'     => $request->name,
                'email'    => $request->email,
                'password' => $request->password,
                'phone'    => $request->phone,
            ]
        ]);

        return redirect()->route('register.otp')
            ->with('success', app()->getLocale()=='km'
                ? "បានផ្ញើ OTP ទៅ {$request->email} ហើយ!"
                : "OTP sent to {$request->email}!"
            );
    }

    // ── Show OTP verify page ───────────────────────────────
    public function showOtp()
    {
        if (!session('otp_register')) {
            return redirect()->route('register');
        }
        return view('auth.otp');
    }

    // ── Step 2: Verify OTP & Create Account ────────────────
    public function verifyOtp(Request $request)
    {
        $request->validate([
            'otp' => 'required|string|size:6',
        ], [
            'otp.required' => app()->getLocale()=='km' ? 'សូមបញ្ចូលលេខ OTP' : 'OTP is required.',
            'otp.size'     => app()->getLocale()=='km' ? 'OTP ត្រូវការ 6 ខ្ទង់' : 'OTP must be 6 digits.',
        ]);

        $data = session('otp_register');
        if (!$data) {
            return redirect()->route('register')
                ->withErrors(['otp' => app()->getLocale()=='km' ? 'Session ផុតកំណត់!' : 'Session expired!']);
        }

        // Find OTP
        $otpRecord = OtpCode::where('email', $data['email'])
            ->where('is_used', false)
            ->latest()
            ->first();

        if (!$otpRecord) {
            return back()->withErrors([
                'otp' => app()->getLocale()=='km' ? 'OTP មិនត្រឹមត្រូវ!' : 'Invalid OTP!'
            ]);
        }

        if ($otpRecord->isExpired()) {
            return back()->withErrors([
                'otp' => app()->getLocale()=='km' ? 'OTP ផុតកំណត់ហើយ! សូម Register ឡើងវិញ។' : 'OTP has expired! Please register again.'
            ]);
        }

        if ($otpRecord->code !== $request->otp) {
            return back()->withErrors([
                'otp' => app()->getLocale()=='km' ? 'លេខ OTP មិនត្រឹមត្រូវ!' : 'Incorrect OTP code!'
            ]);
        }

        // Mark OTP as used
        $otpRecord->update(['is_used' => true]);

        // Create user
        $user = User::create([
            'name'      => $data['name'],
            'email'     => $data['email'],
            'password'  => Hash::make($data['password']),
            'phone'     => $data['phone'] ?? null,
            'role'      => 'Customer',
            'is_active' => true,
        ]);

        // Clear session
        session()->forget('otp_register');

        Auth::login($user);

        return redirect()->route('home')->with('success',
            app()->getLocale()=='km'
                ? 'ស្វាគមន៍មក RoboTech Academy! គណនីបានបង្កើតដោយជោគជ័យ!'
                : 'Welcome to RoboTech Academy! Your account has been created.'
        );
    }

    // ── Resend OTP ─────────────────────────────────────────
    public function resendOtp(Request $request)
    {
        $data = session('otp_register');
        if (!$data) {
            return redirect()->route('register');
        }

        // Delete old OTP
        OtpCode::where('email', $data['email'])->delete();

        // New OTP
        $otp = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        OtpCode::create([
            'email'      => $data['email'],
            'code'       => $otp,
            'expires_at' => now()->addMinutes(10),
            'is_used'    => false,
        ]);

        try {
            Mail::to($data['email'])->send(new OtpMail($otp, $data['name']));
        } catch (\Exception $e) {
            return back()->withErrors([
                'otp' => app()->getLocale()=='km' ? 'មិនអាចផ្ញើ OTP បានទេ!' : 'Failed to resend OTP!'
            ]);
        }

        return back()->with('success',
            app()->getLocale()=='km' ? 'បានផ្ញើ OTP ថ្មីហើយ!' : 'New OTP sent!'
        );
    }

    // ── Handle logout ──────────────────────────────────────
    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('success',
            app()->getLocale()=='km' ? 'បានចេញដោយជោគជ័យ!' : 'Logged out successfully.'
        );
    }

    // ── Google OAuth ───────────────────────────────────────
    public function redirectToGoogle()
    {
        return Socialite::driver('google')->redirect();
    }

    public function handleGoogleCallback()
    {
        try {
            $googleUser = Socialite::driver('google')->user();
        } catch (\Exception $e) {
            return redirect()->route('login')
                ->withErrors(['email' => app()->getLocale()=='km'
                    ? 'ការចូលដោយ Google បានបរាជ័យ'
                    : 'Google authentication failed.']);
        }

        $user = User::where('email', $googleUser->getEmail())->first();

        if ($user) {
            if (!$user->is_active) {
                return redirect()->route('login')
                    ->withErrors(['email' => app()->getLocale()=='km'
                        ? 'គណនីរបស់អ្នកត្រូវបានបិទ!'
                        : 'Your account has been deactivated.']);
            }
            Auth::login($user);
            if ($user->isAdmin() || $user->isStaff()) {
                return redirect()->route('admin.dashboard');
            }
            return redirect()->route('home')->with('success',
                app()->getLocale()=='km' ? 'ស្វាគមន៍ត្រឡប់មកវិញ!' : 'Welcome back!'
            );
        }

        $user = User::create([
            'name'      => $googleUser->getName(),
            'email'     => $googleUser->getEmail(),
            'password'  => Hash::make(Str::random(32)),
            'role'      => 'Customer',
            'is_active' => true,
        ]);

        Auth::login($user);

        return redirect()->route('home')->with('success',
            app()->getLocale()=='km'
                ? 'ស្វាគមន៍មក RoboTech Academy!'
                : 'Welcome to RoboTech Academy!'
        );
    }
}