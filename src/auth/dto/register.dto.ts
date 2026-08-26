import { ApiProperty } from '@nestjs/swagger';
import { IsEmail, IsString, MinLength } from 'class-validator';

export class RegisterDto {
  @ApiProperty({ example: 'Alice', minLength: 2 })
  @IsString()
  @MinLength(2)
  name!: string;

  @ApiProperty({ example: 'alice@example.com', format: 'email' })
  @IsEmail()
  email!: string;

  @ApiProperty({ example: 'S3cretP@ss', minLength: 8, writeOnly: true })
  @IsString()
  @MinLength(8)
  password!: string;
}
