<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Mail;
use App\Mail\UserInvitationMail;

class UserController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        try {
            $query = User::query();

            // Apply filters
            if ($request->filled('role')) {
                $query->where('role', $request->role);
            }

            if ($request->filled('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phone_number', 'like', "%{$search}%");
                });
            }

            // Pagination
            $perPage = $request->get('per_page', 15);
            $users = $query->latest()->paginate($perPage);

            // Hide sensitive data
            $users->getCollection()->makeHidden(['password', 'remember_token']);

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'data' => $users,
                    'message' => 'Users retrieved successfully'
                ]);
            }

            return view('users.index', compact('users'));

        } catch (\Exception $e) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to retrieve users',
                    'error' => $e->getMessage()
                ], 500);
            }

            return back()->withErrors(['error' => 'Failed to retrieve users']);
        }
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(Request $request)
    {
        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'data' => [
                    'roles' => ['admin', 'lab_technician']
                ]
            ]);
        }

        return view('admin.users.create-account');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'role' => 'required|in:admin,lab_technician',
            'phone_number' => 'nullable|string|max:20',
            'specialization' => 'nullable|string|max:255',
            'profile_pic' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048', // 2MB max
        ]);

        if ($validator->fails()) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            return back()->withErrors($validator)->withInput();
        }

        try {
            $userData = $validator->validated();
            $randomPassword = Str::random(12);
            $userData['password'] = Hash::make($randomPassword);

            // Handle profile picture upload
            if ($request->hasFile('profile_pic')) {
                $profilePicPath = $this->handleProfilePictureUpload($request->file('profile_pic'));
                $userData['profile_pic'] = $profilePicPath;
            }

            $user = User::create($userData);
            $user->makeHidden(['password', 'remember_token']);

            // Generate password reset token
            $token = app('auth.password.broker')->createToken($user);

            // Send invitation email
            Mail::to($user->email)->send(new UserInvitationMail($user, $token, $randomPassword));

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'data' => $user,
                    'message' => 'User created successfully'
                ], 201);
            }

            return redirect()->route('users.index')->with('success', 'User created and invitation email sent!');

        } catch (\Exception $e) {
            // Clean up uploaded file if user creation fails
            if (isset($profilePicPath) && Storage::disk('public')->exists($profilePicPath)) {
                Storage::disk('public')->delete($profilePicPath);
            }

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to create user',
                    'error' => $e->getMessage()
                ], 500);
            }

            return back()->withErrors(['error' => 'Failed to create user'])->withInput();
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, string $id)
    {
        try {
            $user = User::findOrFail($id);
            $user->makeHidden(['password', 'remember_token']);

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'data' => $user,
                    'message' => 'User retrieved successfully'
                ]);
            }

            return view('users.show', compact('user'));

        } catch (ModelNotFoundException $e) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not found'
                ], 404);
            }

            return abort(404);
        } catch (\Exception $e) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to retrieve user',
                    'error' => $e->getMessage()
                ], 500);
            }

            return back()->withErrors(['error' => 'Failed to retrieve user']);
        }
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Request $request, string $id)
    {
        try {
            $user = User::findOrFail($id);
            $user->makeHidden(['password', 'remember_token']);

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'data' => [
                        'user' => $user,
                        'roles' => ['admin', 'doctor', 'lab_technician', 'clinic_manager']
                    ]
                ]);
            }

            return view('users.edit', compact('user'));

        } catch (ModelNotFoundException $e) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not found'
                ], 404);
            }

            return abort(404);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        try {
            $user = User::findOrFail($id);

            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
                'password' => 'nullable|string|min:8|confirmed',
                'role' => 'required|in:admin,doctor,lab_technician,clinic_manager',
                'phone_number' => 'nullable|string|max:20',
                'specialization' => 'nullable|string|max:255',
                'profile_pic' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
                'remove_profile_pic' => 'boolean',
            ]);

            if ($validator->fails()) {
                if ($request->expectsJson()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Validation failed',
                        'errors' => $validator->errors()
                    ], 422);
                }

                return back()->withErrors($validator)->withInput();
            }

            $userData = $validator->validated();

            // Handle password update
            if (!empty($userData['password'])) {
                $userData['password'] = Hash::make($userData['password']);
            } else {
                unset($userData['password']);
            }

            // Handle profile picture removal
            if ($request->boolean('remove_profile_pic')) {
                if ($user->profile_pic && Storage::disk('public')->exists($user->profile_pic)) {
                    Storage::disk('public')->delete($user->profile_pic);
                }
                $userData['profile_pic'] = null;
            }

            // Handle profile picture upload
            if ($request->hasFile('profile_pic')) {
                // Delete old profile picture
                if ($user->profile_pic && Storage::disk('public')->exists($user->profile_pic)) {
                    Storage::disk('public')->delete($user->profile_pic);
                }

                $profilePicPath = $this->handleProfilePictureUpload($request->file('profile_pic'));
                $userData['profile_pic'] = $profilePicPath;
            }

            $user->update($userData);
            $user->makeHidden(['password', 'remember_token']);

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'data' => $user,
                    'message' => 'User updated successfully'
                ]);
            }

            return redirect()->route('users.show', $user->id)->with('success', 'User updated successfully');

        } catch (ModelNotFoundException $e) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not found'
                ], 404);
            }

            return abort(404);
        } catch (\Exception $e) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to update user',
                    'error' => $e->getMessage()
                ], 500);
            }

            return back()->withErrors(['error' => 'Failed to update user'])->withInput();
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, string $id)
    {
        try {
            $user = User::findOrFail($id);

            // Check if user can be deleted (e.g., not the current user)
            if (auth()->id() === $user->id) {
                if ($request->expectsJson()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'You cannot delete your own account'
                    ], 422);
                }

                return back()->withErrors(['error' => 'You cannot delete your own account']);
            }

            // Delete profile picture if exists
            if ($user->profile_pic && Storage::disk('public')->exists($user->profile_pic)) {
                Storage::disk('public')->delete($user->profile_pic);
            }

            $user->delete();

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'User deleted successfully'
                ]);
            }

            return redirect()->route('users.index')->with('success', 'User deleted successfully');

        } catch (ModelNotFoundException $e) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not found'
                ], 404);
            }

            return abort(404);
        } catch (\Exception $e) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to delete user',
                    'error' => $e->getMessage()
                ], 500);
            }

            return back()->withErrors(['error' => 'Failed to delete user']);
        }
    }

    /**
     * Get users by role.
     */
    public function getByRole(Request $request, string $role)
    {
        $validator = Validator::make(['role' => $role], [
            'role' => 'required|in:admin,doctor,lab_technician,clinic_manager'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid role',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $users = User::where('role', $role)
                ->select('id', 'name', 'email', 'role', 'specialization')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $users,
                'message' => "Users with role '{$role}' retrieved successfully"
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve users',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Handle profile picture upload.
     */
    private function handleProfilePictureUpload($file): string
    {
        $filename = time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
        $path = 'profile_pics/' . $filename;

        Storage::disk('public')->putFileAs('profile_pics', $file, $filename);

        return $path;
    }

    /**
     * Get user profile picture URL.
     */
    public function getProfilePicture(Request $request, string $id)
    {
        try {
            $user = User::findOrFail($id);

            if (!$user->profile_pic) {
                return response()->json([
                    'success' => false,
                    'message' => 'No profile picture found'
                ], 404);
            }

            $url = Storage::disk('public')->url($user->profile_pic);

            return response()->json([
                'success' => true,
                'data' => ['url' => $url],
                'message' => 'Profile picture URL retrieved successfully'
            ]);

        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'User not found'
            ], 404);
        }
    }
}
