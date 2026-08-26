# Prisma 7 — Guía de Migraciones para NestJS + PostgreSQL

> Guía práctica para este proyecto: **NestJS + TypeScript + Prisma 7.10.0 + PostgreSQL**.
>
> El objetivo es trabajar con Prisma Migrate de forma similar a las migrations de Laravel, pero entendiendo las diferencias importantes, especialmente respecto a **rollbacks**, historial de migraciones y despliegues.

---

## 1. Stack del proyecto

La configuración que estamos usando es:

```text
NestJS
TypeScript
Prisma 7.10.0
PostgreSQL
@prisma/adapter-pg
```

Prisma 7 utiliza un driver adapter para conectarse a PostgreSQL.

La configuración de Prisma está separada de NestJS:

```text
.env
   ↓
prisma.config.ts
   ↓
Prisma Migrate / Prisma CLI

.env
   ↓
ConfigModule
   ↓
PrismaService
   ↓
PrismaPg
   ↓
PostgreSQL
```

---

# 2. Estructura recomendada

Para evitar que `schema.prisma` crezca demasiado, utilizaremos un schema dividido en varios archivos:

```text
social-app/
│
├── src/
│   ├── generated/
│   │   └── prisma/
│   │       └── ...
│   │
│   ├── prisma/
│   │   ├── prisma.module.ts
│   │   └── prisma.service.ts
│   │
│   ├── users/
│   ├── auth/
│   ├── posts/
│   └── ...
│
├── prisma/
│   ├── schema.prisma
│   │
│   ├── models/
│   │   ├── user.prisma
│   │   ├── role.prisma
│   │   ├── post.prisma
│   │   └── ...
│   │
│   └── migrations/
│       ├── 20260825190000_create_users/
│       │   └── migration.sql
│       ├── 20260825200000_create_roles/
│       │   └── migration.sql
│       └── ...
│
├── prisma.config.ts
├── .env
├── package.json
└── tsconfig.json
```

En Prisma 7, `prisma.config.ts` puede apuntar al directorio que contiene el schema:

```ts
import "dotenv/config";
import { defineConfig, env } from "prisma/config";

export default defineConfig({
  schema: "prisma/",

  migrations: {
    path: "prisma/migrations",
  },

  datasource: {
    url: env("DATABASE_URL"),
  },
});
```

El `schema.prisma` principal mantiene el `generator` y el `datasource`:

```prisma
generator client {
  provider = "prisma-client"
  output   = "../src/generated/prisma"
}

datasource db {
  provider = "postgresql"
}
```

Los modelos pueden vivir en `prisma/models/*.prisma`.

Prisma combina esos archivos al ejecutar comandos como `generate` o `migrate`.

---

# 3. Concepto fundamental: schema vs migration

Hay que separar dos conceptos.

## `schema.prisma` / modelos

Representan el **estado deseado** de la base de datos.

Ejemplo:

```prisma
model User {
  id        Int      @id @default(autoincrement())
  name      String
  email     String   @unique
  createdAt DateTime @default(now())
}
```

## `migrations/`

Representan el **historial de cómo llegamos a ese estado**.

Por ejemplo:

```text
migrations/
├── 20260825190000_create_users/
│   └── migration.sql
├── 20260825200000_add_role_to_users/
│   └── migration.sql
└── 20260825210000_create_posts/
    └── migration.sql
```

No se debe eliminar el historial simplemente porque el schema actual ya contiene todo.

El historial es parte del proyecto y debe estar versionado en Git.

---

# 4. Crear la primera migración

Supongamos que tenemos:

```text
prisma/models/user.prisma
```

```prisma
model User {
  id        Int      @id @default(autoincrement())
  name      String
  email     String   @unique
  password  String
  active    Boolean  @default(true)
  createdAt DateTime @default(now())
  updatedAt DateTime @updatedAt
}
```

Creamos la migración:

```bash
npx prisma migrate dev --name create_users
```

Prisma:

1. Compara el schema con la base de datos.
2. Utiliza una shadow database para comprobar el historial.
3. Genera SQL.
4. Crea la carpeta de migración.
5. Aplica la migración a la base de desarrollo.
6. Registra la migración en `_prisma_migrations`.

