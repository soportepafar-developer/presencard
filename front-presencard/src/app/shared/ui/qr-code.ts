import { ChangeDetectionStrategy, Component, computed, effect, input, signal } from '@angular/core';
import { QR_QUIET_ZONE, QrMatrix, createQrMatrix, qrSvgPath, qrTotalModules } from '../utils/qr';

/** Lado máximo en pantalla (px). Se ajusta al múltiplo entero de módulos más cercano para bordes nítidos. */
const MAX_SIZE = 240;

/** Dibuja el QR de `value` como SVG vectorial (negro sobre blanco, con zona de silencio). */
@Component({
  selector: 'app-qr-code',
  changeDetection: ChangeDetectionStrategy.OnPush,
  template: `
    @if (matrix(); as m) {
      <svg
        role="img"
        [attr.aria-label]="label()"
        [attr.viewBox]="'0 0 ' + total() + ' ' + total()"
        [attr.width]="size()"
        [attr.height]="size()"
        shape-rendering="crispEdges"
      >
        <rect width="100%" height="100%" fill="#ffffff" />
        <path [attr.d]="path()" fill="#000000" />
      </svg>
    } @else {
      <div class="placeholder" [style.width.px]="MAX_SIZE" [style.height.px]="MAX_SIZE" aria-hidden="true"></div>
    }
  `,
  styles: `
    :host {
      display: inline-block;
      line-height: 0;
    }
    svg,
    .placeholder {
      max-width: 100%;
      height: auto;
      aspect-ratio: 1;
    }
    .placeholder {
      background: var(--blue-50);
    }
  `,
})
export class QrCode {
  readonly value = input.required<string>();
  readonly label = input('Código QR');

  protected readonly MAX_SIZE = MAX_SIZE;
  protected readonly matrix = signal<QrMatrix | null>(null);
  protected readonly total = computed(() => qrTotalModules(this.matrix() ?? [], QR_QUIET_ZONE));
  protected readonly path = computed(() => qrSvgPath(this.matrix() ?? []));
  /** Múltiplo entero del número de módulos: cada módulo ocupa un número exacto de píxeles. */
  protected readonly size = computed(() => this.total() * Math.max(1, Math.floor(MAX_SIZE / this.total())));

  constructor() {
    effect((onCleanup) => {
      const value = this.value();
      let stale = false;
      onCleanup(() => (stale = true));

      void createQrMatrix(value).then((matrix) => {
        if (!stale) {
          this.matrix.set(matrix);
        }
      });
    });
  }
}
