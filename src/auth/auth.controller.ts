import {
  Body,
  Controller,
  Get,
  HttpCode,
  HttpStatus,
  Post,
  UseGuards,
} from '@nestjs/common';
import {
  ApiBadRequestResponse,
  ApiBearerAuth,
  ApiConflictResponse,
  ApiCreatedResponse,
  ApiOkResponse,
  ApiOperation,
  ApiTags,
  ApiUnauthorizedResponse,
} from '@nestjs/swagger';
import { ApiError } from 'src/common/dto/api-error.dto';
import { AuthService } from './auth.service';
import { CurrentUser } from './decorators/current-user.decorator';
import { Public } from './decorators/public.decorator';
import { AuthTokensDoc, AuthUserViewDoc } from './dto/auth-response.dto';
import { LoginDto } from './dto/login.dto';
import { LogoutResponseDoc } from './dto/logout-response.dto';
import { RefreshDto } from './dto/refresh.dto';
import { RegisterDto } from './dto/register.dto';
import { JwtAuthGuard } from './guards/jwt-auth.guard';
import type { AuthTokens, AuthUserView } from './auth.service';
import type { JwtUser } from './types/jwt-payload.type';

@ApiTags('auth')
@Controller('auth')
export class AuthController {
  constructor(private readonly auth: AuthService) {}

  @Public()
  @Post('register')
  @HttpCode(HttpStatus.CREATED)
  @ApiOperation({ summary: 'Create a new user and issue a token pair' })
  @ApiCreatedResponse({ description: 'User created', type: AuthTokensDoc })
  @ApiBadRequestResponse({
    description: 'Validation failed (missing/weak fields)',
    type: ApiError,
    example: {
      statusCode: 400,
      message: [
        'email must be an email',
        'password must be longer than or equal to 8 characters',
      ],
      error: 'Bad Request',
    },
  })
  @ApiConflictResponse({
    description: 'Email already registered',
    type: ApiError,
    example: {
      statusCode: 409,
      message: 'Email already registered',
      error: 'Conflict',
    },
  })
  register(@Body() dto: RegisterDto): Promise<AuthTokens> {
    return this.auth.register(dto);
  }

  @Public()
  @Post('login')
  @HttpCode(HttpStatus.OK)
  @ApiOperation({ summary: 'Exchange email+password for a token pair' })
  @ApiOkResponse({ description: 'Authenticated', type: AuthTokensDoc })
  @ApiBadRequestResponse({
    description: 'Validation failed',
    type: ApiError,
    example: { statusCode: 400, message: ['email must be an email'], error: 'Bad Request' },
  })
  @ApiUnauthorizedResponse({
    description: 'Invalid credentials',
    type: ApiError,
    example: { statusCode: 401, message: 'Invalid credentials', error: 'Unauthorized' },
  })
  login(@Body() dto: LoginDto): Promise<AuthTokens> {
    return this.auth.login(dto);
  }

  @Public()
  @Post('refresh')
  @HttpCode(HttpStatus.OK)
  @ApiOperation({
    summary: 'Rotate the refresh token',
    description:
      'Old refresh is revoked and a fresh pair is returned. ' +
      'Reusing a revoked/expired token returns 401.',
  })
  @ApiOkResponse({ description: 'New token pair', type: AuthTokensDoc })
  @ApiBadRequestResponse({
    description: 'Validation failed (refreshToken length out of range)',
    type: ApiError,
    example: { statusCode: 400, message: ['refreshToken must be longer than or equal to 20 characters'], error: 'Bad Request' },
  })
  @ApiUnauthorizedResponse({
    description: 'Refresh token is invalid, expired, or already revoked',
    type: ApiError,
    example: { statusCode: 401, message: 'Invalid refresh token', error: 'Unauthorized' },
  })
  refresh(@Body() dto: RefreshDto): Promise<AuthTokens> {
    return this.auth.refresh(dto.refreshToken);
  }

  @ApiBearerAuth('bearer')
  @UseGuards(JwtAuthGuard)
  @Post('logout')
  @HttpCode(HttpStatus.OK)
  @ApiOperation({ summary: 'Revoke the supplied refresh token' })
  @ApiOkResponse({ type: LogoutResponseDoc })
  @ApiUnauthorizedResponse({
    description: 'Missing/invalid bearer token',
    type: ApiError,
    example: { statusCode: 401, message: 'Unauthorized', error: 'Unauthorized' },
  })
  logout(
    @CurrentUser() user: JwtUser,
    @Body() dto: RefreshDto,
  ): Promise<{ success: true }> {
    return this.auth.logout(user.id, dto.refreshToken);
  }

  @ApiBearerAuth('bearer')
  @UseGuards(JwtAuthGuard)
  @Get('me')
  @ApiOperation({ summary: 'Get the authenticated user' })
  @ApiOkResponse({ type: AuthUserViewDoc })
  @ApiUnauthorizedResponse({
    description: 'Missing/invalid bearer token',
    type: ApiError,
    example: { statusCode: 401, message: 'Unauthorized', error: 'Unauthorized' },
  })
  me(@CurrentUser() user: JwtUser): Promise<AuthUserView> {
    return this.auth.me(user.id);
  }
}
