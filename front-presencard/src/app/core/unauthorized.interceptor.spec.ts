import { HttpClient, provideHttpClient, withInterceptors } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { TestBed } from '@angular/core/testing';
import { Router } from '@angular/router';
import { AuthService } from './auth.service';
import { unauthorizedInterceptor } from './unauthorized.interceptor';

describe('unauthorizedInterceptor', () => {
  let http: HttpClient;
  let controller: HttpTestingController;
  let auth: jasmine.SpyObj<AuthService>;
  let router: jasmine.SpyObj<Router>;

  beforeEach(() => {
    auth = jasmine.createSpyObj<AuthService>('AuthService', ['clear']);
    router = jasmine.createSpyObj<Router>('Router', ['navigate']);
    router.navigate.and.resolveTo(true);

    TestBed.configureTestingModule({
      providers: [
        provideHttpClient(withInterceptors([unauthorizedInterceptor])),
        provideHttpClientTesting(),
        { provide: AuthService, useValue: auth },
        { provide: Router, useValue: router },
      ],
    });
    http = TestBed.inject(HttpClient);
    controller = TestBed.inject(HttpTestingController);
  });

  afterEach(() => controller.verify());

  it('un 401 en la API del panel limpia la sesión y manda al login', () => {
    http.get('/api/cards').subscribe({ error: () => undefined });
    controller.expectOne('/api/cards').flush(null, { status: 401, statusText: 'Unauthorized' });

    expect(auth.clear).toHaveBeenCalled();
    expect(router.navigate).toHaveBeenCalledWith(['/login']);
  });

  it('un 401 en /api/login (credenciales malas) no redirige', () => {
    http.post('/api/login', {}).subscribe({ error: () => undefined });
    controller.expectOne('/api/login').flush(null, { status: 401, statusText: 'Unauthorized' });

    expect(auth.clear).not.toHaveBeenCalled();
    expect(router.navigate).not.toHaveBeenCalled();
  });

  it('otros errores se propagan sin tocar la sesión', () => {
    let status = 0;
    http.get('/api/cards').subscribe({ error: (e) => (status = e.status) });
    controller.expectOne('/api/cards').flush(null, { status: 500, statusText: 'Error' });

    expect(status).toBe(500);
    expect(auth.clear).not.toHaveBeenCalled();
  });

  it('un 401 al guardar el perfil (PUT /api/me, sesión expirada) sí redirige', () => {
    http.put('/api/me', {}).subscribe({ error: () => undefined });
    controller.expectOne('/api/me').flush(null, { status: 401, statusText: 'Unauthorized' });

    expect(router.navigate).toHaveBeenCalledWith(['/login']);
  });

  it('la comprobación inicial de sesión (GET /api/me) devuelve 401 sin redirigir', () => {
    http.get('/api/me').subscribe({ error: () => undefined });
    controller.expectOne('/api/me').flush(null, { status: 401, statusText: 'Unauthorized' });

    expect(router.navigate).not.toHaveBeenCalled();
  });

  it('un 403 (sin permiso) no cierra la sesión', () => {
    http.get('/api/users').subscribe({ error: () => undefined });
    controller.expectOne('/api/users').flush(null, { status: 403, statusText: 'Forbidden' });

    expect(auth.clear).not.toHaveBeenCalled();
    expect(router.navigate).not.toHaveBeenCalled();
  });
});
