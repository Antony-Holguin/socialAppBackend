# Migrations — guía práctica para este proyecto

Esta guía es **específica al proyecto** `social-app` (NestJS + Prisma 7 con driver adapter). Para el *background* teórico completo (cómo se compara con las migrations de Laravel, ciclo de deploy vs dev, qué es una shadow DB, etc.) consulta `prisma-7-migrations-guide.md` en la raíz. Aquí nos centramos en los comandos y atajos para el día a día.

---

## TL;DR

```bash
# Una línea por cambio
npx prisma migrate dev --name <nombre_descriptivo_en_snake>     # dev: crea + aplica + genera cliente
npx prisma migrate deploy                                       # prod/staging: solo aplica
npx prisma generate                                             # regenera el cliente sin migrar
npx prisma migrate reset                                        # ⚠️ borra y re-aplica TODO (dev only)
npx prisma studio                                               # UI web (puerto 5555) para inspeccionar
```

> **Prisma 7 — recuerda**: `migrate dev` *sí* ejecuta `prisma generate` por ti en esta versión (7.10) aunque la guía genérica diga lo contrario. Si ves un error de "module not found" tras una migración, ejecuta `npx prisma generate` a mano.

---

## Anatomía del esquema en este repo

Este proyecto **parte el `schema.prisma` en varios archivos**:

```
prisma/
├── schema.prisma              # generator + datasource. NO editas modelos aquí dentro.
├── models/
│   ├── user.prisma
│   ├── role.prisma            # huérfano, aún no tiene relación con User
│   └── refresh-token.prisma
└── migrations/                # historial versionado, NO borrar
    ├── 20260826001018_create_users/
    ├── 20260826001326_add_phone_to_users/
    ├── 20260826010006_create_users/   # ⚠️ hace DROP COLUMN phone (residuo)
    ├── 20260826010055_create_roles/
    └── 20260826015850_create_refresh_tokens/
```

`schema.prisma` apunta al directorio:

```prisma
generator client {
  provider = "prisma-client"
  output   = "../src/generated/prisma"
}

datasource db {
  provider = "postgresql"
}
```

> Fíjate: **no hay `url` en el `datasource`** — la URL viene de `prisma7.config.ts` → `env("DATABASE_URL")`. Eso es coherente con el driver adapter nativo de Prisma 7.

---

## Receta 1 — Añadir un modelo nuevo

Ejemplo: vas a necesitar un modelo `Post` con título, cuerpo y FK a `User`.

1. Crea `prisma/models/post.prisma`:
   ```prisma
   model Post {
     id        Int      @id @default(autoincrement())
     title     String
     body      String
     authorId  Int
     author    User     @relation(fields: [authorId], references: [id], onDelete: Cascade)
     createdAt DateTime @default(now())
     updatedAt DateTime @updatedAt

     @@index([authorId])
   }
   ```
2. Edita `prisma/models/user.prisma` y añade el back-reference:
   ```prisma
   model User {
     id            Int            @id @default(autoincrement())
     // ... campos actuales ...
     refreshTokens RefreshToken[]
     posts         Post[]
   }
   ```
3. Genera la migración:
   ```bash
   npx prisma migrate dev --name create_posts
   ```
   Prisma:
   - Compara `schema.prisma` + `models/*.prisma` contra la DB.
   - Genera el SQL en `prisma/migrations/<timestamp>_create_posts/migration.sql`.
   - Lo aplica a la DB.
   - Regenera el cliente en `src/generated/prisma/`.
4. Verifica el SQL generado:
   ```bash
   cat prisma/migrations/<timestamp>_create_posts/migration.sql
   ```
   Debería contener `CREATE TABLE "Post"` + `ALTER TABLE "Post" ADD CONSTRAINT ... FOREIGN KEY ...` + el `@@index`.
5. Commit:
   ```bash
   git add prisma/migrations/<timestamp>_create_posts/ prisma/models/post.prisma prisma/models/user.prisma
   git commit -m "feat: add Post model"
   ```

---

## Receta 2 — Modificar un modelo existente

Ejemplo: añadir `bio String?` a `User`.

1. Edita `prisma/models/user.prisma` y añade la línea:
   ```prisma
   model User {
     id        Int      @id @default(autoincrement())
     // ...
     bio       String?
     // ...
   }
   ```
