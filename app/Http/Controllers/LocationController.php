<?php

namespace App\Http\Controllers;

use App\Http\Traits\ApiResponseFormatter;
use App\Models\Province;
use Faker\Provider\Base;
use Illuminate\Http\Request;

class LocationController extends BaseController
{
    use ApiResponseFormatter;

    public function provinces(Request $request)
    {
        return $this->paginateData(
            $request,
            Province::query(),
            allowedSort: ['id', 'name'],
            allowedRelations: [],
            searchableColumns: ['name'],
            filterableColumns: []
        );
    }
}
