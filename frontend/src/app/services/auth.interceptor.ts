import { HttpInterceptorFn } from '@angular/common/http';
import { inject } from '@angular/core';
import { Auth } from './auth';

// Agrega "Authorization: Bearer <token>" y "Accept: application/json" a cada pedido a la API
export const authInterceptor: HttpInterceptorFn = (req, next) => {
  const token = inject(Auth).token();
  const headers: Record<string, string> = { Accept: 'application/json' };
  if (token) {
    headers['Authorization'] = `Bearer ${token}`;
  }
  return next(req.clone({ setHeaders: headers }));
};