2. `npx prisma migrate dev --name add_bio_to_users`
3. Revisa `prisma/migrations/<timestamp>_add_bio_to_users/migration.sql` — debería ser **solo** `ALTER TABLE "User" ADD COLUMN "bio" TEXT;`.

> ⚠️ **No hagas dos cambios no relacionados en la misma migración.** Mantén cada migración focalizada (un `ALTER`, una tabla, etc.). Eso te permite revertir con `migrate reset` o aplicar cherry-picked en otras ramas sin dolor.

---

## Receta 3 — Renombrar o eliminar una columna

Prisma trata los renombrados como `DROP COLUMN` + `ADD COLUMN`, **lo cual borra datos**. Para preservar datos tienes tres opciones:

### Opción A — Renombrar con `@@map` (limpio, sin pérdida)
Si solo cambias el nombre lógico:
```prisma
model User {
  displayName String @map("name")   // columna física sigue siendo `name`
}
```
Aquí no hay migración — solo se actualiza el cliente.

### Opción B — Renombrar la columna física con un rename manual
1. `npx prisma migrate dev --create-only --name rename_user_name_to_display_name`
2. Edita el SQL generado para usar `ALTER TABLE "User" RENAME COLUMN "name" TO "display_name";` (Prisma genera `DROP + ADD` por defecto).
3. Actualiza `schema.prisma` con el nuevo nombre.
4. `npx prisma migrate dev` aplica el cambio.

### Opción C — Solo cuando puedas permitirte perder datos
Migración normal + backfill en una segunda migración.

---

## Receta 4 — Crear la migración sin aplicarla todavía

Útil cuando quieres revisar el SQL antes de tocar la DB (p.ej. revisarlo en un PR).

```bash
npx prisma migrate dev --create-only --name <nombre>
```

Edita `prisma/migrations/<timestamp>_<nombre>/migration.sql` si lo necesitas, luego:

```bash
npx prisma migrate dev   # la aplica
```

---

## Receta 5 — Cambiar la estructura pero preservando filas existentes

Si Prisma detecta una operación **destructiva** (drop column, change type, drop table) durante `migrate dev`, te pregunta:

```
? Do you want to create a migration that drops the column?
 » Yes, drop the column
   No, cancel
```

Si dices **Yes**, se crea la migración destructiva. Si dices **No**, se aborta.

**Recomendación:** cancela, y considera:
1. Añadir la columna nueva (no destructiva).
2. Migración de datos que copia de la vieja a la nueva.
3. Migración final que borra la vieja (o déjala si es poco espacio).

---

## Receta 6 — Inspeccionar la DB con Prisma Studio

```bash
npx prisma studio
```

Abre `http://localhost:5555`. Lee y edita filas. **No usar contra la DB de producción** sin protección.

---

## Receta 7 — Resetear todo (⚠️ solo dev local)

Borra el schema y reaplica todas las migraciones desde cero:

```bash
npx prisma migrate reset
```

Confirmará el borrado antes de proceder. Útil cuando:
- Cambias el `provider` o la URL.
- Tienes drift entre migraciones y DB.
- Solo quieres empezar limpio durante desarrollo.

**Nunca** en CI ni contra producción.

---

## Snippets de SQL comunes que vas a querer escribir a mano

Si quieres más control, edita el `.sql` directamente. Prisma acepta cualquier SQL válido.

| Necesitas… | SQL |
|---|---|
| Añadir índice condicional único | `CREATE UNIQUE INDEX "User_phone_key" ON "User"("phone") WHERE "phone" IS NOT NULL;` |
| Borrar cascade | `ALTER TABLE "Post" DROP CONSTRAINT "Post_authorId_fkey", ADD CONSTRAINT "Post_authorId_fkey" FOREIGN KEY ("authorId") REFERENCES "User"("id") ON DELETE CASCADE;` |
| Renombrar columna preservando datos | `ALTER TABLE "User" RENAME COLUMN "name" TO "displayName";` |
| Backfill desde otra columna | `UPDATE "User" SET "displayName" = COALESCE(NULLIF("displayName", ''), "email");` |
| Crear tipo enum | Ver Receta 8 ↓ |

---

## Receta 8 — Enums

Prisma soporta `enum` en modelos:

```prisma
enum UserRole {
  USER
  ADMIN
  MODERATOR
}

model User {
  // ...
  role UserRole @default(USER)
}
```

La migración resultante crea el tipo PostgreSQL con `CREATE TYPE "UserRole" AS ENUM (...)` y lo usa en la columna.

