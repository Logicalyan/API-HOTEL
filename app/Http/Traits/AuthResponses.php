<?php

namespace App\Http\Traits;

use Illuminate\Http\JsonResponse;

trait AuthResponses
{
    /**
     * Format a successful authentication response.
     *
     * @param mixed $data The primary data (e.g., user object, token).
     * @param string $message A success message.
     * @param int $status The HTTP status code.
     * @return JsonResponse
     */
    protected function authSuccessResponse(mixed $data, string $message, int $status = 200): JsonResponse
    {
        return response()->json([
            'data' => $data,
            'message' => $message,
            'error' => null,
            'errors' => [],
            'status' => $status,
        ], $status);
    }

    /**
     * Format an authentication error response.
     *
     * @param string $error A general error message.
     * @param string|null $message An optional, more specific message.
     * @param array $errors Detailed error messages (e.g., validation errors).
     * @param int $status The HTTP status code.
     * @return JsonResponse
     */
    protected function authErrorResponse(string $error, ?string $message = null, array $errors = [], int $status = 401): JsonResponse
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
