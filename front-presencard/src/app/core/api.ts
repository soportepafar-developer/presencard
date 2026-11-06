import { HttpErrorResponse } from '@angular/common/http';
import { APP_ROOT } from './app-root';

/** La API vive en el mismo dominio y bajo el mismo prefijo que la app (proxy en desarrollo). */
export const API_BASE = `${APP_ROOT}/api`;

/** Mensaje legible para el usuario a partir de un error HTTP de la API. */
export function apiErrorMessage(error: unknown, fallback = 'Ocurrió un error inesperado. Inténtalo de nuevo.'): string {
  if (error instanceof HttpErrorResponse) {
    const message = (error.error as { message?: unknown } | null)?.message;

    if (typeof message === 'string' && message !== '') {
      return message;
    }

    if (error.status === 0) {
      return 'No se pudo conectar con el servidor.';
    }
  }

  return fallback;
}
