<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use App\Models\Patient;

class ProfileController extends Controller
{
    /**
     * Display the user's profile form.
     */
    public function edit(Request $request): View
    {
        return view('profile.edit', [
            'user' => $request->user(),
        ]);
    }

    /**
     * Get current user profile (API).
     */
    public function showUser(Request $request): JsonResponse
    {
        $user = $request->user();
        $user->makeHidden(['password', 'remember_token']);

        $user->profile_picture_url = $user->profile_pic ? Storage::disk('public')->url($user->profile_pic) : null;

        return response()->json([
            'success' => true,
            'user' => $user,
            'message' => 'Profile retrieved successfully'
        ]);
    }

    /**
     * Update the user's profile information (Web).
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        try {
            $data = $request->validated();
            $user = $request->user();

            // Handle profile picture upload
            if ($request->hasFile('profile_pic')) {
                if ($user->profile_pic && Storage::disk('public')->exists($user->profile_pic)) {
                    Storage::disk('public')->delete($user->profile_pic);
                }

                $file = $request->file('profile_pic');
                $filename = time() . '_' . $file->getClientOriginalName();
                $path = $file->storeAs('profile_pics', $filename, 'public');
                $data['profile_pic'] = $path;
            }

            if (!$user->isAdmin() && isset($data['role'])) {
                unset($data['role']);
            }

            $user->fill($data);

            if ($user->isDirty('email')) {
                $user->email_verified_at = now();
            }

            $user->save();

            return redirect()->route('profile.edit')->with('success', 'Profile updated successfully');

        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Failed to update profile: ' . $e->getMessage()])->withInput();
        }
    }

    /**
     * Update the user's profile information (API).
     */
    public function updateApi(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'string|max:255',
            'email' => ['string', 'lowercase', 'email', 'max:255', 'unique:users,email,' . $request->user()->id],
            'role' => 'sometimes|required|in:admin,lab_technician',
            'phone_number' => 'nullable|string|max:25',
            'specialization' => 'nullable|string|max:255',
            'profile_pic' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'remove_profile_pic' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $validatedData = $validator->validated();
            $user = $request->user();

            // Handle profile picture removal
            if ($request->boolean('remove_profile_pic')) {
                if ($user->profile_pic && Storage::disk('public')->exists($user->profile_pic)) {
                    Storage::disk('public')->delete($user->profile_pic);
                }
                $validatedData['profile_pic'] = null;
            }

            if ($request->hasFile('profile_pic')) {
                if ($user->profile_pic && Storage::disk('public')->exists($user->profile_pic)) {
                    Storage::disk('public')->delete($user->profile_pic);
                }

                $file = $request->file('profile_pic');
                $filename = time() . '_' . $file->getClientOriginalName();
                $path = $file->storeAs('profile_pics', $filename, 'public');
                $validatedData['profile_pic'] = $path;
            }
            if (!$user->isAdmin() && isset($validatedData['role'])) {
                unset($validatedData['role']);
            }

            $user->fill($validatedData);

            if ($user->isDirty('email')) {
                $user->email_verified_at = now();
            }

            $user->save();
            $user = $user->fresh();
            $user->makeHidden(['password', 'remember_token']);

            return response()->json([
                'success' => true,
                'data' => [
                    'user' => $user,
                    'profile_picture_url' => $user->profile_pic ? Storage::disk('public')->url($user->profile_pic) : null
                ],
                'message' => 'Profile updated successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update profile',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete the user's account (Web).
     */
    public function destroy(Request $request): RedirectResponse
    {
        $user = $request->user();
        if ($user->profile_pic && Storage::disk('public')->exists($user->profile_pic)) {
            Storage::disk('public')->delete($user->profile_pic);
        }

        Auth::logout();
        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->to('/');
    }

    /**
     * Delete user account (API).
     */
    public function destroyApi(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            if ($user->profile_pic && Storage::disk('public')->exists($user->profile_pic)) {
                Storage::disk('public')->delete($user->profile_pic);
            }
            $user->tokens()->delete();
            $user->delete();
            return response()->json([
                'success' => true,
                'message' => 'Account deleted successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete account',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
