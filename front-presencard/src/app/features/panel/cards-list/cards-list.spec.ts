import { HttpErrorResponse } from '@angular/common/http';
import { signal } from '@angular/core';
import { TestBed } from '@angular/core/testing';
import { provideRouter } from '@angular/router';
import { RouterTestingHarness } from '@angular/router/testing';
import { of, throwError } from 'rxjs';
import { AuthService } from '../../../core/auth.service';
import { PresencardService } from '../../../core/presencard.service';
import { Presencard, PresencardPage } from '../../../shared/models/presencard';
import { CardsList } from './cards-list';

const card = (id: number, fullName: string): Presencard => ({
  id,
  hashId: `hash${String(id).padStart(8, '0')}`,
  ownerId: 1,
  ownerName: 'Usuario Uno',
  fullName,
  jobTitle: 'CTO',
  company: null,
  email: `${fullName.split(' ')[0].toLowerCase()}@pafar.com.ve`,
  phone: '0412-1112233',
  whatsapp: null,
  instagram: null,
  telegram: null,
  colorPrimary: null,
  colorAccent: null,
  universoSolucionesUrl: 'https://enlacesmultiples.pafar.com.ve/universodesoluciones/',
  createdAt: '2026-10-21T10:00:00+00:00',
  updatedAt: '2026-10-21T10:00:00+00:00',
  publicUrl: `http://localhost:8080/c/hash${String(id).padStart(8, '0')}`,
});

const page = (items: Presencard[], overrides: Partial<PresencardPage> = {}): PresencardPage => ({
  items,
  total: items.length,
  page: 1,
  perPage: 10,
  totalPages: 1,
  ...overrides,
});

