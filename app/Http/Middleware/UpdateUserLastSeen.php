<?php

namespace App\Http\Middleware;

use App\Support\SchemaCache;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class UpdateUserLastSeen
{
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::check() && SchemaCache::hasColumn('users', 'last_seen_at')) {
            $user = Auth::user();

            // Chỉ update tối đa 1 lần/phút để tránh ghi database quá nhiều
            if (
                empty($user->last_seen_at) ||
                now()->diffInSeconds($user->last_seen_at) >= 60
            ) {
                $user->forceFill([
                    'last_seen_at' => now(),
                ])->saveQuietly();
            }
        }

        return $next($request);
    }
}
