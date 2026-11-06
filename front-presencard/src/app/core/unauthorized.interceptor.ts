import { HttpErrorResponse, HttpInterceptorFn, HttpRequest } from '@angular/common/http';
import { inject } from '@angular/core';
import { Router } from '@angular/router';
import { catchError, throwError } from 'rxjs';
import { API_BASE } from './api';
import { AuthService } from './auth.service';

/** Login y la comprobación inicial de sesión responden 401 por diseño: no deben redirigir. */
const isAuthProbe = (request: HttpRequest<unknown>): boolean =>
  (request.method === 'POST' && request.url === `${API_BASE}/login`) ||
  (request.method === 'GET' && request.url === `${API_BASE}/me`);

/** Si la sesión expira (401 en la API del panel), vuelve al login. */
export const unauthorizedInterceptor: HttpInterceptorFn = (request, next) => {
  const auth = inject(AuthService);
  const router = inject(Router);

  return next(request).pipe(
    catchError((error: unknown) => {
      if (error instanceof HttpErrorResponse && error.status === 401 && !isAuthProbe(request)) {
        auth.clear();
        void router.navigate(['/login']);
      }

      return throwError(() => error);
    }),
  );
};
