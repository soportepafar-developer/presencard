import { downloadBlob, slugify } from './download';

describe('slugify', () => {
  it('quita tildes, símbolos y espacios sobrantes', () => {
    expect(slugify('María Fernanda Rojas')).toBe('maria-fernanda-rojas');
    expect(slugify('  Jaime  "JP" <Padrón> & Co ')).toBe('jaime-jp-padron-co');
    expect(slugify('Ñandú / Año')).toBe('nandu-ano');
  });

  it('devuelve vacío si no queda nada utilizable', () => {
    expect(slugify('***')).toBe('');
  });
});

describe('downloadBlob', () => {
  it('descarga el archivo con el nombre pedido y libera la URL temporal', () => {
    jasmine.clock().install();
    const names: string[] = [];
    const create = spyOn(URL, 'createObjectURL').and.returnValue('blob:prueba');
    const revoke = spyOn(URL, 'revokeObjectURL');
    spyOn(HTMLAnchorElement.prototype, 'click').and.callFake(function (this: HTMLAnchorElement) {
      names.push(`${this.download}|${this.href}`);
    });

    downloadBlob(new Blob(['x'], { type: 'image/png' }), 'qr-ana.png');

    expect(create).toHaveBeenCalled();
    expect(names).toEqual(['qr-ana.png|blob:prueba']);
    expect(document.querySelector('a[download]')).toBeNull();
    expect(revoke).not.toHaveBeenCalled();

    jasmine.clock().tick(1000);
    expect(revoke).toHaveBeenCalledWith('blob:prueba');
    jasmine.clock().uninstall();
  });
});
