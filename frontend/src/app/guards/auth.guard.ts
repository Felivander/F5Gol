import { inject } from '@angular/core';
import { CanActivateFn, Router } from '@angular/router';
import { Auth } from '../services/auth';

// Solo deja entrar con sesión iniciada; si no, manda a /login
export const sesionGuard: CanActivateFn = () => {
  const auth = inject(Auth);
  return auth.logueado() ? true : inject(Router).createUrlTree(['/login']);
};

// Solo para administradores: sin sesión manda a /login, un jugador vuelve a /clubes
export const administradorGuard: CanActivateFn = () => {
  const auth = inject(Auth);
  const router = inject(Router);
  if (!auth.logueado()) {
    return router.createUrlTree(['/login']);
  }
  return auth.usuario()?.rol === 'administrador' ? true : router.createUrlTree(['/clubes']);
};
