<?php

// app/Http/Traits/ApiResponseFormatter.php
namespace App\Http\Traits;

use Illuminate\Http\JsonResponse;
use Illuminate\Pagination\LengthAwarePaginator;

trait ApiResponseFormatter
{
    protected function successResponse(mixed $data = null, ?string $message = 'Operation successful.', int $status = 200): JsonResponse
    {
        $response = [
            'data' => $data,
            'message' => $message,
            'error' => null,
            'errors' => [],
            'status' => $status,
        ];

        if ($data instanceof LengthAwarePaginator) {
            $response['data'] = $data->items();
            $response['meta'] = [
                'total' => $data->total(),
                'per_page' => $data->perPage(),
                'current_page' => $data->currentPage(),
                'last_page' => $data->lastPage(),
                'from' => $data->firstItem(),
                'to' => $data->lastItem(),
            ];
            $response['links'] = [
                'first' => $data->url(1),
                'last' => $data->url($data->lastPage()),
                'prev' => $data->previousPageUrl(),
                'next' => $data->nextPageUrl(),
            ];
        }

        return response()->json($response, $status);
    }

    protected function errorResponse(string $error, ?string $message = null, array $errors = [], int $status = 400): JsonResponse
    {
        return response()->json([
            'data' => null,
            'message' => $message,
            'error' => $error,
            'errors' => $errors,
            'status' => $status,
        ], $status);
    }
}
