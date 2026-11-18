import { HttpErrorResponse, HttpInterceptorFn } from '@angular/common/http';
import { inject } from '@angular/core';
import { Router } from '@angular/router';
import { catchError, throwError } from 'rxjs';
import { Auth } from './auth';

// Agrega "Authorization: Bearer <token>" y "Accept: application/json" a cada pedido a la API.
// Si la API responde 401 a un pedido que llevaba token (el token venció o lo revocaron),
// cierra la sesión local y manda a /login avisando que la sesión terminó.
export const authInterceptor: HttpInterceptorFn = (req, next) => {
  const auth = inject(Auth);
  const router = inject(Router);
  const token = auth.token();

  const headers: Record<string, string> = { Accept: 'application/json' };
  if (token) {
    headers['Authorization'] = `Bearer ${token}`;
  }

  return next(req.clone({ setHeaders: headers })).pipe(
    catchError((error: unknown) => {
      // Sin token, un 401 es solo "credenciales incorrectas" en el login: no hay sesión que cerrar.
      // El logout ya limpia la sesión por su cuenta.
      const sesionVencida = error instanceof HttpErrorResponse
        && error.status === 401
        && !!token
        && !req.url.endsWith('/logout');

      if (sesionVencida) {
        auth.limpiarSesion();
        router.navigate(['/login'], { queryParams: { sesion: 'vencida' } });
      }
      return throwError(() => error);
    })
  );
};
