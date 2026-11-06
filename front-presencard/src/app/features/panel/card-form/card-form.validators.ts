import { AbstractControl, ValidationErrors } from '@angular/forms';

const MIN_WHATSAPP_DIGITS = 8;
const MAX_WHATSAPP_DIGITS = 15;

/** Usuario ("@marca", "marca") o URL del perfil de Instagram. */
export const INSTAGRAM_PATTERN =
  /^(@?[A-Za-z0-9._]{1,30}|https?:\/\/(www\.)?instagram\.com\/[A-Za-z0-9._]+\/?.*)$/i;

/** WhatsApp opcional: al quitar símbolos debe quedar entre 8 y 15 dígitos (con código de país). */
export function whatsappValidator(control: AbstractControl<string>): ValidationErrors | null {
  const value = (control.value ?? '').trim();

  if (value === '') {
    return null;
  }

  const digits = value.replace(/\D+/g, '').length;

  return digits >= MIN_WHATSAPP_DIGITS && digits <= MAX_WHATSAPP_DIGITS ? null : { whatsapp: true };
}

/** Usuario ("@marca", "marca") o enlace t.me/telegram.me de Telegram (usuarios de 5 a 32 caracteres). */
export const TELEGRAM_PATTERN = /^(@?[A-Za-z0-9_]{5,32}|(https?:\/\/)?(t|telegram)\.me\/[A-Za-z0-9_]{5,32}\/?.*)$/i;
