<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class CheckPermission
{
    public function handle(Request $request, Closure $next, string $permission)
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        if (!$user->hasPermission($permission)) {
            DB::table('audit_logs')->insert([
                'user_id'    => $user->id,
                'permission' => $permission,
                'context'    => json_encode(['status' => 'denied']),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'created_at' => now('UTC'),
            ]);
            return response()->json(['error' => 'Forbidden'], 403);
        }

        return $next($request);
    }
}
