<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class BasicAuthGate
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $configuredUser = (string) config('app.basic_auth.user', '');
        $configuredPassword = (string) config('app.basic_auth.password', '');

        if ($configuredUser === '' || $configuredPassword === '') {
            if (app()->environment('local')) {
                return $next($request);
            }

            abort(503, 'Konfigurasi akses belum diatur.');
        }

        $providedUser = (string) $request->getUser();
        $providedPassword = (string) $request->getPassword();

        $userMatches = hash_equals($configuredUser, $providedUser);
        $passwordMatches = hash_equals($configuredPassword, $providedPassword);

        if (! $userMatches || ! $passwordMatches) {
            return response('Unauthorized.', 401, [
                'WWW-Authenticate' => 'Basic realm="Inventaris Bengkel"',
            ]);
        }

        return $next($request);
    }
}
