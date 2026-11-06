/**
 * Códigos QR limpios para compartir el enlace de una tarjeta.
 *
 * - El QR contiene solo la URL: cuanto más corta, menos módulos ("píxeles") y más fácil de escanear.
 * - Se usa el menor tamaño posible (ver `compact`) y una zona de silencio de 4 módulos, como pide el estándar.
 * - Al dibujar, cada módulo mide un número ENTERO de píxeles y sin suavizado: bordes nítidos en cualquier pantalla.
 */

export type QrErrorCorrection = 'L' | 'M' | 'Q' | 'H';

/**
 * 'compact' (por defecto): nivel M (corrige ~15 % de daño), salvo que L (~7 %) permita una cuadrícula
 * más pequeña; entonces se prefiere la más pequeña, que es la más fácil de leer para una cámara de móvil.
 */
export type QrLevel = QrErrorCorrection | 'compact';

/** Margen blanco alrededor del código, en módulos (el estándar pide mínimo 4). */
export const QR_QUIET_ZONE = 4;

export type QrMatrix = boolean[][];

/** El generador se carga bajo demanda: solo pesa cuando alguien abre el modal del enlace. */
async function buildMatrix(value: string, level: QrErrorCorrection): Promise<QrMatrix> {
  const { default: qrcode } = await import('qrcode-generator');
  const qr = qrcode(0, level); // 0 = versión (tamaño) automática: la más pequeña que quepa
  qr.addData(value, 'Byte');
  qr.make();

  const size = qr.getModuleCount();

  return Array.from({ length: size }, (_, row) => Array.from({ length: size }, (_, col) => qr.isDark(row, col)));
}

export async function createQrMatrix(value: string, level: QrLevel = 'compact'): Promise<QrMatrix> {
  if (level !== 'compact') {
    return buildMatrix(value, level);
  }

  const [medium, low] = await Promise.all([buildMatrix(value, 'M'), buildMatrix(value, 'L')]);

  return low.length < medium.length ? low : medium;
}

/** Lado total (módulos) incluida la zona de silencio. */
export const qrTotalModules = (matrix: QrMatrix, quietZone = QR_QUIET_ZONE): number => matrix.length + quietZone * 2;

/** Tramos horizontales continuos de módulos oscuros: [columna, fila, largo]. Menos formas = SVG más liviano. */
export function qrDarkRuns(matrix: QrMatrix): Array<[number, number, number]> {
  const runs: Array<[number, number, number]> = [];

  matrix.forEach((cells, row) => {
    let start = -1;

    cells.forEach((dark, col) => {
      if (dark && start === -1) {
        start = col;
      }

      if (!dark && start !== -1) {
        runs.push([start, row, col - start]);
        start = -1;
      }
    });

    if (start !== -1) {
      runs.push([start, row, cells.length - start]);
    }
  });

  return runs;
}

/** Atributo `d` de un único <path> con todos los módulos oscuros (ya desplazados por la zona de silencio). */
export function qrSvgPath(matrix: QrMatrix, quietZone = QR_QUIET_ZONE): string {
  return qrDarkRuns(matrix)
    .map(([col, row, length]) => `M${col + quietZone} ${row + quietZone}h${length}v1h-${length}z`)
    .join('');
}

/**
 * PNG nítido: el tamaño de módulo es el mayor entero que quepa en `targetPixels`, así no hay
 * escalado fraccionario (que difuminaría los bordes). Negro sobre blanco para máxima fiabilidad.
 */
export function qrPngBlob(matrix: QrMatrix, targetPixels = 1024, quietZone = QR_QUIET_ZONE): Promise<Blob> {
  const total = qrTotalModules(matrix, quietZone);
  const scale = Math.max(1, Math.floor(targetPixels / total));
  const canvas = document.createElement('canvas');
  canvas.width = total * scale;
  canvas.height = total * scale;

  const context = canvas.getContext('2d');

  if (!context) {
    return Promise.reject(new Error('El navegador no permite generar la imagen.'));
  }

  context.imageSmoothingEnabled = false;
  context.fillStyle = '#ffffff';
  context.fillRect(0, 0, canvas.width, canvas.height);
  context.fillStyle = '#000000';

  for (const [col, row, length] of qrDarkRuns(matrix)) {
    context.fillRect((col + quietZone) * scale, (row + quietZone) * scale, length * scale, scale);
  }

  return new Promise<Blob>((resolve, reject) =>
    canvas.toBlob((blob) => (blob ? resolve(blob) : reject(new Error('No se pudo generar la imagen.'))), 'image/png'),
  );
}