En Prisma 7, `migrate dev` **ya no ejecuta automáticamente `prisma generate`**. Por tanto, después de una migración normalmente debemos ejecutar:

```bash
npx prisma generate
```

---

# 5. Crear una nueva migración

Este será el flujo habitual.

## Paso 1 — Modificar el modelo

Por ejemplo:

```prisma
model User {
  id        Int      @id @default(autoincrement())
  name      String
  email     String   @unique
  password  String
  phone     String?
  active    Boolean  @default(true)
  createdAt DateTime @default(now())
  updatedAt DateTime @updatedAt
}
```

## Paso 2 — Crear y aplicar la migración

```bash
npx prisma migrate dev --name add_phone_to_users
```

## Paso 3 — Generar Prisma Client

```bash
npx prisma generate
```

Resultado:

```text
prisma/
├── models/
│   └── user.prisma
│
└── migrations/
    ├── 20260825190000_create_users/
    │   └── migration.sql
    │
    └── 20260825200000_add_phone_to_users/
        └── migration.sql
```

---

# 6. Convención para nombres de migraciones

Usa nombres cortos, descriptivos y en inglés.

Buenos ejemplos:

```text
create_users
create_roles
add_role_to_users
add_phone_to_users
add_unique_email_to_users
create_posts
create_comments
add_deleted_at_to_users
remove_legacy_field
rename_username_to_display_name
```

Evita:

```text
migration1
test
changes
update
fix
stuff
```

La migración debe poder entenderse meses después.

---

# 7. Crear una migración sin aplicarla

A veces queremos revisar o modificar el SQL antes de ejecutarlo.

Usamos:

```bash
npx prisma migrate dev --name add_phone_to_users --create-only
```

Esto crea la migración pero **no la aplica**.

Ejemplo:

```text
prisma/migrations/
└── 20260825200000_add_phone_to_users/
    └── migration.sql
```

Podemos abrir:

```text
migration.sql
```

Revisar el SQL y, si es necesario, modificarlo.

Después:

```bash
npx prisma migrate dev
```

Esto aplicará la migración pendiente.

---

# 8. ¿Podemos modificar `migration.sql`?

Sí.

Prisma Migrate genera SQL automáticamente, pero las migraciones son archivos SQL personalizables.

Esto es especialmente útil para:

- migraciones de datos;
- SQL específico de PostgreSQL;
- índices especiales;
- funciones;
- triggers;
- cambios que Prisma no expresa directamente;
- transformaciones de datos antes de cambiar una columna.

Ejemplo conceptual:

```sql
ALTER TABLE "User"
ADD COLUMN "phone" TEXT;
```

Pero hay que distinguir:

### Migración no aplicada

Se puede editar antes de ejecutarla.

### Migración ya aplicada y compartida

**No se debe editar.**

Si una migración ya fue ejecutada y está en Git, considérala inmutable.

Si necesitas otro cambio:

```bash
npx prisma migrate dev --name nueva_migracion
```

---

# 9. ¿Cómo funciona el rollback?

Aquí existe una diferencia importante con Laravel.

Prisma Migrate **no tiene un comando equivalente a**:

```bash
php artisan migrate:rollback
```

para deshacer automáticamente la última migración.

No existe:

```bash
npx prisma migrate rollback
```

como flujo normal.

Prisma considera las migraciones como un historial acumulativo.

Por eso hay que distinguir varios escenarios.

---

# 10. Rollback en desarrollo: `migrate reset`

Si estás en desarrollo y no te importa perder los datos:

```bash
npx prisma migrate reset
```

Esto:

1. Elimina/reseteará el schema de desarrollo.
2. Lo vuelve a crear.
3. Ejecuta todas las migraciones desde cero.
4. Ejecuta el seed si está configurado.

Ejemplo:

```text
migrations/
├── 001_create_users
├── 002_create_roles
└── 003_add_phone
```

Después de:

```bash
npx prisma migrate reset
```

Prisma vuelve a ejecutar:

```text
001_create_users
        ↓
002_create_roles
        ↓
003_add_phone
```

### ADVERTENCIA

`migrate reset` destruye los datos del entorno que estás reseteando.

