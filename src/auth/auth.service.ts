import {
  ConflictException,
  Injectable,
  UnauthorizedException,
} from '@nestjs/common';
import { ConfigService } from '@nestjs/config';
import { JwtService } from '@nestjs/jwt';
import { PrismaService } from 'prisma/prisma.service';
import { RegisterDto } from './dto/register.dto';
import { LoginDto } from './dto/login.dto';
import { PasswordService } from './password.service';
import { RefreshTokenService } from './refresh-token.service';
import type { JwtPayload } from './types/jwt-payload.type';

export interface AuthUserView {
  id: number;
  name: string;
  email: string;
  active?: boolean;
  createdAt?: Date;
}

export interface AuthTokens {
  accessToken: string;
  refreshToken: string;
  user: AuthUserView;
}

@Injectable()
export class AuthService {
  constructor(
    private readonly prisma: PrismaService,
    private readonly jwt: JwtService,
    private readonly config: ConfigService,
    private readonly passwords: PasswordService,
    private readonly refreshTokens: RefreshTokenService,
  ) {}

  private get accessSecret(): string {
    return this.config.get<string>('JWT_ACCESS_SECRET') ?? '';
  }

  private get refreshSecret(): string {
    return this.config.get<string>('JWT_REFRESH_SECRET') ?? '';
  }

  private get accessTtl(): string {
    return this.config.get<string>('JWT_ACCESS_TTL', '15m');
  }

  private get refreshTtl(): string {
    return this.config.get<string>('JWT_REFRESH_TTL', '7d');
  }

  private async issueTokens(
    user: { id: number; email: string },
  ): Promise<{ accessToken: string; refreshToken: string }> {
    const payload: JwtPayload = { sub: user.id, email: user.email };

    const accessToken = await this.jwt.signAsync(payload, {
      secret: this.accessSecret,
      expiresIn: this.accessTtl as unknown as number,
    });

    const refreshToken = this.refreshTokens.generate();
    await this.refreshTokens.create(user.id, refreshToken);

    return { accessToken, refreshToken };
  }

  async register(dto: RegisterDto): Promise<AuthTokens> {
    const existing = await this.prisma.user.findUnique({
      where: { email: dto.email },
    });
    if (existing) {
      throw new ConflictException('Email already registered');
    }

    const passwordHash = await this.passwords.hash(dto.password);
    const user = await this.prisma.user.create({
      data: {
        name: dto.name,
        email: dto.email,
        password: passwordHash,
      },
      select: { id: true, name: true, email: true, active: true, createdAt: true },
    });

    const { accessToken, refreshToken } = await this.issueTokens(user);

    return {
      accessToken,
      refreshToken,
      user: {
        id: user.id,
        name: user.name,
        email: user.email,
        active: user.active,
        createdAt: user.createdAt,
      },
    };
  }

  async login(dto: LoginDto): Promise<AuthTokens> {
    const user = await this.prisma.user.findUnique({
      where: { email: dto.email },
    });

    if (!user || !user.active) {
      throw new UnauthorizedException('Invalid credentials');
    }

    const ok = await this.passwords.verify(user.password, dto.password);
    if (!ok) {
      throw new UnauthorizedException('Invalid credentials');
    }

    const tokens = await this.issueTokens(user);

    return {
      ...tokens,
      user: { id: user.id, name: user.name, email: user.email, active: user.active },
    };
  }

  async refresh(refreshToken: string): Promise<AuthTokens> {
    const record = await this.refreshTokens.findActive(refreshToken);
    if (!record) {
      throw new UnauthorizedException('Invalid refresh token');
    }

    const user = await this.prisma.user.findUnique({
      where: { id: record.userId },
      select: { id: true, name: true, email: true, active: true },
    });

    if (!user || !user.active) {
      throw new UnauthorizedException('Invalid refresh token');
    }

    const newRefresh = await this.refreshTokens.rotate(refreshToken, user.id);

    const accessToken = await this.jwt.signAsync(
      { sub: user.id, email: user.email } satisfies JwtPayload,
      { secret: this.accessSecret, expiresIn: this.accessTtl as unknown as number },
    );

    return {
      accessToken,
      refreshToken: newRefresh,
      user: { id: user.id, name: user.name, email: user.email, active: user.active },
    };
  }

  async logout(userId: number, refreshToken: string): Promise<{ success: true }> {
    await this.refreshTokens.revoke(userId, refreshToken);
    return { success: true };
  }

  async me(userId: number): Promise<AuthUserView> {
    const user = await this.prisma.user.findUnique({
      where: { id: userId },
      select: { id: true, name: true, email: true, active: true, createdAt: true },
    });

    if (!user) {
      throw new UnauthorizedException('User no longer exists');
    }

    return user;
  }
}
