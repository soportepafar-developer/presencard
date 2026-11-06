import { FormControl, FormGroup } from '@angular/forms';
import { passwordPolicyValidator, sameValueValidator } from './validators';

describe('passwordPolicyValidator', () => {
  const check = (value: string) => passwordPolicyValidator(new FormControl(value, { nonNullable: true }));

  it('acepta de 8 caracteres a 72 bytes', () => {
    expect(check('12345678')).toBeNull();
    expect(check('Contraseña segura 2026')).toBeNull();
    expect(check('a'.repeat(72))).toBeNull();
  });

  it('rechaza menos de 8 caracteres o más de 72 bytes', () => {
    expect(check('1234567')).toEqual({ passwordPolicy: true });
    expect(check('a'.repeat(73))).toEqual({ passwordPolicy: true });
    expect(check('ñ'.repeat(37))).toEqual({ passwordPolicy: true }); // 74 bytes en UTF-8
  });

  it('deja el vacío a Validators.required', () => {
    expect(check('')).toBeNull();
  });
});

describe('sameValueValidator', () => {
  const group = (a: string, b: string) =>
    new FormGroup({ pass: new FormControl(a), confirm: new FormControl(b) }, { validators: [sameValueValidator('pass', 'confirm')] });

  it('es válido cuando ambos campos coinciden', () => {
    expect(group('secreto-123', 'secreto-123').errors).toBeNull();
  });

  it('marca "mismatch" cuando difieren', () => {
    expect(group('secreto-123', 'secreto-124').errors).toEqual({ mismatch: true });
  });
});
