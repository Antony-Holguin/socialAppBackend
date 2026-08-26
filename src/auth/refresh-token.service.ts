import { Injectable } from '@nestjs/common';
import { ConfigService } from '@nestjs/config';
import { createHash, randomBytes } from 'crypto';
import { PrismaService } from 'prisma/prisma.service';
import type { RefreshToken } from 'src/generated/prisma/client';

const MS_PER_DAY = 24 * 60 * 60 * 1000;

@Injectable()
export class RefreshTokenService {
  constructor(
    private readonly prisma: PrismaService,
    private readonly config: ConfigService,
  ) {}

  /** Generates an opaque, URL-safe random refresh token. */
  generate(): string {
    return randomBytes(64).toString('base64url');
  }

  /** Hash a plain refresh token for storage / lookup (sha256, fast). */
  hash(plain: string): string {
    return createHash('sha256').update(plain).digest('hex');
  }

  private expiresAt(): Date {
    const ttl = this.config.get<string>('JWT_REFRESH_TTL', '7d');
    const days = /^\d+d$/.test(ttl) ? Number(ttl.replace('d', '')) : 7;
    return new Date(Date.now() + days * MS_PER_DAY);
  }

  /** Persist a brand-new hashed refresh token for a user. */
  async create(userId: number, plain: string): Promise<RefreshToken> {
    return this.prisma.refreshToken.create({
      data: {
        userId,
        hash: this.hash(plain),
        expiresAt: this.expiresAt(),
      },
    });
  }

  /** Look up an active (non-revoked, non-expired) refresh by its plain value. */
  async findActive(plain: string): Promise<RefreshToken | null> {
    return this.prisma.refreshToken.findFirst({
      where: {
        hash: this.hash(plain),
        revokedAt: null,
        expiresAt: { gt: new Date() },
      },
    });
  }

  /**
   * Rotate: revoke the old token and persist a new one in a single transaction.
   * Returns the new plain-text token.
   */
  async rotate(oldPlain: string, userId: number): Promise<string> {
    const newPlain = this.generate();
    const newHash = this.hash(newPlain);
    const expiresAt = this.expiresAt();

    await this.prisma.$transaction([
      this.prisma.refreshToken.updateMany({
        where: { userId, hash: this.hash(oldPlain), revokedAt: null },
        data: { revokedAt: new Date() },
      }),
      this.prisma.refreshToken.create({
        data: { userId, hash: newHash, expiresAt },
      }),
    ]);

    return newPlain;
  }

  /** Revoke a single refresh token (used by /auth/logout). */
  async revoke(userId: number, plain: string): Promise<number> {
    const result = await this.prisma.refreshToken.updateMany({
      where: { userId, hash: this.hash(plain), revokedAt: null },
      data: { revokedAt: new Date() },
    });
    return result.count;
  }
}
