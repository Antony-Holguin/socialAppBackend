<?php

namespace App\Http\Cookie;

use Symfony\Component\HttpFoundation\Cookie;

/**
 * Cookie name conventions — kept in sync with sakai-react/lib/auth/api.ts.
 *
 *   accessToken  → httpOnly, scoped to /api/v1, sent on every API request.
 *   refreshToken → httpOnly, scoped to /api/v1/auth, only the refresh/logout
 *                  routes need it (least-privilege path).
 */
class CookieService
{
    public const COOKIE_ACCESS = 'accessToken';

    public const COOKIE_REFRESH = 'refreshToken';

    public function __construct(
        protected bool $secure = false,
        protected int $accessTtlSeconds = 60 * 60, // 1h (covers access token life)
        protected int $refreshTtlSeconds = 7 * 24 * 60 * 60, // 7d
        protected string $apiPath = '/api/v1',
    ) {}

    /**
     * Stamp both auth cookies on the response. Called on login/register/refresh.
     *
     * @return array<int, Cookie>
     */
    public function setAuthCookies(string $accessToken, string $refreshToken): array
    {
        $now = time();

        return [
            Cookie::create(self::COOKIE_ACCESS, $accessToken, $now + $this->accessTtlSeconds, $this->apiPath, null, $this->secure, true, false, 'lax'),
            Cookie::create(self::COOKIE_REFRESH, $refreshToken, $now + $this->refreshTtlSeconds, $this->apiPath.'/auth', null, $this->secure, true, false, 'lax'),
        ];
    }

    /**
     * Clear both auth cookies (logout). Path must match what we set them with
     * or the browser won't evict them.
     *
     * @return array<int, Cookie>
     */
    public function clearAuthCookies(): array
    {
        return [
            Cookie::create(self::COOKIE_ACCESS, '', 0, $this->apiPath),
            Cookie::create(self::COOKIE_REFRESH, '', 0, $this->apiPath.'/auth'),
        ];
    }
}
