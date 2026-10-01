<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireRolePermission
{
    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $role = $request->user()?->assignedRole;

        // Accounts predating role assignment keep their existing access.
        if ($role && strcasecmp($role->name, 'admin') !== 0) {
            foreach ($permissions as $permission) {
                abort_unless(in_array($permission, $role->permissions, true), 403);
            }
        }

        return $next($request);
    }
}
