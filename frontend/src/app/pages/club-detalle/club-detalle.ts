import { Component, OnInit, inject, input, signal } from '@angular/core';
import { CurrencyPipe } from '@angular/common';
import { RouterLink } from '@angular/router';
import { Club } from '../../models';
import { Api } from '../../services/api';
import { Auth } from '../../services/auth';

import { CanchaDiagrama } from '../../components/cancha-diagrama/cancha-diagrama';

@Component({
  selector: 'app-club-detalle',
  imports: [RouterLink, CurrencyPipe, CanchaDiagrama],
  templateUrl: './club-detalle.html'
})
export class ClubDetalle implements OnInit {
  private api = inject(Api);
  private auth = inject(Auth);

  // Viene del parámetro de ruta /clubes/:id
  id = input.required<string>();
  // ?mover=<id de reserva>: se está eligiendo un turno nuevo para esa reserva
  mover = input<string>();

  club = signal<Club | null>(null);
  error = signal('');

  // El administrador dueño ve los botones para cargar y editar canchas
  esDueno() {
    const usuario = this.auth.usuario();
    return !!usuario && usuario.id === this.club()?.administrador_id;
  }

  ngOnInit() {
    this.api.getClub(Number(this.id())).subscribe({
      next: club => this.club.set(club),
      error: () => this.error.set('No se encontró el club.')
    });
  }
}
