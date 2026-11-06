import { readEmbeddedState } from './embedded-state';

describe('readEmbeddedState', () => {
  let script: HTMLScriptElement;

  beforeEach(() => {
    script = document.createElement('script');
    script.type = 'application/json';
    script.id = 'presencard-state';
  });

  afterEach(() => script.remove());

  it('devuelve null si el servidor no embebió estado', () => {
    expect(readEmbeddedState(document)).toBeNull();
  });

  it('lee el JSON embebido, incluyendo caracteres escapados por el servidor', () => {
    script.textContent = '{"card":{"hashId":"aaaaaaaaaaaa","fullName":"A \\u003Cb\\u003E \\u0026 \\u0022Z\\u0022"}}';
    document.body.appendChild(script);

    const state = readEmbeddedState(document);

    expect(state?.card?.fullName).toBe('A <b> & "Z"');
  });

  it('devuelve null si el JSON está dañado', () => {
    script.textContent = '{no es json';
    document.body.appendChild(script);

    expect(readEmbeddedState(document)).toBeNull();
  });
});
