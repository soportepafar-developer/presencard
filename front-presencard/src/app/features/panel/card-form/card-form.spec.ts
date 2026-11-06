import { HttpErrorResponse } from '@angular/common/http';
import { TestBed } from '@angular/core/testing';
import { Router, provideRouter } from '@angular/router';
import { RouterTestingHarness } from '@angular/router/testing';
import { of, throwError } from 'rxjs';
import { PresencardService } from '../../../core/presencard.service';
import { Presencard } from '../../../shared/models/presencard';
import { CardForm } from './card-form';

const saved: Presencard = {
  id: 5,
  hashId: 'aB3dE5gH7jK9',
  ownerId: 1,
  ownerName: null,
  fullName: 'Ana Pérez',
  jobTitle: 'CTO',
  company: 'Consultores JPL',
  email: 'ana@pafar.com.ve',
  phone: '0412-1112233',
  whatsapp: '584121112233',
  instagram: 'ana.perez',
  telegram: null,
  colorPrimary: null,
  colorAccent: null,
  universoSolucionesUrl: 'https://enlacesmultiples.pafar.com.ve/universodesoluciones/',
  createdAt: '2026-10-21T10:00:00+00:00',
  updatedAt: '2026-10-21T10:00:00+00:00',
  publicUrl: 'http://localhost:8080/c/aB3dE5gH7jK9',
};

