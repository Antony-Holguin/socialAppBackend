import { SetMetadata } from '@nestjs/common';

export const IS_PUBLIC_KEY = 'isPublic';

/**
 * Mark a controller or handler as public — bypasses `JwtAuthGuard`.
 *
 * Useful on `/auth/login`, `/auth/register`, `/auth/refresh`.
 */
export const Public = () => SetMetadata(IS_PUBLIC_KEY, true);
