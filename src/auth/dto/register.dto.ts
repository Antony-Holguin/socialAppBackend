import { ApiProperty } from '@nestjs/swagger';
import { IsEmail, IsString, MinLength } from 'class-validator';

export class RegisterDto {
  @ApiProperty({ example: 'Antony', minLength: 2 })
  @IsString()
  @MinLength(2)
  name!: string;

  @ApiProperty({ example: 'alice@example.com', format: 'email' })
  @IsEmail()
  email!: string;

  @ApiProperty({ example: 'Tecni@2026', minLength: 8, writeOnly: true })
  @IsString()
  @MinLength(8)
  password!: string;
}