describe('CardForm', () => {
  let service: jasmine.SpyObj<PresencardService>;

  async function open(url: string): Promise<{ harness: RouterTestingHarness; root: HTMLElement }> {
    TestBed.configureTestingModule({
      providers: [
        provideRouter([
          { path: 'panel/cards/new', component: CardForm },
          { path: 'panel/cards/:id/edit', component: CardForm },
          { path: 'panel/cards', children: [] },
        ]),
        { provide: PresencardService, useValue: service },
      ],
    });
    const harness = await RouterTestingHarness.create();
    await harness.navigateByUrl(url, CardForm);
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

  const submit = (harness: RouterTestingHarness, root: HTMLElement) => {
    (root.querySelector('form') as HTMLFormElement).dispatchEvent(new Event('submit'));
    harness.detectChanges();
  };

  beforeEach(() => {
    service = jasmine.createSpyObj<PresencardService>('PresencardService', ['get', 'create', 'update']);
  });

  it('no envía un formulario sin nombre: es lo único obligatorio', async () => {
    const { harness, root } = await open('/panel/cards/new');

    submit(harness, root);

    expect(service.create).not.toHaveBeenCalled();
    expect(root.querySelectorAll('.field-error').length).toBe(1);
    expect(root.querySelector('.field-error')?.textContent).toContain('nombre');
  });

  it('marca cargo, correo y teléfono como opcionales', async () => {
    const { root } = await open('/panel/cards/new');

    for (const id of ['jobTitle', 'company', 'email', 'phone']) {
      expect(root.querySelector(`label[for="${id}"] .optional`)?.textContent).toContain('opcional');
    }

    expect(root.querySelector('label[for="fullName"] .optional')).toBeNull();
  });

  it('permite crear una tarjeta con solo el nombre y envía los datos vacíos como null', async () => {
    service.create.and.returnValue(of({ ...saved, jobTitle: null, company: null, email: null, phone: null }));
    const { harness, root } = await open('/panel/cards/new');

    fill(root, { fullName: 'Persona Sin Datos', jobTitle: '  ', company: ' ', email: '', phone: ' ' });
    submit(harness, root);

    expect(service.create).toHaveBeenCalledWith({
      fullName: 'Persona Sin Datos',
      jobTitle: null,
      company: null,
      email: null,
      phone: null,
      whatsapp: null,
      instagram: null,
      telegram: null,
      colorPrimary: null,
      colorAccent: null,
    });
    expect((root.querySelector('app-share-dialog dialog') as HTMLDialogElement).open).toBeTrue();
  });

  it('la empresa es opcional pero limitada a 150 caracteres', async () => {
    const { harness, root } = await open('/panel/cards/new');

    fill(root, { fullName: 'Ana', company: 'x'.repeat(151) });
    submit(harness, root);

    expect(service.create).not.toHaveBeenCalled();
    expect(root.textContent).toContain('La empresa no puede superar 150 caracteres.');
  });

  it('si se escribe un correo, debe ser válido', async () => {
    const { harness, root } = await open('/panel/cards/new');

    fill(root, { fullName: 'Ana', email: 'no-es-correo' });
    submit(harness, root);

    expect(service.create).not.toHaveBeenCalled();
    expect(root.textContent).toContain('Ingresa un correo válido');
  });

  it('ya no muestra el mensaje sobre el enlace a Universo de Soluciones', async () => {
    const { root } = await open('/panel/cards/new');

    expect(root.textContent).not.toContain('Universo de Soluciones');
    expect(root.textContent).not.toContain('enlace permanente');
  });

  it('valida el WhatsApp (8 a 15 dígitos)', async () => {
    const { harness, root } = await open('/panel/cards/new');

    fill(root, { whatsapp: '123' });
    (root.querySelector('#whatsapp') as HTMLInputElement).dispatchEvent(new Event('blur'));
    harness.detectChanges();

    expect(root.textContent).toContain('Debe tener entre 8 y 15 dígitos');
  });

  it('crea la tarjeta con datos normalizados y muestra el modal con la URL única', async () => {
    service.create.and.returnValue(of(saved));
    const { harness, root } = await open('/panel/cards/new');

    fill(root, {
      fullName: '  Ana Pérez ',
      jobTitle: 'CTO',
      company: ' Pafar Ingeniería ',
      email: 'ana@pafar.com.ve',
      phone: '0412-1112233',
      whatsapp: '   ',
      instagram: '@ana.perez',
    });
    submit(harness, root);

    expect(service.create).toHaveBeenCalledWith({
      fullName: 'Ana Pérez',
      jobTitle: 'CTO',
      company: 'Pafar Ingeniería',
      email: 'ana@pafar.com.ve',
      phone: '0412-1112233',
      whatsapp: null,
      instagram: '@ana.perez',
      telegram: null,
      colorPrimary: null,
      colorAccent: null,
    });

    const dialog = root.querySelector('app-share-dialog dialog') as HTMLDialogElement;
    expect(dialog.open).toBeTrue();
    expect(dialog.textContent).toContain('¡Tarjeta creada!');
    expect((dialog.querySelector('input') as HTMLInputElement).value).toBe(saved.publicUrl);
  });

  it('al cerrar el modal de creación vuelve al listado', async () => {
    service.create.and.returnValue(of(saved));
    const { harness, root } = await open('/panel/cards/new');
    const navigate = spyOn(TestBed.inject(Router), 'navigate').and.resolveTo(true);

    fill(root, { fullName: 'Ana Pérez', jobTitle: 'CTO', email: 'ana@pafar.com.ve', phone: '1' });
    submit(harness, root);
    (root.querySelector('app-share-dialog .close') as HTMLButtonElement).click();

    expect(navigate).toHaveBeenCalledWith(['/panel/cards'], { state: { flash: 'Se creó la tarjeta de Ana Pérez.' } });
  });

  it('muestra el mensaje de la API cuando el servidor rechaza los datos', async () => {
    service.create.and.returnValue(throwError(() => new HttpErrorResponse({ status: 422, error: { message: 'El correo electrónico no es válido.' } })));
    const { harness, root } = await open('/panel/cards/new');

    fill(root, { fullName: 'Ana', jobTitle: 'CTO', email: 'a@b.co', phone: '1' });
    submit(harness, root);

    expect(root.querySelector('.alert-error')?.textContent).toContain('El correo electrónico no es válido.');
    expect(root.querySelector('app-share-dialog dialog')?.hasAttribute('open')).toBeFalse();
  });

  it('en edición carga la tarjeta, actualiza y vuelve al listado', async () => {
    service.get.and.returnValue(of(saved));
    service.update.and.returnValue(of({ ...saved, jobTitle: 'CEO' }));
    const { harness, root } = await open('/panel/cards/5/edit');
    const navigate = spyOn(TestBed.inject(Router), 'navigate').and.resolveTo(true);

    expect(service.get).toHaveBeenCalledWith(5);
    expect((root.querySelector('#fullName') as HTMLInputElement).value).toBe('Ana Pérez');
    expect((root.querySelector('#company') as HTMLInputElement).value).toBe('Consultores JPL');
    expect(root.querySelector('h1')?.textContent).toContain('Editar tarjeta');
    expect(root.textContent).toContain(saved.publicUrl);

    fill(root, { jobTitle: 'CEO' });
    submit(harness, root);

    expect(service.update).toHaveBeenCalledWith(5, jasmine.objectContaining({ jobTitle: 'CEO', whatsapp: '584121112233' }));
    expect(navigate).toHaveBeenCalledWith(['/panel/cards'], { state: { flash: 'Se guardaron los cambios de Ana Pérez.' } });
  });

  it('en edición de una tarjeta sin datos opcionales deja los campos vacíos y los guarda como null', async () => {
    const bare = { ...saved, jobTitle: null, company: null, email: null, phone: null };
    service.get.and.returnValue(of(bare));
    service.update.and.returnValue(of(bare));
    const { harness, root } = await open('/panel/cards/5/edit');
    spyOn(TestBed.inject(Router), 'navigate').and.resolveTo(true);

    expect((root.querySelector('#jobTitle') as HTMLInputElement).value).toBe('');
    expect((root.querySelector('#email') as HTMLInputElement).value).toBe('');

    submit(harness, root);

    expect(service.update).toHaveBeenCalledWith(5, jasmine.objectContaining({ jobTitle: null, company: null, email: null, phone: null }));
  });

  it('en edición muestra el error si la tarjeta no existe', async () => {
    service.get.and.returnValue(throwError(() => new HttpErrorResponse({ status: 404, error: { message: 'No existe la tarjeta con id 5.' } })));

    const { root } = await open('/panel/cards/5/edit');

    expect(root.querySelector('[role="alert"]')?.textContent).toContain('No existe la tarjeta con id 5.');
    expect(root.querySelector('form')).toBeNull();
  });

  describe('Telegram y paleta de colores', () => {
    it('guarda Telegram y lo marca como opcional', async () => {
      service.create.and.returnValue(of(saved));
      const { harness, root } = await open('/panel/cards/new');

      fill(root, { fullName: 'Ana', telegram: 'https://t.me/ana_perez' });
      submit(harness, root);

      expect(service.create).toHaveBeenCalledWith(jasmine.objectContaining({ telegram: 'https://t.me/ana_perez' }));
    });

    it('rechaza un usuario de Telegram inválido', async () => {
      const { harness, root } = await open('/panel/cards/new');

      fill(root, { fullName: 'Ana', telegram: 'ab' });
      submit(harness, root);

      expect(service.create).not.toHaveBeenCalled();
      expect(root.textContent).toContain('t.me/usuario');
    });

    it('con la paleta por defecto no guarda colores', async () => {
      service.create.and.returnValue(of(saved));
      const { harness, root } = await open('/panel/cards/new');

      fill(root, { fullName: 'Ana' });
      submit(harness, root);

      expect(service.create).toHaveBeenCalledWith(jasmine.objectContaining({ colorPrimary: null, colorAccent: null }));
    });

    it('permite elegir una paleta sugerida con un clic', async () => {
      service.create.and.returnValue(of(saved));
      const { harness, root } = await open('/panel/cards/new');
      const presets = root.querySelectorAll<HTMLButtonElement>('.preset');

      presets[1].click();
      harness.detectChanges();
      fill(root, { fullName: 'Ana' });
      submit(harness, root);

      expect(presets[1].getAttribute('aria-pressed')).toBe('true');
      expect(presets[0].getAttribute('aria-pressed')).toBe('false');
      expect(service.create).toHaveBeenCalledWith(jasmine.objectContaining({ colorPrimary: '#0B6E99', colorAccent: '#F2A900' }));
    });

    it('acepta colores escritos a mano y los envía en mayúsculas', async () => {
      service.create.and.returnValue(of(saved));
      const { harness, root } = await open('/panel/cards/new');

      fill(root, { fullName: 'Ana', colorPrimary: '#112233', colorAccent: '#abcdef' });
      submit(harness, root);

      expect(service.create).toHaveBeenCalledWith(jasmine.objectContaining({ colorPrimary: '#112233', colorAccent: '#ABCDEF' }));
    });

    it('no envía colores con formato inválido y lo explica', async () => {
      const { harness, root } = await open('/panel/cards/new');

      fill(root, { fullName: 'Ana', colorPrimary: 'azul' });
      submit(harness, root);

      expect(service.create).not.toHaveBeenCalled();
      expect(root.textContent).toContain('#RRGGBB');
    });

    it('el selector de color actualiza el campo de texto', async () => {
      const { harness, root } = await open('/panel/cards/new');
      const picker = root.querySelector('input[type="color"]') as HTMLInputElement;

      picker.value = '#445566';
      picker.dispatchEvent(new Event('input'));
      harness.detectChanges();

      expect((root.querySelector('#colorPrimary') as HTMLInputElement).value).toBe('#445566');
    });

    it('la vista previa usa los colores elegidos', async () => {
      const { harness, root } = await open('/panel/cards/new');

      fill(root, { colorPrimary: '#112233' });
      harness.detectChanges();

      expect((root.querySelector('.preview') as HTMLElement).style.getPropertyValue('--blue').toUpperCase()).toBe('#112233');
    });

    it('al editar carga Telegram y los colores guardados', async () => {
      service.get.and.returnValue(of({ ...saved, telegram: 'ana_perez', colorPrimary: '#7A1F3D', colorAccent: '#E07A5F' }));
      const { root } = await open('/panel/cards/5/edit');

      expect((root.querySelector('#telegram') as HTMLInputElement).value).toBe('ana_perez');
      expect((root.querySelector('#colorPrimary') as HTMLInputElement).value).toBe('#7A1F3D');
      expect((root.querySelector('#colorAccent') as HTMLInputElement).value).toBe('#E07A5F');
    });

    it('al editar una tarjeta sin colores muestra la paleta por defecto', async () => {
      service.get.and.returnValue(of(saved));
      const { root } = await open('/panel/cards/5/edit');

      expect((root.querySelector('#colorPrimary') as HTMLInputElement).value).toBe('#3240A5');
      expect(root.querySelector('.preset[aria-pressed="true"]')?.textContent).toContain('Pafar');
    });
  });
});
