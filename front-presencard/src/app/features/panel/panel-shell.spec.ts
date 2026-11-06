import { signal } from '@angular/core';
import { TestBed } from '@angular/core/testing';
import { Router, provideRouter } from '@angular/router';
import { RouterTestingHarness } from '@angular/router/testing';
import { of } from 'rxjs';
import { AuthService } from '../../core/auth.service';
import { User } from '../../shared/models/user';
import { PanelShell } from './panel-shell';

describe('PanelShell', () => {
  const user = (role: User['role']): User => ({
    id: 1,
    email: 'maria@pafar.net',
    fullName: 'María Fernanda Rojas',
    role,
    active: true,
    createdAt: '2026-10-27T15:37:30+00:00',
    updatedAt: '2026-10-27T15:37:30+00:00',
  });

  let auth: { user: ReturnType<typeof signal<User | null>>; isAdmin: ReturnType<typeof signal<boolean>>; logout: jasmine.Spy };

  async function open(role: User['role']): Promise<HTMLElement> {
    auth = {
      user: signal<User | null>(user(role)),
      isAdmin: signal(role === 'ROLE_ADMIN'),
      logout: jasmine.createSpy('logout').and.returnValue(of(undefined)),
    };
    TestBed.configureTestingModule({
      providers: [provideRouter([{ path: 'panel', component: PanelShell, children: [] }]), { provide: AuthService, useValue: auth }],
    });
    const harness = await RouterTestingHarness.create();
    await harness.navigateByUrl('/panel', PanelShell);
    harness.detectChanges();

    return harness.routeNativeElement as HTMLElement;
  }

  const links = (root: HTMLElement) => [...root.querySelectorAll('.nav a')].map((a) => a.textContent?.trim());

  it('el administrador ve Tarjetas y Usuarios', async () => {
    const root = await open('ROLE_ADMIN');

    expect(links(root)).toEqual(['Tarjetas', 'Usuarios']);
  });

  it('el usuario normal solo ve Tarjetas', async () => {
    const root = await open('ROLE_USER');

    expect(links(root)).toEqual(['Tarjetas']);
  });

  it('muestra el nombre y las iniciales con acceso al perfil', async () => {
    const root = await open('ROLE_USER');

    expect(root.querySelector('.who')?.textContent).toContain('María Fernanda Rojas');
    expect(root.querySelector('.me .avatar')?.textContent?.trim()).toBe('MF');
    expect(root.querySelector('a.me')?.getAttribute('href')).toBe('/panel/profile');
  });

  it('cerrar sesión llama al servidor y vuelve al login', async () => {
    const root = await open('ROLE_USER');
    const navigate = spyOn(TestBed.inject(Router), 'navigate').and.resolveTo(true);

    (root.querySelector('.session button') as HTMLButtonElement).click();

    expect(auth.logout).toHaveBeenCalled();
    expect(navigate).toHaveBeenCalledWith(['/login']);
  });
});
