import { toObservable, takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { ChangeDetectionStrategy, Component, computed, inject, signal } from '@angular/core';
import { FormControl, ReactiveFormsModule } from '@angular/forms';
import { Router, RouterLink } from '@angular/router';
import { catchError, debounceTime, distinctUntilChanged, of, switchMap, tap } from 'rxjs';
import { apiErrorMessage } from '../../../core/api';
import { AuthService } from '../../../core/auth.service';
import { PresencardService } from '../../../core/presencard.service';
import { Presencard, PresencardListQuery, PresencardPage } from '../../../shared/models/presencard';
import { ConfirmDialog } from '../../../shared/ui/confirm-dialog';
import { Icon } from '../../../shared/ui/icon';
import { initialsOf } from '../../../shared/utils/initials';
import { ShareDialog } from '../share-dialog/share-dialog';

@Component({
  selector: 'app-cards-list',
  imports: [ReactiveFormsModule, RouterLink, Icon, ShareDialog, ConfirmDialog],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './cards-list.html',
  styleUrl: './cards-list.scss',
})
export class CardsList {
  private readonly service = inject(PresencardService);
  private readonly router = inject(Router);
  protected readonly auth = inject(AuthService);

  protected readonly search = new FormControl('', { nonNullable: true });
  protected readonly page = signal<PresencardPage | null>(null);
  protected readonly loading = signal(true);
  protected readonly loadError = signal<string | null>(null);
  protected readonly flash = signal<string | null>(null);

  protected readonly sharing = signal<Presencard | null>(null);
  protected readonly deleting = signal<Presencard | null>(null);
  protected readonly deleteBusy = signal(false);
  protected readonly deleteError = signal<string | null>(null);
  protected readonly deleteMessage = computed(
    () =>
      `¿Eliminar la tarjeta de ${this.deleting()?.fullName ?? ''}? Su enlace público dejará de funcionar y no se puede deshacer.`,
  );

  protected readonly initialsOf = initialsOf;

  /** Cargo y empresa en una línea; "Sin cargo" si no hay ninguno. */
  protected subtitle(card: Presencard): string {
    return [card.jobTitle, card.company].filter(Boolean).join(' · ') || 'Sin cargo';
  }

  /** Cambiar este valor (aunque sea el mismo contenido) dispara una nueva carga. */
  private readonly query = signal<PresencardListQuery>({ q: '', page: 1 });

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
              this.loadError.set(apiErrorMessage(error, 'No se pudo cargar el listado.'));
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

  protected goToPage(page: number): void {
    this.query.update((query) => ({ ...query, page }));
  }

  protected reload(): void {
    this.query.update((query) => ({ ...query }));
  }

  protected askDelete(card: Presencard): void {
    this.deleteError.set(null);
    this.deleting.set(card);
  }

  protected cancelDelete(): void {
    if (!this.deleteBusy()) {
      this.deleting.set(null);
    }
  }

  protected confirmDelete(): void {
    const card = this.deleting();

    if (!card) {
      return;
    }

    this.deleteBusy.set(true);
    this.deleteError.set(null);

    this.service.delete(card.id).subscribe({
      next: () => {
        this.deleteBusy.set(false);
        this.deleting.set(null);
        this.flash.set(`Se eliminó la tarjeta de ${card.fullName}.`);

        // Si era el último elemento de la página, se retrocede una.
        const current = this.query().page;
        const wasLastOnPage = (this.page()?.items.length ?? 0) <= 1 && current > 1;
        this.goToPage(wasLastOnPage ? current - 1 : current);
      },
      error: (error: unknown) => {
        this.deleteBusy.set(false);
        this.deleteError.set(apiErrorMessage(error, 'No se pudo eliminar la tarjeta.'));
      },
    });
  }
}
