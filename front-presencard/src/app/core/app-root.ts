/**
 * Prefijo de la app dentro del dominio: '' si vive en la raíz (desarrollo) o, por ejemplo, '/presencard'
 * si está en una subcarpeta (https://pafar.net/presencard). Symfony lo publica en una <meta> del index.html,
 * así una misma compilación sirve para cualquier ubicación.
 */
export function readAppRoot(doc: Document = document): string {
  const raw = doc.querySelector<HTMLMetaElement>('meta[name="presencard-root"]')?.content ?? '';

  return raw.trim().replace(/\/+$/, '');
}

export const APP_ROOT = readAppRoot();
