<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;

class UserManagementController extends Controller
{
    /**
     * Display a listing of all users.
     *
     * @return \Illuminate\View\View
     */
    public function index()
    {
        // Fetch all users with pagination
        $users = User::orderBy('created_at', 'desc')->paginate(10);

        return view('admin.users.index', compact('users'));
    }

    /**
     * Display the specified user.
     *
     * @param  int  $id
     * @return \Illuminate\View\View
     */
    public function show($id)
    {
        $user = User::findOrFail($id);

        return view('admin.users.show', compact('user'));
    }

    /**
     * Update the user role.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\RedirectResponse
     */
    public function updateRole(Request $request, $id)
    {
        $user = User::findOrFail($id);

        // Validate the incoming request
        $validated = $request->validate([
            'role' => 'required|in:admin,user,lab_technician',
        ]);

        // Update user role
        $user->update(['role' => $validated['role']]);

        return redirect()->route('users.index')->with('success', "User {$user->name}'s role updated to {$validated['role']}");
    }

    /**
     * Remove the specified user from the database.
     *
     * @param  int  $id
     * @return \Illuminate\Http\RedirectResponse
     */
    public function destroy($id)
    {
        // Check if this is the current user
        if ((int) $id === request()->user()->getKey()) {
            return back()->with('error', 'You cannot delete your own account.');
        }

        $user = User::findOrFail($id);
        $userName = $user->name;

        // Delete user
        $user->delete();

        return redirect()->route('users.index')->with('success', "User {$userName} has been deleted successfully.");
    }
}
