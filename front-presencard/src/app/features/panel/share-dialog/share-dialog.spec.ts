import { TestBed } from '@angular/core/testing';
import { Presencard } from '../../../shared/models/presencard';
import { ShareDialog } from './share-dialog';

const card: Presencard = {
  id: 1,
  hashId: 'aB3dE5gH7jK9',
  ownerId: 1,
  ownerName: null,
  fullName: 'Ana Pérez',
  jobTitle: 'CTO',
  company: 'Pafar Ingeniería',
  email: 'ana@pafar.com.ve',
  phone: '0412-1112233',
  whatsapp: null,
  instagram: null,
  telegram: null,
  colorPrimary: null,
  colorAccent: null,
  universoSolucionesUrl: 'https://enlacesmultiples.pafar.com.ve/universodesoluciones/',
  createdAt: '2026-10-21T10:00:00+00:00',
  updatedAt: '2026-10-21T10:00:00+00:00',
  publicUrl: 'http://localhost:8080/c/aB3dE5gH7jK9',
};

describe('ShareDialog', () => {
  async function open(isOpen = true, data: Presencard = card) {
    const fixture = TestBed.createComponent(ShareDialog);
    fixture.componentRef.setInput('card', data);
    fixture.componentRef.setInput('open', isOpen);
    fixture.detectChanges();

    const root = fixture.nativeElement as HTMLElement;
    for (let i = 0; i < 100 && isOpen && !root.querySelector('app-qr-code svg'); i++) {
      await new Promise((resolve) => setTimeout(resolve, 10));
      fixture.detectChanges();
    }

    return { fixture, root };
  }

  const downloadButton = (root: HTMLElement) =>
    [...root.querySelectorAll<HTMLButtonElement>('.qr button')].find((b) => b.textContent?.includes('Descargar')) as HTMLButtonElement;

  it('muestra la URL única y el código QR de esa misma URL', async () => {
    const { root } = await open();

    expect((root.querySelector('#share-url') as HTMLInputElement).value).toBe(card.publicUrl);
    expect(root.querySelector('.qr h3')?.textContent).toContain('Código QR');
    expect(root.querySelector('app-qr-code svg')?.getAttribute('aria-label')).toBe('Código QR de la tarjeta de Ana Pérez');
    expect(root.querySelector('.qr .hint')?.textContent).toContain('cámara del móvil');
  });

  it('muestra cargo y empresa en una sola línea', async () => {
    const { root } = await open();

    expect(root.querySelector('.person span')?.textContent).toBe('CTO · Pafar Ingeniería');
  });

  it('funciona con una tarjeta sin cargo: muestra el nombre y no deja una línea vacía', async () => {
    const { root } = await open(true, { ...card, jobTitle: null, company: null, email: null, phone: null });

    expect(root.querySelector('.person strong')?.textContent).toContain('Ana Pérez');
    expect(root.querySelector('.person span')).toBeNull();
    expect(root.querySelector('app-qr-code svg')).not.toBeNull();
  });

  it('no genera el QR mientras el modal está cerrado', async () => {
    const { root } = await open(false);

    expect(root.querySelector('.qr')).toBeNull();
  });

  it('descarga el QR como PNG con el nombre de la persona', async () => {
    const { fixture, root } = await open();
    const blobs: Blob[] = [];
    const files: string[] = [];
    spyOn(URL, 'createObjectURL').and.callFake((blob: Blob | MediaSource) => {
      blobs.push(blob as Blob);
      return 'blob:qr';
    });
    spyOn(URL, 'revokeObjectURL');
    spyOn(HTMLAnchorElement.prototype, 'click').and.callFake(function (this: HTMLAnchorElement) {
      files.push(this.download);
    });

    downloadButton(root).click();
    await fixture.whenStable();
    for (let i = 0; i < 100 && files.length === 0; i++) {
      await new Promise((resolve) => setTimeout(resolve, 10));
    }

    expect(files).toEqual(['qr-ana-perez.png']);
    expect(blobs[0].type).toBe('image/png');
    expect(blobs[0].size).toBeGreaterThan(200);
  });

  it('avisa si no se pudo generar la imagen', async () => {
    const { fixture, root } = await open();
    spyOn(HTMLCanvasElement.prototype, 'toBlob').and.callFake((callback: BlobCallback) => callback(null));

    downloadButton(root).click();
    for (let i = 0; i < 100 && !root.querySelector('.qr .alert-error'); i++) {
      await new Promise((resolve) => setTimeout(resolve, 10));
      fixture.detectChanges();
    }

    expect(root.querySelector('.qr .alert-error')?.textContent).toContain('No se pudo generar el QR');
    expect(downloadButton(root).disabled).toBeFalse();
  });
});
