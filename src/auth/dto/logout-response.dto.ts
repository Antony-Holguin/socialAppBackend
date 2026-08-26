import { ApiProperty } from '@nestjs/swagger';

export class LogoutResponseDoc {
  @ApiProperty({ example: true }) success!: true;
}
