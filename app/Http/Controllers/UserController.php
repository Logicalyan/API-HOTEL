<?php

namespace App\Http\Controllers;

use App\Http\Resources\UserResource;
use App\Http\Traits\ApiResponseFormatter;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    use ApiResponseFormatter;

    public function index(Request $request)
    {
        //1.Pengaturan Query
        $usersQuery = User::query();

        $usersQuery->with('roles');

        // 2. Pengaturan Relasi
        // Tambahkan relasi dengan tabel `model_has_roles`
        $usersQuery->leftJoin('model_has_roles', function ($join) {
            $join->on('users.id', '=', 'model_has_roles.model_id')
                ->where('model_has_roles.model_type', '=', User::class); // Penting untuk filter hanya user
        })
            ->leftJoin('roles', 'model_has_roles.role_id', '=', 'roles.id');

        // 3. Pengaturan Pencarian
        if ($request->has('search') && $request->input('search')) {
            $searchTerm = $request->input('search');
            $usersQuery->where(function (Builder $query) use ($searchTerm) {
                $query->where('users.name', 'like', '%' . $searchTerm . '%')
                    ->orWhere('users.email', 'like', '%' . $searchTerm . '%')
                    ->orWhere('roles.name', 'like', '%' . $searchTerm . '%'); // Cari juga di nama role
            });
        }

        // 4. Pengaturan Filter
        if ($request->has('role') && $request->input('role')) {
            $roleName = $request->input('role');
            // Pastikan role yang diminta ada di tabel `roles`
            $roleExists = Role::where('name', $roleName)->exists();
            if ($roleExists) {
                $usersQuery->where('roles.name', $roleName);
            } else {
                return $this->errorResponse('Role not found', 404);
            }
        }

        // 5. Pengaturan Pengurutan
        $sortBy = $request->input('sortBy', 'created_at'); // Kolom default
        $sortOrder = $request->input('sortOrder', 'desc'); // Urutan default (asc/desc)

        // Kolom yang diizinkan untuk diurutkan
        $allowedSortColumns = ['name', 'email', 'created_at', 'role_name']; // 'role_name' adalah alias untuk roles.name

        if (!in_array($sortBy, $allowedSortColumns)) {
            $sortBy = 'created_at'; // Fallback ke default jika kolom tidak valid
        }

        // Validasi urutan (asc/desc)
        if (!in_array(strtolower($sortOrder), ['asc', 'desc'])) {
            $sortOrder = 'desc'; // Fallback ke default jika urutan tidak valid
        }

        // Logika pengurutan berdasarkan `sortBy`
        if ($sortBy === 'role_name') {
            // Urutkan berdasarkan kolom 'name' dari tabel 'roles'
            $usersQuery->orderBy('roles.name', $sortOrder);
        } else {
            // Urutkan berdasarkan kolom yang ada di tabel 'users'
            $usersQuery->orderBy('users.' . $sortBy, $sortOrder);
        }

        // 6. Pilih Kolom Asli User dan Terapkan Paginasi
        // Penting: Pilih kembali semua kolom dari tabel 'users' untuk menghindari konflik atau kehilangan data
        $usersQuery->select('users.*');

        // Minimal 10 data awal, tapi bisa diatur melalui query parameter 'per_page'
        $perPage = $request->input('per_page', 10);
        $perPage = max(1, min(100, (int)$perPage)); // Batasi antara 1 sampai 100 data per halaman

        $users = $usersQuery->paginate($perPage);

        // 7. Kembalikan respons menggunakan trait ApiResponseFormatter
        // Trait ini akan otomatis menambahkan 'meta' untuk paginasi
        return $this->successResponse(
            $users,
            'Users retrieved successfully with filters.',
            200
        );
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
        // 1. Validasi Input
        $validatedData = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => [
                'required',
                'string',
                Password::min(8)
                    ->letters()->mixedCase()->numbers()->symbols()
            ], // 'confirmed' membutuhkan password_confirmation
            'roles' => ['nullable', 'array'], // Roles adalah array nama role
            'roles.*' => ['string', Rule::exists('roles', 'name')], // Setiap role harus ada di tabel roles berdasarkan namanya
        ]);


        // --- Mulai Database Transaction ---
        DB::beginTransaction();

        try {
            // 2. Buat User Baru
            $user = User::create([
                'name' => $validatedData['name'],
                'email' => $validatedData['email'],
                'password' => Hash::make($validatedData['password']),
            ]);

            // 3. Berikan Role kepada User (jika ada)
            if (isset($validatedData['roles'])) {
                $user->assignRole($validatedData['roles']);
            } else {
                // Opsional: Berikan role default jika tidak ada role yang dipilih
                // $user->assignRole('user');
            }


            // 4. Kembalikan Respons Sukses
            // Eager load roles jika Anda ingin mengembalikannya di respons
            $user->load('roles'); // Load relasi roles
            // Tambahkan role_names jika menggunakan accessor di model
            $user->append('role_names'); // Append accessor jika tidak appends secara default

            // --- Selesai Database Transaction ---
            DB::commit();

            return $this->successResponse(
                $user,
                'User created successfully.',
                201 // Status code 201 Created
            );
        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            // Tangani error validasi (sudah otomatis oleh Laravel jika pakai $request->validate)
            return $this->errorResponse(
                'Validation Failed',
                'Validation Error',
                [$e->errors()],
                422 // Status code 422 Unprocessable Entity
            );
        } catch (\Exception $e) {
            // Rollback transaksi jika ada kesalahan di database
            DB::rollBack();
            // Tangani error umum lainnya
            return $this->errorResponse(
                'Server Error',
                'Failed to create user',
                [$e->getMessage()],
                500
            );
        }
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
