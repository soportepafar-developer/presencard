import { HttpClient } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { Observable, tap } from 'rxjs';
import { ProfileInput, User } from '../shared/models/user';
import { API_BASE } from './api';
import { AuthService } from './auth.service';

/** Perfil del usuario autenticado (cualquier rol). */
@Injectable({ providedIn: 'root' })
export class ProfileService {
  private readonly http = inject(HttpClient);
  private readonly auth = inject(AuthService);

  update(input: ProfileInput): Observable<User> {
    return this.http.put<User>(`${API_BASE}/me`, input).pipe(tap((user) => this.auth.setUser(user)));
  }

  changePassword(currentPassword: string, newPassword: string): Observable<void> {
    return this.http.put<void>(`${API_BASE}/me/password`, { currentPassword, newPassword });
  }
}
