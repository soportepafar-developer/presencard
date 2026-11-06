import { NavigationError } from '@angular/router';
import { APP_ROOT } from './app-root';

/**
 * Tras un despliegue, los archivos de las pantallas (chunks) cambian de nombre. Una pestaña abierta con la
 * versión anterior pide un archivo que ya no existe y Angular falla en silencio: el clic "no hace nada".
 * Recargar la página en la URL de destino carga el index.html nuevo y resuelve el problema.
 */
const CHUNK_ERROR = /dynamically imported module|importing a module script failed|failed to load module script|loading chunk .* failed/i;

/** Evita bucles de recarga si el archivo realmente no existe: máximo una recarga por URL cada 15 s. */
const RELOAD_GUARD_MS = 15_000;
const GUARD_KEY = 'presencard:chunk-reload';

export const isChunkLoadError = (error: unknown): boolean => {
  const message = error instanceof Error ? error.message : typeof error === 'string' ? error : '';

  return CHUNK_ERROR.test(message);
};

export interface ChunkReloadDeps {
  /** Navega de verdad (recarga completa) a la URL indicada. */
  assign: (url: string) => void;
  /** Prefijo de la instalación ('' en la raíz): error.url es la ruta del router, sin él. */
  root: string;
  now: () => number;
  storage: Pick<Storage, 'getItem' | 'setItem'> | null;
}

const defaultDeps = (): ChunkReloadDeps => ({
  assign: (url) => window.location.assign(url),
  root: APP_ROOT,
  now: () => Date.now(),
  storage: safeSessionStorage(),
});

function safeSessionStorage(): Storage | null {
  try {
    return window.sessionStorage;
  } catch {
    return null; // modo privado o almacenamiento bloqueado
  }
}

/** Devuelve true si se recargó la página. */
export function recoverFromChunkError(error: NavigationError, deps: ChunkReloadDeps = defaultDeps()): boolean {
  if (!isChunkLoadError(error.error)) {
    return false;
  }

  const now = deps.now();

  try {
    const [lastUrl, lastTime] = (deps.storage?.getItem(GUARD_KEY) ?? '').split('@');

    if (lastUrl === error.url && now - Number(lastTime) < RELOAD_GUARD_MS) {
      return false;
    }

    deps.storage?.setItem(GUARD_KEY, `${error.url}@${now}`);
  } catch {
    // Sin almacenamiento no hay protección contra bucles: se recarga igualmente una vez por clic.
  }

  deps.assign(`${deps.root}${error.url}`);

  return true;
}