> **Cambiar un enum después requiere recrearlo.** Prisma 7 soporta `prisma migrate diff` para generar SQL de union/intersection manualmente. Si te encuentras modificando enums en producción, lee la doc oficial primero — es el punto más frágil de Prisma.

---

## Snippets para NestJS tras una migración

### Tras añadir un modelo

1. Si vas a exponer endpoints, crea el módulo nuevo (`src/posts/`):
   ```ts
   // src/posts/posts.service.ts
   @Injectable()
   export class PostsService {
     constructor(private readonly prisma: PrismaService) {}
     list(authorId?: number) {
       return this.prisma.post.findMany({
         where: authorId ? { authorId } : undefined,
         orderBy: { createdAt: 'desc' },
       });
     }
   }
   ```
2. `src/posts/posts.module.ts` → `@Module({ controllers: [PostsController], providers: [PostsService] })`.
3. Importa en `app.module.ts`.

### Tras añadir un campo nuevo

Si quieres validarlo vía DTO, modifica el DTO existente o crea uno nuevo — el campo del modelo es **fuera de banda** del DTO, no se acoplan. Los DTOs solo validan lo que entra por HTTP.

### Tras añadir un índice

Nada que cambiar en código. Prisma lo aplica y el query planner de Postgres lo usa automáticamente.

---

## Rollbacks y debugging

| Síntoma | Qué hacer |
|---|---|
| Una migración falló a mitad | `npx prisma migrate resolve --applied <name>` o `--rolled-back <name>` para reconciliar el historial |
| Drift: tu DB tiene cambios que no están en el historial | `npx prisma migrate diff` para ver las diferencias; commitea una migración que las describa, o `migrate reset` si vas a empezar limpio |
| "Drift detected: migration history does not match schema" | La DB tiene migrations aplicadas que no están en `prisma/migrations/`. Lo más rápido en dev: `migrate reset`; en producción es delicado, sigue la doc oficial |
| Cliente desactualizado tras migración | `npx prisma generate` |
| El cliente TS-jest no resuelve `./internal/class.js` | Ya está parcheado vía `moduleNameMapper` con `^(\\.{1,2}/.*)\\.js$": "$1"` en `package.json` y `test/jest-e2e.json` |

### Estado actual de las migraciones en este repo

```
20260826001018_create_users         ← User inicial
20260826001326_add_phone_to_users   ← añade columna phone
20260826010006_create_users         ⚠️ DROP COLUMN phone (residuo de prueba)
20260826010055_create_roles         ← Role independiente
20260826015850_create_refresh_tokens ← RefreshToken con FK a User + cascade
```

Hay un residuo (`add_phone_to_users` + `create_users` que la revierte). En proyectos limpios esto no debería existir; te lo dejo así documentado para que veas qué pasa cuando experimentas. Si quieres limpiar: en local `prisma migrate reset` y rehaz desde cero, o usa `prisma migrate diff` para crear una migración que borre `phone` definitivamente.

---

## Workflow recomendado por día

```text
feature branch
   ↓
edita prisma/models/*.prisma
   ↓
npx prisma migrate dev --name add_<loquesea>
   ↓
revisa prisma/migrations/<timestamp>_add_<loquesea>/migration.sql
   ↓
npx prisma generate    # (raras veces necesario manual; migrate dev ya lo hace)
   ↓
usa el nuevo modelo en código (PrismaService está @Global())
   ↓
npm test               # unit
npm run test:e2e       # e2e (limpia y repuebla las tablas que tocas)
   ↓
git add . && git commit -m "feat: <descripción>"
   ↓
PR review
   ↓
merge a main → en CI/staging `npx prisma migrate deploy`
```

> **Por qué `migrate deploy` y no `migrate dev` en CI/staging:** `dev` es interactivo y puede crear shadow DB; `deploy` es 100% no-interactivo y solo aplica migraciones pendientes del historial. Es el comando correcto para cualquier entorno donde no quieres sorpresas.

---

## Referencias internas

- `prisma-7-migrations-guide.md` — teoría completa (shadow DB, deploy vs dev, drift).
- `prisma/prisma.service.ts` — cómo se crea el cliente con driver adapter.
- `prisma7.config.ts` — `defineConfig` con `env("DATABASE_URL")`.
- `README.md §6` — resumen corto de este mismo flujo.
