import { FormControl } from '@angular/forms';
import { INSTAGRAM_PATTERN, whatsappValidator } from './card-form.validators';

describe('whatsappValidator', () => {
  const check = (value: string) => whatsappValidator(new FormControl(value, { nonNullable: true }));

  it('acepta vacío (campo opcional)', () => {
    expect(check('')).toBeNull();
    expect(check('   ')).toBeNull();
  });

  it('acepta números de 8 a 15 dígitos con símbolos', () => {
    expect(check('584123258691')).toBeNull();
    expect(check('+58 (412) 325-8691')).toBeNull();
  });

  it('rechaza menos de 8 o más de 15 dígitos', () => {
    expect(check('1234567')).toEqual({ whatsapp: true });
    expect(check('1234567890123456')).toEqual({ whatsapp: true });
  });
});

describe('INSTAGRAM_PATTERN', () => {
  it('acepta usuario, @usuario y URL del perfil', () => {
    for (const value of ['pafar_ve', '@pafar.ve', 'https://www.instagram.com/pafar_ve/', 'http://instagram.com/x?hl=es']) {
      expect(INSTAGRAM_PATTERN.test(value)).withContext(value).toBeTrue();
    }
  });

  it('rechaza textos con espacios, símbolos o dominios ajenos', () => {
    for (const value of ['no valido', 'a!b', 'https://example.com/pafar', 'a'.repeat(31)]) {
      expect(INSTAGRAM_PATTERN.test(value)).withContext(value).toBeFalse();
    }
  });
});
