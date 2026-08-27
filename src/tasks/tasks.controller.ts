import {
  Body,
  Controller,
  Delete,
  Get,
  Param,
  ParseIntPipe,
  Patch,
  Post,
  Query,
} from '@nestjs/common';
import {
  ApiBearerAuth,
  ApiOperation,
  ApiParam,
  ApiTags,
} from '@nestjs/swagger';
import { CurrentUser } from 'src/auth/decorators/current-user.decorator';
import type { JwtUser } from 'src/auth/types/jwt-payload.type';
import { CreateTaskDto } from './dto/create-task.dto';
import { QueryTasksDto } from './dto/query-tasks.dto';
import { UpdateTaskDto } from './dto/update-task.dto';
import { Task } from './entities/task.entity';
import { TasksService } from './tasks.service';

@ApiTags('tasks')
@ApiBearerAuth('bearer')
@Controller('tasks')
export class TasksController {
  constructor(private readonly tasksService: TasksService) {}

  @Post()
  @ApiOperation({
    summary: 'Crear una tarea',
    description: 'El authorId se asigna automáticamente desde el JWT.',
  })
  create(
    @CurrentUser() user: JwtUser,
    @Body() createTaskDto: CreateTaskDto,
  ): Promise<Task> {
    return this.tasksService.create(createTaskDto, user);
  }

  /**
   * IMPORTANT: keep `/mine` BEFORE `/:id`. Otherwise Nest matches 'mine'
   * as the `:id` param and `findOne('mine')` throws.
   */
  @Get('mine')
  @ApiOperation({ summary: 'Listar las tareas del usuario autenticado' })
  findMine(
    @CurrentUser() user: JwtUser,
    @Query() query: QueryTasksDto,
  ): Promise<Task[]> {
    return this.tasksService.findMine(user, query);
  }

  @Get()
  @ApiOperation({ summary: 'Listar todas las tareas (paginado)' })
  findAll(@Query() query: QueryTasksDto): Promise<Task[]> {
    return this.tasksService.findAll(query);
  }

  @Get(':id')
  @ApiParam({ name: 'id', type: Number, example: 1 })
  @ApiOperation({ summary: 'Obtener una tarea por id' })
  findOne(@Param('id', ParseIntPipe) id: number): Promise<Task> {
    return this.tasksService.findOne(id);
  }

  @Patch(':id')
  @ApiParam({ name: 'id', type: Number, example: 1 })
  @ApiOperation({ summary: 'Actualizar una tarea (parcial)' })
  update(
    @Param('id', ParseIntPipe) id: number,
    @Body() updateTaskDto: UpdateTaskDto,
  ): Promise<Task> {
    return this.tasksService.update(id, updateTaskDto);
  }

  @Delete(':id')
  @ApiParam({ name: 'id', type: Number, example: 1 })
  @ApiOperation({ summary: 'Eliminar una tarea' })
  remove(@Param('id', ParseIntPipe) id: number): Promise<Task> {
    return this.tasksService.remove(id);
  }
}
