import { HttpErrorResponse } from '@angular/common/http';
import { TestBed } from '@angular/core/testing';
import { Router, provideRouter } from '@angular/router';
import { RouterTestingHarness } from '@angular/router/testing';
import { of, throwError } from 'rxjs';
import { AuthService } from '../../core/auth.service';
import { Login } from './login';

describe('Login', () => {
  let auth: jasmine.SpyObj<AuthService>;

  async function open(): Promise<{ harness: RouterTestingHarness; root: HTMLElement }> {
    TestBed.configureTestingModule({
      providers: [provideRouter([{ path: 'login', component: Login }, { path: 'panel', children: [] }]), { provide: AuthService, useValue: auth }],
    });
    const harness = await RouterTestingHarness.create();
    await harness.navigateByUrl('/login', Login);
    harness.detectChanges();

    return { harness, root: harness.routeNativeElement as HTMLElement };
  }

  function fill(root: HTMLElement, email: string, password: string): void {
    for (const [id, value] of [['email', email], ['password', password]]) {
      const input = root.querySelector(`#${id}`) as HTMLInputElement;
      input.value = value;
      input.dispatchEvent(new Event('input'));
    }
  }

  const submit = (harness: RouterTestingHarness, root: HTMLElement) => {
    (root.querySelector('form') as HTMLFormElement).dispatchEvent(new Event('submit'));
    harness.detectChanges();
  };

  beforeEach(() => {
    auth = jasmine.createSpyObj<AuthService>('AuthService', ['login']);
  });

  it('no intenta ingresar con el formulario vacío', async () => {
    const { harness, root } = await open();

    submit(harness, root);

    expect(auth.login).not.toHaveBeenCalled();
    expect(root.querySelectorAll('.field-error').length).toBe(2);
  });

  it('ingresa y navega al panel', async () => {
    auth.login.and.returnValue(of({
        id: 1,
        email: 'admin@pafar.net',
        fullName: 'Admin',
        role: 'ROLE_ADMIN' as const,
        active: true,
        createdAt: '2026-10-27T15:37:30+00:00',
        updatedAt: '2026-10-27T15:37:30+00:00',
      }));
    const { harness, root } = await open();
    const navigate = spyOn(TestBed.inject(Router), 'navigate').and.resolveTo(true);

    fill(root, ' admin@pafar.net ', 'secreto');
    submit(harness, root);

    expect(auth.login).toHaveBeenCalledWith('admin@pafar.net', 'secreto');
    expect(navigate).toHaveBeenCalledWith(['/panel']);
  });

  it('muestra un mensaje claro si las credenciales son incorrectas', async () => {
    auth.login.and.returnValue(throwError(() => new HttpErrorResponse({ status: 401 })));
    const { harness, root } = await open();

    fill(root, 'admin@pafar.net', 'mala');
    submit(harness, root);

    expect(root.querySelector('[role="alert"]')?.textContent).toContain('Correo o contraseña incorrectos.');
    expect((root.querySelector('button[type="submit"]') as HTMLButtonElement).disabled).toBeFalse();
  });

  it('muestra el motivo cuando la cuenta está desactivada', async () => {
    auth.login.and.returnValue(
      throwError(() => new HttpErrorResponse({ status: 403, error: { message: 'Tu cuenta está desactivada. Contacta al administrador.' } })),
    );
    const { harness, root } = await open();

    fill(root, 'ana@pafar.net', 'Clave-Correcta-1');
    submit(harness, root);

    expect(root.querySelector('[role="alert"]')?.textContent).toContain('Tu cuenta está desactivada');
  });

  it('muestra el aviso de espera cuando se agotaron los intentos (429)', async () => {
    auth.login.and.returnValue(
      throwError(() => new HttpErrorResponse({ status: 429, error: { message: 'Demasiados intentos fallidos. Inténtalo de nuevo en 15 minuto(s).' } })),
    );
    const { harness, root } = await open();

    fill(root, 'admin@pafar.net', 'Clave-Correcta-1');
    submit(harness, root);

    expect(root.querySelector('[role="alert"]')?.textContent).toContain('Demasiados intentos fallidos');
    expect(root.querySelector('[role="alert"]')?.textContent).not.toContain('incorrectos');
  });

  it('muestra el aviso de servicio no disponible cuando falla la base de datos (503)', async () => {
    auth.login.and.returnValue(
      throwError(() => new HttpErrorResponse({ status: 503, error: { message: 'No se pudo verificar tu acceso en este momento. Inténtalo de nuevo en unos minutos.' } })),
    );
    const { harness, root } = await open();

    fill(root, 'admin@pafar.net', 'Clave-Correcta-1');
    submit(harness, root);

    expect(root.querySelector('[role="alert"]')?.textContent).toContain('No se pudo verificar tu acceso');
  });
});
