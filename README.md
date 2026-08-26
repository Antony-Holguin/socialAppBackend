# social-app

Backend **NestJS 11 + Prisma 7 (driver adapter)** con autenticación JWT (access + refresh rotativo). Pensado como punto de partida para una *social app*: ya trae `User` + `RefreshToken` modelados, auth completa, validación global y suite de pruebas (unit + e2e contra Postgres).

---

## 1. Stack

| Capa | Tecnología |
|---|---|
| Framework | NestJS 11 (`platform-express`) |
| Lenguaje | TypeScript 5.7 (`module: nodenext`) |
| ORM | Prisma 7 con `@prisma/adapter-pg` (driver adapter nativo) |
| DB | PostgreSQL |
| Validación | `class-validator` + `class-transformer` + `ValidationPipe` global |
| Auth | `@nestjs/jwt`, `@nestjs/passport`, `passport-jwt` |
| Hashing | `argon2` (argon2id) |
| Tests | Jest 30 + ts-jest + supertest |
| Lint / Format | ESLint 9 (flat config, type-checked) + Prettier |

> ORM huérfano: `typeorm` y `@nestjs/typeorm` siguen instalados pero **no se usan**. Limpieza sugerida antes de producción.

---

## 2. Prerequisitos

- **Node.js ≥ 20** (probado en Node 24).
- **PostgreSQL ≥ 14** accesible en `localhost:5432` (o ajustar `DATABASE_URL`).
- `openssl` para generar los secretos JWT (o cualquier generador de bytes aleatorios).

---

## 3. Setup rápido

```bash
# 1) Instalar dependencias
npm install

# 2) Variables de entorno
cat > .env <<'EOF'
PORT=3000
DATABASE_URL="postgresql://USER:PASS@localhost:5432/socialapp"
JWT_ACCESS_SECRET="$(openssl rand -base64 48)"
JWT_REFRESH_SECRET="$(openssl rand -base64 48)"
JWT_ACCESS_TTL=15m
JWT_REFRESH_TTL=7d
EOF

# 3) Crear esquema en la DB (la migración crea tablas; Prisma exige DB existente)
createdb socialapp      # o desde tu cliente Postgres preferido

# 4) Aplicar migraciones + generar cliente
npx prisma migrate dev
npx prisma generate

# 5) Arrancar
npm run start:dev
```

Cuando arranque verás `Listening on http://localhost:3000`.

---

## 4. Variables de entorno

| Variable | Requerida | Default | Descripción |
|---|---|---|---|
| `PORT` | no | `3000` | Puerto HTTP |
| `DATABASE_URL` | **sí** | — | Cadena de conexión Postgres (Prisma driver adapter) |
| `JWT_ACCESS_SECRET` | **sí** | — | Firma del access token. ≥ 32 bytes aleatorios, **único**. |
| `JWT_REFRESH_SECRET` | **sí** | — | Firma del refresh token. Distinto del access. |
| `JWT_ACCESS_TTL` | no | `15m` | Vida del access token (formato `ms`/`StringValue`: `15m`, `1h`, `7d`) |
| `JWT_REFRESH_TTL` | no | `7d` | Vida de un refresh token antes de expirar |

> `.env` está en `.gitignore`. Genera los secretos **nunca** los commitees.

---

## 5. Estructura del proyecto

