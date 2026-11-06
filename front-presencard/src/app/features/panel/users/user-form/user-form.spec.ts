import { HttpErrorResponse } from '@angular/common/http';
import { signal } from '@angular/core';
import { TestBed } from '@angular/core/testing';
import { Router, provideRouter } from '@angular/router';
import { RouterTestingHarness } from '@angular/router/testing';
import { of, throwError } from 'rxjs';
import { AuthService } from '../../../../core/auth.service';
import { UserService } from '../../../../core/user.service';
import { User } from '../../../../shared/models/user';
import { UserForm } from './user-form';

const saved: User = {
  id: 5,
  email: 'ana@pafar.net',
  fullName: 'Ana Pérez',
  role: 'ROLE_USER',
  active: true,
  createdAt: '2026-10-27T15:37:30+00:00',
  updatedAt: '2026-10-27T15:37:30+00:00',
};

describe('UserForm', () => {
  let service: jasmine.SpyObj<UserService>;
  const SELF_ID = 1;

  async function open(url: string): Promise<{ harness: RouterTestingHarness; root: HTMLElement }> {
    TestBed.configureTestingModule({
      providers: [
        provideRouter([
          { path: 'panel/users/new', component: UserForm },
          { path: 'panel/users/:id/edit', component: UserForm },
          { path: 'panel/users', children: [] },
        ]),
        { provide: UserService, useValue: service },
        { provide: AuthService, useValue: { user: signal({ id: SELF_ID }) } },
      ],
    });
    const harness = await RouterTestingHarness.create();
    await harness.navigateByUrl(url, UserForm);
    harness.detectChanges();

    return { harness, root: harness.routeNativeElement as HTMLElement };
  }

  function fill(root: HTMLElement, values: Record<string, string>): void {
    for (const [id, value] of Object.entries(values)) {
      const input = root.querySelector(`#${id}`) as HTMLInputElement;
      input.value = value;
      input.dispatchEvent(new Event(input.tagName === 'SELECT' ? 'change' : 'input'));
    }
  }

  const submit = (harness: RouterTestingHarness, root: HTMLElement) => {
    (root.querySelector('form') as HTMLFormElement).dispatchEvent(new Event('submit'));
    harness.detectChanges();
  };

  beforeEach(() => {
    service = jasmine.createSpyObj<UserService>('UserService', ['get', 'create', 'update', 'resetPassword']);
  });

  it('al crear exige nombre, correo y una contraseña válida', async () => {
    const { harness, root } = await open('/panel/users/new');

    submit(harness, root);
    expect(service.create).not.toHaveBeenCalled();
    expect(root.querySelectorAll('.field-error').length).toBe(3);

    fill(root, { fullName: 'Ana Pérez', email: 'ana@pafar.net', password: 'corta' });
    submit(harness, root);
    expect(service.create).not.toHaveBeenCalled();
    expect(root.textContent).toContain('Debe tener entre 8 y 72 caracteres');
  });

  it('crea el usuario con el rol elegido y vuelve al listado con un aviso', async () => {
    service.create.and.returnValue(of(saved));
    const { harness, root } = await open('/panel/users/new');
    const navigate = spyOn(TestBed.inject(Router), 'navigate').and.resolveTo(true);

    fill(root, { fullName: '  Ana Pérez ', email: ' ana@pafar.net ', role: 'ROLE_ADMIN', password: 'Clave-Temporal-1' });
    submit(harness, root);

    expect(service.create).toHaveBeenCalledWith({
      fullName: 'Ana Pérez',
      email: 'ana@pafar.net',
      role: 'ROLE_ADMIN',
      password: 'Clave-Temporal-1',
    });
    expect(navigate).toHaveBeenCalledWith(['/panel/users'], { state: { flash: 'Se creó el usuario Ana Pérez.' } });
  });

  it('crea con rol Usuario por defecto', async () => {
    service.create.and.returnValue(of(saved));
    const { harness, root } = await open('/panel/users/new');
    spyOn(TestBed.inject(Router), 'navigate').and.resolveTo(true);

    fill(root, { fullName: 'Ana', email: 'ana@pafar.net', password: 'Clave-Temporal-1' });
    submit(harness, root);

    expect(service.create).toHaveBeenCalledWith(jasmine.objectContaining({ role: 'ROLE_USER' }));
  });

  it('"Generar" propone una contraseña segura y la deja visible', async () => {
    const { harness, root } = await open('/panel/users/new');

    const generate = [...root.querySelectorAll<HTMLButtonElement>('app-password-field button')].find((b) => b.textContent?.includes('Generar'));
    generate?.click();
    harness.detectChanges();

    const password = root.querySelector('#password') as HTMLInputElement;
    expect(password.value.length).toBe(12);
    expect(password.type).toBe('text');
  });

  it('muestra el error del servidor, por ejemplo un correo repetido', async () => {
    service.create.and.returnValue(
      throwError(() => new HttpErrorResponse({ status: 422, error: { message: 'El correo ana@pafar.net ya está registrado.' } })),
    );
    const { harness, root } = await open('/panel/users/new');

    fill(root, { fullName: 'Ana', email: 'ana@pafar.net', password: 'Clave-Temporal-1' });
    submit(harness, root);

    expect(root.querySelector('.alert-error')?.textContent).toContain('ya está registrado');
  });

  it('al editar carga el usuario, no pide contraseña y guarda los cambios', async () => {
    service.get.and.returnValue(of(saved));
    service.update.and.returnValue(of({ ...saved, fullName: 'Ana M. Pérez', role: 'ROLE_ADMIN' }));
    const { harness, root } = await open('/panel/users/5/edit');
    const navigate = spyOn(TestBed.inject(Router), 'navigate').and.resolveTo(true);

    expect(service.get).toHaveBeenCalledWith(5);
    expect(root.querySelector('h1')?.textContent).toContain('Editar usuario');
    expect((root.querySelector('#fullName') as HTMLInputElement).value).toBe('Ana Pérez');
    expect(root.querySelector('#password')).toBeNull();

    fill(root, { fullName: 'Ana M. Pérez', role: 'ROLE_ADMIN' });
    submit(harness, root);

    expect(service.update).toHaveBeenCalledWith(5, { fullName: 'Ana M. Pérez', email: 'ana@pafar.net', role: 'ROLE_ADMIN' });
    expect(navigate).toHaveBeenCalledWith(['/panel/users'], { state: { flash: 'Se guardaron los cambios de Ana M. Pérez.' } });
  });

  it('al editar tu propia cuenta el rol queda bloqueado y se envía sin cambios', async () => {
    service.get.and.returnValue(of({ ...saved, id: SELF_ID, role: 'ROLE_ADMIN' }));
    service.update.and.returnValue(of({ ...saved, id: SELF_ID, role: 'ROLE_ADMIN' }));
    const { harness, root } = await open(`/panel/users/${SELF_ID}/edit`);
    spyOn(TestBed.inject(Router), 'navigate').and.resolveTo(true);

    expect((root.querySelector('#role') as HTMLSelectElement).disabled).toBeTrue();
    expect(root.textContent).toContain('No puedes cambiar tu propio rol');

    submit(harness, root);

    expect(service.update).toHaveBeenCalledWith(SELF_ID, jasmine.objectContaining({ role: 'ROLE_ADMIN' }));
  });

  it('al editar permite restablecer la contraseña del usuario', async () => {
    service.get.and.returnValue(of(saved));
    service.resetPassword.and.returnValue(of(undefined));
    const { harness, root } = await open('/panel/users/5/edit');

    const open_ = [...root.querySelectorAll<HTMLButtonElement>('.note button')].find((b) => b.textContent?.includes('Restablecer'));
    open_?.click();
    harness.detectChanges();
    await harness.fixture.whenStable();
    harness.detectChanges();

    const dialog = root.querySelector('app-reset-password-dialog dialog') as HTMLDialogElement;
    expect(dialog.open).toBeTrue();

    const input = dialog.querySelector('input') as HTMLInputElement;
    input.value = 'Reset-Clave-777';
    input.dispatchEvent(new Event('input'));
    (dialog.querySelector('form') as HTMLFormElement).dispatchEvent(new Event('submit'));
    harness.detectChanges();

    expect(service.resetPassword).toHaveBeenCalledWith(5, 'Reset-Clave-777');
    expect(root.querySelector('.alert-success')?.textContent).toContain('Se restableció la contraseña de Ana Pérez');
  });

  it('muestra el error si el usuario no existe', async () => {
    service.get.and.returnValue(throwError(() => new HttpErrorResponse({ status: 404, error: { message: 'No existe el usuario con id 5.' } })));

    const { root } = await open('/panel/users/5/edit');

    expect(root.querySelector('[role="alert"]')?.textContent).toContain('No existe el usuario con id 5.');
    expect(root.querySelector('form')).toBeNull();
  });
});
