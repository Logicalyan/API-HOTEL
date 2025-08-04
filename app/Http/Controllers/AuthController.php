<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use App\Http\Traits\AuthResponses;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use App\Mail\ResetPasswordRequestMail;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

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
        $user->otp_expires_at = now()->addMinutes(2);

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
                'Password reset code sent successfully. Please check your email.',
                200
            );
        } else {
            return $this->authErrorResponse('Reset Password Failed', 'Failed to send reset password code.', [], 500);
        }
    }

    public function verifyOTP(Request $request)
    {
        // Implementasi logika untuk verifikasi OTP

        $request->validate([
            'email' => 'required|string|email',
            'code' => 'required|integer',
        ]);

        $user = User::where('email', $request->email)->where('otp_code', $request->code)->first();

        if (!$user) {
            return $this->authErrorResponse('OTP Verification Failed', 'Invalid email or OTP code.', [], 401);
        }

        if ($user->otp_expires_at < now()) {
            // Hapus OTP setelah kadaluarsa atau digunakan
            $user->otp_code = null;
            $user->otp_expires_at = null;
            $user->save();
            return $this->authErrorResponse('OTP Verification Failed', 'OTP has expired.', [], 400);
        }


        // OTP valid. Hapus OTP dari tabel users karena sudah digunakan.
        $user->otp_code = null;
        $user->otp_expires_at = null;
        $user->save();

        // Generate token reset password baru dan simpan di password_reset_tokens
        $token = Str::random(60); // Token acak 60 karakter

        // Hapus token lama untuk email ini jika ada
        DB::table('password_reset_tokens')->where('email', $user->email)->delete();

        DB::table('password_reset_tokens')->insert([
            'email' => $user->email,
            'token' => $token,
            'created_at' => now(),
        ]);

        return $this->authSuccessResponse(
            ['email' => $user->email, 'token' => $token], // <-- Kirim email dan token ke frontend
            'OTP verified successfully. You can now reset your password.',
            200
        );
    }

    public function resetPassword(Request $request)
    {
        // Implementasi logika untuk reset password

        $request->validate([
            'email' => 'required|string|email',
            'token' => 'required|string',
            'new_password' => [
                'required',
                Password::min(8)
                    ->letters()
                    ->mixedCase()
                    ->numbers()
                    ->symbols()
            ],
        ]);

        // Cari token di tabel password_reset_tokens
        $passwordReset = DB::table('password_reset_tokens')
            ->where('email', $request->email)
            ->where('token', $request->token)
            ->first();

        if (!$passwordReset) {
            return $this->authErrorResponse('Reset Password Failed', 'Invalid email or reset token.', [], 403); // 403 Forbidden
        }

        // Cek apakah token sudah kadaluarsa (misal 60 menit)
        if (now()->diffInMinutes($passwordReset->created_at) > 60) { // Token berlaku 60 menit
            DB::table('password_reset_tokens')->where('email', $request->email)->delete(); // Hapus token kadaluarsa
            return $this->authErrorResponse('Reset Password Failed', 'Reset token has expired.', [], 400);
        }

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return $this->authErrorResponse('Reset Password Failed', 'User not found.', [], 404);
        }

        $user->password = Hash::make($request->new_password);

        if ($user->save()) {
            // Hapus token dari tabel password_reset_tokens setelah berhasil reset
            DB::table('password_reset_tokens')->where('email', $request->email)->delete();

            return $this->authSuccessResponse(
                null,
                'Password reset successfully. You can now login with your new password.',
                200
            );
        } else {
            return $this->authErrorResponse('Reset Password Failed', 'Failed to reset password.', [], 500);
        }
    }
}
