<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    // ✅ GET ALL USERS
    public function index()
    {
        $users = User::with('roles')->get();
        return response()->json($users);
    }

    // ✅ GET SINGLE USER
    public function show($id)
    {
        $user = User::with('roles')->findOrFail($id);
        return response()->json($user);
    }

    // ✅ CREATE NEW USER (dengan role)
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required',
            'email' => 'required|email|unique:users',
            'password' => 'required|min:6',
            'role' => 'nullable|string' // optional assign role
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => bcrypt($validated['password']),
        ]);

        if (!empty($validated['role'])) {
            $user->assignRole($validated['role']);
        }

        return response()->json([
            'message' => 'User created',
            'user' => $user
        ], 201);
    }

    // ✅ UPDATE USER
    public function update(Request $request, $id)
    {
        $user = User::findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|string',
            'email' => 'sometimes|email|unique:users,email,' . $id,
            'password' => 'sometimes|min:6',
            'role' => 'nullable|string',
        ]);

        if (isset($validated['name'])) $user->name = $validated['name'];
        if (isset($validated['email'])) $user->email = $validated['email'];
        if (isset($validated['password'])) $user->password = bcrypt($validated['password']);

        $user->save();

        if (!empty($validated['role'])) {
            $user->syncRoles([$validated['role']]); // replace existing roles
        }

        return response()->json([
            'message' => 'User updated',
            'user' => $user,
        ]);
    }

    // ✅ DELETE USER
    public function destroy($id)
    {
        $user = User::findOrFail($id);
        $user->delete();

        return response()->json(['message' => 'User deleted']);
    }
}