```
social-app/
├── prisma/
│   ├── schema.prisma                  # generator + datasource
│   ├── prisma.config.ts               # defineConfig de Prisma 7
│   ├── prisma.module.ts               # @Global() PrismaModule
│   ├── prisma.service.ts              # PrismaClient + PrismaPg adapter
│   ├── models/
│   │   ├── user.prisma                # User + refreshTokens[]
│   │   ├── role.prisma                # Role (sin relación, aún)
│   │   └── refresh-token.prisma       # RefreshToken (hashed, con expiración)
│   └── migrations/                    # Historial versionado
│
├── src/
│   ├── main.ts                        # Bootstrap + ValidationPipe global
│   ├── app.module.ts                  # Raíz: APP_GUARD → JwtAuthGuard
│   ├── app.controller.ts              # GET / (público, “Hello World”)
│   │
│   ├── auth/                          # Módulo de autenticación
│   │   ├── auth.module.ts
│   │   ├── auth.controller.ts         # /auth/{register,login,refresh,logout,me}
│   │   ├── auth.service.ts            # Lógica de tokens y rotación
│   │   ├── password.service.ts        # argon2id wrap (inyectable y testeable)
│   │   ├── refresh-token.service.ts   # Crear / buscar / rotar / revocar
│   │   ├── decorators/
│   │   │   ├── public.decorator.ts    # @Public() — bypass del guard
│   │   │   └── current-user.decorator.ts  # @CurrentUser() desde req.user
│   │   ├── guards/
│   │   │   └── jwt-auth.guard.ts      # AuthGuard('jwt') + reflector @Public
│   │   ├── strategies/
│   │   │   └── jwt.strategy.ts        # passport-jwt + validate → req.user
│   │   ├── dto/
│   │   │   ├── register.dto.ts        # name / email / password (min 8)
│   │   │   ├── login.dto.ts           # email / password (min 1)
│   │   │   └── refresh.dto.ts         # refreshToken (Length 20–2048)
│   │   └── types/
│   │       └── jwt-payload.type.ts    # JwtPayload y JwtUser
│   │
│   ├── users/                         # CRUD `/users` (todos protegidos)
│   │   ├── users.module.ts
│   │   ├── users.controller.ts
│   │   ├── users.service.ts           # Implementación real Prisma-backed
│   │   ├── entities/user.entity.ts    # Marcador TS (vacío)
│   │   └── dto/{create,update}-user.dto.ts
│   │
│   └── generated/prisma/              # Cliente Prisma generado (no editar)
│
└── test/
    ├── app.e2e-spec.ts                # smoke e2e de GET /
    └── auth.e2e-spec.ts               # register / login / me / refresh / logout
```

---

## 6. Workflow de Prisma

Para el día a día (añadir modelo, modificar campo, renombrar, reset, debug drift, etc.) consulta **[`MIGRATIONS.md`](./MIGRATIONS.md)** — guía práctica específica de este proyecto.

El proyecto sigue la **`MIGRATIONS.md` en la raíz** para el flujo cotidiano. Detrás también está la guía genérica `prisma-7-migrations-guide.md` (teoría: shadow DB, drift, deploy vs dev).

Resumen en una línea:

### Editar un modelo
1. Modifica el archivo en `prisma/models/*.prisma`.
2. Crea migración + aplica a la DB:
   ```bash
   npx prisma migrate dev --name <nombre_descriptivo_en_snake_inglés>
   ```
3. Regenera el cliente (`prisma migrate dev` ya lo hace en Prisma 7.10, pero si algo lo necesita):
   ```bash
   npx prisma generate
   ```

### Ver / inspeccionar la DB
- Cliente gráfico: `npx prisma studio` (abre navegador en `:5555`).
- Solo el historial: `ls prisma/migrations/`.

### Convenciones
- Una migración = un cambio lógico (p.ej. `create_refresh_tokens`, `add_phone_to_users`).
- **No borres** migraciones ya versionadas; el historial es parte del proyecto.
- Para revertir en local puedes hacer `migrate reset` (destruye y re-aplica).

---

## 7. Cómo correr la app

| Comando | Qué hace |
|---|---|
| `npm run start` | Arranca sin watch (build + node dist) |
| `npm run start:dev` | `nest start --watch` (recomendado en dev) |
| `npm run start:debug` | Igual + `--inspect-brk` para el debugger |
| `npm run start:prod` | Ejecuta el bundle ya compilado (`node dist/main`) |
| `npm run build` | Solo compila (`tsconfig.build.json` → `dist/`) |
| `npm run lint` | ESLint con auto-fix sobre `{src,test}/**/*.ts` |
| `npm run format` | Prettier sobre `src/` y `test/` |

---

## 8. Tests

```bash
# Unit (jest config en package.json): 6 suites, 17 tests
npm test

# E2E (jest config en test/jest-e2e.json): arranca AppModule + Prisma real
# ⚠️ antes de cada test: borra todas las filas de `User` y `RefreshToken`.
npm run test:e2e
```

