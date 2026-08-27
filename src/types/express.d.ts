/**
 * Module augmentation: makes `req.user` known to TypeScript everywhere.
 *
 * Without this, accessing `req.user` requires a cast like
 * `(req as any).user` or `(req as Request & { user?: JwtUser }).user`.
 *
 * After this file is included by the compiler, Passport's
 * `req.user = { id, email }` assignment is type-checked automatically.
 *
 * Make sure this file is covered by `tsconfig.json` `"include"`.
 */
import 'express';
import type { JwtUser } from '../auth/types/jwt-payload.type';

declare global {
  namespace Express {
    interface Request {
      user?: JwtUser;
    }
  }
}
