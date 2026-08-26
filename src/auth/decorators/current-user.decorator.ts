import { ExecutionContext, createParamDecorator } from '@nestjs/common';
import type { Request } from 'express';
import type { JwtUser } from '../types/jwt-payload.type';

/**
 * Extracts the JWT user attached by `JwtStrategy.validate()` (i.e. `req.user`).
 *
 * @example
 *   @Get('me')
 *   me(@CurrentUser() user: JwtUser) {
 *     return this.auth.me(user.id);
 *   }
 */
export const CurrentUser = createParamDecorator(
  (_data: unknown, ctx: ExecutionContext): JwtUser => {
    const req = ctx.switchToHttp().getRequest<Request & { user?: JwtUser }>();
    if (!req.user) {
      throw new Error(
        'CurrentUser() used on a route without JwtAuthGuard. Use @Public() if you really mean it.',
      );
    }
    return req.user;
  },
);
