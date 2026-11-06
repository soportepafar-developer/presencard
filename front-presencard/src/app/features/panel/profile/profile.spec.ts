import { HttpErrorResponse } from '@angular/common/http';
import { signal } from '@angular/core';
import { TestBed } from '@angular/core/testing';
import { provideRouter } from '@angular/router';
import { RouterTestingHarness } from '@angular/router/testing';
import { of, throwError } from 'rxjs';
import { AuthService } from '../../../core/auth.service';
import { ProfileService } from '../../../core/profile.service';
import { User } from '../../../shared/models/user';
import { Profile } from './profile';

const me: User = {
  id: 3,
  email: 'ana@pafar.net',
  fullName: 'Ana Pérez',
  role: 'ROLE_USER',
  active: true,
  createdAt: '2026-10-27T15:37:30+00:00',
  updatedAt: '2026-10-27T15:37:30+00:00',
};

describe('Profile', () => {
  let profiles: jasmine.SpyObj<ProfileService>;

  async function open(user: User = me): Promise<{ harness: RouterTestingHarness; root: HTMLElement }> {
    TestBed.configureTestingModule({
      providers: [
        provideRouter([{ path: 'panel/profile', component: Profile }]),
        { provide: ProfileService, useValue: profiles },
        { provide: AuthService, useValue: { user: signal(user) } },
      ],
    });
    const harness = await RouterTestingHarness.create();
    await harness.navigateByUrl('/panel/profile', Profile);
    harness.detectChanges();

    return { harness, root: harness.routeNativeElement as HTMLElement };
  }

  function fill(root: HTMLElement, values: Record<string, string>): void {
    for (const [id, value] of Object.entries(values)) {
      const input = root.querySelector(`#${id}`) as HTMLInputElement;
      input.value = value;
      input.dispatchEvent(new Event('input'));
    }
  }

  const submitForm = (harness: RouterTestingHarness, root: HTMLElement, index: number) => {
    root.querySelectorAll('form')[index].dispatchEvent(new Event('submit'));
    harness.detectChanges();
  };

  beforeEach(() => {
    profiles = jasmine.createSpyObj<ProfileService>('ProfileService', ['update', 'changePassword']);
  });

  it('muestra los datos, el rol y el estado de la cuenta', async () => {
    const { root } = await open();

    expect(root.querySelector('h1')?.textContent).toContain('Ana Pérez');
    expect(root.querySelector('.avatar')?.textContent?.trim()).toBe('AP');
    expect(root.querySelector('.meta')?.textContent).toContain('Usuario');
    expect(root.querySelector('.meta')?.textContent).toContain('Cuenta activa');
    expect((root.querySelector('#fullName') as HTMLInputElement).value).toBe('Ana Pérez');
    expect((root.querySelector('#email') as HTMLInputElement).value).toBe('ana@pafar.net');
  });

  it('muestra "Administrador" para quien lo es', async () => {
    const { root } = await open({ ...me, role: 'ROLE_ADMIN' });

    expect(root.querySelector('.meta')?.textContent).toContain('Administrador');
  });

  it('solo permite guardar los datos cuando hubo cambios', async () => {
    const { harness, root } = await open();
    const save = root.querySelectorAll('form')[0].querySelector('button[type="submit"]') as HTMLButtonElement;

    expect(save.disabled).toBeTrue();

    fill(root, { fullName: 'Ana M. Pérez' });
    harness.detectChanges();

    expect(save.disabled).toBeFalse();
  });

  it('actualiza nombre y correo y confirma el cambio', async () => {
    profiles.update.and.returnValue(of({ ...me, fullName: 'Ana M. Pérez', email: 'ana.m@pafar.net' }));
    const { harness, root } = await open();

    fill(root, { fullName: ' Ana M. Pérez ', email: ' ana.m@pafar.net ' });
    submitForm(harness, root, 0);

    expect(profiles.update).toHaveBeenCalledWith({ fullName: 'Ana M. Pérez', email: 'ana.m@pafar.net' });
    expect(root.querySelector('.alert-success')?.textContent).toContain('Tus datos se actualizaron');
  });

  it('no envía datos inválidos y muestra el error del servidor (correo repetido)', async () => {
    const { harness, root } = await open();

    fill(root, { email: 'no-es-correo' });
    submitForm(harness, root, 0);
    expect(profiles.update).not.toHaveBeenCalled();
    expect(root.textContent).toContain('Ingresa un correo válido');

    profiles.update.and.returnValue(
      throwError(() => new HttpErrorResponse({ status: 422, error: { message: 'El correo admin@pafar.net ya está registrado.' } })),
    );
    fill(root, { email: 'admin@pafar.net' });
    submitForm(harness, root, 0);

    expect(root.querySelector('.alert-error')?.textContent).toContain('ya está registrado');
  });

  it('exige que la nueva contraseña coincida con su confirmación', async () => {
    const { harness, root } = await open();

    fill(root, { currentPassword: 'actual-123', newPassword: 'Nueva-Clave-999', confirmPassword: 'otra-cosa-999' });
    root.querySelector('#confirmPassword')?.dispatchEvent(new Event('blur'));
    submitForm(harness, root, 1);

    expect(profiles.changePassword).not.toHaveBeenCalled();
    expect(root.textContent).toContain('Las contraseñas no coinciden');
  });

  it('rechaza una contraseña nueva demasiado corta', async () => {
    const { harness, root } = await open();

    fill(root, { currentPassword: 'actual-123', newPassword: 'corta', confirmPassword: 'corta' });
    submitForm(harness, root, 1);

    expect(profiles.changePassword).not.toHaveBeenCalled();
    expect(root.textContent).toContain('Debe tener entre 8 y 72 caracteres');
  });

  it('cambia la contraseña, avisa y limpia el formulario', async () => {
    profiles.changePassword.and.returnValue(of(undefined));
    const { harness, root } = await open();

    fill(root, { currentPassword: 'actual-123', newPassword: 'Nueva-Clave-999', confirmPassword: 'Nueva-Clave-999' });
    submitForm(harness, root, 1);

    expect(profiles.changePassword).toHaveBeenCalledWith('actual-123', 'Nueva-Clave-999');
    expect(root.querySelector('.alert-success')?.textContent).toContain('Contraseña actualizada');
    expect((root.querySelector('#currentPassword') as HTMLInputElement).value).toBe('');
    expect((root.querySelector('#newPassword') as HTMLInputElement).value).toBe('');
  });

  it('muestra el error del servidor si la contraseña actual es incorrecta', async () => {
    profiles.changePassword.and.returnValue(
      throwError(() => new HttpErrorResponse({ status: 422, error: { message: 'La contraseña actual no es correcta.' } })),
    );
    const { harness, root } = await open();

    fill(root, { currentPassword: 'equivocada', newPassword: 'Nueva-Clave-999', confirmPassword: 'Nueva-Clave-999' });
    submitForm(harness, root, 1);

    expect(root.querySelector('.alert-error')?.textContent).toContain('La contraseña actual no es correcta.');
    expect((root.querySelector('#newPassword') as HTMLInputElement).value).toBe('Nueva-Clave-999');
  });
});
