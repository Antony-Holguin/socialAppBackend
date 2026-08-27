import { Injectable, NotFoundException } from '@nestjs/common';
import { PrismaService } from 'prisma/prisma.service';
import type { JwtUser } from 'src/auth/types/jwt-payload.type';
import { CreateTaskDto } from './dto/create-task.dto';
import { QueryTasksDto } from './dto/query-tasks.dto';
import { UpdateTaskDto } from './dto/update-task.dto';
import { Task } from './entities/task.entity';

@Injectable()
export class TasksService {
  constructor(private readonly prisma: PrismaService) {}

  /**
   * Reusable select: only public columns. Add new public fields here once
   * and every method picks them up. Never add sensitive fields (passwords,
   * tokens, internal notes).
   */
  private readonly publicSelect = {
    id: true,
    title: true,
    active: true,
    authorId: true,
    createdAt: true,
    updatedAt: true,
  } as const;

  /** Create a task owned by the authenticated user. */
  async create(createTaskDto: CreateTaskDto, user: JwtUser): Promise<Task> {
    return await this.prisma.task.create({
      data: {
        title: createTaskDto.title,
        active: createTaskDto.active ?? true,
        authorId: user.id, // always from JWT, never from the body
      },
      select: this.publicSelect,
    });
  }

  /** Paginated list with optional active filter. */
  async findAll(q: QueryTasksDto): Promise<Task[]> {
    return await this.prisma.task.findMany({
      where: { active: q.active },
      select: this.publicSelect,
      orderBy: { id: 'desc' },
      skip: (q.page - 1) * q.limit,
      take: q.limit,
    });
  }

  /** List only the tasks owned by the authenticated user. */
  async findMine(user: JwtUser, q: QueryTasksDto): Promise<Task[]> {
    return await this.prisma.task.findMany({
      where: { authorId: user.id, active: q.active },
      select: this.publicSelect,
      orderBy: { id: 'desc' },
      skip: (q.page - 1) * q.limit,
      take: q.limit,
    });
  }

  /** Find one task or throw 404. */
  async findOne(id: number): Promise<Task> {
    const task = await this.prisma.task.findUnique({
      where: { id },
      select: this.publicSelect,
    });
    if (!task) throw new NotFoundException(`Task #${id} not found`);
    return task;
  }

  /** Update a task or throw 404. */
  async update(id: number, updateTaskDto: UpdateTaskDto): Promise<Task> {
    return await this.prisma.task.update({
      where: { id },
      data: updateTaskDto,
      select: this.publicSelect,
    });
  }

  /** Delete a task or throw 404. */
  async remove(id: number): Promise<Task> {
    return await this.prisma.task.delete({
      where: { id },
      select: this.publicSelect,
    });
  }
}
