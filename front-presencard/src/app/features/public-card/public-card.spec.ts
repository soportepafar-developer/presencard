import { HttpErrorResponse } from '@angular/common/http';
import { TestBed } from '@angular/core/testing';
import { provideRouter } from '@angular/router';
import { RouterTestingHarness } from '@angular/router/testing';
import { of, throwError } from 'rxjs';
import { PresencardService } from '../../core/presencard.service';
import { PublicCardData } from '../../shared/models/presencard';
import { PublicCard } from './public-card';

const UNIVERSO = 'https://enlacesmultiples.pafar.com.ve/universodesoluciones/';

const data: PublicCardData = {
  hashId: 'aB3dE5gH7jK9',
  fullName: 'Jaime Leonardo Padrón Faria',
  jobTitle: 'Socio/a, Fundador o CEO',
  company: 'Consultores JPL / Pafar Ingeniería / Somos 3D3',
  email: 'jaileop@yahoo.com',
  phone: '+58 412-3258691',
  whatsapp: '584123258691',
  instagram: 'pafar_ve',
  telegram: null,
  colorPrimary: null,
  colorAccent: null,
  universoSolucionesUrl: UNIVERSO,
  publicUrl: 'http://localhost:8080/c/aB3dE5gH7jK9',
};

describe('PublicCard', () => {
  let service: jasmine.SpyObj<PresencardService>;
  let embedded: HTMLScriptElement | null = null;

  async function open(hashId = data.hashId): Promise<HTMLElement> {
    TestBed.configureTestingModule({
      providers: [provideRouter([{ path: 'c/:hashId', component: PublicCard }]), { provide: PresencardService, useValue: service }],
    });
    const harness = await RouterTestingHarness.create();
    await harness.navigateByUrl(`/c/${hashId}`, PublicCard);
    await harness.fixture.whenStable();
    harness.detectChanges();

    return harness.routeNativeElement as HTMLElement;
  }

  function embed(state: object): void {
    embedded = document.createElement('script');
    embedded.type = 'application/json';
    embedded.id = 'presencard-state';
    embedded.textContent = JSON.stringify(state);
    document.body.appendChild(embedded);
  }

  const href = (root: HTMLElement, selector: string) => root.querySelector(selector)?.getAttribute('href');

  beforeEach(() => {
    service = jasmine.createSpyObj<PresencardService>('PresencardService', ['publicCard']);
  });

  afterEach(() => {
    embedded?.remove();
    embedded = null;
  });

  it('usa el estado embebido por el servidor sin pedir la tarjeta otra vez', async () => {
    embed({ card: data });

    const root = await open();

    expect(service.publicCard).not.toHaveBeenCalled();
    expect(root.querySelector('h1')?.textContent).toContain('Jaime Leonardo Padrón Faria');
    expect(document.title).toBe('Jaime Leonardo Padrón Faria · Socio/a, Fundador o CEO · Consultores JPL / Pafar Ingeniería / Somos 3D3');
  });

  it('sin estado embebido la pide a la API', async () => {
    service.publicCard.and.returnValue(of(data));

    const root = await open();

    expect(service.publicCard).toHaveBeenCalledWith(data.hashId);
    expect(root.querySelector('.role')?.textContent).toContain('Socio/a, Fundador o CEO');
  });

  it('ignora un estado embebido que pertenece a otra tarjeta', async () => {
    embed({ card: { ...data, hashId: 'zzzzzzzzzzzz' } });
    service.publicCard.and.returnValue(of(data));

    await open();

    expect(service.publicCard).toHaveBeenCalledWith(data.hashId);
  });

  it('muestra iniciales, contacto y enlaces de acción', async () => {
    embed({ card: data });

    const root = await open();

    expect(root.querySelector('.avatar')?.textContent?.trim()).toBe('JL');
    expect(root.textContent).toContain('jaileop@yahoo.com');
    expect(href(root, 'a.whatsapp')).toBe('https://wa.me/584123258691');
    expect(href(root, '.quick a[href^="tel:"]')).toBe('tel:+584123258691');
    expect(href(root, '.quick a[href^="mailto:"]')).toBe('mailto:jaileop@yahoo.com');
    expect(href(root, '.quick a[href*="instagram.com"]')).toBe('https://instagram.com/pafar_ve');
  });

  it('muestra la empresa bajo el cargo', async () => {
    embed({ card: data });

    const root = await open();

    expect(root.querySelector('.company')?.textContent).toContain('Consultores JPL / Pafar Ingeniería / Somos 3D3');
  });

  it('ofrece guardar el contacto en un archivo .vcf de esta tarjeta', async () => {
    embed({ card: data });

    const root = await open();
    const save = root.querySelector('a.save') as HTMLAnchorElement;

    expect(save.textContent).toContain('Guardar contacto');
    expect(save.getAttribute('href')).toBe('/api/public/cards/aB3dE5gH7jK9/vcard.vcf');
    expect(save.hasAttribute('download')).toBeTrue();
  });

  it('siempre incluye el enlace permanente a Universo de Soluciones', async () => {
    embed({ card: { ...data, whatsapp: null, instagram: null } });

    const root = await open();

    const link = root.querySelector('a.universe') as HTMLAnchorElement;
    expect(link.getAttribute('href')).toBe(UNIVERSO);
    expect(link.textContent).toContain('Universo de Soluciones');
    expect(link.target).toBe('_blank');
    expect(link.rel).toContain('noopener');
  });

  it('oculta WhatsApp e Instagram cuando no están registrados', async () => {
    embed({ card: { ...data, whatsapp: null, instagram: null } });

    const root = await open();

    expect(root.querySelector('a.whatsapp')).toBeNull();
    expect(root.querySelector('a[href*="instagram.com"]')).toBeNull();
    expect(root.querySelector('a[href^="tel:"]')).not.toBeNull();
  });

  it('muestra Telegram como acción rápida y en el detalle cuando está registrado', async () => {
    embed({ card: { ...data, telegram: 'pafar_ve' } });

    const root = await open();

    expect(href(root, '.quick a[href*="t.me"]')).toBe('https://t.me/pafar_ve');
    expect(root.querySelector('.details a[href="https://t.me/pafar_ve"]')?.textContent).toContain('@pafar_ve');
  });

  it('oculta Telegram cuando no está registrado', async () => {
    embed({ card: data });

    const root = await open();

    expect(root.querySelector('a[href*="t.me"]')).toBeNull();
  });

  it('sin paleta propia no redefine los colores (conserva la de Presencard)', async () => {
    embed({ card: data });

    await open();
    const host = document.querySelector('app-public-card') as HTMLElement;

    expect(host.style.getPropertyValue('--blue')).toBe('');
    expect(host.style.getPropertyValue('--green')).toBe('');
  });

  it('aplica los colores elegidos a la tarjeta', async () => {
    embed({ card: { ...data, colorPrimary: '#7A1F3D', colorAccent: '#E07A5F' } });

    await open();
    const host = document.querySelector('app-public-card') as HTMLElement;

    expect(host.style.getPropertyValue('--blue').toUpperCase()).toBe('#7A1F3D');
    expect(host.style.getPropertyValue('--green').toUpperCase()).toBe('#E07A5F');
    expect(host.style.getPropertyValue('--blue-50')).not.toBe('');
    expect(host.style.getPropertyValue('--on-blue')).not.toBe('');
  });

  it('pinta el avatar por encima de la cabecera (corrige el solapamiento visto en Safari/iOS)', async () => {
    embed({ card: data });

    const root = await open();
    const avatar = getComputedStyle(root.querySelector('.avatar') as HTMLElement);

    expect(avatar.position).toBe('relative');
    expect(Number(avatar.zIndex)).toBeGreaterThan(0);
  });

  it('muestra "no encontrada" cuando la API responde 404', async () => {
    service.publicCard.and.returnValue(throwError(() => new HttpErrorResponse({ status: 404 })));

    const root = await open('noexisteaaaa');

    expect(root.querySelector('h1')?.textContent).toContain('Tarjeta no encontrada');
  });

  it('muestra un error con reintento ante fallos de red', async () => {
    service.publicCard.and.returnValue(throwError(() => new HttpErrorResponse({ status: 0 })));
    const root = await open();

    expect(root.textContent).toContain('No pudimos cargar la tarjeta');

    service.publicCard.and.returnValue(of(data));
    (root.querySelector('button.btn-primary') as HTMLButtonElement).click();
    await new Promise((resolve) => setTimeout(resolve));

    expect(service.publicCard).toHaveBeenCalledTimes(2);
  });

  describe('con datos opcionales ausentes', () => {
    const bare: PublicCardData = { ...data, jobTitle: null, company: null, email: null, phone: null, whatsapp: null, instagram: null, telegram: null };

    it('muestra solo el nombre, sin cargo ni acciones ni detalles de contacto', async () => {
      embed({ card: bare });

      const root = await open();

      expect(root.querySelector('h1')?.textContent).toContain('Jaime Leonardo Padrón Faria');
      expect(root.querySelector('.role')).toBeNull();
      expect(root.querySelector('.quick')).toBeNull();
      expect(root.querySelector('.details')).toBeNull();
      expect(root.querySelector('a[href^="tel:"]')).toBeNull();
      expect(root.querySelector('a[href^="mailto:"]')).toBeNull();
    });

    it('el botón de guardar contacto aparece aunque no haya más datos que el nombre', async () => {
      embed({ card: bare });

      const root = await open();

      expect(root.querySelector('a.save')).not.toBeNull();
      expect(root.querySelector('.company')).toBeNull();
    });

    it('conserva siempre el enlace permanente a Universo de Soluciones', async () => {
      embed({ card: bare });

      const root = await open();

      expect(href(root, 'a.universe')).toBe(UNIVERSO);
    });

    it('el título de la pestaña lleva solo el nombre', async () => {
      embed({ card: bare });

      await open();

      expect(document.title).toBe('Jaime Leonardo Padrón Faria');
    });

    it('muestra únicamente los datos que sí existen', async () => {
      embed({ card: { ...bare, phone: '+58 412-3258691' } });

      const root = await open();

      expect(root.querySelectorAll('.quick a').length).toBe(1);
      expect(href(root, '.quick a')).toBe('tel:+584123258691');
      expect(root.querySelectorAll('.details li').length).toBe(1);
      expect(root.querySelector('a[href^="mailto:"]')).toBeNull();
    });
  });
});
