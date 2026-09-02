<?php

namespace App\Http\Middleware;

use App\Http\Cookie\CookieService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Bridge between the frontend's cookie-based auth and Laravel's
 * Bearer-token JWT guard.
 *
 * The Sakai React client never touches the access token — it lives only in
 * an httpOnly cookie. Laravel's auth:api guard, however, reads from the
 * Authorization header. This middleware copies the cookie into a synthetic
 * header so the guard does the right thing without any custom guard code.
 */
class AuthenticateFromCookie
{
    public function __construct(protected CookieService $cookies) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->bearerToken() && $token = $request->cookie(CookieService::COOKIE_ACCESS)) {
            $request->headers->set('Authorization', 'Bearer '.$token);
        }

        return $next($request);
    }
}
