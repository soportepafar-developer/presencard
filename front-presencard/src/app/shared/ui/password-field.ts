import { ChangeDetectionStrategy, Component, input, signal } from '@angular/core';
import { FormControl, ReactiveFormsModule } from '@angular/forms';
import { generatePassword } from '../utils/password';
import { Icon } from './icon';

/**
 * Campo de contraseña con botón mostrar/ocultar y, opcionalmente, generador de contraseñas seguras
 * (para que el administrador asigne una temporal sin inventarla). Los mensajes de error/ayuda
 * se proyectan como contenido.
 */
@Component({
  selector: 'app-password-field',
  imports: [ReactiveFormsModule, Icon],
  changeDetection: ChangeDetectionStrategy.OnPush,
  template: `
    <div class="field">
      <label [for]="inputId()">{{ label() }}</label>
      <div class="row">
        <input
          [id]="inputId()"
          class="input"
          [type]="visible() ? 'text' : 'password'"
          [formControl]="control()"
          [attr.autocomplete]="autocomplete()"
          [class.is-invalid]="invalid()"
        />
        <button
          type="button"
          class="btn btn-ghost btn-icon"
          (click)="visible.set(!visible())"
          [attr.aria-label]="visible() ? 'Ocultar contraseña' : 'Mostrar contraseña'"
          [attr.aria-pressed]="visible()"
        >
          <app-icon [name]="visible() ? 'eye-off' : 'eye'" />
        </button>
        @if (generator()) {
          <button type="button" class="btn btn-ghost" (click)="generate()"><app-icon name="key" /> Generar</button>
        }
      </div>
      <ng-content />
    </div>
  `,
  styles: `
    .row {
      display: flex;
      gap: 0.5rem;
    }
    .input {
      flex: 1;
      min-width: 0;
    }
  `,
})
export class PasswordField {
  readonly control = input.required<FormControl<string>>();
  readonly inputId = input.required<string>();
  readonly label = input.required<string>();
  readonly autocomplete = input('new-password');
  readonly generator = input(false);
  readonly invalid = input(false);

  protected readonly visible = signal(false);

  protected generate(): void {
    this.control().setValue(generatePassword());
    this.control().markAsDirty();
    this.visible.set(true); // se muestra para que quien la genera pueda anotarla y entregarla
  }
}
