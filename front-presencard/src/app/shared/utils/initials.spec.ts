import { initialsOf } from './initials';

describe('initialsOf', () => {
  it('toma las iniciales de las dos primeras palabras', () => {
    expect(initialsOf('Jaime Leonardo Padrón Faria')).toBe('JL');
  });

  it('funciona con un solo nombre y con espacios extra', () => {
    expect(initialsOf('  ana ')).toBe('A');
    expect(initialsOf('maría   fernanda')).toBe('MF');
  });

  it('devuelve ? si no hay nombre', () => {
    expect(initialsOf('   ')).toBe('?');
  });
});
