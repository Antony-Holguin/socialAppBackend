# Relaciones en Prisma + NestJS

Guía práctica para definir y usar relaciones **uno a uno**, **uno a muchos** y **muchos a muchos** en un backend NestJS con Prisma ORM.

---

## 📑 Tabla de contenidos

1. [Setup inicial](#1-setup-inicial)
2. [Uno a Uno (1:1)](#2-uno-a-uno-11)
3. [Uno a Muchos (1:N)](#3-uno-a-muchos-1n)
4. [Muchos a Muchos (M:N)](#4-muchos-a-muchos-mn)
   - [4a. Implícita](#4a-implícita-simple)
   - [4b. Explícita](#4b-explícita-recomendada-con-campos-extra)
5. [Relación autorreferencial](#5-relación-autorreferencial)
6. [Acciones en cascada](#6-acciones-en-cascada-on-delete)
7. [Uso desde NestJS](#7-uso-desde-nestjs)
8. [Tips y errores comunes](#8-tips-y-errores-comunes)
9. [Relaciones polimórficas](#9-relaciones-polimórficas)
   - [9a. Múltiples FKs opcionales (recomendado)](#9a-múltiples-fks-opcionales-recomendado)
   - [9b. Tablas de unión separadas (más limpio)](#9b-tablas-de-unión-separadas-más-limpio)
   - [9c. Discriminator + JSON (más flexible, menos seguro)](#9c-discriminator--json-más-flexible-menos-seguro)
   - [9d. Helper en NestJS para tipar la entidad padre](#9d-helper-en-nestjs-para-tipar-la-entidad-padre)

---

## 1. Setup inicial

### Instalar dependencias
```bash
npm i @prisma/client
npm i -D prisma
npx prisma init
```

### Configurar `prisma/schema.prisma`
```prisma
generator client {
  provider = "prisma-client-js"
}

datasource db {
  provider = "postgresql" // o mysql, sqlite, mongodb, etc.
  url      = env("DATABASE_URL")
}
```

### Crear `PrismaService` en NestJS
```ts
// src/prisma/prisma.service.ts
import { Injectable, OnModuleInit, OnModuleDestroy } from '@nestjs/common';
import { PrismaClient } from '@prisma/client';

@Injectable()
export class PrismaService extends PrismaClient
  implements OnModuleInit, OnModuleDestroy {
  async onModuleInit() {
    await this.$connect();
  }
  async onModuleDestroy() {
    await this.$disconnect();
  }
}
```

```ts
// src/prisma/prisma.module.ts
import { Global, Module } from '@nestjs/common';
import { PrismaService } from './prisma.service';

@Global()
@Module({
  providers: [PrismaService],
  exports: [PrismaService],
})
export class PrismaModule {}
```

Importar `PrismaModule` en `AppModule` y listo — queda inyectable en cualquier service.

---

## 2. Uno a Uno (1:1)

**Caso:** un `User` tiene un único `Profile` y viceversa.

### `schema.prisma`
```prisma
model User {
  id        Int      @id @default(autoincrement())
  email     String   @unique
  profile   Profile?
  createdAt DateTime @default(now())
}

model Profile {
  id     Int    @id @default(autoincrement())
  bio    String?
  userId Int    @unique // 👈 @unique es lo que hace 1:1
  user   User   @relation(fields: [userId], references: [id])
}
```

### Reglas clave
- La **FK va en el lado "dependiente"** (en este caso `Profile`).
- El **`@unique`** en la FK es lo que garantiza que sea 1:1 (un user no puede tener dos profiles con el mismo `userId`).
- Si la relación es opcional, declara la FK como `Int?` y el campo de relación como `Profile?`.

### NestJS
```ts
// Crear user con su profile en una sola operación
const user = await this.prisma.user.create({
  data: {
    email: 'ana@correo.com',
    profile: { create: { bio: 'Hola soy Ana' } },
  },
  include: { profile: true },
});

// Obtener user con su profile
const userWithProfile = await this.prisma.user.findUnique({
  where: { id: 1 },
  include: { profile: true },
});
```

---

## 3. Uno a Muchos (1:N)

**Caso:** un `Author` tiene muchos `Post`. Cada `Post` pertenece a un único `Author`.

### `schema.prisma`
```prisma
model Author {
  id    Int    @id @default(autoincrement())
  name  String
  posts Post[] // 👈 lado "uno" — array sin FK
}

model Post {
  id       Int    @id @default(autoincrement())
  title    String
  content  String?
  authorId Int
  author   Author @relation(fields: [authorId], references: [id])
}
```

### Reglas clave
- El lado "uno" (`Author`) lleva un **array** (`Post[]`) sin FK.
- El lado "muchos" (`Post`) lleva la **FK + relación**.
- Si el `Author` puede existir sin posts, no hace falta nada extra; si querés obligar a que tenga al menos uno, validalo en la lógica de negocio (Prisma no lo soporta nativamente).

### NestJS
```ts
// Crear autor con varios posts anidados
const author = await this.prisma.author.create({
  data: {
    name: 'Carlos',
    posts: {
      create: [
        { title: 'Post 1', content: '...' },
        { title: 'Post 2', content: '...' },
      ],
    },
  },
  include: { posts: true },
});

// Obtener autor con sus posts
const authorWithPosts = await this.prisma.author.findUnique({
  where: { id: 1 },
  include: { posts: true },
});

// Crear post asignándolo a un autor existente
const newPost = await this.prisma.post.create({
  data: {
    title: 'Post 3',
    author: { connect: { id: 1 } }, // 👈 conecta por id existente
  },
});
```

---

## 4. Muchos a Muchos (M:N)

**Caso:** un `Post` puede tener muchos `Tag` y un `Tag` puede estar en muchos `Post`.

Hay dos formas, una simple y otra más potente.

### 4a. Implícita (simple)

Prisma crea la tabla intermedia automáticamente.

```prisma
model Post {
  id    Int    @id @default(autoincrement())
  title String
  tags  Tag[]  @relation("PostTags")
}

model Tag {
  id    Int    @id @default(autoincrement())
  name  String @unique
  posts Post[] @relation("PostTags")
}
```

> 💡 El nombre `@relation("PostTags")` es opcional pero útil si vas a tener **varias M:N entre los mismos modelos**.

### NestJS
```ts
// Crear post y conectarlo a tags existentes
const post = await this.prisma.post.create({
  data: {
    title: 'Mi post',
    tags: {
      connect: [{ id: 1 }, { id: 2 }],
    },
  },
  include: { tags: true },
});

// Crear tags nuevos y conectarlos a un post existente
await this.prisma.post.update({
  where: { id: 1 },
  data: {
    tags: {
      create: [{ name: 'nestjs' }, { name: 'prisma' }],
    },
  },
});

// Desconectar un tag
await this.prisma.post.update({
  where: { id: 1 },
  data: {
    tags: { disconnect: [{ id: 2 }] },
  },
});
```

### 4b. Explícita (recomendada con campos extra)

Si la relación intermedia necesita datos (fecha, autor de la asignación, estado, etc.), declarás el modelo intermedio vos.

```prisma
model Post {
  id   Int       @id @default(autoincrement())
  title String
  tags  PostTag[]
}

model Tag {
  id    Int       @id @default(autoincrement())
  name  String    @unique
  posts PostTag[]
}

model PostTag {
  postId    Int
  tagId     Int
  createdAt DateTime @default(now()) // 👈 campo extra

  post Post @relation(fields: [postId], references: [id])
  tag  Tag  @relation(fields: [tagId], references: [id])

  @@id([postId, tagId]) // PK compuesta
  @@index([tagId])
}
```

### NestJS
```ts
// Crear la relación con metadata
await this.prisma.postTag.create({
  data: {
    post: { connect: { id: 1 } },
    tag:  { connect: { id: 2 } },
  },
});

// Consultar posts con sus tags (vía tabla intermedia)
const post = await this.prisma.post.findUnique({
  where: { id: 1 },
  include: {
    tags: {
      include: { tag: true }, // tags es PostTag[], tiene .tag adentro
    },
  },
});
```

---

## 5. Relación autorreferencial

Un modelo que se relaciona consigo mismo. Útil para jerarquías (categorías con subcategorías, empleados con jefes, etc.).

### `schema.prisma`
```prisma
model Category {
  id        Int        @id @default(autoincrement())
  name      String
  parentId   Int?
  parent     Category?  @relation("CategoryHierarchy", fields: [parentId], references: [id])
  children  Category[] @relation("CategoryHierarchy")
}
```

### NestJS
```ts
// Crear categoría raíz
const root = await this.prisma.category.create({
  data: { name: 'Tecnología' },
});

// Crear subcategoría debajo de la raíz
const child = await this.prisma.category.create({
  data: { name: 'Frontend', parentId: root.id },
});

// Traer árbol completo
const tree = await this.prisma.category.findUnique({
  where: { id: root.id },
  include: { children: { include: { children: true } } },
});
```

---

## 6. Acciones en cascada (`onDelete`)

Sin esto, Prisma no sabe qué hacer cuando borrás un registro relacionado. **Definilo siempre o te va a explotar en producción.**

```prisma
model Post {
  id       Int    @id @default(autoincrement())
  authorId Int
  author   Author @relation(fields: [authorId], references: [id], onDelete: Cascade)
}
```

| Opción | Comportamiento |
|--------|---------------|
| `Cascade` | Borra los registros hijos también |
| `Restrict` | Impide el borrado del padre si hay hijos |
| `NoAction` | Similar a Restrict (depende del motor SQL) |
| `SetNull` | Pone la FK del hijo en `null` (requiere FK opcional `Int?`) |
| `SetDefault` | Pone un valor por defecto (requiere `@default`) |

---

## 7. Uso desde NestJS

### Estructura típica
```
src/
├── prisma/
│   ├── prisma.module.ts
│   └── prisma.service.ts
├── users/
│   ├── users.module.ts
│   ├── users.service.ts
│   └── users.controller.ts
```

### Ejemplo de service con varias relaciones
```ts
// src/users/users.service.ts
import { Injectable } from '@nestjs/common';
import { PrismaService } from '../prisma/prisma.service';

@Injectable()
export class UsersService {
  constructor(private prisma: PrismaService) {}

  // Traer user con perfil + posts + tags de cada post
  findOneFull(id: number) {
    return this.prisma.user.findUnique({
      where: { id },
      include: {
        profile: true,
        posts: {
          include: {
            tags: { include: { tag: true } },
            author: true,
          },
        },
      },
    });
  }

  // Crear user con profile y posts en una sola transacción
  async createFull(data: {
    email: string;
    bio?: string;
    posts?: { title: string; tagNames?: string[] }[];
  }) {
    return this.prisma.user.create({
      data: {
        email: data.email,
        profile: data.bio ? { create: { bio: data.bio } } : undefined,
        posts: data.posts
          ? {
              create: data.posts.map((p) => ({
                title: p.title,
                tags: p.tagNames
                  ? { create: p.tagNames.map((name) => ({ name })) }
                  : undefined,
              })),
            }
          : undefined,
      },
      include: {
        profile: true,
        posts: { include: { tags: true } },
      },
    });
  }
}
```

### Tipado en `include` y `select`
Prisma genera tipos automáticamente. Usalos para no perder autocomplete:
```ts
import { Prisma } from '@prisma/client';

const userWithPosts = await this.prisma.user.findUnique({
  where: { id: 1 },
  include: { posts: true },
});
//    ^? Prisma.UserGetPayload<{ include: { posts: true } }>
```

---

## 8. Tips y errores comunes

### ✅ Tips

- **Convención de FK:** Prisma usa `<modelo>Id` por defecto. Ej: en `Post` con relación a `Author`, la FK se llama `authorId`.
- **Después de tocar el schema, siempre:**
  ```bash
  npx prisma migrate dev --name nombre_cambio
  npx prisma generate
  ```
- **Usá `select` en vez de `include` cuando solo necesitás algunos campos** — es más performante.
- **Para queries anidadas complejas, considerá `Prisma.validator` con tipos reutilizables.**

### ❌ Errores comunes

| Error | Causa | Solución |
|-------|-------|----------|
| `Foreign key constraint failed` | No definiste `onDelete` y tratás de borrar un padre con hijos | Agregá `onDelete: Cascade` (o la que corresponda) |
| `Relation field needs @relation` | Falta el decorador `@relation` en el lado "dueño" | Agregá `user User @relation(fields: [userId], references: [id])` |
| `Unique constraint failed` en 1:1 | Intentás crear dos profiles para el mismo user | Asegurate de que la FK tenga `@unique` |
| No se generan los tipos | Olvidaste correr `prisma generate` | Corrélos después de cada cambio en `schema.prisma` |
| La relación no aparece en queries | Olvidaste el array en el lado "uno" (`posts Post[]`) | Agregalo en el modelo padre |

---

## 9. Relaciones polimórficas

**Caso real:** un `Comment` puede pertenecer a un `Post`, a un `Video`, o a una `Photo`. No se sabe de antemano a cuál, y **puede variar en el futuro** (mañana querés comentar también en `Story`, `Event`, etc.).

### ⚠️ El problema con Prisma

Prisma **no soporta relaciones polimórficas nativas** (a diferencia de Rails, Sequelize o Django). Tenés que simularlas con uno de estos tres patrones:

---

### 9a. Múltiples FKs opcionales (recomendado)

La idea: el `Comment` tiene **varias FKs nullable**, una por cada modelo al que puede apuntar. A nivel aplicación se valida que **exactamente una** esté seteada.

#### `schema.prisma`
```prisma
model Comment {
  id      Int    @id @default(autoincrement())
  content String
  createdAt DateTime @default(now())

  // FKs opcionales — solo una debería estar set a la vez
  postId  Int?
  videoId Int?
  photoId Int?

  post  Post?  @relation(fields: [postId],  references: [id], onDelete: Cascade)
  video Video? @relation(fields: [videoId], references: [id], onDelete: Cascade)
  photo Photo? @relation(fields: [photoId], references: [id], onDelete: Cascade)

  @@index([postId])
  @@index([videoId])
  @@index([photoId])
}

model Post {
  id       Int       @id @default(autoincrement())
  title    String
  comments Comment[]
}

model Video {
  id       Int       @id @default(autoincrement())
  url      String
  comments Comment[]
}

model Photo {
  id       Int       @id @default(autoincrement())
  url      String
  comments Comment[]
}
```

#### ✅ Pros
- **Integridad referencial real** (FKs en la DB).
- **Cascadas funcionan** con `onDelete: Cascade`.
- Queries con `include` siguen funcionando normal.

#### ❌ Contras
- Tenés que **validar en la app** que solo una FK esté set (constraint CHECK o validación de servicio).
- Cada nuevo modelo "comentable" requiere migración.

#### Validación a nivel servicio en NestJS
```ts
// src/comments/comments.service.ts
import { BadRequestException, Injectable } from '@nestjs/common';
import { PrismaService } from '../prisma/prisma.service';

type Commentable = 'post' | 'video' | 'photo';

@Injectable()
export class CommentsService {
  constructor(private prisma: PrismaService) {}

  async create(content: string, target: { type: Commentable; id: number }) {
    const data: any = { content };

    // 👇 solo uno de estos se va a setear
    if (target.type === 'post')  data.postId  = target.id;
    if (target.type === 'video') data.videoId = target.id;
    if (target.type === 'photo') data.photoId = target.id;

    return this.prisma.comment.create({ data });
  }

  // Helper para queries
  findForPost(postId: number) {
    return this.prisma.comment.findMany({
      where: { postId, videoId: null, photoId: null },
    });
  }
}
```

---

### 9b. Tablas de unión separadas (más limpio)

Cada modelo "comentable" tiene su **propia tabla intermedia**. Más tablas, pero cada relación es **estrictamente 1:N** y el modelo queda super prolijo.

#### `schema.prisma`
```prisma
model Comment {
  id        Int   @id @default(autoincrement())
  content   String
  createdAt DateTime @default(now())

  onPosts  CommentOnPost[]
  onVideos CommentOnVideo[]
}

// ----- Relación con Post -----
model CommentOnPost {
  commentId Int
  postId    Int

  comment Comment @relation(fields: [commentId], references: [id], onDelete: Cascade)
  post    Post    @relation(fields: [postId],    references: [id], onDelete: Cascade)

  @@id([commentId, postId])
  @@index([postId])
}

model Post {
  id       Int             @id @default(autoincrement())
  title    String
  comments CommentOnPost[]
}

// ----- Relación con Video -----
model CommentOnVideo {
  commentId Int
  videoId   Int

  comment Comment @relation(fields: [commentId], references: [id], onDelete: Cascade)
  video   Video   @relation(fields: [videoId],   references: [id], onDelete: Cascade)

  @@id([commentId, videoId])
  @@index([videoId])
}

model Video {
  id       Int              @id @default(autoincrement())
  url      String
  comments CommentOnVideo[]
}
```

#### ✅ Pros
- **Máxima claridad** del modelo de datos.
- Cero validaciones extra en la app.
- Podés agregar **campos extra a la relación** (rating, pinned, etc.) en cada tabla intermedia.

#### ❌ Contras
- **Más migraciones** y proliferación de tablas si tenés muchos tipos comentables.
- Queries para "traer todos los comentarios de un commentable" son más verbosas.

#### NestJS
```ts
// Crear comment en un Post
await this.prisma.commentOnPost.create({
  data: {
    post:    { connect: { id: postId } },
    comment: { create: { content: 'Buen post!' } },
  },
  include: { comment: true },
});

// Traer todos los comments de un Post
const post = await this.prisma.post.findUnique({
  where: { id: postId },
  include: {
    comments: { include: { comment: true } },
  },
});
```

---

### 9c. Discriminator + JSON (más flexible, menos seguro)

Un único campo `commentableType` indica el modelo y `commentableId` el id. **No hay FK real**, por lo que se pierde la integridad referencial.

#### `schema.prisma`
```prisma
model Comment {
  id               Int      @id @default(autoincrement())
  content          String
  commentableType  String   // "Post" | "Video" | "Photo"
  commentableId    Int
  metadata         Json?    // 👈 datos extra flexibles
  createdAt        DateTime @default(now())

  @@index([commentableType, commentableId])
}
```

#### ✅ Pros
- **Una sola tabla**, super flexible.
- Escala a N modelos sin migrar.
- Sirve si los "comentables" son dinámicos (configurados por admin, por ejemplo).

#### ❌ Contras
- **No hay FK en la DB** — si borrás un Post, los comments quedan huérfanos. Hay que limpiar manualmente o con un job.
- **No podés usar `include`** para traer la entidad padre directamente.
- Tenés que **validar el `commentableType`** en la app contra una whitelist.

#### NestJS — cargar la entidad padre manualmente
```ts
type CommentableType = 'Post' | 'Video' | 'Photo';

async createComment(
  content: string,
  type: CommentableType,
  id: number,
) {
  // 1) Validar que la entidad padre existe
  const exists = await this.prisma[type.toLowerCase()].findUnique({ where: { id } });
  if (!exists) throw new NotFoundException(`${type} ${id} no existe`);

  // 2) Crear el comment
  return this.prisma.comment.create({
    data: {
      content,
      commentableType: type,
      commentableId: id,
    },
  });
}

async findCommentsFor(type: CommentableType, id: number) {
  return this.prisma.comment.findMany({
    where: { commentableType: type, commentableId: id },
  });
}
```

---

### 9d. Helper en NestJS para tipar la entidad padre

Si usás el patrón **9c (discriminator)**, este helper te simplifica la vida evitando el `as any`:

```ts
// src/common/prisma-helpers.ts
import { PrismaClient } from '@prisma/client';

type CommentableModel = 'post' | 'video' | 'photo';

export async function assertExists(
  prisma: PrismaClient,
  model: CommentableModel,
  id: number,
) {
  // Prisma tipa esto mal a veces, pero funciona en runtime
  const found = await (prisma as any)[model].findUnique({ where: { id } });
  if (!found) {
    throw new NotFoundException(`${model} con id ${id} no existe`);
  }
  return found;
}
```

---

### 📊 ¿Cuál patrón elijo?

| Patrón | Integridad FK | Flexibilidad | Tablas extra | Mejor para... |
|--------|:---:|:---:|:---:|---|
| **9a. Múltiples FKs opcionales** | ✅ Sí | 🟡 Media | 0 | Pocos modelos comentables (< 5) que cambian seguido |
| **9b. Tablas de unión** | ✅ Sí | 🔴 Baja | N | Muchos modelos comentables y/o cada relación tiene metadata distinta |
| **9c. Discriminator + JSON** | ❌ No | 🟢 Alta | 0 | Comentables **dinámicos** o muchos (> 10) y la performance no es crítica |

**Regla de oro:** empezá con **9a** (múltiples FKs). Si te quedás corto o tenés que meter muchos modelos nuevos, migrá a **9b**. Usá **9c** solo si realmente necesitás flexibilidad extrema (sistemas de plugins, CMS multi-tenant, etc.).

---

## 🚀 Cheatsheet rápido

```prisma
// 1:1
model A { b B? }
model B { aId Int @unique; a A @relation(fields: [aId], references: [id]) }

// 1:N
model A { bs B[] }
model B { aId Int; a A @relation(fields: [aId], references: [id]) }

// M:N implícita
model A { bs B[] @relation("AB") }
model B { as A[] @relation("AB") }

// M:N explícita
model A       { abs AB[] }
model B       { abs AB[] }
model AB {
  aId Int; bId Int
  a A @relation(fields: [aId], references: [id])
  b B @relation(fields: [bId], references: [id])
  @@id([aId, bId])
}
```

---

> **¿Te quedó alguna duda?** Lo más común al principio es no entender **cuál es el lado "dueño"** de la relación. La regla es simple: el dueño es el que tiene `fields` + `references`. El otro lado solo lleva el array o el objeto sin esos campos. Una vez que lo tenés claro, todo lo demás cae solo.
