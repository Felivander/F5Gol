import { Component, OnInit, inject, input, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { RouterLink } from '@angular/router';
import { Cancha, Turno } from '../../models';
import { Api } from '../../services/api';

@Component({
  selector: 'app-cancha-turnos',
  imports: [RouterLink, FormsModule],
  templateUrl: './cancha-turnos.html'
})
export class CanchaTurnos implements OnInit {
  private api = inject(Api);

  // Viene del parámetro de ruta /canchas/:id
  id = input.required<string>();

  cancha = signal<Cancha | null>(null);
  turnos = signal<Turno[]>([]);
  fecha = '';
  cargando = signal(true);
  error = signal('');

  ngOnInit() {
    this.api.getCancha(Number(this.id())).subscribe({
      next: cancha => this.cancha.set(cancha),
      error: () => this.error.set('No se encontró la cancha.')
    });
    this.buscarTurnos();
  }

  buscarTurnos() {
    this.cargando.set(true);
    this.api.getTurnos(Number(this.id()), this.fecha).subscribe({
      next: turnos => {
        // La API ordena solo por hora; acá ordenamos por fecha y hora
        const orden = (t: Turno) => t.fecha + t.hora_inicio;
        this.turnos.set([...turnos].sort((a, b) => orden(a).localeCompare(orden(b))));
        this.cargando.set(false);
      },
      error: () => {
        this.error.set('No se pudieron cargar los turnos.');
        this.cargando.set(false);
      }
    });
  }
}
