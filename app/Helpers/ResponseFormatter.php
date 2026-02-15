<?php

namespace App\Helpers;

class ResponseFormatter
{
    /**
     * Send a success response.
     *
     * @param mixed $data
     * @param string|null $message
     * @param int $code
     * @return \Illuminate\Http\JsonResponse
     */
    public static function success($data = null, $message = null, $code = 200)
    {
        return response()->json([
            'meta' => [
                'code' => $code,
                'status' => 'success',
                'message' => $message
            ],
            'data' => $data
        ], $code);
    }

    /**
     * Send an error response.
     *
     * @param mixed $data
     * @param string|null $message
     * @param int $code
     * @return \Illuminate\Http\JsonResponse
     */
    public static function error($data = null, $message = null, $code = 400)
    {
        return response()->json([
            'meta' => [
                'code' => $code,
                'status' => 'error',
                'message' => $message
            ],
            'data' => $data
        ], $code);
    }
}