<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class AdminMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!Auth::check()) {
            return redirect()->route('login');
        }

        $user = Auth::user();

        if (!$user->is_active) {
            Auth::logout();
            return redirect()->route('login')->with('error',
                app()->getLocale()=='km'
                    ? 'គណនីរបស់អ្នកត្រូវបានបិទ។'
                    : 'Your account has been deactivated.'
            );
        }

        if (!$user->canAccessAdmin()) {
            if ($request->expectsJson()) {
                return response()->json(['status' => 'error', 'message' => 'Unauthorized.'], 403);
            }
            return redirect()->route('home')->with('error',
                app()->getLocale()=='km'
                    ? 'អ្នកមិនមានសិទ្ធចូលប្រើផ្ទាំងនេះ។'
                    : 'Access denied.'
            );
        }

        return $next($request);
    }
}