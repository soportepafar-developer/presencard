import { readAppRoot } from './app-root';

describe('readAppRoot', () => {
  const docWith = (content?: string): Document => {
    const doc = document.implementation.createHTMLDocument('t');

    if (content !== undefined) {
      const meta = doc.createElement('meta');
      meta.name = 'presencard-root';
      meta.content = content;
      doc.head.appendChild(meta);
    }

    return doc;
  };

  it('es vacío cuando no hay meta (desarrollo, raíz del dominio)', () => {
    expect(readAppRoot(docWith())).toBe('');
    expect(readAppRoot(docWith(''))).toBe('');
  });

  it('devuelve el prefijo de la subcarpeta sin barra final', () => {
    expect(readAppRoot(docWith('/presencard'))).toBe('/presencard');
    expect(readAppRoot(docWith(' /presencard/ '))).toBe('/presencard');
  });

  it('en las pruebas de Karma, sin la meta en la página, la raíz es vacía', () => {
    expect(readAppRoot()).toBe('');
  });
});