Debe utilizarse únicamente en desarrollo.

Nunca ejecutar contra producción por accidente.

---

# 11. Rollback lógico en desarrollo

Supongamos que tenemos:

```text
001_create_users
002_add_phone
```

y descubrimos que `phone` fue una mala decisión.

No debemos borrar simplemente:

```text
002_add_phone
```

si ya fue aplicada.

Una estrategia limpia es:

1. Modificar el schema al estado deseado.
2. Crear una nueva migración que revierta el cambio.

Por ejemplo:

```text
001_create_users
002_add_phone
003_remove_phone
```

Esto mantiene un historial coherente.

---

# 12. Rollback físico de una migración

Si necesitas un rollback SQL específico, puedes crear una nueva migración que haga explícitamente el cambio contrario.

Ejemplo:

Migración original:

```sql
ALTER TABLE "User"
ADD COLUMN "phone" TEXT;
```

Nueva migración:

```sql
ALTER TABLE "User"
DROP COLUMN "phone";
```

El historial queda:

```text
001_create_users
002_add_phone
003_remove_phone
```

Esto es preferible a modificar o borrar `002_add_phone`.

---

# 13. ¿Por qué Prisma no tiene rollback automático?

Porque un rollback genérico puede ser peligroso.

Ejemplo:

```text
add phone
```

No es simplemente:

```text
drop phone
```

si entre ambas migraciones hubo:

- datos nuevos;
- cambios de datos;
- índices;
- relaciones;
- modificaciones de constraints;
- migraciones posteriores.

Un rollback automático podría destruir información.

Prisma favorece que las migraciones posteriores describan explícitamente el nuevo estado.

---

# 14. Si cometiste un error antes de compartir la migración

Este es un caso diferente.

Si estás trabajando solo y acabas de crear una migración:

```text
003_add_phone
```

pero **todavía no la has compartido ni enviado al repositorio**, puedes reconsiderar la migración.

Una opción habitual durante desarrollo es:

```bash
npx prisma migrate reset
```

y luego corregir el schema y volver a generar las migraciones.

También puedes eliminar una migración local no compartida y reconstruir el historial si sabes exactamente qué estás haciendo.

La regla práctica:

> Las migraciones locales todavía no compartidas pueden reorganizarse. Las migraciones compartidas deben tratarse como inmutables.

---

# 15. Migraciones destructivas

Hay que tener especial cuidado con:

```text
DROP COLUMN
DROP TABLE
```

y cambios como:

```text
String → Int
nullable → required
```

Ejemplo peligroso:

```prisma
email String?
```

cambiar a:

```prisma
email String
```

Si existen registros con:

```text
email = NULL
```

la migración puede fallar.

Primero necesitas resolver los datos.

---

# 16. Patrón seguro para cambios de columnas

Supongamos que quieres convertir:

```text
username
```

en obligatorio.

### Paso 1

Mantenerlo nullable:

```prisma
username String?
```

### Paso 2

Crear migración.

### Paso 3

Migrar/rellenar los datos existentes.

Por ejemplo, mediante SQL o un script.

### Paso 4

Cambiar:

```prisma
username String
```

### Paso 5

Crear otra migración.

Resultado:

```text
001_create_users
002_add_username
003_backfill_username
004_make_username_required
```

Este patrón es mucho más seguro en producción.

---

# 17. Renombrar columnas

Cuidado con:

```text
firstName → name
```

Prisma puede interpretar ciertos cambios como:

```text
DROP firstName
ADD name
```

Eso puede causar pérdida de datos.

Para cambios de nombres importantes, revisa siempre:

```text
migration.sql
```

antes de aplicar la migración.

La migración correcta debería conservar los datos mediante SQL apropiado, por ejemplo:

```sql
ALTER TABLE "User"
RENAME COLUMN "firstName" TO "name";
```

No asumir que Prisma siempre inferirá exactamente la operación semántica que deseas.

---

# 18. Agregar campos obligatorios a tablas existentes

Evita pasar directamente de:

```prisma
model User {
  id Int @id @default(autoincrement())
}
```

a:

```prisma
model User {
  id      Int    @id @default(autoincrement())
  country String
}
```

