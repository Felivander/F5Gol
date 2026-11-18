import { HttpClient } from '@angular/common/http';
import { Injectable, computed, inject, signal } from '@angular/core';
import { finalize, tap } from 'rxjs';
import { LoginResponse, NuevoUsuario, Usuario } from '../models';

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
      tap(res => this.guardarSesion(res))
    );
  }

  // La API devuelve el token igual que el login, así que queda logueado al registrarse
  register(datos: NuevoUsuario) {
    return this.http.post<LoginResponse>('/api/register', datos).pipe(
      tap(res => this.guardarSesion(res))
    );
  }

  // Login con Google: Laravel devuelve solo el token (en la URL), así que se guarda
  // y después se piden los datos del usuario a /api/user
  iniciarConToken(token: string) {
    localStorage.setItem(TOKEN_KEY, token);
    this.token.set(token);
    return this.http.get<Usuario>('/api/user').pipe(
      tap({
        next: usuario => {
          localStorage.setItem(USER_KEY, JSON.stringify(usuario));
          this.usuario.set(usuario);
        },
        error: () => this.limpiarSesion()
      })
    );
  }

  private guardarSesion(res: LoginResponse) {
    localStorage.setItem(TOKEN_KEY, res.access_token);
    localStorage.setItem(USER_KEY, JSON.stringify(res.user));
    this.token.set(res.access_token);
    this.usuario.set(res.user);
  }

  // Revoca el token en la API y después limpia la sesión local,
  // aunque la API falle (por ejemplo, si el token ya había vencido)
  logout() {
    return this.http.post('/api/logout', {}).pipe(
      finalize(() => this.limpiarSesion())
    );
  }

  // También la usa el interceptor cuando la API responde 401 (token vencido o revocado)
  limpiarSesion() {
    localStorage.removeItem(TOKEN_KEY);
    localStorage.removeItem(USER_KEY);
    this.token.set(null);
    this.usuario.set(null);
  }
}
