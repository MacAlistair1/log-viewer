<?php

namespace Jeeven\LogViewer\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class Authorize
{
    public function handle(Request $request, Closure $next)
    {
        if (!config('log-viewer.authorization.enabled', true)) {
            return $next($request);
        }

        $guard = config('log-viewer.authorization.guard', 'web');
        $ability = config('log-viewer.authorization.gates.view', 'viewLogViewer');

        // No gate defined by the host app? Only allow in local for safety.
        if (!Gate::has($ability)) {
            if (app()->environment('local')) {
                return $next($request);
            }
            throw new AccessDeniedHttpException(
                "Log Viewer: define a '{$ability}' Gate in your AuthServiceProvider to grant access outside local."
            );
        }

        $user = Auth::guard($guard)->user();

        if (!Gate::forUser($user)->allows($ability)) {
            throw new AccessDeniedHttpException('Log Viewer: you are not authorized to view logs.');
        }

        return $next($request);
    }
}
