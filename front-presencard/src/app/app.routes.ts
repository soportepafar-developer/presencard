import { Routes } from '@angular/router';
import { adminGuard, authGuard, guestGuard } from './core/auth.guard';

export const routes: Routes = [
  {
    path: 'login',
    canActivate: [guestGuard],
    loadComponent: () => import('./features/login/login').then((m) => m.Login),
    title: 'Ingresar · Presencard',
  },
  {
    path: 'panel',
    canActivate: [authGuard],
    loadComponent: () => import('./features/panel/panel-shell').then((m) => m.PanelShell),
    children: [
      { path: '', pathMatch: 'full', redirectTo: 'cards' },
      {
        path: 'cards',
        loadComponent: () => import('./features/panel/cards-list/cards-list').then((m) => m.CardsList),
        title: 'Personas registradas · Presencard',
      },
      {
        path: 'cards/new',
        loadComponent: () => import('./features/panel/card-form/card-form').then((m) => m.CardForm),
        title: 'Nueva tarjeta · Presencard',
      },
      {
        path: 'cards/:id/edit',
        loadComponent: () => import('./features/panel/card-form/card-form').then((m) => m.CardForm),
        title: 'Editar tarjeta · Presencard',
      },
      {
        path: 'users',
        canActivate: [adminGuard],
        loadComponent: () => import('./features/panel/users/users-list/users-list').then((m) => m.UsersList),
        title: 'Usuarios · Presencard',
      },
      {
        path: 'users/new',
        canActivate: [adminGuard],
        loadComponent: () => import('./features/panel/users/user-form/user-form').then((m) => m.UserForm),
        title: 'Nuevo usuario · Presencard',
      },
      {
        path: 'users/:id/edit',
        canActivate: [adminGuard],
        loadComponent: () => import('./features/panel/users/user-form/user-form').then((m) => m.UserForm),
        title: 'Editar usuario · Presencard',
      },
      {
        path: 'profile',
        loadComponent: () => import('./features/panel/profile/profile').then((m) => m.Profile),
        title: 'Mi perfil · Presencard',
      },
    ],
  },
  {
    path: 'c/:hashId',
    loadComponent: () => import('./features/public-card/public-card').then((m) => m.PublicCard),
  },
  { path: '', pathMatch: 'full', redirectTo: 'panel' },
  {
    path: '**',
    loadComponent: () => import('./features/not-found').then((m) => m.NotFound),
    title: 'Página no encontrada · Presencard',
  },
];
