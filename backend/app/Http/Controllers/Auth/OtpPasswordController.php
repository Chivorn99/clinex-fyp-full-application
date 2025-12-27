<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Mail\SendOtpMail;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Log;

class OtpPasswordController extends Controller
{
    public function showRequestForm()
    {
        return view('auth.forgot-password-otp');
    }

    public function sendOtp(Request $request)
    {
        $request->validate(['email' => 'required|email|exists:users']);
        $code = random_int(100000, 999999);
        $expiresAt = Carbon::now()->addMinutes(10);

        DB::table('password_otps')->updateOrInsert(
            ['email' => $request->email],
            ['code' => $code, 'expires_at' => $expiresAt, 'updated_at' => now()]
        );

        Mail::to($request->email)
            ->send(new SendOtpMail($code, $expiresAt));

        return redirect()
            ->route('password.verify.otp', ['email' => $request->email])
            ->with('status', 'OTP sent! Check your email.');
    }

    /**
     * Verify the OTP code provided by the user.
     */
    public function verifyOtp(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:users',
            'code' => 'required|digits:6',
        ]);

        $otp = DB::table('password_otps')
            ->where('email', $request->email)
            ->where('code', $request->code)
            ->where('expires_at', '>', now())
            ->first();

        if (!$otp) {
            return back()->withErrors(['code' => 'Invalid or expired code']);
        }

        return redirect()->route('password.reset.otp', ['email' => $request->email, 'code' => $request->code]);
    }

    /**
     * Resend OTP to user's email.
     */
    public function resendOtp(Request $request)
    {
        $request->validate(['email' => 'required|email|exists:users']);
        $code = random_int(100000, 999999);
        $expiresAt = Carbon::now()->addMinutes(10);

        DB::table('password_otps')->updateOrInsert(
            ['email' => $request->email],
            [
                'code' => $code,
                'expires_at' => $expiresAt,
                'created_at' => now(),
                'updated_at' => now()
            ]
        );

        Mail::to($request->email)
            ->send(new SendOtpMail($code, $expiresAt));

        return redirect()->route('password.verify.otp', ['email' => $request->email])
            ->with('status', 'New OTP sent! Check your email.');
    }

    /**
     * Show the reset password form after OTP verification.
     */
    public function showResetForm(Request $request)
    {
        return view('auth.reset-password-otp', [
            'email' => $request->email,
            'code' => $request->code
        ]);
    }

    public function resetPassword(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:users',
            'code' => 'required|digits:6',
            'password' => 'required|confirmed|min:8',
        ]);

        $otp = DB::table('password_otps')
            ->where('email', $request->email)
            ->where('code', $request->code)
            ->where('expires_at', '>', now())
            ->first();

        if (!$otp) {
            return back()->withErrors(['code' => 'Invalid or expired code']);
        }

        User::where('email', $request->email)
            ->update(['password' => Hash::make($request->password)]);

        DB::table('password_otps')->where('email', $request->email)->delete();

        return redirect()->route('login')->with('status', 'Password updated!');
    }

    /**
     * Send OTP for password reset (API).
     */
    public function sendOtpApi(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email|exists:users,email',
        ]);

        try {
            $code = random_int(100000, 999999);
            $expiresAt = Carbon::now()->addMinutes(10);

            // Store OTP in database
            DB::table('password_otps')->updateOrInsert(
                ['email' => $request->email],
                [
                    'code' => $code,
                    'expires_at' => $expiresAt,
                    'created_at' => now(),
                    'updated_at' => now()
                ]
            );

            // Send OTP via email
            Mail::to($request->email)->send(new SendOtpMail($code, $expiresAt));

            Log::info("OTP sent to {$request->email}", ['code' => $code]); // Remove in production

            return response()->json([
                'success' => true,
                'message' => 'OTP sent successfully. Please check your email.',
                'data' => [
                    'email' => $request->email,
                    'expires_at' => $expiresAt->toDateTimeString(),
                    'expires_in_minutes' => 10
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to send OTP', [
                'email' => $request->email,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to send OTP. Please try again.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Verify OTP and reset password (API).
     */
    public function resetPasswordApi(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email|exists:users,email',
            'code' => 'required|digits:6',
            'password' => 'required|string|min:8|confirmed',
        ]);

        try {
            // Find valid OTP
            $otp = DB::table('password_otps')
                ->where('email', $request->email)
                ->where('code', $request->code)
                ->where('expires_at', '>', now())
                ->first();

            if (!$otp) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid or expired OTP code.',
                    'errors' => [
                        'code' => ['The provided OTP code is invalid or has expired.']
                    ]
                ], 422);
            }

            // Update user password
            $user = User::where('email', $request->email)->first();
            $user->update([
                'password' => Hash::make($request->password),
                'email_verified_at' => now() // Verify email if not already verified
            ]);

            // Delete used OTP
            DB::table('password_otps')->where('email', $request->email)->delete();

            // Optionally revoke all existing tokens for security
            $user->tokens()->delete();

            Log::info("Password reset successful for {$request->email}");

            return response()->json([
                'success' => true,
                'message' => 'Password has been reset successfully.',
                'data' => [
                    'email' => $request->email,
                    'reset_at' => now()->toDateTimeString()
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Password reset failed', [
                'email' => $request->email,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to reset password. Please try again.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Verify OTP without resetting password (for validation).
     */
    public function verifyOtpApi(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email|exists:users,email',
            'code' => 'required|digits:6',
        ]);

        $otp = DB::table('password_otps')
            ->where('email', $request->email)
            ->where('code', $request->code)
            ->where('expires_at', '>', now())
            ->first();

        if (!$otp) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired OTP code.',
                'errors' => [
                    'code' => ['The provided OTP code is invalid or has expired.']
                ]
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'OTP verified successfully.',
            'data' => [
                'email' => $request->email,
                'verified_at' => now()->toDateTimeString()
            ]
        ]);
    }

    /**
     * Get OTP for testing purposes (DEVELOPMENT ONLY).
     */
    public function getOtpForTesting(Request $request): JsonResponse
    {
        // Only allow in non-production environments
        if (app()->environment('production')) {
            return response()->json([
                'success' => false,
                'message' => 'This endpoint is not available in production.'
            ], 403);
        }

        $request->validate([
            'email' => 'required|email|exists:users,email',
        ]);

        try {
            // Get the latest OTP for the email
            $otp = DB::table('password_otps')
                ->where('email', $request->email)
                ->orderBy('created_at', 'desc')
                ->first();

            if (!$otp) {
                return response()->json([
                    'success' => false,
                    'message' => 'No OTP found for this email.',
                    'data' => [
                        'email' => $request->email,
                        'suggestion' => 'Send an OTP first using POST /api/password/otp-request'
                    ]
                ], 404);
            }

            $isExpired = Carbon::parse($otp->expires_at)->isPast();

            return response()->json([
                'success' => true,
                'message' => 'OTP retrieved successfully (TESTING ONLY).',
                'data' => [
                    'email' => $request->email,
                    'code' => $otp->code,
                    'expires_at' => $otp->expires_at,
                    'created_at' => $otp->created_at,
                    'is_expired' => $isExpired,
                    'expires_in_seconds' => $isExpired ? 0 : Carbon::parse($otp->expires_at)->diffInSeconds(now()),
                    'status' => $isExpired ? 'expired' : 'active'
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to retrieve OTP for testing', [
                'email' => $request->email,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve OTP.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get all OTPs for testing purposes (DEVELOPMENT ONLY).
     */
    public function getAllOtpsForTesting(): JsonResponse
    {
        // Only allow in non-production environments
        if (app()->environment('production')) {
            return response()->json([
                'success' => false,
                'message' => 'This endpoint is not available in production.'
            ], 403);
        }

        try {
            $otps = DB::table('password_otps')
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function ($otp) {
                    $isExpired = Carbon::parse($otp->expires_at)->isPast();
                    return [
                        'email' => $otp->email,
                        'code' => $otp->code,
                        'expires_at' => $otp->expires_at,
                        'created_at' => $otp->created_at,
                        'is_expired' => $isExpired,
                        'status' => $isExpired ? 'expired' : 'active'
                    ];
                });

            return response()->json([
                'success' => true,
                'message' => 'All OTPs retrieved successfully (TESTING ONLY).',
                'data' => [
                    'otps' => $otps,
                    'total_count' => $otps->count(),
                    'active_count' => $otps->where('status', 'active')->count(),
                    'expired_count' => $otps->where('status', 'expired')->count()
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to retrieve all OTPs for testing', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve OTPs.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
