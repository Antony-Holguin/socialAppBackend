import { INestApplication, ValidationPipe } from '@nestjs/common';
import { Test, TestingModule } from '@nestjs/testing';
import * as argon2 from 'argon2';
import request from 'supertest';
import { App } from 'supertest/types';
import { AppModule } from '../src/app.module';
import { PrismaService } from '../prisma/prisma.service';

/**
 * Auth end-to-end tests against the **real** Postgres database.
 * The user's DATABASE_URL is reused; the suite resets the rows it touches.
 *
 * Required env: DATABASE_URL (already in .env).
 */
describe('Auth (e2e)', () => {
  let app: INestApplication<App>;
  let prisma: PrismaService;

  beforeAll(async () => {
    const moduleRef: TestingModule = await Test.createTestingModule({
      imports: [AppModule],
    }).compile();

    app = moduleRef.createNestApplication();
    app.setGlobalPrefix('api/v1');
    app.useGlobalPipes(
      new ValidationPipe({
        whitelist: true,
        forbidNonWhitelisted: true,
        transform: true,
      }),
    );
    await app.init();

    prisma = moduleRef.get(PrismaService);
  });

  afterAll(async () => {
    await app.close();
  });

  beforeEach(async () => {
    await prisma.refreshToken.deleteMany();
    await prisma.user.deleteMany();
  });

  const valid = {
    name: 'Alice',
    email: 'alice@example.com',
    password: 'S3cretP@ss',
  };

  it('rejects an empty /api/v1/auth/register body with 400', async () => {
    const res = await request(app.getHttpServer())
      .post('/api/v1/auth/register')
      .send({});
    expect(res.status).toBe(400);
  });

  it('registers a user and returns access + refresh tokens', async () => {
    const res = await request(app.getHttpServer())
      .post('/api/v1/auth/register')
      .send(valid);
    expect(res.status).toBe(201);
    expect(res.body.accessToken).toEqual(expect.any(String));
    expect(res.body.refreshToken).toEqual(expect.any(String));
    expect(res.body.user).toEqual(
      expect.objectContaining({ id: expect.any(Number), email: valid.email }),
    );
    expect(res.body.user.password).toBeUndefined();
  });

  it('rejects duplicate registration with 409', async () => {
    await request(app.getHttpServer()).post('/api/v1/auth/register').send(valid).expect(201);
    const dup = await request(app.getHttpServer()).post('/api/v1/auth/register').send(valid);
    expect(dup.status).toBe(409);
  });

  it('hashes the password before storing it', async () => {
    await request(app.getHttpServer()).post('/api/v1/auth/register').send(valid).expect(201);
    const stored = await prisma.user.findUnique({ where: { email: valid.email } });
    expect(stored).not.toBeNull();
    expect(stored!.password.startsWith('$argon2')).toBe(true);
    expect(stored!.password).not.toContain(valid.password);
    await expect(argon2.verify(stored!.password, valid.password)).resolves.toBe(true);
  });

  it('logs in with correct credentials', async () => {
    await request(app.getHttpServer()).post('/api/v1/auth/register').send(valid).expect(201);
    const res = await request(app.getHttpServer())
      .post('/api/v1/auth/login')
      .send({ email: valid.email, password: valid.password });
    expect(res.status).toBe(200);
    expect(res.body.accessToken).toEqual(expect.any(String));
  });

  it('rejects login with a wrong password (401)', async () => {
    await request(app.getHttpServer()).post('/api/v1/auth/register').send(valid).expect(201);
    const res = await request(app.getHttpServer())
      .post('/api/v1/auth/login')
      .send({ email: valid.email, password: 'WRONG-PASSWORD' });
    expect(res.status).toBe(401);
  });

  it('GET /api/v1/auth/me requires a valid bearer', async () => {
    const noAuth = await request(app.getHttpServer()).get('/api/v1/auth/me');
    expect(noAuth.status).toBe(401);

    const reg = await request(app.getHttpServer())
      .post('/api/v1/auth/register')
      .send(valid)
      .expect(201);
    const me = await request(app.getHttpServer())
      .get('/api/v1/auth/me')
      .set('Authorization', `Bearer ${reg.body.accessToken}`);
    expect(me.status).toBe(200);
    expect(me.body.email).toBe(valid.email);
    expect(me.body.password).toBeUndefined();
  });

  it('rotates the refresh token and revokes the old one', async () => {
    const reg = await request(app.getHttpServer())
      .post('/api/v1/auth/register')
      .send(valid)
      .expect(201);
    const oldRefresh = reg.body.refreshToken as string;

    const fresh = await request(app.getHttpServer())
      .post('/api/v1/auth/refresh')
      .send({ refreshToken: oldRefresh });
    expect(fresh.status).toBe(200);
    expect(fresh.body.refreshToken).not.toBe(oldRefresh);

    const replay = await request(app.getHttpServer())
      .post('/api/v1/auth/refresh')
      .send({ refreshToken: oldRefresh });
    expect(replay.status).toBe(401);
  });

  it('logout invalidates the refresh token', async () => {
    const reg = await request(app.getHttpServer())
      .post('/api/v1/auth/register')
      .send(valid)
      .expect(201);
    const access = reg.body.accessToken as string;
    const refresh = reg.body.refreshToken as string;

    const out = await request(app.getHttpServer())
      .post('/api/v1/auth/logout')
      .set('Authorization', `Bearer ${access}`)
      .send({ refreshToken: refresh });
    expect(out.status).toBe(200);

    const followup = await request(app.getHttpServer())
      .post('/api/v1/auth/refresh')
      .send({ refreshToken: refresh });
    expect(followup.status).toBe(401);
  });
});
