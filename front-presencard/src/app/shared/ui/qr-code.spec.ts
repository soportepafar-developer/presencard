import { TestBed } from '@angular/core/testing';
import { QrCode } from './qr-code';

describe('QrCode', () => {
  const URL_ = 'http://localhost:8080/c/aB3dE5gH7jK9';

  async function create(value = URL_) {
    const fixture = TestBed.createComponent(QrCode);
    fixture.componentRef.setInput('value', value);
    fixture.componentRef.setInput('label', 'QR de Ana');
    fixture.detectChanges();

    const root = fixture.nativeElement as HTMLElement;
    for (let i = 0; i < 100 && !root.querySelector('svg'); i++) {
      await new Promise((resolve) => setTimeout(resolve, 10));
      fixture.detectChanges();
    }

    return { fixture, root, svg: root.querySelector('svg') as SVGSVGElement };
  }

  it('muestra primero un marcador y luego el QR', async () => {
    const fixture = TestBed.createComponent(QrCode);
    fixture.componentRef.setInput('value', URL_);
    fixture.detectChanges();

    expect((fixture.nativeElement as HTMLElement).querySelector('.placeholder')).not.toBeNull();
  });

  it('dibuja un SVG accesible con la zona de silencio incluida', async () => {
    const { svg } = await create();

    expect(svg.getAttribute('role')).toBe('img');
    expect(svg.getAttribute('aria-label')).toBe('QR de Ana');
    expect(svg.getAttribute('viewBox')).toBe('0 0 37 37'); // 29 módulos + 4 de silencio por lado
    expect(svg.getAttribute('shape-rendering')).toBe('crispEdges');
    expect(svg.querySelector('rect')?.getAttribute('fill')).toBe('#ffffff');
    expect(svg.querySelector('path')?.getAttribute('fill')).toBe('#000000');
  });

  it('usa un tamaño en pantalla múltiplo del número de módulos (píxeles enteros)', async () => {
    const { svg } = await create();

    expect(Number(svg.getAttribute('width')) % 37).toBe(0);
    expect(svg.getAttribute('width')).toBe(svg.getAttribute('height'));
    expect(Number(svg.getAttribute('width'))).toBeLessThanOrEqual(240);
  });

  it('el primer trazo es la fila superior del patrón de posición', async () => {
    const { svg } = await create();

    expect(svg.querySelector('path')?.getAttribute('d')?.startsWith('M4 4h7v1h-7z')).toBeTrue();
  });

  it('se regenera cuando cambia la URL', async () => {
    const { fixture, svg } = await create();
    const before = svg.querySelector('path')?.getAttribute('d');

    fixture.componentRef.setInput('value', URL_.replace('aB3d', 'zZ3d'));
    fixture.detectChanges();
    await new Promise((resolve) => setTimeout(resolve, 100));
    fixture.detectChanges();

    expect((fixture.nativeElement as HTMLElement).querySelector('path')?.getAttribute('d')).not.toBe(before);
  });
});
