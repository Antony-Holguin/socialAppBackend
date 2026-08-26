import { Injectable } from '@nestjs/common';
import { PasswordService } from 'src/auth/password.service';
import { PrismaService } from 'prisma/prisma.service';
import { CreateUserDto } from './dto/create-user.dto';
import { UpdateUserDto } from './dto/update-user.dto';

@Injectable()
export class UsersService {
  constructor(
    private readonly prisma: PrismaService,
    private readonly passwords: PasswordService,
  ) {}

  private readonly publicSelect = {
    id: true,
    name: true,
    email: true,
    active: true,
    createdAt: true,
    updatedAt: true,
  } as const;

  async create(createUserDto: CreateUserDto) {
    const passwordHash = await this.passwords.hash(createUserDto.password);
    return this.prisma.user.create({
      data: {
        ...createUserDto,
        password: passwordHash,
      },
      select: this.publicSelect,
    });
  }

  findAll() {
    return this.prisma.user.findMany({ select: this.publicSelect });
  }

  findOne(id: number) {
    return this.prisma.user.findUnique({
      where: { id },
      select: this.publicSelect,
    });
  }

  findByEmail(email: string) {
    return this.prisma.user.findUnique({ where: { email } });
  }

  update(id: number, updateUserDto: UpdateUserDto) {
    return this.prisma.user.update({
      where: { id },
      data: updateUserDto,
      select: this.publicSelect,
    });
  }

  remove(id: number) {
    return this.prisma.user.delete({
      where: { id },
      select: this.publicSelect,
    });
  }
}
