<?php

namespace App\Http\Middleware;

use App\Enums\Permission;
use App\Enums\RoleName;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureOwnerPortalAccess
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        abort_unless(
            $user instanceof User
            && $user->hasRole(RoleName::Owner)
            && $user->hasPermission(Permission::OwnerPortalView)
            && $user->currentOwnerRecords()->exists(),
            403,
        );

        return $next($request);
    }
}
