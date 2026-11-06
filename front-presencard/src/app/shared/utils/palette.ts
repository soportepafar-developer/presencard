/** Paleta por defecto de Presencard: la que usa toda tarjeta que no eligió colores. */
export const DEFAULT_PRIMARY = '#3240A5';
export const DEFAULT_ACCENT = '#008D36';

export interface PalettePreset {
  name: string;
  primary: string;
  accent: string;
}

/** Combinaciones sugeridas para elegir con un clic (el usuario también puede escribir sus propios colores). */
export const PALETTE_PRESETS: readonly PalettePreset[] = [
  { name: 'Pafar', primary: DEFAULT_PRIMARY, accent: DEFAULT_ACCENT },
  { name: 'Océano', primary: '#0B6E99', accent: '#F2A900' },
  { name: 'Bosque', primary: '#1F5F4A', accent: '#C8963E' },
  { name: 'Carbón', primary: '#1F2937', accent: '#D4A017' },
  { name: 'Vino', primary: '#7A1F3D', accent: '#E07A5F' },
  { name: 'Violeta', primary: '#5B3FA8', accent: '#17A2B8' },
  { name: 'Rojo', primary: '#B3261E', accent: '#1B1F3B' },
  { name: 'Grafito', primary: '#2B2D42', accent: '#00A6A6' },
];

/** Color en formato #RRGGBB (el mismo que valida el backend). */
export const HEX_COLOR_PATTERN = /^#[0-9A-Fa-f]{6}$/;

type Rgb = readonly [number, number, number];

const WHITE: Rgb = [255, 255, 255];
const BLACK: Rgb = [0, 0, 0];
const INK = '#1B1F3B';

const toRgb = (hex: string): Rgb => [1, 3, 5].map((i) => parseInt(hex.slice(i, i + 2), 16)) as unknown as Rgb;

const toHex = (rgb: Rgb): string =>
  '#' + rgb.map((v) => Math.round(v).toString(16).padStart(2, '0')).join('').toUpperCase();

/** Mezcla `color` con `other`; `amount` es la proporción de `other` (0 a 1). */
const mix = (color: Rgb, other: Rgb, amount: number): Rgb =>
  [0, 1, 2].map((i) => color[i] + (other[i] - color[i]) * amount) as unknown as Rgb;

const luminance = (rgb: Rgb): number => {
  const [r, g, b] = rgb.map((v) => {
    const c = v / 255;
    return c <= 0.03928 ? c / 12.92 : Math.pow((c + 0.055) / 1.055, 2.4);
  });

  return 0.2126 * r + 0.7152 * g + 0.0722 * b;
};

/** Relación de contraste WCAG entre dos colores (de 1 a 21). */
export function contrastRatio(a: string, b: string): number {
  const [light, dark] = [luminance(toRgb(a)), luminance(toRgb(b))].sort((x, y) => y - x);

  return (light + 0.05) / (dark + 0.05);
}

/** Texto legible (blanco o tinta oscura) sobre un fondo del color dado. */
export function readableOn(background: string): string {
  return contrastRatio('#FFFFFF', background) >= contrastRatio(INK, background) ? '#FFFFFF' : INK;
}

/** Oscurece el color hasta que se lea (contraste mínimo 4.5) como texto sobre blanco. */
export function inkOf(color: string): string {
  let rgb = toRgb(color);

  for (let step = 0; step < 20 && contrastRatio(toHex(rgb), '#FFFFFF') < 4.5; step++) {
    rgb = mix(rgb, BLACK, 0.08);
  }

  return toHex(rgb);
}

export const isHexColor = (value: string | null | undefined): value is string =>
  !!value && HEX_COLOR_PATTERN.test(value);

/**
 * Variables CSS de una paleta. Reutilizan los nombres de los tokens globales (`--blue*` = principal,
 * `--green*` = acento), así que los botones y chips heredados se recolorean solos dentro de la tarjeta.
 * Sin colores válidos devuelve null: la tarjeta conserva la paleta por defecto.
 */
export function paletteVars(primary: string | null, accent: string | null): Record<string, string> | null {
  const p = isHexColor(primary) ? primary.toUpperCase() : null;
  const a = isHexColor(accent) ? accent.toUpperCase() : null;

  if (!p && !a) {
    return null;
  }

  const vars: Record<string, string> = {};

  if (p) {
    const rgb = toRgb(p);
    vars['--blue'] = p;
    vars['--blue-700'] = toHex(mix(rgb, BLACK, 0.25));
    vars['--blue-100'] = toHex(mix(rgb, WHITE, 0.86));
    vars['--blue-50'] = toHex(mix(rgb, WHITE, 0.93));
    vars['--blue-ink'] = inkOf(p);
    vars['--on-blue'] = readableOn(p);
  }

  if (a) {
    const rgb = toRgb(a);
    vars['--green'] = a;
    vars['--green-700'] = toHex(mix(rgb, BLACK, 0.2));
    vars['--green-50'] = toHex(mix(rgb, WHITE, 0.92));
    vars['--accent-glow'] = `rgba(${rgb.join(', ')}, 0.32)`;
    vars['--on-green'] = readableOn(a);
  }

  return vars;
}
