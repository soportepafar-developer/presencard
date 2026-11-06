import {
  DEFAULT_ACCENT,
  DEFAULT_PRIMARY,
  PALETTE_PRESETS,
  contrastRatio,
  inkOf,
  isHexColor,
  paletteVars,
  readableOn,
} from './palette';

describe('palette', () => {
  it('devuelve null cuando no hay colores válidos (se conserva la paleta por defecto)', () => {
    expect(paletteVars(null, null)).toBeNull();
    expect(paletteVars('', 'rojo')).toBeNull();
    expect(paletteVars('#fff', '3240A5')).toBeNull();
  });

  it('deriva los tonos del color principal y del acento', () => {
    const vars = paletteVars('#3240a5', '#008d36')!;

    expect(vars['--blue']).toBe('#3240A5');
    expect(vars['--green']).toBe('#008D36');
    expect(vars['--blue-700']).toMatch(/^#[0-9A-F]{6}$/);
    expect(vars['--blue-50']).toMatch(/^#[0-9A-F]{6}$/);
    expect(vars['--accent-glow']).toBe('rgba(0, 141, 54, 0.32)');
  });

  it('solo define las variables del color que se eligió', () => {
    const vars = paletteVars('#7A1F3D', null)!;

    expect(vars['--blue']).toBe('#7A1F3D');
    expect(vars['--green']).toBeUndefined();
  });

  it('el tono -700 es más oscuro y los tintes más claros que el color base', () => {
    const vars = paletteVars('#3240A5', '#008D36')!;

    expect(contrastRatio(vars['--blue-700'], '#FFFFFF')).toBeGreaterThan(contrastRatio('#3240A5', '#FFFFFF'));
    expect(contrastRatio(vars['--blue-50'], '#FFFFFF')).toBeLessThan(1.2);
    expect(contrastRatio(vars['--blue-100'], '#FFFFFF')).toBeLessThan(contrastRatio('#3240A5', '#FFFFFF'));
  });

  it('elige texto blanco sobre fondos oscuros y tinta oscura sobre fondos claros', () => {
    expect(readableOn('#3240A5')).toBe('#FFFFFF');
    expect(readableOn('#FFD400')).toBe('#1B1F3B');
    expect(readableOn('#FFFFFF')).toBe('#1B1F3B');
  });

  it('oscurece un color claro hasta que se pueda leer como texto sobre blanco', () => {
    const ink = inkOf('#FFD400');

    expect(contrastRatio(ink, '#FFFFFF')).toBeGreaterThanOrEqual(4.5);
    expect(inkOf('#1F2937')).toBe('#1F2937');
  });

  it('todas las paletas sugeridas tienen texto legible sobre su color principal y su acento', () => {
    for (const preset of PALETTE_PRESETS) {
      const vars = paletteVars(preset.primary, preset.accent)!;

      expect(contrastRatio(vars['--on-blue'], preset.primary)).withContext(preset.name).toBeGreaterThanOrEqual(4.5);
      // El acento solo lleva texto en negrita o grande (botón de WhatsApp, bloque inferior): mínimo WCAG 3.
      expect(contrastRatio(vars['--on-green'], preset.accent)).withContext(preset.name).toBeGreaterThanOrEqual(3);
      expect(contrastRatio(vars['--blue-ink'], '#FFFFFF')).withContext(preset.name).toBeGreaterThanOrEqual(4.5);
    }
  });

  it('la primera paleta sugerida es la de Pafar (la por defecto)', () => {
    expect(PALETTE_PRESETS[0]).toEqual({ name: 'Pafar', primary: DEFAULT_PRIMARY, accent: DEFAULT_ACCENT });
  });

  it('valida el formato #RRGGBB', () => {
    expect(isHexColor('#a1B2c3')).toBeTrue();
    expect(isHexColor('a1b2c3')).toBeFalse();
    expect(isHexColor('#abc')).toBeFalse();
    expect(isHexColor(null)).toBeFalse();
  });
});
