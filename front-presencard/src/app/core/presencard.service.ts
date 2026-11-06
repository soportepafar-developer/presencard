import { HttpClient, HttpParams } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { Observable } from 'rxjs';
import {
  Presencard,
  PresencardInput,
  PresencardListQuery,
  PresencardPage,
  PublicCardData,
} from '../shared/models/presencard';
import { API_BASE } from './api';

@Injectable({ providedIn: 'root' })
export class PresencardService {
  private readonly http = inject(HttpClient);
  private readonly cardsUrl = `${API_BASE}/cards`;

  list(query: PresencardListQuery): Observable<PresencardPage> {
    let params = new HttpParams().set('page', query.page);

    if (query.q.trim() !== '') {
      params = params.set('q', query.q.trim());
    }

    return this.http.get<PresencardPage>(this.cardsUrl, { params });
  }

  get(id: number): Observable<Presencard> {
    return this.http.get<Presencard>(`${this.cardsUrl}/${id}`);
  }

  create(input: PresencardInput): Observable<Presencard> {
    return this.http.post<Presencard>(this.cardsUrl, input);
  }

  update(id: number, input: PresencardInput): Observable<Presencard> {
    return this.http.put<Presencard>(`${this.cardsUrl}/${id}`, input);
  }

  delete(id: number): Observable<void> {
    return this.http.delete<void>(`${this.cardsUrl}/${id}`);
  }

  publicCard(hashId: string): Observable<PublicCardData> {
    return this.http.get<PublicCardData>(`${API_BASE}/public/cards/${encodeURIComponent(hashId)}`);
  }
}
