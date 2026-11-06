import { provideHttpClient } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { TestBed } from '@angular/core/testing';
import { PresencardInput } from '../shared/models/presencard';
import { PresencardService } from './presencard.service';

describe('PresencardService', () => {
  let service: PresencardService;
  let http: HttpTestingController;

  const input: PresencardInput = {
    fullName: 'Ana Pérez',
    jobTitle: 'CTO',
    company: 'Consultores JPL',
    email: 'ana@pafar.com.ve',
    phone: '0412-1112233',
    whatsapp: null,
    instagram: null,
    telegram: null,
    colorPrimary: null,
    colorAccent: null,
  };

  beforeEach(() => {
    TestBed.configureTestingModule({ providers: [provideHttpClient(), provideHttpClientTesting()] });
    service = TestBed.inject(PresencardService);
    http = TestBed.inject(HttpTestingController);
  });

  afterEach(() => http.verify());

  it('list envía página y búsqueda recortada', () => {
    service.list({ q: '  ana ', page: 2 }).subscribe();

    const request = http.expectOne((r) => r.url === '/api/cards');
    expect(request.request.params.get('page')).toBe('2');
    expect(request.request.params.get('q')).toBe('ana');
    request.flush({ items: [], total: 0, page: 2, perPage: 10, totalPages: 1 });
  });

  it('list omite q cuando la búsqueda está vacía', () => {
    service.list({ q: '   ', page: 1 }).subscribe();

    const request = http.expectOne((r) => r.url === '/api/cards');
    expect(request.request.params.has('q')).toBeFalse();
    request.flush({ items: [], total: 0, page: 1, perPage: 10, totalPages: 1 });
  });

  it('get, create, update y delete usan el verbo y la URL correctos', () => {
    service.get(7).subscribe();
    expect(http.expectOne('/api/cards/7').request.method).toBe('GET');

    service.create(input).subscribe();
    const create = http.expectOne('/api/cards');
    expect(create.request.method).toBe('POST');
    expect(create.request.body).toEqual(input);

    service.update(7, input).subscribe();
    const update = http.expectOne((r) => r.method === 'PUT');
    expect(update.request.url).toBe('/api/cards/7');

    service.delete(7).subscribe();
    expect(http.expectOne((r) => r.method === 'DELETE').request.url).toBe('/api/cards/7');
  });

  it('publicCard codifica el hash en la URL', () => {
    service.publicCard('a/b c').subscribe();

    expect(http.expectOne('/api/public/cards/a%2Fb%20c').request.method).toBe('GET');
  });
});
