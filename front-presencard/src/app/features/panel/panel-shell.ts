import { ChangeDetectionStrategy, Component, computed, inject } from '@angular/core';
import { Router, RouterLink, RouterLinkActive, RouterOutlet } from '@angular/router';
import { AuthService } from '../../core/auth.service';
import { Icon } from '../../shared/ui/icon';
import { initialsOf } from '../../shared/utils/initials';

@Component({
  selector: 'app-panel-shell',
  imports: [RouterOutlet, RouterLink, RouterLinkActive, Icon],
  changeDetection: ChangeDetectionStrategy.OnPush,
  template: `
    <header class="topbar">
      <div class="topbar-inner">
        <a class="brand" routerLink="/panel/cards" aria-label="Presencard, ir a las tarjetas">
          <span class="logo">P</span>
          <span class="name">Presencard</span>
        </a>

        <nav class="nav" aria-label="Secciones del panel">
          <a routerLink="/panel/cards" routerLinkActive="active"><app-icon name="link" /> Tarjetas</a>
          @if (auth.isAdmin()) {
            <a routerLink="/panel/users" routerLinkActive="active"><app-icon name="users" /> Usuarios</a>
          }
        </nav>

        <div class="session">
          <a class="me" routerLink="/panel/profile" routerLinkActive="active" aria-label="Mi perfil">
            <span class="avatar" aria-hidden="true">{{ initials() }}</span>
            <span class="who">{{ auth.user()?.fullName }}</span>
          </a>
          <button type="button" class="btn btn-ghost" (click)="logout()" aria-label="Cerrar sesión">
            <app-icon name="logout" /><span class="label">Cerrar sesión</span>
          </button>
        </div>
      </div>
    </header>
    <main class="content">
      <router-outlet />
    </main>
  `,
  styleUrl: './panel-shell.scss',
})
export class PanelShell {
  protected readonly auth = inject(AuthService);
  private readonly router = inject(Router);

  protected readonly initials = computed(() => initialsOf(this.auth.user()?.fullName ?? ''));

  protected logout(): void {
    const goToLogin = () => void this.router.navigate(['/login']);

    this.auth.logout().subscribe({ next: goToLogin, error: goToLogin });
  }
}
