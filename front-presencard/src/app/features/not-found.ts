import { ChangeDetectionStrategy, Component } from '@angular/core';
import { RouterLink } from '@angular/router';

@Component({
  selector: 'app-not-found',
  imports: [RouterLink],
  changeDetection: ChangeDetectionStrategy.OnPush,
  template: `
    <main>
      <p class="code">404</p>
      <h1>Página no encontrada</h1>
      <p>La dirección que buscas no existe o fue movida.</p>
      <a class="btn btn-primary" routerLink="/panel">Ir al inicio</a>
    </main>
  `,
  styles: `
    main {
      display: grid;
      justify-items: center;
      gap: 0.75rem;
      padding: 20vh 1.5rem;
      text-align: center;
    }
    .code {
      color: var(--blue);
      font-size: 4rem;
      font-weight: 800;
      line-height: 1;
    }
    p {
      color: var(--muted);
    }
  `,
})
export class NotFound {}
