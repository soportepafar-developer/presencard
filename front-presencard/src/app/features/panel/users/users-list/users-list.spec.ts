import { HttpErrorResponse } from '@angular/common/http';
import { signal } from '@angular/core';
import { TestBed } from '@angular/core/testing';
import { provideRouter } from '@angular/router';
import { RouterTestingHarness } from '@angular/router/testing';
import { of, throwError } from 'rxjs';
import { AuthService } from '../../../../core/auth.service';
import { UserService } from '../../../../core/user.service';
import { User, UserPage } from '../../../../shared/models/user';
import { UsersList } from './users-list';

const user = (id: number, fullName: string, overrides: Partial<User> = {}): User => ({
  id,
  email: `${fullName.split(' ')[0].toLowerCase()}@pafar.net`,
  fullName,
  role: 'ROLE_USER',
  active: true,
  createdAt: '2026-10-27T15:37:30+00:00',
  updatedAt: '2026-10-27T15:37:30+00:00',
  ...overrides,
});

const page = (items: User[], overrides: Partial<UserPage> = {}): UserPage => ({
  items,
  total: items.length,
  page: 1,
  perPage: 10,
  totalPages: 1,
  ...overrides,
});

describe('UsersList', () => {
  let service: jasmine.SpyObj<UserService>;
  const SELF_ID = 1;

  async function open(): Promise<{ harness: RouterTestingHarness; root: HTMLElement }> {
    TestBed.configureTestingModule({
      providers: [
        provideRouter([{ path: 'panel/users', component: UsersList }]),
        { provide: UserService, useValue: service },
        { provide: AuthService, useValue: { user: signal({ id: SELF_ID }), isAdmin: signal(true) } },
      ],
    });
    const harness = await RouterTestingHarness.create();
    await harness.navigateByUrl('/panel/users', UsersList);
    await harness.fixture.whenStable();
    harness.detectChanges();

    return { harness, root: harness.routeNativeElement as HTMLElement };
  }

  const rows = (root: HTMLElement) => [...root.querySelectorAll<HTMLElement>('li.row:not(.row-head)')];
  const byLabel = (row: HTMLElement, text: string) =>
    row.querySelector<HTMLElement>(`[aria-label^="${text}"]`) as HTMLButtonElement;

  async function settle(harness: RouterTestingHarness): Promise<void> {
    harness.detectChanges();
    await harness.fixture.whenStable();
    harness.detectChanges();
  }

  beforeEach(() => {
    service = jasmine.createSpyObj<UserService>('UserService', ['list', 'setActive', 'delete', 'resetPassword']);
  });

  it('lista usuarios con rol y estado, marcando la propia cuenta', async () => {
    service.list.and.returnValue(
      of(page([user(1, 'Admin Root', { role: 'ROLE_ADMIN' }), user(2, 'Ana Pérez'), user(3, 'Luis Gómez', { active: false })])),
    );

    const { root } = await open();
    const [admin, ana, luis] = rows(root);

    expect(root.textContent).toContain('3 usuarios');
    expect(admin.textContent).toContain('Administrador');
    expect(admin.textContent).toContain('(tú)');
    expect(ana.textContent).toContain('Usuario');
    expect(ana.textContent).toContain('Activo');
    expect(luis.textContent).toContain('Desactivado');
    expect(service.list).toHaveBeenCalledWith({ q: '', page: 1 });
  });

  it('no ofrece desactivar ni eliminar la propia cuenta, pero sí editarla y restablecer su clave', async () => {
    service.list.and.returnValue(of(page([user(SELF_ID, 'Admin Root', { role: 'ROLE_ADMIN' }), user(2, 'Ana Pérez')])));

    const { root } = await open();
    const [self, other] = rows(root);

    expect(self.querySelector('[aria-label^="Editar"]')).not.toBeNull();
    expect(self.querySelector('[aria-label^="Restablecer"]')).not.toBeNull();
    expect(self.querySelector('[aria-label^="Desactivar"]')).toBeNull();
    expect(self.querySelector('[aria-label^="Eliminar"]')).toBeNull();
    expect(other.querySelector('[aria-label^="Desactivar"]')).not.toBeNull();
    expect(other.querySelector('[aria-label^="Eliminar"]')).not.toBeNull();
  });

  it('busca con retraso (debounce)', async () => {
    service.list.and.returnValue(of(page([user(2, 'Ana Pérez')])));
    const { harness, root } = await open();

    const input = root.querySelector('input[type="search"]') as HTMLInputElement;
    input.value = 'ana';
    input.dispatchEvent(new Event('input'));
    expect(service.list).toHaveBeenCalledTimes(1);

    await new Promise((resolve) => setTimeout(resolve, 400));
    harness.detectChanges();

    expect(service.list).toHaveBeenCalledWith({ q: 'ana', page: 1 });
  });

  it('desactiva a un usuario y recarga el listado con un aviso', async () => {
    service.list.and.returnValue(of(page([user(2, 'Ana Pérez')])));
    service.setActive.and.returnValue(of(user(2, 'Ana Pérez', { active: false })));
    const { harness, root } = await open();

    byLabel(rows(root)[0], 'Desactivar').click();
    await settle(harness);

    expect(service.setActive).toHaveBeenCalledWith(2, false);
    expect(service.list).toHaveBeenCalledTimes(2);
    expect(root.querySelector('.alert-success')?.textContent).toContain('Se desactivó la cuenta de Ana Pérez');
  });

  it('reactiva a un usuario desactivado', async () => {
    service.list.and.returnValue(of(page([user(2, 'Ana Pérez', { active: false })])));
    service.setActive.and.returnValue(of(user(2, 'Ana Pérez')));
    const { harness, root } = await open();

    byLabel(rows(root)[0], 'Activar').click();
    await settle(harness);

    expect(service.setActive).toHaveBeenCalledWith(2, true);
    expect(root.querySelector('.alert-success')?.textContent).toContain('Se activó la cuenta de Ana Pérez');
  });

  it('muestra el error del servidor si no se puede cambiar el estado', async () => {
    service.list.and.returnValue(of(page([user(2, 'Ana Pérez')])));
    service.setActive.and.returnValue(
      throwError(() => new HttpErrorResponse({ status: 409, error: { message: 'Debe existir al menos un administrador activo.' } })),
    );
    const { harness, root } = await open();

    byLabel(rows(root)[0], 'Desactivar').click();
    harness.detectChanges();

    expect(root.querySelector('.alert-error')?.textContent).toContain('Debe existir al menos un administrador activo.');
  });

  it('elimina tras confirmar y avisa que las tarjetas pasan al administrador', async () => {
    service.list.and.returnValue(of(page([user(2, 'Ana Pérez')])));
    service.delete.and.returnValue(of(undefined));
    const { harness, root } = await open();

    byLabel(rows(root)[0], 'Eliminar').click();
    harness.detectChanges();

    const dialog = root.querySelector('app-confirm-dialog dialog') as HTMLDialogElement;
    expect(dialog.open).toBeTrue();
    expect(dialog.textContent).toContain('sus tarjetas pasarán a tu cuenta');
    expect(service.delete).not.toHaveBeenCalled();

    (dialog.querySelector('.btn-danger') as HTMLButtonElement).click();
    await settle(harness);

    expect(service.delete).toHaveBeenCalledWith(2);
    expect(service.list).toHaveBeenCalledTimes(2);
    expect(root.querySelector('.alert-success')?.textContent).toContain('Sus tarjetas pasaron a tu cuenta');
  });

  it('restablece la contraseña de otro usuario desde un diálogo', async () => {
    service.list.and.returnValue(of(page([user(2, 'Ana Pérez')])));
    service.resetPassword.and.returnValue(of(undefined));
    const { harness, root } = await open();

    byLabel(rows(root)[0], 'Restablecer').click();
    harness.detectChanges();
    await harness.fixture.whenStable();
    harness.detectChanges();

    const dialog = root.querySelector('app-reset-password-dialog dialog') as HTMLDialogElement;
    expect(dialog.open).toBeTrue();
    expect(dialog.textContent).toContain('Ana Pérez');

    // Contraseña inválida: no se envía.
    const form = dialog.querySelector('form') as HTMLFormElement;
    form.dispatchEvent(new Event('submit'));
    harness.detectChanges();
    expect(service.resetPassword).not.toHaveBeenCalled();

    const input = dialog.querySelector('input') as HTMLInputElement;
    input.value = 'Reset-Clave-777';
    input.dispatchEvent(new Event('input'));
    form.dispatchEvent(new Event('submit'));
    await settle(harness);

    expect(service.resetPassword).toHaveBeenCalledWith(2, 'Reset-Clave-777');
    expect(root.querySelector('.alert-success')?.textContent).toContain('Se restableció la contraseña de Ana Pérez');
  });
});
