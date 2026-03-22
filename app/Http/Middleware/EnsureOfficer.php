<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureOfficer
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || $user->role !== 'officer') {
            return response()->json([
                'message' => 'Unauthorized. Officer access required.',
            ], 403);
        }

        if ($user->status !== 'approved') {
            return response()->json([
                'message' => 'Account not approved. Contact administrator.',
            ], 403);
        }

        if (isset($user->is_active) && ! $user->is_active) {
            return response()->json([
                'message' => 'Account is deactivated. Contact administrator.',
            ], 403);
        }

        return $next($request);
    }
}
