<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class ExpireIdleSession
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            return $next($request);
        }

        $lastSeen = $request->session()->get('last_seen_at');
        $lifetime = (int) config('session.lifetime');

        if (is_numeric($lastSeen) && $lifetime > 0) {
            $idleFor = now()->getTimestamp() - (int) $lastSeen;

            if ($idleFor >= ($lifetime * 60)) {
                Auth::guard('web')->logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return redirect()
                    ->route('login')
                    ->with('status', __('Your session timed out. Please sign in again.'));
            }
        }

        $request->session()->put('last_seen_at', now()->getTimestamp());

        return $next($request);
    }
}
