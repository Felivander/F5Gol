import { Component, DestroyRef, OnInit, computed, inject, input, signal } from '@angular/core';
import { Router, RouterLink } from '@angular/router';
import { Icono } from '../../components/icono/icono';
import { fechaRelativa, partesFecha, pesos, textoTipo } from '../../formato';
import { SalaPartido as Sala, textoSuperficie } from '../../models';
import { Api } from '../../services/api';

const REFRESCO_MS = 15_000;

// Un lugar del partido: ocupado por un jugador (o por un amigo que anotó) o libre
interface Lugar {
  nombre: string | null;
  soyYo: boolean;
  amigo: boolean;
}

// Sala de un partido de matchmaking: /partidos/:id (solo para los anotados y el dueño del club)
@Component({
  selector: 'app-sala-partido',
  imports: [RouterLink, Icono],
  templateUrl: './sala-partido.html',
  styleUrl: './sala-partido.css'
})
export class SalaPartido implements OnInit {
  private api = inject(Api);
  private router = inject(Router);

  // Id del turno, del parámetro de ruta
  id = input.required<string>();

  sala = signal<Sala | null>(null);
  cargando = signal(true);
  error = signal('');
  cancelando = signal(false);
  copiado = signal(false);

  protected textoSuperficie = textoSuperficie;
  protected textoTipo = textoTipo;
  protected pesos = pesos;

  protected fecha = computed(() => {
    const s = this.sala();
    return s ? { ...partesFecha(s.fecha), relativa: fechaRelativa(s.fecha) } : null;
  });

  // Un casillero por lugar: primero cada jugador, después sus amigos, después los libres
  protected lugares = computed<Lugar[]>(() => {
    const s = this.sala();
    if (!s) {
      return [];
    }
    const lista: Lugar[] = [];
    for (const j of s.jugadores) {
      lista.push({ nombre: j.nombre, soyYo: j.soy_yo, amigo: false });
      for (let i = 1; i < j.cantidad; i++) {
        lista.push({ nombre: `Amigo de ${j.soy_yo ? 'vos' : j.nombre}`, soyYo: j.soy_yo, amigo: true });
      }
    }
    while (lista.length < s.cupo_maximo) {
      lista.push({ nombre: null, soyYo: false, amigo: false });
    }
    return lista;
  });

  // "20/11 a las 18:00", para avisar hasta cuándo se puede cancelar
  protected cancelarHasta = computed(() => {
    const hasta = this.sala()?.mi_reserva?.cancelar_hasta;
    if (!hasta) {
      return '';
    }
    const [dia, hora] = hasta.split(' ');
    return `${fechaRelativa(dia).toLowerCase()} a las ${hora}`;
  });

  constructor() {
    // El cupo se actualiza solo mientras la sala está abierta
    const refresco = setInterval(() => {
      if (!document.hidden && !this.cancelando()) {
        this.cargar(true);
      }
    }, REFRESCO_MS);
    inject(DestroyRef).onDestroy(() => clearInterval(refresco));
  }

  ngOnInit(): void {
    this.cargar();
  }

  cargar(silencioso = false): void {
    this.api.getPartido(Number(this.id())).subscribe({
      next: sala => {
        this.sala.set(sala);
        this.error.set('');
        this.cargando.set(false);
      },
      error: err => {
        // 403: no está anotado · 404: no existe o no es de matchmaking
        if (!silencioso || err.status === 403 || err.status === 404) {
          this.sala.set(null);
          this.error.set(err.error?.message ?? 'No se pudo cargar el partido.');
        }
        this.cargando.set(false);
      }
    });
  }

  // Link a la grilla de ese día, para pasarle a quien quiera sumarse
  async copiarInvitacion(): Promise<void> {
    const s = this.sala();
    if (!s) {
      return;
    }
    const link = `${location.origin}/canchas/${s.cancha.id}?dia=${s.fecha}`;
    try {
      await navigator.clipboard.writeText(link);
      this.copiado.set(true);
      setTimeout(() => this.copiado.set(false), 2500);
    } catch {
      prompt('Copiá este link para invitar:', link);
    }
  }

  salirDelPartido(): void {
    const reserva = this.sala()?.mi_reserva;
    if (!reserva || !confirm('¿Querés bajarte de este partido? Tu lugar queda libre para otro jugador.')) {
      return;
    }
    this.cancelando.set(true);
    this.error.set('');
    this.api.cancelarReserva(reserva.id).subscribe({
      next: res => this.router.navigate(['/mis-reservas'], { state: { aviso: res.message } }),
      error: err => {
        // 409: ya está fuera del plazo de cancelación
        this.error.set(err.error?.message ?? 'No se pudo cancelar la inscripción.');
        this.cancelando.set(false);
        this.cargar(true);
      }
    });
  }
}