si ya existen usuarios.

PostgreSQL no puede poner un valor arbitrario en `country` para las filas existentes.

Una estrategia segura:

```text
1. Agregar country como nullable
2. Ejecutar migración
3. Rellenar country para los registros existentes
4. Cambiar country a NOT NULL
5. Crear nueva migración
```

---

# 19. Migraciones de datos

Prisma Migrate permite incluir SQL personalizado en una migración.

Por ejemplo:

```sql
UPDATE "User"
SET "active" = true
WHERE "active" IS NULL;
```

Después puedes hacer que el campo sea obligatorio.

Una migración puede contener tanto:

```sql
ALTER TABLE ...
```

como:

```sql
UPDATE ...
```

Esto es útil para cambios de esquema que requieren transformación de datos.

---

# 20. `migrate dev`

Este es el comando principal durante desarrollo:

```bash
npx prisma migrate dev --name nombre_migracion
```

Ejemplo:

```bash
npx prisma migrate dev --name create_posts
```

En Prisma 7, `migrate dev`:

- utiliza una shadow database;
- detecta drift;
- genera migraciones;
- aplica migraciones pendientes;
- actualiza `_prisma_migrations`.

Importante:

```text
Prisma 7
migrate dev
    ↓
NO asumir que generate se ejecutó automáticamente
```

Por eso:

```bash
npx prisma generate
```

debe ejecutarse cuando necesites regenerar Prisma Client.

---

# 21. `migrate status`

Para revisar el estado:

```bash
npx prisma migrate status
```

Úsalo frecuentemente.

Puede ayudarte a detectar:

- migraciones pendientes;
- migraciones aplicadas;
- diferencias entre historial y base de datos;
- migraciones fallidas.

Antes de desplegar:

```bash
npx prisma migrate status
```

es una buena comprobación.

---

# 22. `migrate deploy`

En staging/producción:

```bash
npx prisma migrate deploy
```

Este es el comando que debemos utilizar para aplicar migraciones existentes.

No usamos:

```bash
npx prisma migrate dev
```

en producción.

`migrate deploy`:

- aplica migraciones pendientes;
- no crea una migración nueva;
- no intenta detectar drift como `migrate dev`;
- no utiliza shadow database;
- no genera Prisma Client.

Por eso el pipeline de producción debe separar:

```text
build/generate
        ↓
deploy migrations
        ↓
start application
```

---

# 23. Flujo recomendado con Git

Supongamos que estás desarrollando:

```text
feature/user-profile
```

Modificas:

```text
prisma/models/user.prisma
```

Luego:

```bash
npx prisma migrate dev --name add_user_profile
```

Se genera:

```text
prisma/migrations/
└── 202608..._add_user_profile/
    └── migration.sql
```

Commit:

```bash
git add prisma/
git commit -m "feat: add user profile migration"
```

Después haces merge a:

```text
main
```

El servidor de staging/producción recibe:

```text
prisma/migrations/
```

y ejecuta:

```bash
npx prisma migrate deploy
```

---

# 24. Nunca hagas esto en producción

No ejecutar:

```bash
npx prisma migrate dev
```

No ejecutar:

```bash
npx prisma migrate reset
```

No ejecutar:

```bash
npx prisma db push
```

como mecanismo normal de despliegue de un proyecto que ya utiliza migrations.

La estrategia debe ser:

```text
Developer
   │
   ├── modifica schema
   │
   ├── migrate dev
   │
   └── commit migration
             │
             ▼
          Git
             │
             ▼
       CI/CD / Server
             │
             └── migrate deploy
```

---

# 25. `db push` vs `migrate dev`

`db push`:

```bash
npx prisma db push
```

sirve principalmente para prototipado cuando quieres sincronizar rápidamente el schema con la base de datos.

No genera un historial de migrations equivalente al flujo de Prisma Migrate.

Para un proyecto serio con historial y despliegues:

```text
schema.prisma
      ↓
migrate dev
      ↓
migration.sql
      ↓
Git
      ↓
migrate deploy
```

---

# 26. ¿Qué es `_prisma_migrations`?

Prisma crea una tabla interna:

