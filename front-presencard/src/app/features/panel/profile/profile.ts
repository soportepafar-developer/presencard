import { ChangeDetectionStrategy, Component, computed, inject, signal } from '@angular/core';
import { AbstractControl, NonNullableFormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { apiErrorMessage } from '../../../core/api';
import { AuthService } from '../../../core/auth.service';
import { ProfileService } from '../../../core/profile.service';
import { ROLE_LABELS } from '../../../shared/models/user';
import { Icon } from '../../../shared/ui/icon';
import { PasswordField } from '../../../shared/ui/password-field';
import { initialsOf } from '../../../shared/utils/initials';
import { passwordPolicyValidator, sameValueValidator } from '../../../shared/validators';

@Component({
  selector: 'app-profile',
  imports: [ReactiveFormsModule, Icon, PasswordField],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './profile.html',
  styleUrl: './profile.scss',
})
export class Profile {
  private readonly auth = inject(AuthService);
  private readonly profiles = inject(ProfileService);
  private readonly fb = inject(NonNullableFormBuilder);

  protected readonly user = this.auth.user;
  protected readonly initials = computed(() => initialsOf(this.user()?.fullName ?? ''));
  protected readonly roleLabel = computed(() => {
    const role = this.user()?.role;

    return role ? ROLE_LABELS[role] : '';
  });

  protected readonly profileForm = this.fb.group({
    fullName: [this.user()?.fullName ?? '', [Validators.required, Validators.maxLength(150)]],
    email: [this.user()?.email ?? '', [Validators.required, Validators.email, Validators.maxLength(180)]],
  });
  protected readonly profileSaving = signal(false);
  protected readonly profileError = signal<string | null>(null);
  protected readonly profileSaved = signal(false);

  protected readonly passwordForm = this.fb.group(
    {
      currentPassword: ['', [Validators.required]],
      newPassword: ['', [Validators.required, passwordPolicyValidator]],
      confirmPassword: ['', [Validators.required]],
    },
    { validators: [sameValueValidator('newPassword', 'confirmPassword')] },
  );
  protected readonly passwordSaving = signal(false);
  protected readonly passwordError = signal<string | null>(null);
  protected readonly passwordSaved = signal(false);

  protected invalid(control: AbstractControl): boolean {
    return control.invalid && (control.touched || control.dirty);
  }

  protected mismatch(): boolean {
    const { confirmPassword } = this.passwordForm.controls;

    return this.passwordForm.hasError('mismatch') && (confirmPassword.touched || confirmPassword.dirty);
  }

  protected saveProfile(): void {
    if (this.profileForm.invalid) {
      this.profileForm.markAllAsTouched();
      return;
    }

    const { fullName, email } = this.profileForm.getRawValue();
    this.profileSaving.set(true);
    this.profileError.set(null);
    this.profileSaved.set(false);

    this.profiles.update({ fullName: fullName.trim(), email: email.trim() }).subscribe({
      next: (user) => {
        this.profileSaving.set(false);
        this.profileSaved.set(true);
        this.profileForm.reset({ fullName: user.fullName, email: user.email });
      },
      error: (error: unknown) => {
        this.profileSaving.set(false);
        this.profileError.set(apiErrorMessage(error, 'No se pudo actualizar el perfil.'));
      },
    });
  }

  protected savePassword(): void {
    if (this.passwordForm.invalid) {
      this.passwordForm.markAllAsTouched();
      return;
    }

    const { currentPassword, newPassword } = this.passwordForm.getRawValue();
    this.passwordSaving.set(true);
    this.passwordError.set(null);
    this.passwordSaved.set(false);

    this.profiles.changePassword(currentPassword, newPassword).subscribe({
      next: () => {
        this.passwordSaving.set(false);
        this.passwordSaved.set(true);
        this.passwordForm.reset();
      },
      error: (error: unknown) => {
        this.passwordSaving.set(false);
        this.passwordError.set(apiErrorMessage(error, 'No se pudo cambiar la contraseña.'));
      },
    });
  }
}
