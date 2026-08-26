import { ConfigService } from '@nestjs/config';
import { JwtService } from '@nestjs/jwt';
import { Test, TestingModule } from '@nestjs/testing';
import { ConflictException, UnauthorizedException } from '@nestjs/common';
import { PrismaService } from 'prisma/prisma.service';
import { AuthService } from './auth.service';
import { PasswordService } from './password.service';
import { RefreshTokenService } from './refresh-token.service';

describe('AuthService', () => {
  let service: AuthService;
  let prisma: { user: { findUnique: jest.Mock; create: jest.Mock }; refreshToken: Record<string, jest.Mock>; $transaction: jest.Mock };
  let jwt: { signAsync: jest.Mock };
  let passwords: { hash: jest.Mock; verify: jest.Mock };
  let refreshTokens: { generate: jest.Mock; create: jest.Mock; findActive: jest.Mock; rotate: jest.Mock; revoke: jest.Mock };

  beforeEach(async () => {
    prisma = {
      user: {
        findUnique: jest.fn(),
        create: jest.fn(),
      },
      refreshToken: {},
      $transaction: jest.fn(),
    };
    jwt = { signAsync: jest.fn() };
    passwords = { hash: jest.fn(), verify: jest.fn() };
    refreshTokens = {
      generate: jest.fn(),
      create: jest.fn(),
      findActive: jest.fn(),
      rotate: jest.fn(),
      revoke: jest.fn(),
    };

    const moduleRef: TestingModule = await Test.createTestingModule({
      providers: [
        AuthService,
        { provide: PrismaService, useValue: prisma },
        { provide: JwtService, useValue: jwt },
        {
          provide: ConfigService,
          useValue: {
            get: jest.fn((key: string, def?: string) => {
              if (key === 'JWT_ACCESS_SECRET') return 'access-secret';
              if (key === 'JWT_REFRESH_SECRET') return 'refresh-secret';
              if (key === 'JWT_ACCESS_TTL') return def ?? '15m';
              if (key === 'JWT_REFRESH_TTL') return def ?? '7d';
              return def;
            }),
          },
        },
        { provide: PasswordService, useValue: passwords },
        { provide: RefreshTokenService, useValue: refreshTokens },
      ],
    }).compile();

    service = moduleRef.get(AuthService);
  });

  describe('register', () => {
    it('creates a user with a hashed password and returns both tokens', async () => {
      prisma.user.findUnique.mockResolvedValue(null);
      passwords.hash.mockResolvedValue('argon2id$hashed');
      prisma.user.create.mockResolvedValue({
        id: 1,
        name: 'Alice',
        email: 'alice@example.com',
        active: true,
        createdAt: new Date(),
      });
      jwt.signAsync.mockResolvedValue('signed-access');
      refreshTokens.generate.mockReturnValue('plain-refresh');

      const result = await service.register({
        name: 'Alice',
        email: 'alice@example.com',
        password: 'S3cretP@ss',
      });

      expect(passwords.hash).toHaveBeenCalledWith('S3cretP@ss');
      expect(prisma.user.create).toHaveBeenCalledWith({
        data: { name: 'Alice', email: 'alice@example.com', password: 'argon2id$hashed' },
        select: expect.any(Object),
      });
      expect(jwt.signAsync).toHaveBeenCalledWith(
        { sub: 1, email: 'alice@example.com' },
        expect.objectContaining({ secret: 'access-secret' }),
      );
      expect(refreshTokens.create).toHaveBeenCalledWith(1, 'plain-refresh');
      expect(result).toEqual({
        accessToken: 'signed-access',
        refreshToken: 'plain-refresh',
        user: {
          id: 1,
          name: 'Alice',
          email: 'alice@example.com',
          active: true,
          createdAt: expect.any(Date),
        },
      });
    });

    it('throws ConflictException when the email is already taken', async () => {
      prisma.user.findUnique.mockResolvedValue({ id: 99 });
      await expect(
        service.register({ name: 'A', email: 'a@b.c', password: 'S3cretP@ss' }),
      ).rejects.toBeInstanceOf(ConflictException);
      expect(prisma.user.create).not.toHaveBeenCalled();
    });
  });

  describe('login', () => {
    it('rejects unknown emails with UnauthorizedException', async () => {
      prisma.user.findUnique.mockResolvedValue(null);
      await expect(
        service.login({ email: 'ghost@x.com', password: 'whatever' }),
      ).rejects.toBeInstanceOf(UnauthorizedException);
    });

    it('rejects inactive users', async () => {
      prisma.user.findUnique.mockResolvedValue({
        id: 1,
        email: 'a@b.c',
        active: false,
        password: 'hash',
      });
      await expect(
        service.login({ email: 'a@b.c', password: 'whatever' }),
      ).rejects.toBeInstanceOf(UnauthorizedException);
    });

    it('rejects wrong passwords', async () => {
      prisma.user.findUnique.mockResolvedValue({
        id: 1,
        email: 'a@b.c',
        active: true,
        password: 'hash',
      });
      passwords.verify.mockResolvedValue(false);
      await expect(
        service.login({ email: 'a@b.c', password: 'wrong' }),
      ).rejects.toBeInstanceOf(UnauthorizedException);
    });

    it('issues both tokens on success', async () => {
      prisma.user.findUnique.mockResolvedValue({
        id: 1,
        name: 'Alice',
        email: 'a@b.c',
        active: true,
        password: 'hash',
      });
      passwords.verify.mockResolvedValue(true);
      jwt.signAsync.mockResolvedValue('signed-access');
      refreshTokens.generate.mockReturnValue('plain-refresh');

      const result = await service.login({ email: 'a@b.c', password: 'right' });
      expect(result.accessToken).toBe('signed-access');
      expect(result.refreshToken).toBe('plain-refresh');
    });
  });

  describe('refresh', () => {
    it('rotates and issues a fresh pair when a valid refresh token is presented', async () => {
      refreshTokens.findActive.mockResolvedValue({ id: 11, userId: 7 });
      prisma.user.findUnique.mockResolvedValue({
        id: 7,
        name: 'Alice',
        email: 'a@b.c',
        active: true,
      });
      refreshTokens.rotate.mockResolvedValue('new-plain-refresh');
      jwt.signAsync.mockResolvedValue('new-access');

      const result = await service.refresh('old-plain-refresh');
      expect(refreshTokens.rotate).toHaveBeenCalledWith('old-plain-refresh', 7);
      expect(result).toEqual({
        accessToken: 'new-access',
        refreshToken: 'new-plain-refresh',
        user: { id: 7, name: 'Alice', email: 'a@b.c', active: true },
      });
    });

    it('throws on invalid/expired refresh tokens', async () => {
      refreshTokens.findActive.mockResolvedValue(null);
      await expect(service.refresh('bad')).rejects.toBeInstanceOf(UnauthorizedException);
    });

    it('throws if the user backing the refresh token is missing/inactive', async () => {
      refreshTokens.findActive.mockResolvedValue({ id: 11, userId: 7 });
      prisma.user.findUnique.mockResolvedValue(null);
      await expect(service.refresh('good-hash')).rejects.toBeInstanceOf(UnauthorizedException);
    });
  });

  describe('logout', () => {
    it('asks RefreshTokenService to revoke the token for that user', async () => {
      refreshTokens.revoke.mockResolvedValue(1);
      await expect(service.logout(7, 'plain-refresh')).resolves.toEqual({ success: true });
      expect(refreshTokens.revoke).toHaveBeenCalledWith(7, 'plain-refresh');
    });
  });
});
