import { ConfigService } from '@nestjs/config';
import { JwtStrategy } from './jwt.strategy';

describe('JwtStrategy', () => {
  it('returns { id, email } from the JWT payload', () => {
    const cfg = {
      get: jest.fn((key: string) => (key === 'JWT_ACCESS_SECRET' ? 'access-secret' : undefined)),
    } as unknown as ConfigService;

    const strategy = new JwtStrategy(cfg);
    expect(strategy.validate({ sub: 42, email: 'a@b.c' })).toEqual({
      id: 42,
      email: 'a@b.c',
    });
  });
});
