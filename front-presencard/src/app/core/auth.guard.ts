import { inject } from '@angular/core';
import { CanActivateFn, Router } from '@angular/router';
import { map } from 'rxjs';
import { AuthService } from './auth.service';

/** Protege /panel: sin sesión redirige a /login. */
export const authGuard: CanActivateFn = () => {
  const router = inject(Router);

  return inject(AuthService)
    .restoreSession()
    .pipe(map((authenticated) => authenticated || router.createUrlTree(['/login'])));
};

/** Evita mostrar /login a quien ya tiene sesión. */
export const guestGuard: CanActivateFn = () => {
  const router = inject(Router);

  return inject(AuthService)
    .restoreSession()
    .pipe(map((authenticated) => !authenticated || router.createUrlTree(['/panel'])));
};

/** Solo administradores: el resto vuelve a sus tarjetas (el servidor además responde 403). */
export const adminGuard: CanActivateFn = () => {
  const auth = inject(AuthService);
  const router = inject(Router);

  return auth.restoreSession().pipe(
    map((authenticated) => {
      if (!authenticated) {
        return router.createUrlTree(['/login']);
      }

      return auth.isAdmin() || router.createUrlTree(['/panel/cards']);
    }),
  );
};
