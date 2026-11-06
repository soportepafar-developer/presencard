import { QR_QUIET_ZONE, QrMatrix, createQrMatrix, qrDarkRuns, qrPngBlob, qrSvgPath, qrTotalModules } from './qr';

/** 36 bytes: cabe en la versión 3 (29x29 módulos) con corrección M. */
const SHORT = 'http://localhost:8080/c/aB3dE5gH7jK9';
/** 45 bytes: con corrección M exige la versión 4 (33x33); con L cabe en la 3 (29x29). */
const LONG = 'https://tarjetas.midominio.com/c/aB3dE5gH7jK9';

/** Comprueba el patrón de posición de 7x7 (borde oscuro, anillo claro, centro oscuro 3x3). */
function expectFinderPattern(matrix: QrMatrix, row0: number, col0: number): void {
  for (let dr = 0; dr < 7; dr++) {
    for (let dc = 0; dc < 7; dc++) {
      const edge = dr === 0 || dr === 6 || dc === 0 || dc === 6;
      const center = dr >= 2 && dr <= 4 && dc >= 2 && dc <= 4;

      expect(matrix[row0 + dr][col0 + dc]).withContext(`(${row0 + dr},${col0 + dc})`).toBe(edge || center);
    }
  }
}

describe('createQrMatrix', () => {
  it('produce una cuadrícula cuadrada pequeña para una URL corta', async () => {
    const matrix = await createQrMatrix(SHORT, 'M');

    expect(matrix.length).toBe(29);
    expect(matrix.every((row) => row.length === 29)).toBeTrue();
  });

  it('respeta la estructura del estándar: 3 patrones de posición y líneas de sincronía', async () => {
    const matrix = await createQrMatrix(SHORT);
    const size = matrix.length;

    expectFinderPattern(matrix, 0, 0);
    expectFinderPattern(matrix, 0, size - 7);
    expectFinderPattern(matrix, size - 7, 0);

    for (let i = 8; i < size - 8; i++) {
      expect(matrix[6][i]).withContext(`sincronía horizontal ${i}`).toBe(i % 2 === 0);
      expect(matrix[i][6]).withContext(`sincronía vertical ${i}`).toBe(i % 2 === 0);
    }
  });

  it('un nivel de corrección más bajo permite una cuadrícula más pequeña', async () => {
    expect((await createQrMatrix(LONG, 'M')).length).toBe(33);
    expect((await createQrMatrix(LONG, 'L')).length).toBe(29);
  });

  it('"compact" elige la cuadrícula más pequeña posible', async () => {
    expect((await createQrMatrix(LONG)).length).toBe(29);
  });

  it('"compact" conserva la corrección M cuando L no reduce el tamaño', async () => {
    expect(await createQrMatrix(SHORT)).toEqual(await createQrMatrix(SHORT, 'M'));
  });

  it('es determinista: la misma URL da siempre el mismo código', async () => {
    expect(await createQrMatrix(SHORT)).toEqual(await createQrMatrix(SHORT));
  });

  it('URLs distintas dan códigos distintos', async () => {
    expect(await createQrMatrix(SHORT)).not.toEqual(await createQrMatrix(SHORT.replace('aB3d', 'zZ3d')));
  });
});

describe('qrSvgPath / qrDarkRuns', () => {
  const tiny: QrMatrix = [
    [true, true, false],
    [false, true, false],
    [true, false, true],
  ];

  it('agrupa módulos oscuros contiguos en tramos horizontales', () => {
    expect(qrDarkRuns(tiny)).toEqual([
      [0, 0, 2],
      [1, 1, 1],
      [0, 2, 1],
      [2, 2, 1],
    ]);
  });

  it('desplaza el dibujo por la zona de silencio de 4 módulos', () => {
    expect(QR_QUIET_ZONE).toBe(4);
    expect(qrSvgPath(tiny)).toBe('M4 4h2v1h-2zM5 5h1v1h-1zM4 6h1v1h-1zM6 6h1v1h-1z');
  });

  it('el lado total suma la zona de silencio por ambos lados', () => {
    expect(qrTotalModules(tiny)).toBe(3 + 8);
  });
});

