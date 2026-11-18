import { ChangeDetectionStrategy, Component, computed, inject, input, signal } from '@angular/core';
import { Router, RouterLink } from '@angular/router';
import { fechaRelativa, partesFecha, pesos } from '../../formato';
import { Equipo, PartidoAbierto, textoSuperficie } from '../../models';
import { Api } from '../../services/api';
import { Auth } from '../../services/auth';
import { CanchaLugares, LugarPartido } from '../cancha-lugares/cancha-lugares';
import { Icono } from '../icono/icono';

// Tarjeta de un partido de matchmaking que se está armando (lobby e inicio).
// Muestra la cancha con los lugares de cada equipo: tocando uno libre, el jugador se suma ahí.
@Component({
  selector: 'app-tarjeta-partido',
  imports: [RouterLink, Icono, CanchaLugares],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './tarjeta-partido.html',
  styleUrl: './tarjeta-partido.css'
})
export class TarjetaPartido {
  private api = inject(Api);
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

  sumando = signal(false);
  error = signal('');

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

  // Tocar un lugar libre: si ya está anotado, suma un amigo; si no, se suma él
  sumarseEn(equipo: Equipo) {
    const usuario = this.auth.usuario();
    if (!usuario) {
      this.router.navigate(['/login']);
      return;
    }
    const p = this.partido();
    const quien = p.estoy_anotado ? 'un amigo' : 'vos';
    if (!confirm(`¿Sumar a ${quien} al Equipo ${equipo} del partido de ${this.cuando().toLowerCase()} a las ${p.hora_inicio}? Son ${this.precio()} en el club.`)) {
      return;
    }
    this.sumando.set(true);
    this.error.set('');
    this.api.crearReserva({ turno_id: p.turno_id, usuario_id: usuario.id, tipo: 'individual', equipo, cantidad_jugadores: 1 }).subscribe({
      next: () => this.router.navigate(['/partidos', p.turno_id]),
      error: err => {
        // 409: el lugar se ocupó mientras tanto o el partido ya empezó
        this.error.set(err.error?.message ?? 'No se pudo sumar al partido.');
        this.sumando.set(false);
      }
    });
  }
}
