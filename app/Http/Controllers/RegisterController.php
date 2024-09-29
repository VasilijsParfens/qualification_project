<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rules;
use Illuminate\Support\Facades\RateLimiter;

class RegisterController extends Controller
{
    // Show the registration form
    public function showRegistrationForm()
    {
        return view('auth.register'); // Return the view for the registration form
    }

    // Handle the registration process
    public function register(Request $request)
    {
        // Create a unique key based on the user's IP address for rate limiting
        $key = 'register.' . $request->ip();

        // Check if the user has exceeded the rate limit for registration attempts
        if (RateLimiter::tooManyAttempts($key, 5)) {
            return back()->with('error', 'Too many registration attempts. Please try again later.'); // Return an error if rate limit exceeded
        }

        // Increment the number of attempts made by this IP address
        RateLimiter::hit($key, 60); // Allow 5 attempts per minute

        // Validate user input
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255', // Name is required, must be a string, and has a maximum length of 255 characters
            'email' => 'required|string|email|max:255|unique:users,email', // Email is required, must be a valid email format, unique in the users table
            'password' => [
                'required',
                'string',
                'min:8', // Minimum length of 8 characters
                'confirmed', // The password must be confirmed (match the confirmation input)
                Rules\Password::min(8)->mixedCase()->numbers()->symbols()->uncompromised(), // Password complexity requirements
            ],
        ]);

        // Handle validation failure
        if ($validator->fails()) {
            // Log the validation errors for debugging purposes
            Log::warning('Registration validation failed', [
                'email' => $request->email, // Log the attempted email
                'errors' => $validator->errors(), // Log the validation errors
            ]);
            return back()->withErrors($validator)->withInput(); // Redirect back with errors and old input
        }

        // Attempt to create a new user with the validated data
        try {
            $user = User::create([
                'name' => strip_tags($request->name), // Sanitize the name input to remove any HTML tags
                'email' => filter_var($request->email, FILTER_SANITIZE_EMAIL), // Sanitize the email input
                'password' => Hash::make($request->password), // Hash the password for secure storage
            ]);

            // Log the successful registration
            Log::info('User registered successfully', ['email' => $request->email]); // Log the registered email

            // Log the user in and redirect them to the home page (optional)
            auth()->login($user); // Automatically log the user in after registration

            return redirect('/')->with('success', 'Registration successful!'); // Redirect with a success message
        } catch (\Exception $e) {
            // Log any exception that occurs during the registration process
            Log::error('Registration failed', [
                'email' => $request->email, // Log the email associated with the failed registration
                'message' => $e->getMessage(), // Log the exception message
            ]);
            return back()->with('error', 'Registration failed. Please try again.'); // Redirect back with an error message
        }
    }
}
