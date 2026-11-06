import { generatePassword } from './password';

describe('generatePassword', () => {
  it('genera contraseñas de la longitud pedida con mayúsculas, minúsculas y números', () => {
    for (let i = 0; i < 100; i++) {
      const password = generatePassword();

      expect(password.length).toBe(12);
      expect(password).toMatch(/[a-z]/);
      expect(password).toMatch(/[A-Z]/);
      expect(password).toMatch(/\d/);
    }
  });

  it('evita caracteres ambiguos (0, O, 1, l, I)', () => {
    for (let i = 0; i < 100; i++) {
      expect(generatePassword(20)).not.toMatch(/[0OlI1]/);
    }
  });

  it('cumple la política del servidor (8 a 72 caracteres)', () => {
    expect(generatePassword(8).length).toBe(8);
    expect(generatePassword(72).length).toBe(72);
  });

  it('reintenta hasta cumplir los requisitos con un generador pobre', () => {
    let calls = 0;
    // Las primeras llamadas solo producen minúsculas; luego aparecen mayúsculas y números.
    const biased = (max: number): number => (calls++ < 12 ? 24 : calls % max);

    const password = generatePassword(12, biased);

    expect(password).toMatch(/[A-Z]/);
    expect(password).toMatch(/\d/);
  });

  it('no repite contraseñas', () => {
    const values = new Set(Array.from({ length: 200 }, () => generatePassword()));

    expect(values.size).toBe(200);
  });
});
