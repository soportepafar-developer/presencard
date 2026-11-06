import { provideHttpClient } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { TestBed } from '@angular/core/testing';
import { User } from '../shared/models/user';
import { AuthService } from './auth.service';

const adminUser: User = {
  id: 1,
  email: 'admin@pafar.net',
  fullName: 'Admin',
  role: 'ROLE_ADMIN',
  active: true,
  createdAt: '2026-10-27T15:37:30+00:00',
  updatedAt: '2026-10-27T15:37:30+00:00',
};

describe('AuthService', () => {
  let service: AuthService;
  let http: HttpTestingController;

  beforeEach(() => {
    TestBed.configureTestingModule({ providers: [provideHttpClient(), provideHttpClientTesting()] });
    service = TestBed.inject(AuthService);
    http = TestBed.inject(HttpTestingController);
  });

  afterEach(() => http.verify());

  it('login envía las credenciales como JSON y guarda el usuario', () => {
    service.login('admin@pafar.net', 'secreto').subscribe();

    const request = http.expectOne('/api/login');
    expect(request.request.method).toBe('POST');
    expect(request.request.body).toEqual({ email: 'admin@pafar.net', password: 'secreto' });
    request.flush(adminUser);

    expect(service.user()?.email).toBe('admin@pafar.net');
    expect(service.isAuthenticated()).toBeTrue();
  });

  it('restoreSession recupera la sesión desde /api/me', () => {
    let result: boolean | undefined;
    service.restoreSession().subscribe((value) => (result = value));

    http.expectOne('/api/me').flush(adminUser);

    expect(result).toBeTrue();
    expect(service.isAuthenticated()).toBeTrue();
  });

  it('restoreSession sin sesión devuelve false y no vuelve a consultar al servidor', () => {
    const results: boolean[] = [];
    service.restoreSession().subscribe((value) => results.push(value));
    http.expectOne('/api/me').flush(null, { status: 401, statusText: 'Unauthorized' });

    service.restoreSession().subscribe((value) => results.push(value));

    expect(results).toEqual([false, false]);
    http.expectNone('/api/me');
  });

  it('restoreSession con usuario ya cargado no consulta al servidor', () => {
    service.login('a@b.com', 'x').subscribe();
    http.expectOne('/api/login').flush({ ...adminUser, email: 'a@b.com' });

    let result: boolean | undefined;
    service.restoreSession().subscribe((value) => (result = value));

    expect(result).toBeTrue();
    http.expectNone('/api/me');
  });

  it('logout limpia el usuario aunque el servidor falle', () => {
    service.login('a@b.com', 'x').subscribe();
    http.expectOne('/api/login').flush({ ...adminUser, email: 'a@b.com' });

    service.logout().subscribe({ error: () => undefined });
    http.expectOne('/api/logout').flush(null, { status: 500, statusText: 'Error' });

    expect(service.isAuthenticated()).toBeFalse();
  });

  it('isAdmin depende del rol del usuario autenticado', () => {
    expect(service.isAdmin()).toBeFalse();

    service.login('admin@pafar.net', 'x').subscribe();
    http.expectOne('/api/login').flush(adminUser);
    expect(service.isAdmin()).toBeTrue();

    service.setUser({ ...adminUser, role: 'ROLE_USER' });
    expect(service.isAdmin()).toBeFalse();
  });

  it('setUser actualiza los datos locales sin pedir nada al servidor', () => {
    service.login('admin@pafar.net', 'x').subscribe();
    http.expectOne('/api/login').flush(adminUser);

    service.setUser({ ...adminUser, fullName: 'Nombre Nuevo' });

    expect(service.user()?.fullName).toBe('Nombre Nuevo');
  });
});
