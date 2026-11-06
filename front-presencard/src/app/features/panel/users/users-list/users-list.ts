import { toObservable, takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { ChangeDetectionStrategy, Component, computed, inject, signal } from '@angular/core';
import { FormControl, ReactiveFormsModule } from '@angular/forms';
import { Router, RouterLink } from '@angular/router';
import { catchError, debounceTime, distinctUntilChanged, of, switchMap, tap } from 'rxjs';
import { apiErrorMessage } from '../../../../core/api';
import { AuthService } from '../../../../core/auth.service';
import { UserService } from '../../../../core/user.service';
import { ROLE_LABELS, User, UserListQuery, UserPage } from '../../../../shared/models/user';
import { ConfirmDialog } from '../../../../shared/ui/confirm-dialog';
import { Icon } from '../../../../shared/ui/icon';
import { initialsOf } from '../../../../shared/utils/initials';
import { ResetPasswordDialog } from '../reset-password-dialog/reset-password-dialog';

@Component({
  selector: 'app-users-list',
  imports: [ReactiveFormsModule, RouterLink, Icon, ConfirmDialog, ResetPasswordDialog],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './users-list.html',
  styleUrl: './users-list.scss',
})
export class UsersList {
  private readonly service = inject(UserService);
  private readonly router = inject(Router);
  protected readonly auth = inject(AuthService);

  protected readonly search = new FormControl('', { nonNullable: true });
  protected readonly page = signal<UserPage | null>(null);
  protected readonly loading = signal(true);
  protected readonly loadError = signal<string | null>(null);
  protected readonly flash = signal<string | null>(null);
  protected readonly actionError = signal<string | null>(null);

  protected readonly resetting = signal<User | null>(null);
  protected readonly deleting = signal<User | null>(null);
  protected readonly deleteBusy = signal(false);
  protected readonly deleteError = signal<string | null>(null);
  protected readonly deleteMessage = computed(
    () =>
      `¿Eliminar a ${this.deleting()?.fullName ?? ''}? Perderá el acceso y sus tarjetas pasarán a tu cuenta. No se puede deshacer.`,
  );

  protected readonly initialsOf = initialsOf;
  protected readonly roleLabels = ROLE_LABELS;

  /** Cambiar este valor (aunque sea el mismo contenido) dispara una nueva carga. */
  private readonly query = signal<UserListQuery>({ q: '', page: 1 });

  constructor() {
    const flash = this.router.getCurrentNavigation()?.extras.state?.['flash'];
    this.flash.set(typeof flash === 'string' ? flash : null);

    toObservable(this.query)
      .pipe(
        tap(() => {
          this.loading.set(true);
          this.loadError.set(null);
        }),
        switchMap((query) =>
          this.service.list(query).pipe(
            catchError((error: unknown) => {
              this.loadError.set(apiErrorMessage(error, 'No se pudo cargar el listado de usuarios.'));
              return of(null);
            }),
          ),
        ),
        takeUntilDestroyed(),
      )
      .subscribe((page) => {
        this.loading.set(false);

        if (page) {
          this.page.set(page);
        }
      });

    this.search.valueChanges
      .pipe(debounceTime(300), distinctUntilChanged(), takeUntilDestroyed())
      .subscribe((q) => this.query.set({ q, page: 1 }));
  }

  protected isSelf(user: User): boolean {
    return this.auth.user()?.id === user.id;
  }

  protected goToPage(page: number): void {
    this.query.update((query) => ({ ...query, page }));
  }

  protected reload(): void {
    this.query.update((query) => ({ ...query }));
  }

  protected toggleActive(user: User): void {
    this.actionError.set(null);
    this.flash.set(null);

    this.service.setActive(user.id, !user.active).subscribe({
      next: (updated) => {
        this.flash.set(updated.active ? `Se activó la cuenta de ${updated.fullName}.` : `Se desactivó la cuenta de ${updated.fullName}.`);
        this.reload();
      },
      error: (error: unknown) => this.actionError.set(apiErrorMessage(error, 'No se pudo cambiar el estado del usuario.')),
    });
  }

  protected passwordReset(user: User): void {
    this.resetting.set(null);
    this.actionError.set(null);
    this.flash.set(`Se restableció la contraseña de ${user.fullName}.`);
  }

  protected askDelete(user: User): void {
    this.deleteError.set(null);
    this.deleting.set(user);
  }

  protected cancelDelete(): void {
    if (!this.deleteBusy()) {
      this.deleting.set(null);
    }
  }

  protected confirmDelete(): void {
    const user = this.deleting();

    if (!user) {
      return;
    }

    this.deleteBusy.set(true);
    this.deleteError.set(null);

    this.service.delete(user.id).subscribe({
      next: () => {
        this.deleteBusy.set(false);
        this.deleting.set(null);
        this.flash.set(`Se eliminó a ${user.fullName}. Sus tarjetas pasaron a tu cuenta.`);

        // Si era el último elemento de la página, se retrocede una.
        const current = this.query().page;
        const wasLastOnPage = (this.page()?.items.length ?? 0) <= 1 && current > 1;
        this.goToPage(wasLastOnPage ? current - 1 : current);
      },
      error: (error: unknown) => {
        this.deleteBusy.set(false);
        this.deleteError.set(apiErrorMessage(error, 'No se pudo eliminar al usuario.'));
      },
    });
  }
}
