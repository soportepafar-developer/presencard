import { HttpClient } from '@angular/common/http';
import { Injectable, computed, inject, signal } from '@angular/core';
import { Observable, catchError, finalize, map, of, tap } from 'rxjs';
import { User } from '../shared/models/user';
import { API_BASE } from './api';

@Injectable({ providedIn: 'root' })
export class AuthService {
  private readonly http = inject(HttpClient);
  private readonly currentUser = signal<User | null>(null);
  /** Evita consultar /api/me en cada navegación una vez que ya se sabe que no hay sesión. */
  private sessionChecked = false;

  readonly user = this.currentUser.asReadonly();
  readonly isAuthenticated = computed(() => this.currentUser() !== null);
  readonly isAdmin = computed(() => this.currentUser()?.role === 'ROLE_ADMIN');

  login(email: string, password: string): Observable<User> {
    return this.http.post<User>(`${API_BASE}/login`, { email, password }).pipe(
      tap((user) => {
        this.currentUser.set(user);
        this.sessionChecked = true;
      }),
    );
  }

  logout(): Observable<void> {
    return this.http.post<void>(`${API_BASE}/logout`, {}).pipe(finalize(() => this.clear()));
  }

  /** Recupera la sesión del servidor (cookie) al cargar la app o al refrescar la página. */
  restoreSession(): Observable<boolean> {
    if (this.currentUser()) {
      return of(true);
    }

    if (this.sessionChecked) {
      return of(false);
    }

    return this.http.get<User>(`${API_BASE}/me`).pipe(
      tap((user) => this.currentUser.set(user)),
      map(() => true),
      catchError(() => of(false)),
      tap(() => (this.sessionChecked = true)),
    );
  }

  /** Refresca los datos locales tras editar el perfil (el nombre aparece en la cabecera). */
  setUser(user: User): void {
    this.currentUser.set(user);
  }

  /** Olvida la sesión local (logout o 401 del servidor). */
  clear(): void {
    this.currentUser.set(null);
    this.sessionChecked = true;
  }
}
