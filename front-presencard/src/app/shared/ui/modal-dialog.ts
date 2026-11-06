import { ChangeDetectionStrategy, Component, ElementRef, effect, input, output, viewChild } from '@angular/core';
import { Icon } from './icon';

let nextId = 0;

/**
 * Modal accesible sobre <dialog>: el navegador gestiona foco, Escape y capa inerte.
 * El padre controla `open` y reacciona a `closed` (Escape, botón X o clic fuera).
 */
@Component({
  selector: 'app-modal-dialog',
  imports: [Icon],
  changeDetection: ChangeDetectionStrategy.OnPush,
  template: `
    <dialog
      #dialog
      [attr.aria-labelledby]="titleId"
      (click)="onClick($event)"
      (close)="onNativeClose()"
    >
      <div class="panel">
        <header>
          <h2 [id]="titleId">{{ heading() }}</h2>
          <button type="button" class="close" aria-label="Cerrar" (click)="closed.emit()">
            <app-icon name="x" />
          </button>
        </header>
        <ng-content />
      </div>
    </dialog>
  `,
  styleUrl: './modal-dialog.scss',
})
export class ModalDialog {
  readonly open = input(false);
  readonly heading = input.required<string>();
  readonly closed = output<void>();

  protected readonly titleId = `modal-title-${nextId++}`;
  private readonly dialog = viewChild.required<ElementRef<HTMLDialogElement>>('dialog');

  constructor() {
    effect(() => {
      const element = this.dialog().nativeElement;

      if (this.open() && !element.open) {
        element.showModal();
      } else if (!this.open() && element.open) {
        element.close();
      }
    });
  }

  /** Clic sobre el fondo (el propio <dialog>) cierra el modal. */
  protected onClick(event: MouseEvent): void {
    if (event.target === this.dialog().nativeElement) {
      this.closed.emit();
    }
  }

  /** Escape cierra el <dialog> de forma nativa: se avisa al padre para que sincronice `open`. */
  protected onNativeClose(): void {
    if (this.open()) {
      this.closed.emit();
    }
  }
}