```text
_prisma_migrations
```

Esta tabla permite a Prisma saber qué migraciones se han aplicado.

Conceptualmente:

```text
migration_name
started_at
finished_at
applied_steps_count
rolled_back_at
logs
```

No debes modificar esta tabla manualmente salvo que estés siguiendo un procedimiento específico de recuperación con `migrate resolve`.

---

# 27. Migración fallida

Supongamos que ejecutas:

```bash
npx prisma migrate deploy
```

y una migración falla.

No debes simplemente borrar la carpeta de la migración.

Primero:

```bash
npx prisma migrate status
```

Después revisa:

```text
migration.sql
```

y los logs.

En producción, Prisma dispone de:

```bash
npx prisma migrate resolve
```

para resolver determinados estados de migración.

---

# 28. `migrate resolve`

`migrate resolve` se utiliza principalmente para situaciones de recuperación en bases de datos de despliegue, por ejemplo:

- migración fallida;
- baseline;
- hotfix manual;
- reconciliar el historial de migrations.

Ejemplos conceptuales:

```bash
npx prisma migrate resolve --applied <migration>
```

o:

```bash
npx prisma migrate resolve --rolled-back <migration>
```

**No utilizar estos comandos a ciegas.**

`resolve` cambia el estado que Prisma considera respecto a una migración; no es un botón mágico para deshacer SQL.

Antes de utilizarlo hay que verificar el estado real de PostgreSQL.

---

# 29. Hotfix manual en producción

Supongamos que por una emergencia alguien ejecutó manualmente:

```sql
CREATE INDEX ...
```

en producción.

Ahora Prisma no conoce ese cambio.

Tenemos un posible **schema drift**.

No debemos simplemente crear otra migración que haga lo mismo sin entender el estado.

Debemos:

1. Revisar el cambio manual.
2. Determinar qué debe representar el historial de Prisma.
3. Revisar `migrate status`.
4. Si corresponde, utilizar `migrate resolve`.
5. Crear una migración que deje el proyecto consistente.

La regla:

> Los cambios manuales en producción deben ser excepcionales y deben reconciliarse con el historial de Prisma.

---

# 30. Drift

Drift significa que la base de datos ya no coincide con el estado que Prisma espera según las migraciones.

Ejemplos:

```text
Migration dice:
User.email existe

Base de datos:
User.email fue eliminado manualmente
```

o:

```text
Migration dice:
no existe índice X

Base de datos:
alguien creó índice X manualmente
```

Durante:

```bash
npx prisma migrate dev
```

Prisma puede detectar este tipo de diferencias utilizando la shadow database.

---

# 31. Shadow database

`migrate dev` necesita una shadow database.

Prisma utiliza esta base temporal para reconstruir y comprobar el historial de migrations.

Conceptualmente:

```text
migration 001
      ↓
migration 002
      ↓
migration 003
      ↓
Shadow DB
```

Después compara el resultado con el estado esperado.

Esto ayuda a detectar:

- migrations modificadas;
- migrations eliminadas;
- cambios manuales;
- inconsistencias del historial.

En un PostgreSQL local, normalmente Prisma puede gestionar este proceso automáticamente.

---

# 32. `migrate diff`

Prisma también permite comparar dos estados:

```bash
npx prisma migrate diff
```

Es especialmente útil cuando necesitas diagnosticar diferencias entre:

```text
schema
```

y:

```text
database
```

Un ejemplo de uso documentado:

```bash
npx prisma migrate diff \
  --from-config-datasource \
  --to-schema=./prisma/schema.prisma \
  --script
```

Esto puede generar SQL para visualizar qué diferencias existen.

---

# 33. Flujo diario recomendado

Cuando trabajes normalmente:

### 1. Actualizar Git

```bash
git pull
```

### 2. Revisar migrations

```bash
npx prisma migrate status
```

### 3. Modificar modelos

```text
prisma/models/user.prisma
```

### 4. Crear migration

```bash
npx prisma migrate dev --name descripcion_del_cambio
```

### 5. Generar Prisma Client

```bash
npx prisma generate
```

### 6. Probar aplicación

```bash
npm run start:dev
```

### 7. Revisar migration SQL

