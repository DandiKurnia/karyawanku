<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    /**
     * Handle an incoming request.
     *
     * Usage in routes:
     *   ->middleware('role:admin')
     *   ->middleware('role:employee')
     *   ->middleware('role:admin,employee')
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (!$user || !in_array($user->role, $roles)) {
            return response()->json([
                'meta' => [
                    'code' => 403,
                    'status' => 'error',
                    'message' => 'Forbidden: You do not have access to this resource'
                ],
                'data' => null
            ], 403);
        }

        return $next($request);
    }
}
