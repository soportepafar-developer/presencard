import { NavigationError } from '@angular/router';
import { ChunkReloadDeps, isChunkLoadError, recoverFromChunkError } from './chunk-reload';

const chunkFailure = () => new TypeError('Failed to fetch dynamically imported module: http://localhost:8080/app/chunk-LXTZJ3AF.js');
const navigationError = (error: unknown, url = '/panel/profile') => new NavigationError(1, url, error);

describe('isChunkLoadError', () => {
  it('reconoce el fallo de carga de pantallas en Chrome, Firefox y Safari', () => {
    expect(isChunkLoadError(chunkFailure())).toBeTrue();
    expect(isChunkLoadError(new TypeError('error loading dynamically imported module: x.js'))).toBeTrue();
    expect(isChunkLoadError(new TypeError('Importing a module script failed.'))).toBeTrue();
    expect(isChunkLoadError(new Error('Loading chunk 12 failed.'))).toBeTrue();
  });

  it('ignora cualquier otro error', () => {
    expect(isChunkLoadError(new Error('Cannot read properties of undefined'))).toBeFalse();
    expect(isChunkLoadError(undefined)).toBeFalse();
    expect(isChunkLoadError({ message: 'dynamically imported module' })).toBeFalse();
  });
});

describe('recoverFromChunkError', () => {
  let assigned: string[];
  let store: Record<string, string>;
  let time: number;
  let deps: ChunkReloadDeps;

  beforeEach(() => {
    assigned = [];
    store = {};
    time = 1_000_000;
    deps = {
      assign: (url) => assigned.push(url),
      root: '',
      now: () => time,
      storage: { getItem: (key) => store[key] ?? null, setItem: (key, value) => void (store[key] = value) },
    };
  });

  it('recarga la página en la URL que el usuario quería abrir', () => {
    expect(recoverFromChunkError(navigationError(chunkFailure()), deps)).toBeTrue();
    expect(assigned).toEqual(['/panel/profile']);
  });

  it('no toca los demás errores de navegación', () => {
    expect(recoverFromChunkError(navigationError(new Error('otro fallo')), deps)).toBeFalse();
    expect(assigned).toEqual([]);
  });

  it('no entra en bucle: una segunda recarga a la misma URL en 15 s se descarta', () => {
    recoverFromChunkError(navigationError(chunkFailure()), deps);
    time += 5_000;

    expect(recoverFromChunkError(navigationError(chunkFailure()), deps)).toBeFalse();
    expect(assigned).toEqual(['/panel/profile']);
  });

  it('pasado el plazo, o para otra URL, vuelve a recargar', () => {
    recoverFromChunkError(navigationError(chunkFailure()), deps);

    time += 20_000;
    expect(recoverFromChunkError(navigationError(chunkFailure()), deps)).toBeTrue();

    time += 1_000;
    expect(recoverFromChunkError(navigationError(chunkFailure(), '/panel/users'), deps)).toBeTrue();
    expect(assigned).toEqual(['/panel/profile', '/panel/profile', '/panel/users']);
  });

  it('funciona aunque el almacenamiento no esté disponible', () => {
    expect(recoverFromChunkError(navigationError(chunkFailure()), { ...deps, storage: null })).toBeTrue();
    expect(assigned).toEqual(['/panel/profile']);
  });

  it('funciona aunque el almacenamiento lance errores', () => {
    const broken = {
      getItem: () => {
        throw new Error('bloqueado');
      },
      setItem: () => {
        throw new Error('bloqueado');
      },
    };

    expect(recoverFromChunkError(navigationError(chunkFailure()), { ...deps, storage: broken })).toBeTrue();
  });

  it('en una subcarpeta recarga con el prefijo de la instalación', () => {
    recoverFromChunkError(navigationError(chunkFailure()), { ...deps, root: '/presencard' });

    expect(assigned).toEqual(['/presencard/panel/profile']);
  });
});
