import { ChangeDetectionStrategy, Component, effect, inject, input, output, signal } from '@angular/core';
import { NonNullableFormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { apiErrorMessage } from '../../../../core/api';
import { UserService } from '../../../../core/user.service';
import { User } from '../../../../shared/models/user';
import { ModalDialog } from '../../../../shared/ui/modal-dialog';
import { PasswordField } from '../../../../shared/ui/password-field';
import { passwordPolicyValidator } from '../../../../shared/validators';

/** El administrador asigna una contraseña nueva a otro usuario (sin necesidad de conocer la anterior). */
@Component({
  selector: 'app-reset-password-dialog',
  imports: [ReactiveFormsModule, ModalDialog, PasswordField],
  changeDetection: ChangeDetectionStrategy.OnPush,
  template: `
    <app-modal-dialog [open]="user() !== null" heading="Restablecer contraseña" (closed)="closed.emit()">
      @if (user(); as target) {
        <p class="intro">
          Define una contraseña temporal para <strong>{{ target.fullName }}</strong
          >. Comunícasela: al ingresar podrá cambiarla desde su perfil.
        </p>

        <form [formGroup]="form" (ngSubmit)="submit(target)" novalidate>
          @if (error(); as message) {
            <p class="alert alert-error" role="alert">{{ message }}</p>
          }

          <app-password-field
            inputId="reset-password"
            label="Nueva contraseña"
            [control]="form.controls.password"
            [generator]="true"
            [invalid]="form.controls.password.invalid && form.controls.password.touched"
          >
            @if (form.controls.password.invalid && form.controls.password.touched) {
              <span class="field-error">Debe tener entre 8 y 72 caracteres.</span>
            } @else {
              <span class="hint">Usa «Generar» para crear una contraseña segura.</span>
            }
          </app-password-field>

          <div class="actions">
            <button type="button" class="btn btn-ghost" (click)="closed.emit()" [disabled]="saving()">Cancelar</button>
            <button type="submit" class="btn btn-primary" [disabled]="saving()">
              {{ saving() ? 'Guardando…' : 'Restablecer' }}
            </button>
          </div>
        </form>
      }
    </app-modal-dialog>
  `,
  styles: `
    .intro {
      color: var(--muted);
    }
    form {
      display: grid;
      gap: 1rem;
    }
    .actions {
      display: flex;
      flex-wrap: wrap;
      justify-content: flex-end;
      gap: 0.75rem;
    }
  `,
})
export class ResetPasswordDialog {
  private readonly users = inject(UserService);

  /** Usuario al que se le restablece la contraseña; null mantiene el diálogo cerrado. */
  readonly user = input<User | null>(null);
  readonly closed = output<void>();
  readonly done = output<User>();

  protected readonly form = inject(NonNullableFormBuilder).group({
    password: ['', [Validators.required, passwordPolicyValidator]],
  });
  protected readonly saving = signal(false);
  protected readonly error = signal<string | null>(null);

  constructor() {
    // Cada vez que se abre para otro usuario, el formulario empieza limpio.
    effect(() => {
      if (this.user()) {
        this.form.reset();
        this.error.set(null);
      }
    });
  }

  protected submit(target: User): void {
    if (this.form.invalid) {
      this.form.markAllAsTouched();
      return;
    }

    this.saving.set(true);
    this.error.set(null);

    this.users.resetPassword(target.id, this.form.getRawValue().password).subscribe({
      next: () => {
        this.saving.set(false);
        this.done.emit(target);
      },
      error: (error: unknown) => {
        this.saving.set(false);
        this.error.set(apiErrorMessage(error, 'No se pudo restablecer la contraseña.'));
      },
    });
  }
}
