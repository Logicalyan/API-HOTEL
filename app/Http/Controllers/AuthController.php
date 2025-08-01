<?php

namespace App\Http\Controllers;

use App\Http\Traits\AuthResponses;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

class AuthController extends Controller
{
    use AuthResponses;

    public function me(Request $request)
    {
        $user = $request->user();

        return response()->json([
            'user' => $user,
            // 'roles' => $user->getRoleNames(),
        ]);
    }

    public function register(Request $request)
    {
        try {
            $request->validate([
                'name' => ['required', 'string', 'max:255'],
                'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
                'password' => ['required', 'string', 'min:8'],
            ]);
        } catch (ValidationException $e) {
            $formattedErrors = [];
            foreach ($e->errors() as $field => $messages) {
                $formattedErrors[] = [
                    'field' => $field,
                    'message' => $messages[0],
                ];
            }
            return $this->authErrorResponse(
                'Validation Failed',
                'The provided data is invalid.',
                $formattedErrors,
                422
            );
        }

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        // --- Bagian Penting: Memberikan Role 'user' secara otomatis ---
        $userRole = Role::where('name', 'user')->first();
        if ($userRole) {
            $user->assignRole($userRole);
        } else {
            // Opsional: Buat role 'user' jika belum ada (hanya jika Anda tidak menggunakan seeder)
            // Atau log error jika role tidak ditemukan, karena ini harusnya dibuat via seeder
            $user->assignRole(Role::firstOrCreate(['name' => 'user']));
        }
        // -----------------------------------------------------------------

        $token = $user->createToken('auth_token')->plainTextToken;

        return $this->authSuccessResponse(
            [
                'user' => $user->only('id', 'name', 'email'),
                'token' => $token,
                'roles' => $user->getRoleNames(), // Opsional: kirim juga role-nya ke frontend
            ],
            'User registered successfully.',
            201
        );
    }

    public function login(Request $request)
    {
        try {
            $request->validate([
                'email' => ['required', 'string', 'email'],
                'password' => ['required', 'string'],
            ]);
        } catch (ValidationException $e) {
            $formattedErrors = [];
            foreach ($e->errors() as $field => $messages) {
                $formattedErrors[] = ['field' => $field, 'message' => $messages[0]];
            }
            return $this->authErrorResponse(
                'Validation Failed',
                'Please check your email and password.',
                $formattedErrors,
                422
            );
        }

        // Coba autentikasi pengguna
        if (!Auth::attempt($request->only('email', 'password'))) {
            return $this->authErrorResponse(
                'Authentication Failed',
                'Invalid credentials.',
                [],
                401 // Status code 401 Unauthorized
            );
        }

        $user = Auth::user();

        // Hapus token lama jika ada, lalu buat yang baru
        $user->tokens()->delete(); // Opsional: hapus token sebelumnya untuk user ini
        $token = $user->createToken('auth_token')->plainTextToken;

        return $this->authSuccessResponse(
            [
                'user' => $user->only('id', 'name', 'email'),
                'token' => $token,
            ],
            'Logged in successfully.',
            200
        );
    }

    public function logout(Request $request)
    {
        // Pastikan user terautentikasi sebelum logout
        if (!Auth::check()) {
            return $this->authErrorResponse(
                'Unauthorized',
                'No authenticated user to log out.',
                [],
                401
            );
        }

        // Hapus token saat ini dari user yang terautentikasi
        Auth::user()->currentAccessToken()->delete();

        return $this->authSuccessResponse(
            null, // Data null karena tidak ada yang perlu dikembalikan setelah logout
            'Logged out successfully.',
            200
        );
    }

    public function user(Request $request)
    {
        return $this->authSuccessResponse(
            [
                'user' => $request->user()->only('id', 'name', 'email'),
                'role' => $request->user()->getRoleNames(),
            ],
            'User profile fetched successfully.',
            200
        );
    }
}
