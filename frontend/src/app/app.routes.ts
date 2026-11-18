import { Routes } from '@angular/router';
import { administradorGuard, sesionGuard } from './guards/auth.guard';

// Cada página se descarga recién cuando se entra (loadComponent): el inicio carga más rápido
// en el celular, porque no baja de entrada el código del panel, los formularios, etc.
export const routes: Routes = [
  { path: '', redirectTo: 'clubes', pathMatch: 'full' },
  { path: 'clubes', loadComponent: () => import('./pages/clubes/clubes').then(m => m.Clubes) },
  { path: 'partidos', loadComponent: () => import('./pages/partidos/partidos').then(m => m.Partidos) },
  {
    path: 'partidos/:id',
    loadComponent: () => import('./pages/sala-partido/sala-partido').then(m => m.SalaPartido),
    canActivate: [sesionGuard]
  },
  // Las rutas fijas van antes de clubes/:id, si no "nuevo" se tomaría como un id
  {
    path: 'clubes/nuevo',
    loadComponent: () => import('./pages/club-form/club-form').then(m => m.ClubForm),
    canActivate: [administradorGuard]
  },
  {
    path: 'clubes/:id/editar',
    loadComponent: () => import('./pages/club-form/club-form').then(m => m.ClubForm),
    canActivate: [administradorGuard]
  },
  { path: 'clubes/:id', loadComponent: () => import('./pages/club-detalle/club-detalle').then(m => m.ClubDetalle) },
  {
    path: 'mis-clubes',
    loadComponent: () => import('./pages/mis-clubes/mis-clubes').then(m => m.MisClubes),
    canActivate: [administradorGuard]
  },
  {
    path: 'canchas/nueva',
    loadComponent: () => import('./pages/cancha-form/cancha-form').then(m => m.CanchaForm),
    canActivate: [administradorGuard]
  },
  {
    path: 'canchas/:id/editar',
    loadComponent: () => import('./pages/cancha-form/cancha-form').then(m => m.CanchaForm),
    canActivate: [administradorGuard]
  },
  {
    path: 'canchas/:id/horarios',
    loadComponent: () => import('./pages/cancha-horarios/cancha-horarios').then(m => m.CanchaHorarios),
    canActivate: [administradorGuard]
  },
  { path: 'canchas/:id', loadComponent: () => import('./pages/cancha-turnos/cancha-turnos').then(m => m.CanchaTurnos) },
  {
    path: 'mis-reservas',
    loadComponent: () => import('./pages/mis-reservas/mis-reservas').then(m => m.MisReservas),
    canActivate: [sesionGuard]
  },
  {
    path: 'panel',
    loadComponent: () => import('./pages/panel-admin/panel-admin').then(m => m.PanelAdmin),
    canActivate: [administradorGuard]
  },
  { path: 'login', loadComponent: () => import('./pages/login/login').then(m => m.Login) },
  { path: 'registro', loadComponent: () => import('./pages/registro/registro').then(m => m.Registro) },
  { path: 'auth/google', loadComponent: () => import('./pages/auth-google/auth-google').then(m => m.AuthGoogle) },
  { path: '**', redirectTo: 'clubes' }
];