```text
prisma/migrations/<migration>/migration.sql
```

### 8. Commit

```bash
git add prisma/ src/
git commit -m "feat: ..."
```

---

# 34. Flujo recomendado para una migración peligrosa

Si vas a:

- eliminar columna;
- eliminar tabla;
- cambiar tipo;
- hacer un campo obligatorio;
- cambiar una relación;
- agregar constraint;
- modificar datos existentes;

usa:

```bash
npx prisma migrate dev --name cambio --create-only
```

Después revisa:

```text
migration.sql
```

Si el SQL es correcto:

```bash
npx prisma migrate dev
```

Después:

```bash
npx prisma generate
```

---

# 35. Ejemplo completo

Tenemos:

```prisma
model User {
  id        Int      @id @default(autoincrement())
  name      String
  email     String   @unique
  createdAt DateTime @default(now())
}
```

Creamos:

```bash
npx prisma migrate dev --name create_users
```

Después queremos agregar:

```prisma
active Boolean @default(true)
```

Ejecutamos:

```bash
npx prisma migrate dev --name add_active_to_users
```

Después queremos agregar:

```prisma
roleId Int?
```

Ejecutamos:

```bash
npx prisma migrate dev --name add_role_to_users
```

Historial:

```text
prisma/migrations/
│
├── 202608..._create_users/
│   └── migration.sql
│
├── 202608..._add_active_to_users/
│   └── migration.sql
│
└── 202608..._add_role_to_users/
    └── migration.sql
```

---

# 36. Rollback de este ejemplo

Supongamos que queremos quitar `roleId`.

No hacemos:

```text
DELETE 202608..._add_role_to_users
```

Creamos un nuevo estado:

```prisma
model User {
  id        Int      @id @default(autoincrement())
  name      String
  email     String   @unique
  active    Boolean  @default(true)
  createdAt DateTime @default(now())
}
```

Luego:

```bash
npx prisma migrate dev --name remove_role_from_users
```

Historial:

```text
create_users
      ↓
add_active_to_users
      ↓
add_role_to_users
      ↓
remove_role_from_users
```

Esto es un **rollback lógico mediante una nueva migration**, no un rollback destructivo del historial.

---

# 37. Rollback total de desarrollo

Si simplemente quieres volver a empezar desde cero:

```bash
npx prisma migrate reset
```

Resultado:

```text
DROP/RESET DATABASE SCHEMA
        ↓
APPLY migration 001
        ↓
APPLY migration 002
        ↓
APPLY migration 003
        ↓
SEED
```

Todos los datos existentes del entorno se pierden.

---

# 38. Seed

Si utilizamos seed, debemos distinguir:

```text
Migration
    ↓
estructura de BD

Seed
    ↓
datos iniciales
```

Ejemplo:

```text
migrations/
    create_users
    create_roles

seed
    ADMIN role
    USER role
```

En Prisma 7, no debes asumir que `migrate dev` ejecutará automáticamente `prisma generate` o el seed.

Ejecuta explícitamente el proceso que hayas configurado para tu proyecto.

---

# 39. Comandos principales

## Desarrollo

```bash
npx prisma migrate dev
```

Crear migration:

```bash
npx prisma migrate dev --name create_users
```

Crear sin aplicar:

```bash
npx prisma migrate dev --create-only
```

Reset:

```bash
npx prisma migrate reset
```

Estado:

```bash
npx prisma migrate status
```

Generar client:

```bash
npx prisma generate
```

Studio:

```bash
npx prisma studio
```

---

## Staging / producción

Ver estado:

```bash
npx prisma migrate status
```

Aplicar pendientes:

```bash
npx prisma migrate deploy
```

Resolver una migración problemática:

```bash
npx prisma migrate resolve
```

Comparar schemas:

```bash
npx prisma migrate diff
```

---

# 40. Qué comando utilizar según la situación

