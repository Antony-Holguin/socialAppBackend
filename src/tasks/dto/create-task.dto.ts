import { ApiProperty } from '@nestjs/swagger';
import { IsBoolean, IsOptional, IsString, MinLength } from 'class-validator';

/**
 * Body for `POST /tasks`.
 *
 * NOTE: `authorId` is intentionally NOT in this DTO. The server assigns it
 * from the JWT (`req.user.id`) in TasksService.create — accepting it from the
 * client would let any user impersonate another.
 */
export class CreateTaskDto {
  @ApiProperty({ example: 'Comprar leche', minLength: 2 })
  @IsString()
  @MinLength(2)
  title!: string;

  @ApiProperty({ example: true, required: false, default: true })
  @IsOptional()
  @IsBoolean()
  active?: boolean;
}
