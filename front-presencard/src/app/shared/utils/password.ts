const ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789'; // sin caracteres ambiguos (0/O, 1/l/I)

/** Entero uniforme en [0, max) con aleatoriedad criptográfica (descarta valores que sesgarían el módulo). */
function secureRandomInt(max: number): number {
  const limit = Math.floor(0x100000000 / max) * max;
  const buffer = new Uint32Array(1);

  do {
    crypto.getRandomValues(buffer);
  } while (buffer[0] >= limit);

  return buffer[0] % max;
}

/** Contraseña temporal legible con mayúsculas, minúsculas y números. */
export function generatePassword(length = 12, randomInt: (max: number) => number = secureRandomInt): string {
  let password: string;

  do {
    password = Array.from({ length }, () => ALPHABET[randomInt(ALPHABET.length)]).join('');
  } while (!/[a-z]/.test(password) || !/[A-Z]/.test(password) || !/\d/.test(password));

  return password;
}
