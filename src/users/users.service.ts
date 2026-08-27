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
    return await this.prisma.user.create({
      data: {
        ...createUserDto,
        password: passwordHash,
      },
      select: this.publicSelect,
    });
  }

  async findAll() {
    return await this.prisma.user.findMany({ select: this.publicSelect });
  }

  async findOne(id: number) {
    return await this.prisma.user.findUnique({
      where: { id },
      select: this.publicSelect,
    });
  }

  async findByEmail(email: string) {
    return await this.prisma.user.findUnique({ where: { email } });
  }

  async update(id: number, updateUserDto: UpdateUserDto) {
    return await this.prisma.user.update({
      where: { id },
      data: updateUserDto,
      select: this.publicSelect,
    });
  }

  async remove(id: number) {
    return await this.prisma.user.delete({
      where: { id },
      select: this.publicSelect,
    });
  }
}
