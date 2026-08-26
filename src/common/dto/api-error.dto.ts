import { ApiProperty } from '@nestjs/swagger';

/**
 * Common shape for every error response produced by this API.
 * Mirrors NestJS's default HttpException serialization.
 *
 * Two `message` shapes:
 * - `string` for `new BadRequestException('foo')`-style throws.
 * - `string[]` for `ValidationPipe` failures, one entry per failed constraint.
 */
export class ApiError {
  @ApiProperty({ example: 401 })
  statusCode!: number;

  @ApiProperty({
    description:
      "Either a single message ('Invalid credentials') or an array of " +
      'validation failures ("email must be an email", "password is too short").',
    oneOf: [
      { type: 'string' },
      { type: 'array', items: { type: 'string' } },
    ],
    example: 'Invalid credentials',
  })
  message!: string | string[];

  @ApiProperty({ example: 'Unauthorized', required: false })
  error?: string;
}
