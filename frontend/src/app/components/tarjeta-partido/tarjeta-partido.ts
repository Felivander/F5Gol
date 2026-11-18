import { ChangeDetectionStrategy, Component, computed, inject, input, signal } from '@angular/core';
import { Router, RouterLink } from '@angular/router';
import { fechaRelativa, partesFecha, pesos } from '../../formato';
import { Equipo, PartidoAbierto, textoSuperficie } from '../../models';
import { Auth } from '../../services/auth';
import { CanchaLugares, LugarPartido } from '../cancha-lugares/cancha-lugares';
import { ModalSumarse, PartidoParaSumarse } from '../modal-sumarse/modal-sumarse';
import { Icono } from '../icono/icono';

// Tarjeta de un partido de matchmaking que se está armando (lobby e inicio).
// Muestra la cancha con los lugares de cada equipo: tocando uno libre se abre el modal para sumarse ahí.
@Component({
  selector: 'app-tarjeta-partido',
  imports: [RouterLink, Icono, CanchaLugares, ModalSumarse],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './tarjeta-partido.html',
  styleUrl: './tarjeta-partido.css'
})
export class TarjetaPartido {
  private router = inject(Router);
  protected auth = inject(Auth);

  partido = input.required<PartidoAbierto>();

  protected fecha = computed(() => partesFecha(this.partido().fecha));
  protected cuando = computed(() => fechaRelativa(this.partido().fecha));
  protected superficie = computed(() => textoSuperficie(this.partido().cancha.superficie));
  protected precio = computed(() => pesos(this.partido().precio_por_jugador));
  protected casiCompleto = computed(() => this.partido().faltan === 1);

  // Lugares de cada equipo para el dibujo: los ocupados sin nombre (el lobby es público)
  protected equipoA = computed(() => this.lugares('A'));
  protected equipoB = computed(() => this.lugares('B'));

  // Datos para el modal de sumarse (null = cerrado) y el equipo que se tocó
  modal = signal<PartidoParaSumarse | null>(null);
  equipoModal = signal<Equipo>('A');

  private lugares(equipo: Equipo): LugarPartido[] {
    const p = this.partido();
    const e = p.equipos[equipo];
    // En el equipo del usuario, el primer lugar ocupado se marca como suyo
    return Array.from({ length: e.cupo }, (_, i) => {
      if (i >= e.anotados) {
        return { nombre: null, soyYo: false, amigo: false };
      }
      const soyYo = p.mi_equipo === equipo && i === 0;
      return { nombre: soyYo ? 'Vos' : 'Ocupado', soyYo, amigo: !soyYo };
    });
  }

  // Tocar un lugar libre (o "Sumar amigos"): abre el modal con ese equipo elegido
  abrirModal(equipo?: Equipo) {
    if (!this.auth.usuario()) {
      this.router.navigate(['/login']);
      return;
    }
    const p = this.partido();
    this.equipoModal.set(equipo ?? p.mi_equipo ?? (p.equipos.B.faltan > p.equipos.A.faltan ? 'B' : 'A'));
    this.modal.set({
      turnoId: p.turno_id,
      canchaId: p.cancha.id,
      fecha: p.fecha,
      cuando: this.cuando(),
      horaInicio: p.hora_inicio,
      equipos: p.equipos,
      precioPorJugador: p.precio_por_jugador,
      yaAnotado: p.estoy_anotado
    });
  }

  // Después de reservar se va a la sala del partido
  alSumarse() {
    const id = this.partido().turno_id;
    this.modal.set(null);
    this.router.navigate(['/partidos', id]);
  }
}
