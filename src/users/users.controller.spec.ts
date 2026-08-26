import { Test, TestingModule } from '@nestjs/testing';
import { UsersController } from './users.controller';
import { UsersService } from './users.service';
import { PrismaService } from 'prisma/prisma.service';
import { PasswordService } from 'src/auth/password.service';

describe('UsersController', () => {
  let controller: UsersController;

  beforeEach(async () => {
    const moduleRef: TestingModule = await Test.createTestingModule({
      controllers: [UsersController],
      providers: [
        UsersService,
        { provide: PrismaService, useValue: {} },
        { provide: PasswordService, useValue: { hash: jest.fn(), verify: jest.fn() } },
      ],
    }).compile();

    controller = moduleRef.get(UsersController);
  });

  it('should be defined', () => {
    expect(controller).toBeDefined();
  });
});