| Situación | Comando |
|---|---|
| Crear migration en desarrollo | `npx prisma migrate dev --name ...` |
| Crear migration sin aplicarla | `npx prisma migrate dev --create-only` |
| Ver migrations | `npx prisma migrate status` |
| Reiniciar BD de desarrollo | `npx prisma migrate reset` |
| Generar Prisma Client | `npx prisma generate` |
| Aplicar migrations en producción | `npx prisma migrate deploy` |
| Resolver migration fallida | `npx prisma migrate resolve` |
| Comparar schemas | `npx prisma migrate diff` |
| Prototipar sin historial | `npx prisma db push` |

---

# 41. Reglas del proyecto

Para este proyecto vamos a seguir estas reglas:

### Regla 1

**Toda modificación estructural de la BD debe pasar por una migration.**

### Regla 2

No modificar migrations ya compartidas.

### Regla 3

No borrar migrations del repositorio para solucionar un problema.

### Regla 4

No utilizar `migrate dev` en producción.

### Regla 5

No utilizar `migrate reset` en producción.

### Regla 6

Revisar SQL generado cuando el cambio sea destructivo o complejo.

### Regla 7

Las migrations deben estar en Git.

### Regla 8

Los cambios de datos y cambios estructurales complejos deben planificarse.

### Regla 9

Para producción utilizar:

```bash
npx prisma migrate deploy
```

### Regla 10

Después de cambios de schema, verificar que Prisma Client esté generado:

```bash
npx prisma generate
```

---

# 42. Comparación rápida con Laravel

| Laravel | Prisma 7 |
|---|---|
| `make:migration` | `migrate dev --name` |
| `migrate` | `migrate dev` |
| `migrate --force` | `migrate deploy` |
| `migrate:rollback` | No existe equivalente automático |
| `migrate:refresh` | `migrate reset` + migrations |
| `migrate:fresh` | `migrate reset` |
| migration PHP | `migration.sql` |
| migration history | `prisma/migrations` + `_prisma_migrations` |
| model/migration schema | `schema.prisma` + `.prisma` |
| `db:seed` | seed configurado |
| `db:wipe` | reset/destrucción según contexto |

La diferencia más importante:

```text
Laravel:
    migration
       ↓
    rollback
       ↓
    migration anterior

Prisma:
    migration 001
       ↓
    migration 002
       ↓
    migration 003
       ↓
    migration 004 ← cambio inverso si hace falta
```

Prisma favorece un historial acumulativo.

---

# 43. Checklist antes de hacer commit

```text
[ ] Modifiqué el archivo .prisma correcto
[ ] La relación entre modelos es correcta
[ ] Ejecuté migrate dev
[ ] Revisé migration.sql
[ ] Verifiqué que no haya DROP inesperados
[ ] Ejecuté prisma generate
[ ] Probé la aplicación
[ ] Verifiqué migrate status
[ ] Incluí prisma/migrations en Git
[ ] No modifiqué migrations anteriores ya compartidas
```

---

# 44. Flujo que usaremos en este proyecto

A partir de ahora, para cualquier cambio de base de datos:

```text
┌──────────────────────────┐
│ 1. Modificar modelo      │
│    *.prisma              │
└────────────┬─────────────┘
             ↓
┌──────────────────────────┐
│ 2. Crear migration       │
│    migrate dev           │
└────────────┬─────────────┘
             ↓
┌──────────────────────────┐
│ 3. Revisar migration.sql │
└────────────┬─────────────┘
             ↓
┌──────────────────────────┐
│ 4. Aplicar / probar      │
└────────────┬─────────────┘
             ↓
┌──────────────────────────┐
│ 5. prisma generate       │
└────────────┬─────────────┘
             ↓
┌──────────────────────────┐
│ 6. Tests                 │
└────────────┬─────────────┘
             ↓
┌──────────────────────────┐
│ 7. Commit + Push         │
└────────────┬─────────────┘
             ↓
┌──────────────────────────┐
│ 8. Producción            │
│    migrate deploy        │
└──────────────────────────┘
```

---

# 45. Referencias oficiales

- Prisma Migrate — documentación general.
- `prisma migrate dev` — desarrollo.
- `prisma migrate deploy` — staging/producción.
- `prisma migrate reset` — reset de desarrollo.
- `prisma migrate resolve` — recuperación de migraciones.
- Multi-file Prisma Schema — organización del schema.

Estas recomendaciones están basadas en la documentación de Prisma ORM 7.
