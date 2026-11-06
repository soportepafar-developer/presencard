import { HttpClient, HttpParams } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { Observable } from 'rxjs';
import { NewUserInput, User, UserInput, UserListQuery, UserPage } from '../shared/models/user';
import { API_BASE } from './api';

/** Gestión de usuarios (solo administradores). */
@Injectable({ providedIn: 'root' })
export class UserService {
  private readonly http = inject(HttpClient);
  private readonly usersUrl = `${API_BASE}/users`;

  list(query: UserListQuery): Observable<UserPage> {
    let params = new HttpParams().set('page', query.page);

    if (query.q.trim() !== '') {
      params = params.set('q', query.q.trim());
    }

    return this.http.get<UserPage>(this.usersUrl, { params });
  }

  get(id: number): Observable<User> {
    return this.http.get<User>(`${this.usersUrl}/${id}`);
  }

  create(input: NewUserInput): Observable<User> {
    return this.http.post<User>(this.usersUrl, input);
  }

  update(id: number, input: UserInput): Observable<User> {
    return this.http.put<User>(`${this.usersUrl}/${id}`, input);
  }

  resetPassword(id: number, password: string): Observable<void> {
    return this.http.put<void>(`${this.usersUrl}/${id}/password`, { password });
  }

  setActive(id: number, active: boolean): Observable<User> {
    return this.http.put<User>(`${this.usersUrl}/${id}/active`, { active });
  }

  delete(id: number): Observable<void> {
    return this.http.delete<void>(`${this.usersUrl}/${id}`);
  }
}