- `npm run test:cov` → cobertura Jest
- `npm run test:watch` → mode watch
- `npm run test:debug` → nodo con `--inspect-brk` + ts-jest

> El e2e requiere la DB accesible. Antes de tocar tu DB de producción, considera crear una DB paralela para tests (p.ej. `socialapp_test`) y apuntar `DATABASE_URL` allí.

### Lo que cubren los tests hoy

| Suite | Casos |
|---|---|
| `src/auth/auth.service.spec.ts` | register happy / duplicate; login bad-pwd / inactive / happy; refresh rotation / expired / user-missing; logout |
| `src/auth/strategies/jwt.strategy.spec.ts` | `validate(payload)` produce `{ id, email }` |
| `src/auth/guards/jwt-auth.guard.spec.ts` | short-circuit en `@Public()` / reflexión correcta |
| `src/users/users.service.spec.ts` | delegación `findAll` / `findByEmail` con Prisma mock |
| `test/auth.e2e-spec.ts` | register 201, duplicate 409, hash argon2, login ok/401, me con/sin bearer, refresh rotation invalida el viejo, logout invalida refresh |
| `test/app.e2e-spec.ts` | smoke `GET /` con `@Public()` |

---

## 9. API — Referencia rápida

> Todos los cuerpos usan `application/json`. **Todas las rutas están protegidas** salvo las marcadas con `@Public()`.

### Auth

| Método | Ruta | Auth | Body |
|---|---|---|---|
| `POST` | `/auth/register` | **Público** | `{ name, email, password }` |
| `POST` | `/auth/login` | **Público** | `{ email, password }` |
| `POST` | `/auth/refresh` | **Público** | `{ refreshToken }` |
| `POST` | `/auth/logout` | Bearer | `{ refreshToken }` |
| `GET`  | `/auth/me` | Bearer | — |

**Respuesta uniforme** para register / login / refresh:
```json
{
  "accessToken": "eyJhbGciOi...",
  "refreshToken": "Z9n9oQ...",
  "user": { "id": 1, "name": "Alice", "email": "alice@example.com", "active": true }
}
```

### Users (CRUD protegido — añadir `@Public()` si lo quieres público)

| Método | Ruta | Body | Descripción |
|---|---|---|---|
| `POST` | `/users` | `CreateUserDto` | Crear (¡ojo, no hashea!) — usa `/auth/register` para el flujo real |
| `GET`  | `/users` | — | Lista todos (proyectado sin `password`) |
| `GET`  | `/users/:id` | — | Detalle sin `password` |
| `PATCH`| `/users/:id` | `UpdateUserDto` | Update parcial |
| `DELETE`| `/users/:id` | — | Elimina |

---

## 10. Cómo funciona JWT aquí

1. **`/auth/register` o `/auth/login`** validan credenciales, y si todo va bien:
   - Generan `accessToken` firmado con `JWT_ACCESS_SECRET` (TTL 15 m, payload `{ sub, email }`).
   - Generan `refreshToken` opaco (86 chars base64url) y guardan `sha256(refreshToken)` en `RefreshToken` con `expiresAt` (7 d).
   - Devuelven ambos en la respuesta.

2. **`Authorization: Bearer <accessToken>`** se valida en cada request por `JwtAuthGuard`. El payload decodificado queda en `req.user` (`{ id, email }`); usa `@CurrentUser()` para extraerlo.

3. **`/auth/refresh`** recibe el `refreshToken` *en el body* (no en header — el refresh no es bearer-auth).
   - Busca por `sha256(refreshToken)` con `revokedAt: null` y `expiresAt > now`.
   - En una `prisma.$transaction` marca el viejo como `revokedAt = now()` e inserta el nuevo.
   - Devuelve un par nuevo. El viejo ya no sirve.

4. **`/auth/logout`** requiere bearer válido; revoca el refresh recibido (idempotente si ya estaba revocado).

### Por qué argon2id en passwords y sha256 en refresh tokens
- **argon2id**: passwords viven mucho, son baja entropía por usuario, lentitud KDF ayuda contra fuerza bruta offline.
- **sha256**: el refresh se valida *en cada request*. argon2 añadiría 50–200 ms a `/auth/refresh` sin proteger más (la amenaza es "DB leak → reuse", y sha256 ya impide eso con un dominio aleatorio de 64 bytes).