describe('CardsList', () => {
  let service: jasmine.SpyObj<PresencardService>;

  async function open(admin = false): Promise<{ harness: RouterTestingHarness; root: HTMLElement }> {
    TestBed.configureTestingModule({
      providers: [
        provideRouter([{ path: 'panel/cards', component: CardsList }]),
        { provide: PresencardService, useValue: service },
        { provide: AuthService, useValue: { isAdmin: signal(admin), user: signal(null) } },
      ],
    });
    const harness = await RouterTestingHarness.create();
    await harness.navigateByUrl('/panel/cards', CardsList);
    await harness.fixture.whenStable();
    harness.detectChanges();

    return { harness, root: harness.routeNativeElement as HTMLElement };
  }

  beforeEach(() => {
    service = jasmine.createSpyObj<PresencardService>('PresencardService', ['list', 'delete']);
  });

  it('muestra las personas registradas y el total', async () => {
    service.list.and.returnValue(of(page([card(1, 'Ana Pérez'), card(2, 'Luis Gómez')])));

    const { root } = await open();

    expect(root.textContent).toContain('Ana Pérez');
    expect(root.textContent).toContain('Luis Gómez');
    expect(root.textContent).toContain('2 tarjetas');
    expect(root.querySelectorAll('li.row:not(.row-head)').length).toBe(2);
    expect(service.list).toHaveBeenCalledWith({ q: '', page: 1 });
  });

  it('muestra el estado vacío con acceso a crear la primera tarjeta', async () => {
    service.list.and.returnValue(of(page([])));

    const { root } = await open();

    expect(root.textContent).toContain('Aún no hay tarjetas');
    expect(root.querySelector('.empty a[href="/panel/cards/new"]')).not.toBeNull();
  });

  it('muestra el error de carga con opción de reintentar', async () => {
    service.list.and.returnValue(throwError(() => new HttpErrorResponse({ status: 500, error: { message: 'Fallo del servidor' } })));

    const { harness, root } = await open();

    expect(root.querySelector('[role="alert"]')?.textContent).toContain('Fallo del servidor');

    service.list.and.returnValue(of(page([card(1, 'Ana Pérez')])));
    (root.querySelector('.retry') as HTMLButtonElement).click();
    harness.detectChanges();
    await harness.fixture.whenStable();
    harness.detectChanges();

    expect(service.list).toHaveBeenCalledTimes(2);
    expect(root.textContent).toContain('Ana Pérez');
  });

  it('busca con retraso (debounce) y vuelve a la página 1', async () => {
    service.list.and.returnValue(of(page([card(1, 'Ana Pérez')])));
    const { harness, root } = await open();

    const input = root.querySelector('input[type="search"]') as HTMLInputElement;
    input.value = 'ana';
    input.dispatchEvent(new Event('input'));
    expect(service.list).toHaveBeenCalledTimes(1);

    await new Promise((resolve) => setTimeout(resolve, 400));
    harness.detectChanges();

    expect(service.list).toHaveBeenCalledWith({ q: 'ana', page: 1 });
  });

  it('pagina hacia la siguiente página', async () => {
    service.list.and.returnValue(of(page([card(1, 'Ana Pérez')], { total: 25, totalPages: 3 })));
    const { harness, root } = await open();

    const next = [...root.querySelectorAll<HTMLButtonElement>('.pager button')].find((b) => b.textContent?.includes('Siguiente'));
    expect(root.querySelector('.pager')?.textContent).toContain('Página 1 de 3');

    next?.click();
    harness.detectChanges();
    await harness.fixture.whenStable();

    expect(service.list).toHaveBeenCalledWith({ q: '', page: 2 });
  });

  it('elimina tras confirmar y recarga el listado', async () => {
    service.list.and.returnValue(of(page([card(1, 'Ana Pérez')])));
    service.delete.and.returnValue(of(undefined));
    const { harness, root } = await open();

    (root.querySelector('button.danger') as HTMLButtonElement).click();
    harness.detectChanges();

    const dialog = root.querySelector('app-confirm-dialog dialog') as HTMLDialogElement;
    expect(dialog.open).toBeTrue();
    expect(dialog.textContent).toContain('Ana Pérez');
    expect(service.delete).not.toHaveBeenCalled();

    (dialog.querySelector('.btn-danger') as HTMLButtonElement).click();
    await harness.fixture.whenStable();
    harness.detectChanges();

    expect(service.delete).toHaveBeenCalledWith(1);
    expect(service.list).toHaveBeenCalledTimes(2);
    expect(root.querySelector('.alert-success')?.textContent).toContain('Se eliminó la tarjeta de Ana Pérez');
  });

  it('cancelar no elimina nada', async () => {
    service.list.and.returnValue(of(page([card(1, 'Ana Pérez')])));
    const { harness, root } = await open();

    (root.querySelector('button.danger') as HTMLButtonElement).click();
    harness.detectChanges();
    const cancel = [...root.querySelectorAll<HTMLButtonElement>('app-confirm-dialog .btn-ghost')].find((b) => b.textContent?.includes('Cancelar'));
    cancel?.click();
    harness.detectChanges();

    expect(service.delete).not.toHaveBeenCalled();
    expect((root.querySelector('app-confirm-dialog dialog') as HTMLDialogElement).open).toBeFalse();
  });

  it('el enlace de cada tarjeta se abre en un modal con su URL pública', async () => {
    service.list.and.returnValue(of(page([card(1, 'Ana Pérez')])));
    const { harness, root } = await open();

    (root.querySelector('.actions button:not(.danger)') as HTMLButtonElement).click();
    harness.detectChanges();

    const dialog = root.querySelector('app-share-dialog dialog') as HTMLDialogElement;
    expect(dialog.open).toBeTrue();
    expect((dialog.querySelector('input') as HTMLInputElement).value).toBe('http://localhost:8080/c/hash00000001');
  });

  it('el administrador ve el título general y quién es el responsable de cada tarjeta', async () => {
    service.list.and.returnValue(of(page([card(1, 'Ana Pérez')])));

    const { root } = await open(true);

    expect(root.querySelector('h1')?.textContent).toContain('Personas registradas');
    expect(root.querySelector('.owner')?.textContent).toContain('Responsable: Usuario Uno');
  });

  it('el usuario normal ve "Mis tarjetas" y no ve responsables', async () => {
    service.list.and.returnValue(of(page([card(1, 'Ana Pérez')])));

    const { root } = await open(false);

    expect(root.querySelector('h1')?.textContent).toContain('Mis tarjetas');
    expect(root.querySelector('.owner')).toBeNull();
  });

  it('muestra "Sin cargo" y "Sin datos de contacto" cuando la tarjeta no tiene esos datos', async () => {
    service.list.and.returnValue(of(page([{ ...card(1, 'Ana Pérez'), jobTitle: null, company: null, email: null, phone: null }])));

    const { root } = await open();
    const row = root.querySelector('li.row:not(.row-head)') as HTMLElement;

    expect(row.textContent).toContain('Sin cargo');
    expect(row.textContent).toContain('Sin datos de contacto');
  });

  it('con solo un dato de contacto muestra ese y no el aviso de "sin datos"', async () => {
    service.list.and.returnValue(of(page([{ ...card(1, 'Ana Pérez'), email: null }])));

    const { root } = await open();
    const row = root.querySelector('li.row:not(.row-head)') as HTMLElement;

    expect(row.textContent).toContain('0412-1112233');
    expect(row.textContent).not.toContain('Sin datos de contacto');
  });

  it('muestra el cargo y la empresa juntos bajo el nombre', async () => {
    service.list.and.returnValue(
      of(page([{ ...card(1, 'Ana Pérez'), company: 'Pafar Ingeniería' }, { ...card(2, 'Luis Gómez'), jobTitle: null, company: 'Somos 3D3' }])),
    );

    const { root } = await open();
    const rows = [...root.querySelectorAll('li.row:not(.row-head)')].map((row) => row.textContent);

    expect(rows[0]).toContain('CTO · Pafar Ingeniería');
    expect(rows[1]).toContain('Somos 3D3');
    expect(rows[1]).not.toContain('Sin cargo');
  });
});
