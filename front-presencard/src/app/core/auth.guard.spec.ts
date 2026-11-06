import { TestBed } from '@angular/core/testing';
import { ActivatedRouteSnapshot, Router, RouterStateSnapshot, UrlTree, provideRouter } from '@angular/router';
import { Observable, of } from 'rxjs';
import { adminGuard, authGuard, guestGuard } from './auth.guard';
import { AuthService } from './auth.service';

describe('guards de sesión', () => {
  function run(guard: typeof authGuard, authenticated: boolean, admin = false): boolean | UrlTree {
    TestBed.configureTestingModule({
      providers: [
        provideRouter([]),
        { provide: AuthService, useValue: { restoreSession: () => of(authenticated), isAdmin: () => admin } },
      ],
    });

    let result!: boolean | UrlTree;
    const outcome = TestBed.runInInjectionContext(() =>
      guard({} as ActivatedRouteSnapshot, {} as RouterStateSnapshot),
    ) as Observable<boolean | UrlTree>;
    outcome.subscribe((value) => (result = value));

    return result;
  }

  const path = (tree: boolean | UrlTree) => TestBed.inject(Router).serializeUrl(tree as UrlTree);

  it('authGuard deja pasar a quien tiene sesión', () => {
    expect(run(authGuard, true)).toBeTrue();
  });

  it('authGuard redirige a /login sin sesión', () => {
    expect(path(run(authGuard, false))).toBe('/login');
  });

  it('guestGuard deja ver el login a quien no tiene sesión', () => {
    expect(run(guestGuard, false)).toBeTrue();
  });

  it('guestGuard redirige a /panel si ya hay sesión', () => {
    expect(path(run(guestGuard, true))).toBe('/panel');
  });

  it('adminGuard deja pasar a los administradores', () => {
    expect(run(adminGuard, true, true)).toBeTrue();
  });

  it('adminGuard devuelve a un usuario normal a sus tarjetas', () => {
    expect(path(run(adminGuard, true, false))).toBe('/panel/cards');
  });

  it('adminGuard manda al login a quien no tiene sesión', () => {
    expect(path(run(adminGuard, false, false))).toBe('/login');
  });
});
