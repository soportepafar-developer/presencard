import { AbstractControl, ValidationErrors, ValidatorFn } from '@angular/forms';

export const PASSWORD_MIN_LENGTH = 8;
/** bcrypt ignora lo que exceda 72 bytes: el servidor lo rechaza en vez de truncarlo. */
export const PASSWORD_MAX_BYTES = 72;

/** Misma política que el servidor (PasswordPolicy): 8 caracteres como mínimo y 72 bytes como máximo. */
export function passwordPolicyValidator(control: AbstractControl<string>): ValidationErrors | null {
  const value = control.value ?? '';

  if (value === '') {
    return null; // la obligatoriedad la decide Validators.required
  }

  const bytes = new TextEncoder().encode(value).length;

  return [...value].length >= PASSWORD_MIN_LENGTH && bytes <= PASSWORD_MAX_BYTES ? null : { passwordPolicy: true };
}

/** Valida a nivel de formulario que dos campos tengan el mismo valor (nueva contraseña y confirmación). */
export function sameValueValidator(field: string, confirmField: string): ValidatorFn {
  return (group: AbstractControl): ValidationErrors | null => {
    const value = group.get(field)?.value;
    const confirmation = group.get(confirmField)?.value;

    return value === confirmation ? null : { mismatch: true };
  };
}
