import { ApiProperty } from '@nestjs/swagger';
import { IsString, Length } from 'class-validator';

export class RefreshDto {
  @ApiProperty({
    description: 'Opaque refresh token issued by /auth/login or /auth/register',
    minLength: 20,
    maxLength: 2048,
    example: 'Z9n9oQ_dummy-base64url-string-with-at-least-20-chars',
  })
  @IsString()
  @Length(20, 2048)
  refreshToken!: string;
}
