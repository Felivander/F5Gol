import { HttpClient } from '@angular/common/http';
import { Injectable, computed, inject, signal } from '@angular/core';
import { tap } from 'rxjs';
import { LoginResponse, Usuario } from '../models';

const TOKEN_KEY = 'f5gol_token';
const USER_KEY = 'f5gol_user';

@Injectable({ providedIn: 'root' })
export class Auth {
  private http = inject(HttpClient);

  readonly token = signal<string | null>(localStorage.getItem(TOKEN_KEY));
  readonly usuario = signal<Usuario | null>(JSON.parse(localStorage.getItem(USER_KEY) ?? 'null'));
  readonly logueado = computed(() => this.token() !== null);

  login(email: string, password: string) {
    return this.http.post<LoginResponse>('/api/login', { email, password }).pipe(
      tap(res => {
        localStorage.setItem(TOKEN_KEY, res.access_token);
        localStorage.setItem(USER_KEY, JSON.stringify(res.user));
        this.token.set(res.access_token);
        this.usuario.set(res.user);
      })
    );
  }

  logout() {
    localStorage.removeItem(TOKEN_KEY);
    localStorage.removeItem(USER_KEY);
    this.token.set(null);
    this.usuario.set(null);
  }
}
