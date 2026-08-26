import { ApiProperty } from '@nestjs/swagger';
import { IsEmail, IsString, MinLength } from 'class-validator';

export class LoginDto {
  @ApiProperty({ example: 'alice@example.com', format: 'email' })
  @IsEmail()
  email!: string;

  @ApiProperty({ example: 'S3cretP@ss', minLength: 1, writeOnly: true })
  @IsString()
  @MinLength(1)
  password!: string;
}
