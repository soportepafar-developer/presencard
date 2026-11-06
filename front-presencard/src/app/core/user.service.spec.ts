import { provideHttpClient } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { TestBed } from '@angular/core/testing';
import { UserService } from './user.service';

describe('UserService', () => {
  let service: UserService;
  let http: HttpTestingController;

  beforeEach(() => {
    TestBed.configureTestingModule({ providers: [provideHttpClient(), provideHttpClientTesting()] });
    service = TestBed.inject(UserService);
    http = TestBed.inject(HttpTestingController);
  });

  afterEach(() => http.verify());

  it('list envía página y búsqueda recortada, y omite q si está vacía', () => {
    service.list({ q: '  ana ', page: 2 }).subscribe();
    const first = http.expectOne((r) => r.url === '/api/users');
    expect(first.request.params.get('page')).toBe('2');
    expect(first.request.params.get('q')).toBe('ana');
    first.flush({ items: [], total: 0, page: 2, perPage: 10, totalPages: 1 });

    service.list({ q: '  ', page: 1 }).subscribe();
    const second = http.expectOne((r) => r.url === '/api/users');
    expect(second.request.params.has('q')).toBeFalse();
    second.flush({ items: [], total: 0, page: 1, perPage: 10, totalPages: 1 });
  });

  it('usa el verbo y la URL correctos en cada operación', () => {
    const input = { fullName: 'Ana', email: 'ana@pafar.net', role: 'ROLE_USER' as const };

    service.get(7).subscribe();
    expect(http.expectOne('/api/users/7').request.method).toBe('GET');

    service.create({ ...input, password: 'secreto-123' }).subscribe();
    const create = http.expectOne('/api/users');
    expect(create.request.method).toBe('POST');
    expect(create.request.body).toEqual({ ...input, password: 'secreto-123' });

    service.update(7, input).subscribe();
    const update = http.expectOne((r) => r.method === 'PUT' && r.url === '/api/users/7');
    expect(update.request.body).toEqual(input);

    service.resetPassword(7, 'nueva-clave-1').subscribe();
    const reset = http.expectOne('/api/users/7/password');
    expect(reset.request.method).toBe('PUT');
    expect(reset.request.body).toEqual({ password: 'nueva-clave-1' });

    service.setActive(7, false).subscribe();
    const active = http.expectOne('/api/users/7/active');
    expect(active.request.method).toBe('PUT');
    expect(active.request.body).toEqual({ active: false });

    service.delete(7).subscribe();
    const remove = http.expectOne((r) => r.method === 'DELETE');
    expect(remove.request.url).toBe('/api/users/7');
  });
});
