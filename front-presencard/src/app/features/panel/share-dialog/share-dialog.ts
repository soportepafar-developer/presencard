import { ChangeDetectionStrategy, Component, ElementRef, input, output, signal, viewChild } from '@angular/core';
import { Presencard } from '../../../shared/models/presencard';
import { Icon } from '../../../shared/ui/icon';
import { ModalDialog } from '../../../shared/ui/modal-dialog';
import { QrCode } from '../../../shared/ui/qr-code';
import { downloadBlob, slugify } from '../../../shared/utils/download';
import { createQrMatrix, qrPngBlob } from '../../../shared/utils/qr';

/** Muestra la URL pública única de una tarjeta y su código QR, con opción de copiarla, abrirla o descargar el QR. */
@Component({
  selector: 'app-share-dialog',
  imports: [ModalDialog, Icon, QrCode],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './share-dialog.html',
  styleUrl: './share-dialog.scss',
})
export class ShareDialog {
  readonly open = input(false);
  readonly heading = input('Enlace de la tarjeta');
  readonly card = input<Presencard | null>(null);
  readonly closed = output<void>();

  protected readonly copied = signal(false);

  /** Cargo y empresa en una línea (ambos opcionales). */
  protected subtitle(card: Presencard): string {
    return [card.jobTitle, card.company].filter(Boolean).join(' · ');
  }

  protected readonly downloading = signal(false);
  protected readonly qrError = signal<string | null>(null);
  private readonly urlInput = viewChild<ElementRef<HTMLInputElement>>('urlInput');
  private resetTimer: ReturnType<typeof setTimeout> | undefined;

  protected async copy(): Promise<void> {
    const url = this.card()?.publicUrl;
    const field = this.urlInput()?.nativeElement;

    if (!url) {
      return;
    }

    try {
      await navigator.clipboard.writeText(url);
      this.copied.set(true);
      clearTimeout(this.resetTimer);
      this.resetTimer = setTimeout(() => this.copied.set(false), 2000);
    } catch {
      // Sin permiso de portapapeles: se deja el texto seleccionado para copiarlo a mano.
      field?.select();
    }
  }

  /** Descarga el QR como PNG nítido listo para pantallas, WhatsApp o imprenta. */
  protected async downloadQr(): Promise<void> {
    const card = this.card();

    if (!card || this.downloading()) {
      return;
    }

    this.downloading.set(true);
    this.qrError.set(null);

    try {
      const blob = await qrPngBlob(await createQrMatrix(card.publicUrl));
      downloadBlob(blob, `qr-${slugify(card.fullName) || card.hashId}.png`);
    } catch {
      this.qrError.set('No se pudo generar el QR. Inténtalo de nuevo.');
    } finally {
      this.downloading.set(false);
    }
  }
}
