<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\RateLimiter;

class LoginController extends Controller
{
    // Show the login form
    public function showLoginForm()
    {
        return view('auth.login');
    }

    // Handle login
    public function login(Request $request)
    {
        // Check if the rate limit has been exceeded
        if (RateLimiter::tooManyAttempts('login:' . $request->ip(), 5)) {
            return back()->with('error', 'Too many login attempts. Please try again later.');
        }

        // Log the incoming request data
        Log::info('Login attempt', ['email' => $request->email, 'ip' => $request->ip()]);

        // Validate user input
        $validator = Validator::make($request->all(), [
            'email' => 'required|string|email|max:255',
            'password' => 'required|string|min:8',
        ]);

        // Handle validation failure
        if ($validator->fails()) {
            Log::warning('Login validation failed', [
                'email' => $request->email,
                'errors' => $validator->errors(),
            ]);
            return back()->withErrors($validator)->withInput();
        }

        // Attempt to log the user in
        if (Auth::attempt(['email' => $request->email, 'password' => $request->password], $request->filled('remember'))) {
            // Log the successful login
            Log::info('User logged in successfully', ['email' => $request->email, 'ip' => $request->ip()]);

            // Clear the rate limiter
            RateLimiter::clear('login:' . $request->ip());

            // Redirect to intended page
            return redirect()->intended('/')->with('success', 'Login successful!');
        }

        // Log the failed login attempt
        Log::warning('Login failed', [
            'email' => $request->email,
            'ip' => $request->ip(),
        ]);

        // Increment the rate limiter
        RateLimiter::hit('login:' . $request->ip());

        return back()->with('error', 'Invalid credentials. Please try again.');
    }


    // Handle logout
    public function logout(Request $request)
    {
        Log::info('User logged out', ['email' => Auth::user()->email]);

        Auth::logout();

        return redirect('/')->with('success', 'You have been logged out.');
    }
}
