import { ExecutionContext, UnauthorizedException } from '@nestjs/common';
import { Reflector } from '@nestjs/core';
import { JwtAuthGuard } from './jwt-auth.guard';
import { IS_PUBLIC_KEY } from '../decorators/public.decorator';

type AnyHandler = () => void;
type AnyClass = new (...args: unknown[]) => object;

function makeContext(): ExecutionContext {
  const handler: AnyHandler = () => undefined;
  const klass: AnyClass = class {};
  return {
    getHandler: () => handler,
    getClass: () => klass,
  } as unknown as ExecutionContext;
}

describe('JwtAuthGuard', () => {
  it('short-circuits to true when @Public() is set on the handler', () => {
    const reflector = {
      getAllAndOverride: jest.fn((key: string) =>
        key === IS_PUBLIC_KEY ? true : false,
      ),
    } as unknown as Reflector;

    const guard = new JwtAuthGuard(reflector);
    expect(guard.canActivate(makeContext())).toBe(true);
  });

  it('queries the Reflector on the handler and class for IS_PUBLIC_KEY', () => {
    const reflector = {
      getAllAndOverride: jest.fn(() => false),
    } as unknown as Reflector;

    const guard = new JwtAuthGuard(reflector);
    const ctx = makeContext();

    // Stub the inherited AuthGuard('jwt').canActivate so we don't touch the real passport pipeline.
    const parentProto = Object.getPrototypeOf(JwtAuthGuard.prototype) as {
      canActivate: (context: ExecutionContext) => unknown;
    };
    const realSuper = parentProto.canActivate;
    parentProto.canActivate = () => true;

    try {
      expect(guard.canActivate(ctx)).toBe(true);
    } finally {
      parentProto.canActivate = realSuper;
    }

    expect(reflector.getAllAndOverride).toHaveBeenCalledWith(IS_PUBLIC_KEY, [
      ctx.getHandler(),
      ctx.getClass(),
    ]);
  });
});
