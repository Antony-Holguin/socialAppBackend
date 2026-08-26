/**
 * Shape of the JWT payload after `JwtStrategy.validate()` ran.
 * This is what gets attached to `req.user` by Passport.
 */
export interface JwtUser {
  id: number;
  email: string;
}

/**
 * Raw payload signed into the access token before validation.
 * `sub` is the user id (per RFC 7519), `email` is for convenient /auth/me.
 */
export interface JwtPayload {
  sub: number;
  email: string;
  iat?: number;
  exp?: number;
}
