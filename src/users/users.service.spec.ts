import { Test, TestingModule } from '@nestjs/testing';
import { PasswordService } from 'src/auth/password.service';
import { PrismaService } from 'prisma/prisma.service';
import { UsersService } from './users.service';

describe('UsersService', () => {
  let service: UsersService;
  let prisma: {
    user: {
      findMany: jest.Mock;
      findUnique: jest.Mock;
      create: jest.Mock;
      update: jest.Mock;
      delete: jest.Mock;
    };
  };
  let passwords: { hash: jest.Mock; verify: jest.Mock };

  beforeEach(async () => {
    prisma = {
      user: {
        findMany: jest.fn(),
        findUnique: jest.fn(),
        create: jest.fn(),
        update: jest.fn(),
        delete: jest.fn(),
      },
    };
    passwords = { hash: jest.fn(), verify: jest.fn() };

    const moduleRef: TestingModule = await Test.createTestingModule({
      providers: [
        UsersService,
        { provide: PrismaService, useValue: prisma },
        { provide: PasswordService, useValue: passwords },
      ],
    }).compile();

    service = moduleRef.get(UsersService);
  });

  it('findAll delegates to prisma.user.findMany without password', async () => {
    prisma.user.findMany.mockResolvedValue([{ id: 1, email: 'a@b.c' }]);
    await expect(service.findAll()).resolves.toEqual([{ id: 1, email: 'a@b.c' }]);
    expect(prisma.user.findMany).toHaveBeenCalledWith(
      expect.objectContaining({ select: expect.any(Object) }),
    );
  });

  it('findByEmail delegates to prisma.user.findUnique with email', async () => {
    prisma.user.findUnique.mockResolvedValue({ id: 1, email: 'a@b.c' });
    await expect(service.findByEmail('a@b.c')).resolves.toEqual({ id: 1, email: 'a@b.c' });
    expect(prisma.user.findUnique).toHaveBeenCalledWith({ where: { email: 'a@b.c' } });
  });

  describe('create', () => {
    it('hashes the password with argon2 before persisting', async () => {
      passwords.hash.mockResolvedValue('$argon2id$argon2id$hashed');
      prisma.user.create.mockResolvedValue({
        id: 1,
        name: 'Alice',
        email: 'alice@example.com',
        active: true,
        createdAt: new Date(),
        updatedAt: new Date(),
      });

      await service.create({
        name: 'Alice',
        email: 'alice@example.com',
        password: 'cleartext-pwd',
      });

      // 1) cleartext is hashed
      expect(passwords.hash).toHaveBeenCalledTimes(1);
      expect(passwords.hash).toHaveBeenCalledWith('cleartext-pwd');

      // 2) prisma receives the hash, not the cleartext
      expect(prisma.user.create).toHaveBeenCalledTimes(1);
      const prismaArgs = prisma.user.create.mock.calls[0][0];
      expect(prismaArgs.data.email).toBe('alice@example.com');
      expect(prismaArgs.data.name).toBe('Alice');
      expect(prismaArgs.data.password).toBe('$argon2id$argon2id$hashed');
      expect(prismaArgs.data.password).not.toBe('cleartext-pwd');
    });

    it('forwards optional active flag when provided', async () => {
      passwords.hash.mockResolvedValue('hashed');
      prisma.user.create.mockResolvedValue({ id: 1 });

      await service.create({
        name: 'Alice',
        email: 'alice@example.com',
        password: 'pwd',
        active: false,
      });

      const prismaArgs = prisma.user.create.mock.calls[0][0];
      expect(prismaArgs.data.active).toBe(false);
    });
  });
});