---

## 11. Añadir un endpoint nuevo

```ts
// src/<feature>/<feature>.controller.ts
import { Controller, Get, UseGuards } from '@nestjs/common';
import { CurrentUser } from 'src/auth/decorators/current-user.decorator';
import type { JwtUser } from 'src/auth/types/jwt-payload.type';

@Controller('posts')
export class PostsController {
  // Por defecto está PROTEGIDO. No hace falta añadir nada.
  @Get()
  list(@CurrentUser() user: JwtUser) {
    return { ownerId: user.id };
  }

  // Si quieres una ruta pública:
  @Public()
  @Get('public-feed')
  publicFeed() {
    return [];
  }
}
```

Para endpoints que **no** requieren autenticación basta con `@Public()`.

---

## 12. Hardening pendiente (no incluido todavía)

Esto sigue en la lista de pendientes. Sin pretender ser exhaustivo:

- **CORS selectivo** por entorno (`app.enableCors({ origin, credentials })`).
- **Helmet** para cabeceras (`@nestjs/platform-express` + `helmet`).
- **Rate-limiting** en `/auth/login` y `/auth/register` con `@nestjs/throttler`.
- **Logger** estructurado (`nestjs-pino`).
- **Compresión** con `compression`.
- **Validación de secreto en arranque** (`process.exit` si `JWT_*_SECRET` falta o < 32 bytes).
- **Refresh-token reuse detection** (campo `family` para revocar toda la cadena si se ve uno viejo dos veces).
- **Access-token revocation list** (Redis/DB para logout real de access tokens).
- **Limpiar `typeorm` + `@nestjs/typeorm`** que están instalados sin uso.
- **Versión del API**: `app.setGlobalPrefix('api/v1')` antes de exponer.
- **`updatedAt` en `User`** está sin `@default(now())` (es correcto para `@updatedAt`, pero hay que recordar que `prisma.user.create({...})` no setea `updatedAt` automáticamente porque `@updatedAt` solo se aplica en updates — Prisma lo rellena en update, en create queda a merced del input).

---

## 13. Troubleshooting

### `Error: JWT_ACCESS_SECRET is not defined`
ConfigModule está global (`isGlobal: true` en `app.module.ts`). Asegúrate de cargar `.env` **antes** de importar — el `ConfigModule.forRoot()` lo hace automáticamente, pero si lees el `.env` con `dotenv/config` en `main.ts` también vale.

### `Cannot find module 'prisma/prisma.service'`
tsconfig tiene `paths: { "prisma/*": ["prisma/*"] }`. Si ves este error en runtime, probablemente corres el build sin aliases. Verifica que `moduleNameMapper` está también en `test/jest-e2e.json`.

### `A dynamic import callback was invoked without --experimental-vm-modules`
Prisma 7 usa dynamic ESM imports para cargar el compilador WASM. El script `test:e2e` ya pasa el flag; si lo invocas a mano añade `NODE_OPTIONS=--experimental-vm-modules`.

### `prisma migrate dev` no aparece el cambio
- Asegúrate de haber editado el archivo bajo `prisma/models/*.prisma` (no solo `schema.prisma`).
- `prisma migrate dev --name ...` necesita una DB alcanzable. Si tira, prueba con `--create-only` para revisar el SQL antes.

### Los tests unitarios fallan porque no encuentra `@prisma/client/runtime/...`
Verifica que `moduleNameMapper` incluye `^(\\.{1,2}/.*)\\.js$": "$1"`. Es necesario por el `module: "nodenext"` del tsconfig.

### Argon2 falla al instalar
`argon2` requiere bindings nativos. Si no compila (`node-gyp`), alternativas:
- `bcrypt` (puro JS, más lento por hash pero portátil).
- `argon2` con prebuilt binarios — suele funcionar en Mac/Linux x64. En ARM/Raspberry Pi puede requerir `sudo apt install build-essential python3`.

---

## 14. Licencia

`UNLICENSED` (privado). Cambia la licencia en `package.json` si lo vas a publicar.
