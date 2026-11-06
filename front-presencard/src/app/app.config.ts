import { APP_BASE_HREF } from '@angular/common';
import { provideHttpClient, withInterceptors } from '@angular/common/http';
import { ApplicationConfig, provideBrowserGlobalErrorListeners, provideZoneChangeDetection } from '@angular/core';
import { provideRouter, withNavigationErrorHandler } from '@angular/router';

import { APP_ROOT } from './core/app-root';
import { recoverFromChunkError } from './core/chunk-reload';
import { unauthorizedInterceptor } from './core/unauthorized.interceptor';
import { routes } from './app.routes';

export const appConfig: ApplicationConfig = {
  providers: [
    provideBrowserGlobalErrorListeners(),
    provideZoneChangeDetection({ eventCoalescing: true }),
    // Si una pestaña antigua pide una pantalla que ya no existe tras un despliegue, recarga en vez de quedarse muda.
    provideRouter(routes, withNavigationErrorHandler((error) => void recoverFromChunkError(error))),
    provideHttpClient(withInterceptors([unauthorizedInterceptor])),
    // Los assets cuelgan de <base href=".../app/">, pero las URLs de la app (/panel, /c/:hash) cuelgan del
    // prefijo de la instalación: '/' en la raíz o '/presencard/' en una subcarpeta.
    { provide: APP_BASE_HREF, useValue: `${APP_ROOT}/` },
  ],
};
