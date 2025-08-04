<?php

namespace App\Http\Controllers;

use App\Http\Traits\ApiResponseFormatter;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    use ApiResponseFormatter;

    public function index() {
        $roles = Role::all(['id', 'name']); // Hanya ambil ID dan nama role

        return $this->successResponse(
            $roles,
            'Roles retrieved successfully.',
            200
        );
    }
}
