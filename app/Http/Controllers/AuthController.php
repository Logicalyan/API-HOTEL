<?php

namespace App\Http\Controllers;

use App\Http\Traits\AuthResponses;
use App\Mail\ResetPasswordRequestMail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

class AuthController extends Controller
{
    use AuthResponses;

    public function register(Request $request)
    {
        try {
            $request->validate([
                'name' => ['required', 'string', 'max:255'],
                'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
                'password' => [
                    'required',
                    'string',
                    Password::min(8)
                        ->letters()
                        ->mixedCase()
                        ->numbers()
                        ->symbols()
                ],
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
                'roles' => $user->getRoleNames(),
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
                'roles' => $request->user()->getRoleNames(),
            ],
            'User profile fetched successfully.',
            200
        );
    }

    public function resetPasswordRequest(Request $request)
    {
        // Implementasi logika untuk permintaan reset password

        $request->validate([
            'email' => 'required|string|email',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return $this->authErrorResponse('Reset Password Failed', 'User not found.', [], 404);
        }

        $code = rand(100000, 999999);
        $user->otp_code = $code;

        if ($user->save()) {
            $emailData = array(
                'heading' => 'Reset Password',
                'name' => $user->name,
                'email' => $user->email,
                'code' => $user->otp_code,
            );

            Mail::to($emailData['email'])->send(new ResetPasswordRequestMail($emailData));
            return $this->authSuccessResponse(
                null,
                'Reset password code sent successfully.',
                200
            );
        } else {
            return $this->authErrorResponse('Reset Password Failed', 'Failed to send reset password code.', [], 500);
        }
    }

    public function resetPassword(Request $request)
    {
        // Implementasi logika untuk reset password

        $request->validate([
            'email' => 'required|string|email',
            'code' => 'required|integer',
            'new_password' => [
                'required',
                Password::min(8)
                    ->letters()
                    ->mixedCase()
                    ->numbers()
                    ->symbols()
            ],
        ]);

        $user = User::where('email', $request->email)->where('otp_code', $request->code)->first();

        if (!$user) {
            return $this->authErrorResponse('Reset Password Failed', 'User not found.', [], 404);
        }

        $user->password = Hash::make($request->new_password);
        $user->otp_code = null;

        if ($user->save()) {
            return $this->authSuccessResponse(
                null,
                'Password reset successfully.',
                200
            );
        } else {
            return $this->authErrorResponse('Reset Password Failed', 'Failed to reset password.', [], 500);
        }
    }
}
