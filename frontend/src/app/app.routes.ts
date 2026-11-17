import { Routes } from '@angular/router';
import { administradorGuard, sesionGuard } from './guards/auth.guard';
import { AuthGoogle } from './pages/auth-google/auth-google';
import { CanchaForm } from './pages/cancha-form/cancha-form';
import { CanchaTurnos } from './pages/cancha-turnos/cancha-turnos';
import { ClubDetalle } from './pages/club-detalle/club-detalle';
import { ClubForm } from './pages/club-form/club-form';
import { Clubes } from './pages/clubes/clubes';
import { Login } from './pages/login/login';
import { MisClubes } from './pages/mis-clubes/mis-clubes';
import { MisReservas } from './pages/mis-reservas/mis-reservas';
import { PanelAdmin } from './pages/panel-admin/panel-admin';
import { Practica } from './pages/practica/practica';
import { Registro } from './pages/registro/registro';

export const routes: Routes = [
  { path: '', redirectTo: 'clubes', pathMatch: 'full' },
  { path: 'clubes', component: Clubes },
  // Las rutas fijas van antes de clubes/:id, si no "nuevo" se tomaría como un id
  { path: 'clubes/nuevo', component: ClubForm, canActivate: [administradorGuard] },
  { path: 'clubes/:id/editar', component: ClubForm, canActivate: [administradorGuard] },
  { path: 'clubes/:id', component: ClubDetalle },
  { path: 'mis-clubes', component: MisClubes, canActivate: [administradorGuard] },
  { path: 'canchas/nueva', component: CanchaForm, canActivate: [administradorGuard] },
  { path: 'canchas/:id/editar', component: CanchaForm, canActivate: [administradorGuard] },
  { path: 'canchas/:id', component: CanchaTurnos },
  { path: 'mis-reservas', component: MisReservas, canActivate: [sesionGuard] },
  { path: 'panel', component: PanelAdmin, canActivate: [administradorGuard] },
  { path: 'login', component: Login },
  { path: 'registro', component: Registro },
  { path: 'auth/google', component: AuthGoogle },
  { path: 'practica', component: Practica },
  { path: '**', redirectTo: 'clubes' }
];
