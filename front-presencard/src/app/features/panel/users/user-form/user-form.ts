import { ChangeDetectionStrategy, Component, OnInit, computed, inject, signal } from '@angular/core';
import { AbstractControl, FormControl, NonNullableFormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { ActivatedRoute, Router, RouterLink } from '@angular/router';
import { apiErrorMessage } from '../../../../core/api';
import { AuthService } from '../../../../core/auth.service';
import { UserService } from '../../../../core/user.service';
import { ROLE_LABELS, User, UserInput, UserRole } from '../../../../shared/models/user';
import { Icon } from '../../../../shared/ui/icon';
import { PasswordField } from '../../../../shared/ui/password-field';
import { passwordPolicyValidator } from '../../../../shared/validators';
import { ResetPasswordDialog } from '../reset-password-dialog/reset-password-dialog';

@Component({
  selector: 'app-user-form',
  imports: [ReactiveFormsModule, RouterLink, Icon, PasswordField, ResetPasswordDialog],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './user-form.html',
  styleUrl: './user-form.scss',
})
export class UserForm implements OnInit {
  private readonly service = inject(UserService);
  private readonly router = inject(Router);
  private readonly route = inject(ActivatedRoute);
  private readonly auth = inject(AuthService);

  /** Presente al editar (/panel/users/:id/edit); ausente al crear. */
  protected readonly userId = Number(this.route.snapshot.paramMap.get('id')) || null;
  protected readonly isEdit = this.userId !== null;

  protected readonly form = inject(NonNullableFormBuilder).group({
    fullName: ['', [Validators.required, Validators.maxLength(150)]],
    email: ['', [Validators.required, Validators.email, Validators.maxLength(180)]],
    role: ['ROLE_USER' as UserRole, [Validators.required]],
  });
  /** Solo al crear: el administrador define la contraseña inicial. */
  protected readonly password = new FormControl('', {
    nonNullable: true,
    validators: [Validators.required, passwordPolicyValidator],
  });

  protected readonly roles: ReadonlyArray<{ value: UserRole; label: string }> = [
    { value: 'ROLE_USER', label: ROLE_LABELS.ROLE_USER },
    { value: 'ROLE_ADMIN', label: ROLE_LABELS.ROLE_ADMIN },
  ];

  protected readonly loading = signal(this.isEdit);
  protected readonly loadError = signal<string | null>(null);
  protected readonly saving = signal(false);
  protected readonly error = signal<string | null>(null);
  protected readonly flash = signal<string | null>(null);

  protected readonly editing = signal<User | null>(null);
  protected readonly resetting = signal<User | null>(null);
  /** Nadie puede cambiarse el rol a sí mismo (evita quedarse sin administradores). */
  protected readonly isSelf = computed(() => this.editing() !== null && this.editing()?.id === this.auth.user()?.id);

  ngOnInit(): void {
    if (this.userId === null) {
      return;
    }

    this.service.get(this.userId).subscribe({
      next: (user) => {
        this.editing.set(user);
        this.form.patchValue({ fullName: user.fullName, email: user.email, role: user.role });

        if (user.id === this.auth.user()?.id) {
          this.form.controls.role.disable();
        }

        this.loading.set(false);
      },
      error: (error: unknown) => {
        this.loadError.set(apiErrorMessage(error, 'No se pudo cargar el usuario.'));
        this.loading.set(false);
      },
    });
  }

  protected invalid(control: AbstractControl): boolean {
    return control.invalid && (control.touched || control.dirty);
  }

  protected submit(): void {
    if (this.form.invalid || (!this.isEdit && this.password.invalid)) {
      this.form.markAllAsTouched();
      this.password.markAsTouched();
      return;
    }

    const raw = this.form.getRawValue();
    const input: UserInput = { fullName: raw.fullName.trim(), email: raw.email.trim(), role: raw.role };

    this.saving.set(true);
    this.error.set(null);

    const request =
      this.userId === null
        ? this.service.create({ ...input, password: this.password.value })
        : this.service.update(this.userId, input);

    request.subscribe({
      next: (user) => {
        this.saving.set(false);
        void this.router.navigate(['/panel/users'], {
          state: { flash: this.isEdit ? `Se guardaron los cambios de ${user.fullName}.` : `Se creó el usuario ${user.fullName}.` },
        });
      },
      error: (error: unknown) => {
        this.saving.set(false);
        this.error.set(apiErrorMessage(error, 'No se pudo guardar el usuario.'));
      },
    });
  }

  protected passwordReset(user: User): void {
    this.resetting.set(null);
    this.flash.set(`Se restableció la contraseña de ${user.fullName}.`);
  }
}
