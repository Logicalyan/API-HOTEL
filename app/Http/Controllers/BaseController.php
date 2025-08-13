<?php

namespace App\Http\Controllers;

use App\Http\Traits\ApiResponseFormatter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class BaseController extends Controller
{
    use ApiResponseFormatter;

    protected function paginateData(
    Request $request,
    Builder $query,
    array $allowedSort = ['id'],
    array $allowedRelations = [],
    array $searchableColumns = [],
    array $filterableColumns = []
) {
    // Search
    if ($request->filled('search') && !empty($searchableColumns)) {
        $keyword = $request->input('search');
        $query->where(function ($q) use ($keyword, $searchableColumns) {
            foreach ($searchableColumns as $column) {
                $q->orWhere($column, 'LIKE', "%{$keyword}%");
            }
        });
    }

    // Filter foreign key
    foreach ($filterableColumns as $column) {
        if ($request->filled($column)) {
            $query->where($column, $request->input($column));
        }
    }

    // Eager loading
    if ($request->filled('with')) {
        $relations = array_intersect(
            explode(',', $request->input('with')),
            $allowedRelations
        );
        $query->with($relations);
    }

    // Sorting
    $sortBy = $request->input('sort_by', 'id');
    $sortDir = $request->input('sort_dir', 'asc');
    if (in_array($sortBy, $allowedSort)) {
        $query->orderBy($sortBy, $sortDir === 'desc' ? 'desc' : 'asc');
    }

    // Pagination
    $perPage = $request->input('per_page', 10);
    $perPage = max(1, min(100, (int) $perPage));
    $data = $query->paginate($perPage);

    return $this->successResponse($data);
}

}
