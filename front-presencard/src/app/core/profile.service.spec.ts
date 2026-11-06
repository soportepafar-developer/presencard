import { provideHttpClient } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { TestBed } from '@angular/core/testing';
import { User } from '../shared/models/user';
import { AuthService } from './auth.service';
import { ProfileService } from './profile.service';

describe('ProfileService', () => {
  let service: ProfileService;
  let http: HttpTestingController;
  let auth: jasmine.SpyObj<AuthService>;

  const updated: User = {
    id: 3,
    email: 'ana.nueva@pafar.net',
    fullName: 'Ana Nueva',
    role: 'ROLE_USER',
    active: true,
    createdAt: '2026-10-27T15:37:30+00:00',
    updatedAt: '2026-10-28T10:00:00+00:00',
  };

  beforeEach(() => {
    auth = jasmine.createSpyObj<AuthService>('AuthService', ['setUser']);
    TestBed.configureTestingModule({
      providers: [provideHttpClient(), provideHttpClientTesting(), { provide: AuthService, useValue: auth }],
    });
    service = TestBed.inject(ProfileService);
    http = TestBed.inject(HttpTestingController);
  });

  afterEach(() => http.verify());

  it('update guarda el perfil y refresca el usuario de la sesión', () => {
    service.update({ fullName: 'Ana Nueva', email: 'ana.nueva@pafar.net' }).subscribe();

    const request = http.expectOne('/api/me');
    expect(request.request.method).toBe('PUT');
    expect(request.request.body).toEqual({ fullName: 'Ana Nueva', email: 'ana.nueva@pafar.net' });
    request.flush(updated);

    expect(auth.setUser).toHaveBeenCalledWith(updated);
  });

  it('changePassword envía la contraseña actual y la nueva', () => {
    service.changePassword('actual-123', 'nueva-456').subscribe();

    const request = http.expectOne('/api/me/password');
    expect(request.request.method).toBe('PUT');
    expect(request.request.body).toEqual({ currentPassword: 'actual-123', newPassword: 'nueva-456' });
    request.flush(null, { status: 204, statusText: 'No Content' });
  });
});
