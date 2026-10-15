import { Routes } from '@angular/router';
import { CanchaTurnos } from './pages/cancha-turnos/cancha-turnos';
import { ClubDetalle } from './pages/club-detalle/club-detalle';
import { Clubes } from './pages/clubes/clubes';
import { Login } from './pages/login/login';
import { Practica } from './pages/practica/practica';

export const routes: Routes = [
  { path: '', redirectTo: 'clubes', pathMatch: 'full' },
  { path: 'clubes', component: Clubes },
  { path: 'clubes/:id', component: ClubDetalle },
  { path: 'canchas/:id', component: CanchaTurnos },
  { path: 'login', component: Login },
  { path: 'practica', component: Practica },
  { path: '**', redirectTo: 'clubes' }
];
