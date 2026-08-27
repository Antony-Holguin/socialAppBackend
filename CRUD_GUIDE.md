# Guía de CRUDs en este proyecto (NestJS + Prisma + JWT + Swagger)

> Pensada para este repo: NestJS 10, Prisma 7 (driver-adapter pg), Passport JWT y Swagger ya montados. Cubre relaciones 1-1, 1-N, N-M, rutas públicas, decoradores útiles y patrones repetibles.

---

## Índice

1. [Anatomía de un módulo CRUD](#1-anatom%C3%ADa-de-un-m%C3%B3dulo-crud)
2. [Relaciones y cómo se ven en Prisma + Nest](#2-relaciones-y-c%C3%B3mo-se-ven-en-prisma--nest)
   - 1-a-1 (`User` ↔ `Profile`)
   - 1-a-N (`User` → `Task`) ← ya lo tienes
   - N-a-M (`User` ↔ `Role` con tabla intermedia)
3. [DTOs: Create / Update / Query](#3-dtos-create--update--query)
4. [Service: el patrón PrismaService](#4-service-el-patr%C3%B3n-prismaservice)
5. [Controller: decoradores de Nest y Swagger](#5-controller-decoradores-de-nest-y-swagger)
6. [Decoradores personalizados útiles](#6-decoradores-personalizados-%C3%BAtiles)
   - `@Public()` (saltar JWT en una ruta)
   - `@CurrentUser()` (leer el usuario del request)
   - `@Roles()` (RBAC)
7. [Swagger: documentar bien los endpoints](#7-swagger-documentar-bien-los-endpoints)
8. [Manejo de errores y validaciones](#8-manejo-de-errores-y-validaciones)
9. [Tests: mocks de PrismaService](#9-tests-mocks-de-prismaservice)
10. [Snippets listos para copiar](#10-snippets-listos-para-copiar)
11. [Convenciones del proyecto](#11-convenciones-del-proyecto)
11. [Convenciones del proyecto](#11-convenciones-del-proyecto)

---

## 1. Anatomía de un módulo CRUD

Cada recurso vive en `src/<recurso>/` con esta estructura:

```
src/<recurso>/
├── <recurso>.module.ts
├── <recurso>.controller.ts
├── <recurso>.service.ts
├── dto/
│   ├── create-<recurso>.dto.ts
│   ├── update-<recurso>.dto.ts
│   └── query-<recurso>.dto.ts        # opcional, para paginación/filtros
└── entities/
    └── <recurso>.entity.ts           # solo Swagger (forma de la respuesta)
```

**Reglas del proyecto**:
- Todas las rutas van protegidas por JWT por defecto (`JwtAuthGuard` global en `app.module.ts`).
- Para abrir una ruta se usa `@Public()`.
- Para leer el usuario actual se usa `@CurrentUser()`.
- El service siempre usa `select` (nunca `include` que devuelva datos sensibles).
- El controller nunca toca Prisma directo.

---

## 2. Relaciones y cómo se ven en Prisma + Nest

### 2.1 Uno-a-N (`User` → `Task`) — ejemplo real ya en este repo

**Prisma** (`prisma/models/user.prisma` y `tasks.prisma`):

```prisma
model User {
  id    Int    @id @default(autoincrement())
  email String @unique
  tasks Task[]
  @@map("users")
}

model Task {
  id       Int      @id @default(autoincrement())
  title    String
  active   Boolean  @default(true)
  authorId Int
  author   User     @relation(fields: [authorId], references: [id])
  @@map("tasks")
}
```

**Cómo se traduce en Prisma Client**:

| Desde | Código | Devuelve |
|-------|--------|----------|
| Task → User | `task.author` | `User` o `null` |
| User → Tasks | `user.tasks` | `Task[]` |
| Filtro | `prisma.task.findMany({ where: { authorId } })` | tareas del autor |
| Filtro anidado | `prisma.user.findUnique({ where: { id }, include: { tasks: true } })` | user con sus tareas |

**Service — `findAll` con JOIN del autor**:

```ts
return this.prisma.task.findMany({
  select: {
    id: true,
    title: true,
    active: true,
    authorId: true,
    createdAt: true,
    updatedAt: true,
    author: { select: { id: true, name: true, email: true } },
  },
  orderBy: { id: 'desc' },
});
```

**Service — crear tarea para el usuario autenticado**:

```ts
async create(dto: CreateTaskDto, user: JwtUser) {
  return this.prisma.task.create({
    data: { title: dto.title, active: dto.active ?? true, authorId: user.id },
    select: this.publicSelect,
  });
}
```

> **Regla de oro**: el `authorId` **nunca** viene del body. Siempre del JWT.

---

### 2.2 Uno-a-uno (`User` ↔ `Profile`)

Útil para datos opcionales que no quieres en la misma tabla (evitas `null` por todas partes).

**Prisma**:

```prisma
model User {
  id      Int      @id @default(autoincrement())
  email   String   @unique
  profile Profile?
}

model Profile {
  id     Int    @id @default(autoincrement())
  bio    String?
  avatar String?
  userId Int    @unique                  // <- @unique hace 1-1
  user   User   @relation(fields: [userId], references: [id])
}
```

**Service — leer user + profile en una sola query**:

```ts
return this.prisma.user.findUnique({
  where: { id },
  select: {
    id: true, email: true, name: true,
    profile: { select: { id: true, bio: true, avatar: true } },
  },
});
```

**Service — crear profile a partir del usuario autenticado**:

```ts
async createProfile(dto: CreateProfileDto, user: JwtUser) {
  return this.prisma.profile.upsert({
    where: { userId: user.id },
    update: { bio: dto.bio, avatar: dto.avatar },
    create: { bio: dto.bio, avatar: dto.avatar, userId: user.id },
    select: { id: true, bio: true, avatar: true, userId: true },
  });
}
```

`upsert` es el patrón natural para 1-1 cuando el recurso "pertenece" a otro: si ya existe lo actualiza, si no lo crea.

---

### 2.3 Muchos-a-Muchos (`User` ↔ `Role`)

Aquí **necesitas una tabla intermedia explícita** si quieres campos extra (fecha de asignación, asignado por, etc.). Prisma también puede generar la implícita, pero la explícita es más clara.

**Prisma — modelo explícito recomendado**:

```prisma
model User {
  id    Int    @id @default(autoincrement())
  email String @unique
  roles UserRole[]                  // <- lado inverso
}

model Role {
  id    Int        @id @default(autoincrement())
  name  String     @unique
  users UserRole[]
}

model UserRole {                    // <- tabla intermedia
  userId     Int
  roleId     Int
  assignedAt DateTime @default(now())
  user User @relation(fields: [userId], references: [id])
  role Role @relation(fields: [roleId], references: [id])
  @@id([userId, roleId])            // <- PK compuesta
}
```

**Service — asignar N roles a un usuario**:

```ts
async setUserRoles(userId: number, roleIds: number[]) {
  return this.prisma.$transaction([
    this.prisma.userRole.deleteMany({ where: { userId } }),
    this.prisma.userRole.createMany({
      data: roleIds.map((roleId) => ({ userId, roleId })),
    }),
    this.prisma.user.findUniqueOrThrow({
      where: { id: userId },
      select: { id: true, email: true, roles: { select: { role: true } } },
    }),
  ]);
}
```

> `$transaction([...])` ejecuta las 3 queries en orden, atómicamente. Si algo falla, todo se revierte.

**Service — leer roles de un usuario**:

```ts
return this.prisma.user.findUnique({
  where: { id: userId },
  select: {
    id: true,
    email: true,
    roles: {
      select: {
        assignedAt: true,
        role: { select: { id: true, name: true } },
      },
    },
  },
});
```

**Service — agregar UN rol sin borrar los existentes**:

```ts
this.prisma.userRole.create({ data: { userId, roleId } });
```

---

### 2.4 Resumen rápido: cómo navega cada relación

| Relación | FK vive en | Prisma Client |
|----------|-----------|---------------|
| 1-N | lado N | `padre.hijos[]`, `hijo.padre` |
| 1-1 | cualquiera (con `@unique`) | `a.profile`, `profile.a` |
| N-M | tabla intermedia (con `@@id([a,b])`) | `user.roles[]`, `role.users[]` |

---

## 3. DTOs: Create / Update / Query

### 3.1 Create

```ts
// src/users/dto/create-user.dto.ts
import { ApiProperty } from '@nestjs/swagger';
import { IsEmail, IsString, MinLength, IsOptional, IsBoolean } from 'class-validator';

export class CreateUserDto {
  @ApiProperty({ example: 'Alice' })
  @IsString() @MinLength(2)
  name!: string;

  @ApiProperty({ example: 'alice@example.com', format: 'email' })
  @IsEmail()
  email!: string;

  @ApiProperty({ example: 'S3cretP@ss', minLength: 8, writeOnly: true })
  @IsString() @MinLength(8)
  password!: string;

  @ApiProperty({ example: true, required: false, default: true })
  @IsOptional() @IsBoolean()
  active?: boolean;
}
```

### 3.2 Update — todo opcional

```ts
// src/users/dto/update-user.dto.ts
import { PartialType } from '@nestjs/swagger';
import { CreateUserDto } from './create-user.dto';

export class UpdateUserDto extends PartialType(CreateUserDto) {}
```

`PartialType` clona el DTO y le pone `?` a cada campo. **Hereda todas las validaciones** (longitud mínima, email, etc.).

### 3.3 Query — paginación y filtros

```ts
// src/tasks/dto/query-tasks.dto.ts
import { ApiPropertyOptional } from '@nestjs/swagger';
import { Type } from 'class-transformer';
import { IsInt, IsOptional, Max, Min } from 'class-validator';

export class QueryTasksDto {
  @ApiPropertyOptional({ example: 1, default: 1 })
  @IsOptional() @Type(() => Number) @IsInt() @Min(1)
  page = 1;

  @ApiPropertyOptional({ example: 20, default: 20, maximum: 100 })
  @IsOptional() @Type(() => Number) @IsInt() @Min(1) @Max(100)
  limit = 20;

  @ApiPropertyOptional({ example: true, default: true })
  @IsOptional() @Type(() => Boolean)
  active?: boolean;
}
```

> `@Type(() => Number)` es **obligatorio** porque `class-transformer` no convierte query strings (`?page=1`) a número automáticamente.

---

## 4. Service: el patrón PrismaService

```ts
@Injectable()
export class TasksService {
  constructor(private readonly prisma: PrismaService) {}

  // select reusable: si añades un campo nuevo, lo añades aquí y todos
  // los métodos lo heredan. Nunca filtres password u otros campos sensibles.
  private readonly publicSelect = {
    id: true, title: true, active: true,
    authorId: true, createdAt: true, updatedAt: true,
  } as const;

  // CREATE
  async create(dto: CreateTaskDto, user: JwtUser) {
    return this.prisma.task.create({
      data: { ...dto, authorId: user.id },
      select: this.publicSelect,
    });
  }

  // LIST con paginación
  findAll(q: QueryTasksDto) {
    const { page, limit, active } = q;
    return this.prisma.task.findMany({
      where: { active },
      select: this.publicSelect,
      orderBy: { id: 'desc' },
      skip: (page - 1) * limit,
      take: limit,
    });
  }

  // GET ONE con manejo de 404
  async findOne(id: number) {
    const task = await this.prisma.task.findUnique({
      where: { id }, select: this.publicSelect,
    });
    if (!task) throw new NotFoundException(`Task #${id} not found`);
    return task;
  }

  // UPDATE
  update(id: number, dto: UpdateTaskDto) {
    return this.prisma.task.update({
      where: { id }, data: dto, select: this.publicSelect,
    });
  }

  // DELETE
  remove(id: number) {
    return this.prisma.task.delete({
      where: { id }, select: this.publicSelect,
    });
  }
}
```

### Excepciones típicas de Prisma y cómo mapearlas

| Error Prisma | HTTP | Cómo |
|--------------|------|------|
| `P2002` (unique constraint) | 409 Conflict | `throw new ConflictException(...)` |
| `P2025` (record not found) | 404 | `findUniqueOrThrow` o chequeo manual |
| FK violation | 400/409 | validar antes o capturar `P2003` |

Helper recomendado:

```ts
private handlePrismaError(e: unknown, ctx: string): never {
  if (e instanceof Prisma.PrismaClientKnownRequestError) {
    if (e.code === 'P2002') throw new ConflictException(`${ctx} ya existe`);
    if (e.code === 'P2025') throw new NotFoundException(`${ctx} no encontrado`);
  }
  throw e;
}
```

---

## 5. Controller: decoradores de Nest y Swagger

```ts
@ApiTags('tasks')                   // agrupa en Swagger
@ApiBearerAuth('bearer')             // muestra el candado
@Controller('tasks')
export class TasksController {
  constructor(private readonly svc: TasksService) {}

  @Post()
  @ApiOperation({ summary: 'Crear tarea' })
  create(@CurrentUser() user: JwtUser, @Body() dto: CreateTaskDto) {
    return this.svc.create(dto, user);
  }

  @Get()
  @ApiOperation({ summary: 'Listar tareas' })
  findAll(@Query() q: QueryTasksDto) {
    return this.svc.findAll(q);
  }

  @Get(':id')
  @ApiParam({ name: 'id', type: Number, example: 1 })
  findOne(@Param('id', ParseIntPipe) id: number) {  // <- valida y convierte
    return this.svc.findOne(id);
  }

  @Patch(':id')
  update(
    @Param('id', ParseIntPipe) id: number,
    @Body() dto: UpdateTaskDto,
  ) {
    return this.svc.update(id, dto);
  }

  @Delete(':id')
  remove(@Param('id', ParseIntPipe) id: number) {
    return this.svc.remove(id);
  }
}
```

### Decoradores de Nest más usados

| Decorador | Sirve para |
|-----------|------------|
| `@Body()` | Body de la request (ya validado por el `ValidationPipe` global) |
| `@Param('id')` | Params de la URL (`/users/:id`) |
| `@Query()` | Query string (`?page=1&limit=20`) |
| `@Req()` | Request entera (último recurso) |
| `@Headers('authorization')` | Un header específico |
| `@Ip()` | IP del cliente |
| `@HttpCode(204)` | Cambiar el código por defecto |
| `@Header('Cache-Control', 'none')` | Setear un header de respuesta |
| `@Param('id', ParseIntPipe)` | Validar y convertir en línea |

---

## 6. Decoradores personalizados útiles

### 6.1 `@Public()` — saltar JWT en una ruta

Ya lo tienes listo en `src/auth/decorators/public.decorator.ts`. Uso:

```ts
@Public()
@Get('health')
health() { return { ok: true }; }
```

Y el guard lo respeta con `Reflector`:

```ts
// src/auth/guards/jwt-auth.guard.ts
@Injectable()
export class JwtAuthGuard extends AuthGuard('jwt') {
  constructor(private reflector: Reflector) { super(); }

  canActivate(ctx: ExecutionContext) {
    const isPublic = this.reflector.getAllAndOverride<boolean>('isPublic', [
      ctx.getHandler(), ctx.getClass(),
    ]);
    if (isPublic) return true;
    return super.canActivate(ctx);
  }
}
```

### 6.2 `@CurrentUser()` — leer el usuario del JWT (recomendado)

Evita usar `@Req() req` y hacer `(req as any).user`.

**`src/auth/decorators/current-user.decorator.ts`**:

```ts
import { createParamDecorator, ExecutionContext } from '@nestjs/common';

export const CurrentUser = createParamDecorator(
  (_data: unknown, ctx: ExecutionContext) => {
    const req = ctx.switchToHttp().getRequest();
    return req.user;
  },
);
```

Uso:

```ts
@Post()
create(@CurrentUser() user: JwtUser, @Body() dto: CreateTaskDto) {
  return this.svc.create(dto, user);
}
```

Para tipar bien el usuario, declara en `src/auth/types/jwt-user.type.ts`:

```ts
export type JwtUser = { id: number; email: string };
```

Y declara un módulo de augmentación (en `src/types/express.d.ts`) para que TS sepa que `req.user` es `JwtUser` sin casts:

```ts
import 'express';
declare global {
  namespace Express {
    interface Request { user?: { id: number; email: string }; }
  }
}
```

### 6.3 `@Roles()` — RBAC básico

**`src/auth/decorators/roles.decorator.ts`**:

```ts
import { SetMetadata } from '@nestjs/common';
export const ROLES_KEY = 'roles';
export const Roles = (...roles: string[]) => SetMetadata(ROLES_KEY, roles);
```

**Guard**:

```ts
@Injectable()
export class RolesGuard implements CanActivate {
  constructor(private reflector: Reflector) {}

  canActivate(ctx: ExecutionContext): boolean {
    const required = this.reflector.getAllAndOverride<string[]>(ROLES_KEY, [
      ctx.getHandler(), ctx.getClass(),
    ]);
    if (!required || required.length === 0) return true;

    const { user } = ctx.switchToHttp().getRequest();
    // user.roles viene del JWT si tu JwtStrategy.validate los carga
    return required.some((r) => user?.roles?.includes(r));
  }
}
```

Registra el guard **después** del de JWT en `app.module.ts` para que el usuario ya esté cargado:

```ts
providers: [
  { provide: APP_GUARD, useClass: JwtAuthGuard },
  { provide: APP_GUARD, useClass: RolesGuard },
],
```

Uso:

```ts
@Roles('admin')
@Delete(':id')
remove(@Param('id', ParseIntPipe) id: number) {
  return this.svc.remove(id);
}
```

---

## 7. Swagger: documentar bien los endpoints

Lo principal ya lo conoces (`@ApiTags`, `@ApiOperation`, `@ApiBearerAuth`, `@ApiParam`). Algunos extras:

```ts
@ApiQuery({ name: 'page', required: false, type: Number, example: 1 })
@ApiResponse({ status: 200, type: [Task] })
@ApiResponse({ status: 404, description: 'Task no encontrada' })
@ApiResponse({ status: 401, description: 'Sin token o token inválido' })
@Get()
findAll(@Query() q: QueryTasksDto) { ... }
```

Si quieres documentar **dos respuestas posibles** (éxito y error):

```ts
@ApiOkResponse({ description: 'Lista paginada', type: [Task] })
@ApiUnauthorizedResponse({ description: 'Sin token' })
```

Para body con ejemplos múltiples:

```ts
@ApiBody({
  type: CreateUserDto,
  examples: {
    a: { summary: 'Admin', value: { name: 'Root', email: 'r@x.com', password: 'P@ssw0rd!' } },
    b: { summary: 'Cliente', value: { name: 'Ana', email: 'a@x.com', password: 'P@ssw0rd!' } },
  },
})
```

---

## 8. Manejo de errores y validaciones

- **`ValidationPipe` global** en `main.ts` (ya lo tienes) valida automáticamente los DTOs con `class-validator`.
- Para errores custom usa las built-in:
  - `BadRequestException` (400)
  - `UnauthorizedException` (401)
  - `ForbiddenException` (403)
  - `NotFoundException` (404)
  - `ConflictException` (409)
- Para mapear errores de Prisma usa el helper del paso 4.

Validación cruzada (ej. "el `endDate` debe ser mayor que `startDate`"):

```ts
@ValidatorConstraint()
class IsAfterStart implements ValidatorConstraintInterface {
  validate(end: string, args: ValidationArguments) {
    const dto = args.object as CreateEventDto;
    return new Date(end) > new Date(dto.startDate);
  }
}

export class CreateEventDto {
  @IsDateString() startDate!: string;
  @IsDateString() @Validate(IsAfterStart) endDate!: string;
}
```

---

## 9. Tests: mocks de PrismaService

Patrón mínimo para unit tests del service:

```ts
const prismaMock = {
  task: {
    create: jest.fn(),
    findMany: jest.fn(),
    findUnique: jest.fn(),
    update: jest.fn(),
    delete: jest.fn(),
  },
} as unknown as PrismaService;

const service = new TasksService(prismaMock);
```

Para e2e con supertest:

```ts
beforeAll(async () => {
  const moduleRef = await Test.createTestingModule({
    imports: [AppModule],
  }).compile();
  app = moduleRef.createNestApplication();
  await app.init();
});
```

Para generar un JWT válido en tests:

```ts
const token = app.get(JwtService).sign({ sub: 1, email: 'a@x.com' });
await request(app.getHttpServer())
  .get('/tasks')
  .set('Authorization', `Bearer ${token}`)
  .expect(200);
```

---

## 10. Snippets listos para copiar

### Módulo CRUD mínimo

```ts
@Module({
  controllers: [XController],
  providers: [XService],
})
export class XModule {}
```

Si necesitas `PrismaService` no tienes que importar `PrismaModule` — Nest lo resuelve por ser singleton global.

### Service CRUD genérico

```ts
@Injectable()
export abstract class CrudService<TCreate, TUpdate, TEntity> {
  protected abstract readonly model: any;
  protected abstract readonly select: any;
  protected abstract readonly entityName: string;

  create(dto: TCreate, ownerId: number) {
    return this.model.create({ data: { ...dto, authorId: ownerId }, select: this.select });
  }
  list() { return this.model.findMany({ select: this.select }); }
  async get(id: number) {
    const x = await this.model.findUnique({ where: { id }, select: this.select });
    if (!x) throw new NotFoundException(`${this.entityName} #${id} not found`);
    return x;
  }
  update(id: number, dto: TUpdate) {
    return this.model.update({ where: { id }, data: dto, select: this.select });
  }
  remove(id: number) {
    return this.model.delete({ where: { id }, select: this.select });
  }
}
```

> Útil cuando tienes varios CRUDs muy parecidos. Si solo tienes 1-2, mejor service concreto (más legible).

### Controller CRUD genérico (con auth)

```ts
@ApiTags('recurso') @ApiBearerAuth('bearer')
@Controller('recurso')
export class XController {
  constructor(private readonly svc: XService) {}

  @Post()  create(@CurrentUser() u: JwtUser, @Body() dto: CreateXDto) { return this.svc.create(dto, u.id); }
  @Get()   list()  { return this.svc.list(); }
  @Get(':id') get(@Param('id', ParseIntPipe) id: number) { return this.svc.get(id); }
  @Patch(':id') upd(@Param('id', ParseIntPipe) id: number, @Body() dto: UpdateXDto) { return this.svc.update(id, dto); }
  @Delete(':id') del(@Param('id', ParseIntPipe) id: number) { return this.svc.remove(id); }
}
```

---

## 11. Convenciones del proyecto

Pequeñas reglas que se aplican en **todo** el repo, no solo en los CRUDs. Si trabajas en equipo, valen la pena.

### 11.1 `async` + `return await`: una sola forma de escribir async

En los services de Nest hay **una sola regla**:

| Situación | Forma correcta |
|-----------|----------------|
| La firma devuelve `Promise<X>` | `async` + cada `return <promesa>` con `return await` |
| La firma devuelve un valor síncrono (`string`, `number`, `Date`, etc.) | sin `async`, sin `await` |

```ts
// ✅ correcto: async + return await sobre promesas
async findOne(id: number): Promise<Task> {
  const task = await this.prisma.task.findUnique({ where: { id }, select: this.publicSelect });
  if (!task) throw new NotFoundException(`Task #${id} not found`);
  return task;
}

async findAll(q: QueryTasksDto): Promise<Task[]> {
  return await this.prisma.task.findMany({ where: { active: q.active }, select: this.publicSelect });
}

// ✅ correcto: sync de verdad, devuelve string
generate(): string {
  return randomBytes(64).toString('base64url');
}

// ❌ incorrecto: async sin await en el return
async findAll(): Promise<Task[]> {
  return this.prisma.task.findMany({ ... });
}

// ❌ incorrecto: try/catch NO atrapa sin await
async findOne(id: number) {
  try {
    return this.prisma.task.findUnique({ ... });   // la promesa escapa del try
  } catch (e) {
    throw new NotFoundException(...);              // nunca se ejecuta
  }
}
```

**Por qué `return await` y no solo `return`**:

1. **Try/catch funciona**. Sin `await`, un `return <promesa>` dentro de un `try` se devuelve tal cual — los rechazos **no son atrapados** por el `catch` (es uno de los bugs más sutiles de JS).
2. **Consistencia visual**: el cuerpo de la función se lee 100% async. Quien lo lee sabe que toda la función es async, sin tener que diferenciar qué línea devuelve promesa y cuál no.
3. **Linters lo exigen**. Reglas como `@typescript-eslint/no-floating-promises` o `no-return-await` configurable marcan estos patrones.
4. **Stack traces más limpios**. V8 conserva mejor el origen del error cuando pasas por `await`.

**Cuándo NO usar `return await`**:

- Devuelves un objeto literal: `return { id, name, email }` (no es promesa).
- Devuelves una variable ya `await`-eada: `const user = await ...; return user;` (ya no es promesa).
- Devuelves un tipo primitivo: `return result.count`.

### 11.2 `select` explícito en Prisma

Nunca `prisma.model.findMany()` a secas. Define un `private readonly publicSelect` en cada service y úsalo en todos los métodos. Si mañana agregas un campo sensible a un modelo, lo agregas a `select` solo si quieres exponerlo.

```ts
private readonly publicSelect = { id: true, title: true /* ... */ } as const;
```

### 11.3 IDs siempre como `number` con `ParseIntPipe`

Los params `:id` llegan como `string` desde la URL. Convierte en el borde con el pipe:

```ts
@Get(':id')
findOne(@Param('id', ParseIntPipe) id: number) { ... }
```

Beneficio: si llega `id="abc"`, Nest responde `400 Bad Request` automáticamente y el service siempre recibe un `number`.

### 11.4 El usuario autenticado va por `@CurrentUser()`

Nunca `@Req() req` ni casts a `any`. El decorator está en `src/auth/decorators/current-user.decorator.ts` y devuelve `JwtUser` ya tipado. La augmentación de tipos vive en `src/types/express.d.ts`.

```ts
@Post()
create(@CurrentUser() user: JwtUser, @Body() dto: CreateXDto) { ... }
```

### 11.5 `authorId`, `userId`, FKs **nunca** desde el body

Si una entidad pertenece a un usuario, el FK se toma del JWT en el service. Aceptarlo del body abre un agujero de seguridad (cualquiera crea tareas "como si fuera" otro usuario).

### 11.6 Errores Prisma mapeados a excepciones HTTP

No dejes que un `P2025` (record not found) o `P2002` (unique constraint) burbujee como 500. Usa el helper de la sección 4 o `findUniqueOrThrow` / `throw new NotFoundException(...)`.

### 11.7 Swagger obligatorio en endpoints nuevos

- `@ApiTags('recurso')` en el controller.
- `@ApiBearerAuth('bearer')` si la ruta está protegida.
- `@ApiOperation({ summary: '...' })` en cada handler.
- `@ApiParam` / `@ApiQuery` / `@ApiResponse` para documentar params, query y respuestas no obvias.

Si el endpoint aparece sin forma en `/docs`, falta `@ApiProperty` en algún DTO/entity.

### 11.8 Tests con `PrismaService` mockeado

En unit tests, mockea solo los métodos del modelo que el test ejercita:

```ts
{ provide: PrismaService, useValue: { task: { create: jest.fn(), findUnique: jest.fn() } } }
```

En e2e, no mockees — usa una base de datos de prueba (`DATABASE_URL` apuntando a otro schema) y `app.get(JwtService).sign(...)` para obtener un token válido.

---

## Checklist antes de hacer commit de un CRUD

- [ ] DTOs con `class-validator` y `@ApiProperty`.
- [ ] Service con `select` (nunca devolver el password u otros campos sensibles).
- [ ] Funciones `async` que devuelven `Promise` usan `return await` (sección 11.1).
- [ ] Funciones síncronas no se marcan `async` (sección 11.1).
- [ ] Errores Prisma mapeados a excepciones HTTP.
- [ ] Rutas que reciben `:id` usan `ParseIntPipe`.
- [ ] `@CurrentUser()` en lugar de `@Req()` cuando necesitas el usuario.
- [ ] `@Public()` solo en endpoints que de verdad deban ser públicos (healthcheck, login, register).
- [ ] Ownership en update/delete cuando aplique.
- [ ] Swagger cubre 200, 400, 401, 404 al menos.
- [ ] Si cambias Prisma: `npx prisma generate` y `npx prisma migrate dev`.

---

¿Quieres que te genere el siguiente CRUD concreto del proyecto (por ejemplo `comments` con relación 1-N a `tasks` y N-M a `users` para menciones), o prefieres primero aplicar este patrón al de `tasks` que ya tienes abierto?
