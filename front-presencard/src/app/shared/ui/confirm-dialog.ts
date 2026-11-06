import { ChangeDetectionStrategy, Component, input, output } from '@angular/core';
import { ModalDialog } from './modal-dialog';

@Component({
  selector: 'app-confirm-dialog',
  imports: [ModalDialog],
  changeDetection: ChangeDetectionStrategy.OnPush,
  template: `
    <app-modal-dialog [open]="open()" [heading]="heading()" (closed)="cancelled.emit()">
      <p class="message">{{ message() }}</p>
      @if (error()) {
        <p class="alert alert-error" role="alert">{{ error() }}</p>
      }
      <div class="actions">
        <button type="button" class="btn btn-ghost" (click)="cancelled.emit()" [disabled]="busy()">Cancelar</button>
        <button type="button" class="btn btn-danger" (click)="confirmed.emit()" [disabled]="busy()">
          {{ busy() ? 'Eliminando…' : confirmLabel() }}
        </button>
      </div>
    </app-modal-dialog>
  `,
  styles: `
    .message {
      color: var(--muted);
    }
    .actions {
      display: flex;
      flex-wrap: wrap;
      justify-content: flex-end;
      gap: 0.75rem;
    }
  `,
})
export class ConfirmDialog {
  readonly open = input(false);
  readonly heading = input.required<string>();
  readonly message = input.required<string>();
  readonly confirmLabel = input('Eliminar');
  readonly busy = input(false);
  readonly error = input<string | null>(null);

  readonly confirmed = output<void>();
  readonly cancelled = output<void>();
}