describe('qrPngBlob', () => {
  // Navegadores como Brave añaden ruido de ±1 a las lecturas del canvas (anti-huella digital): se compara con
  // tolerancia. Lo que importa es que cada píxel sea claramente negro o blanco, sin grises intermedios.
  const NOISE = 3;
  const isBlack = (px: number[]): boolean => px.every((channel) => channel <= NOISE);
  const isWhite = (px: number[]): boolean => px.every((channel) => channel >= 255 - NOISE);

  async function render(matrix: QrMatrix, target?: number): Promise<{ data: Uint8ClampedArray; width: number }> {
    const blob = await qrPngBlob(matrix, target);
    expect(blob.type).toBe('image/png');

    const bitmap = await createImageBitmap(blob);
    const canvas = document.createElement('canvas');
    canvas.width = bitmap.width;
    canvas.height = bitmap.height;
    const context = canvas.getContext('2d') as CanvasRenderingContext2D;
    context.drawImage(bitmap, 0, 0);

    return { data: context.getImageData(0, 0, canvas.width, canvas.height).data, width: bitmap.width };
  }

  const pixel = (image: { data: Uint8ClampedArray; width: number }, x: number, y: number): number[] => {
    const i = (y * image.width + x) * 4;

    return [image.data[i], image.data[i + 1], image.data[i + 2]];
  };

  it('dibuja cada módulo con un número entero de píxeles, en blanco y negro puros', async () => {
    const matrix = await createQrMatrix(SHORT);
    const total = qrTotalModules(matrix); // 29 + 8 = 37
    const image = await render(matrix, 1024);
    const scale = Math.floor(1024 / total); // 27 px por módulo

    expect(image.width).toBe(total * scale);

    for (let row = 0; row < matrix.length; row++) {
      for (let col = 0; col < matrix.length; col++) {
        const center = pixel(image, (col + QR_QUIET_ZONE) * scale + (scale >> 1), (row + QR_QUIET_ZONE) * scale + (scale >> 1));

        expect(matrix[row][col] ? isBlack(center) : isWhite(center)).withContext(`módulo (${row},${col}): ${center}`).toBeTrue();
      }
    }
  });

  it('deja una zona de silencio blanca alrededor', async () => {
    const matrix = await createQrMatrix(SHORT);
    const image = await render(matrix, 1024);
    const scale = Math.floor(1024 / qrTotalModules(matrix));
    const margin = QR_QUIET_ZONE * scale;

    for (const [x, y] of [[0, 0], [margin - 1, margin - 1], [image.width - 1, image.width - 1], [image.width / 2, 2]]) {
      expect(isWhite(pixel(image, Math.floor(x), Math.floor(y)))).withContext(`(${x},${y})`).toBeTrue();
    }
  });

  it('los bordes son nítidos: sin tonos grises entre un módulo y el siguiente', async () => {
    const matrix = await createQrMatrix(SHORT);
    const image = await render(matrix, 1024);
    const scale = Math.floor(1024 / qrTotalModules(matrix));
    const y = (QR_QUIET_ZONE + 3) * scale + (scale >> 1); // fila central del patrón de posición superior
    let transitions = 0;
    let previousBlack = false;

    for (let x = 0; x < image.width; x++) {
      const px = pixel(image, x, y);
      const black = isBlack(px);

      expect(black || isWhite(px)).withContext(`x=${x}: ${px}`).toBeTrue(); // nunca un gris intermedio
      transitions += black !== previousBlack ? 1 : 0;
      previousBlack = black;
    }

    // Fila central del patrón de posición: borde, anillo claro, centro 3x3 y borde = 4 transiciones, más
    // las de los otros dos patrones y los datos; basta con comprobar que hay transiciones y todas son limpias.
    expect(transitions).toBeGreaterThan(4);
  });

  it('el PNG descargado se lee con un lector de QR real y devuelve la URL exacta', async () => {
    const Detector = (window as unknown as { BarcodeDetector?: new (options: { formats: string[] }) => { detect(source: ImageBitmap): Promise<Array<{ rawValue: string }>> } }).BarcodeDetector;

    if (!Detector) {
      pending('Este navegador no incluye BarcodeDetector.');
      return;
    }

    for (const url of [SHORT, LONG]) {
      const blob = await qrPngBlob(await createQrMatrix(url), 1024);
      const found = await new Detector({ formats: ['qr_code'] }).detect(await createImageBitmap(blob));

      expect(found.map((code) => code.rawValue)).withContext(url).toEqual([url]);
    }
  });
});
