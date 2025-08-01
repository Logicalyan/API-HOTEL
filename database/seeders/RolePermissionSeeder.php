<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolePermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Step 1: Permissions
        $permissions = [
            'view dashboard',
            'edit user',
            'delete user',
            'create post',
            'edit post',
            'delete post',
        ];

        foreach ($permissions as $perm) {
            Permission::firstOrCreate(['name' => $perm]);
        }

        // Step 2: Roles
        $adminRole = Role::firstOrCreate(['name' => 'admin']);
        $adminRole->syncPermissions($permissions);

        $editorRole = Role::firstOrCreate(['name' => 'editor']);
        $editorRole->syncPermissions([
            'create post',
            'edit post',
            'delete post',
        ]);

        $userRole = Role::firstOrCreate(['name' => 'user']);
        $userRole->syncPermissions(['view dashboard']);

        // ✅ Step 3: Create default admin user
        $admin = User::firstOrCreate(
            ['email' => 'admin@test'],
            [
                'name' => 'Super Admin',
                'password' => Hash::make('admin123'), // default password
            ]
        );

        $admin->assignRole('admin');
    }
}
