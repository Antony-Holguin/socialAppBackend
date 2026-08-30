import { ApiProperty } from '@nestjs/swagger';

/**
 * Public shape of a Task returned by the API.
 * Used by Swagger to document responses and by the service as a return type.
 *
 * NOTE: this is NOT the Prisma model. It only lists what we choose to expose.
 * If you add a sensitive column to the Prisma model, do NOT add it here.
 */
export class Task {
  @ApiProperty({ example: 1 })
  id!: number;

  @ApiProperty({ example: 'Comprar leche' })
  title!: string;

  @ApiProperty({ example: true })
  active!: boolean;

  @ApiProperty({
    example: 1,
    description: 'ID of the user who created the task',
  })
  authorId!: number;

  @ApiProperty({ example: '2026-08-26T12:00:00.000Z' })
  createdAt!: Date | string;

  @ApiProperty({ example: '2026-08-26T12:00:00.000Z' })
  updatedAt!: Date | string;
}
