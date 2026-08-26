import { ApiProperty } from '@nestjs/swagger';

export class AuthUserViewDoc {
  @ApiProperty({ example: 1 }) id!: number;
  @ApiProperty({ example: 'Alice' }) name!: string;
  @ApiProperty({ example: 'alice@example.com' }) email!: string;
  @ApiProperty({ example: true, required: false }) active?: boolean;
  @ApiProperty({ required: false, type: String, format: 'date-time' })
  createdAt?: Date;
}

export class AuthTokensDoc {
  @ApiProperty({ description: 'Short-lived JWT (default 15 minutes).' })
  accessToken!: string;

  @ApiProperty({
    description:
      'Opaque refresh token (default 7 days). Rotated on each /auth/refresh. ' +
      'Stored hashed (sha256) server-side.',
  })
  refreshToken!: string;

  @ApiProperty({ type: AuthUserViewDoc })
  user!: AuthUserViewDoc;
}
