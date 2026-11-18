import { Component, DestroyRef, OnInit, computed, inject, input, signal } from '@angular/core';
import { Router, RouterLink } from '@angular/router';
import { CanchaLugares, LugarPartido } from '../../components/cancha-lugares/cancha-lugares';
import { Icono } from '../../components/icono/icono';
import { fechaRelativa, partesFecha, pesos, textoTipo } from '../../formato';
import { Equipo, SalaPartido as Sala, textoSuperficie } from '../../models';
import { Api } from '../../services/api';
import { Auth } from '../../services/auth';

const REFRESCO_MS = 15_000;

// Sala de un partido de matchmaking: /partidos/:id (solo para los anotados y el dueño del club)
@Component({
  selector: 'app-sala-partido',
  imports: [RouterLink, Icono, CanchaLugares],
  templateUrl: './sala-partido.html',
  styleUrl: './sala-partido.css'
})
export class SalaPartido implements OnInit {
  private api = inject(Api);
  private router = inject(Router);
  private auth = inject(Auth);

  // Id del turno, del parámetro de ruta
  id = input.required<string>();

  sala = signal<Sala | null>(null);
  cargando = signal(true);
  error = signal('');
  cancelando = signal(false);
  copiado = signal(false);
  // Mientras se suma un amigo desde la cancha
  sumando = signal(false);

  protected textoSuperficie = textoSuperficie;
  protected textoTipo = textoTipo;
  protected pesos = pesos;

  protected fecha = computed(() => {
    const s = this.sala();
    return s ? { ...partesFecha(s.fecha), relativa: fechaRelativa(s.fecha) } : null;
  });

  // Lugares de cada equipo: primero cada jugador, después sus amigos, después los libres
  protected equipoA = computed(() => this.lugaresDe('A'));
  protected equipoB = computed(() => this.lugaresDe('B'));

  private lugaresDe(equipo: Equipo): LugarPartido[] {
    const s = this.sala();
    if (!s) {
      return [];
    }
    const lista: LugarPartido[] = [];
    for (const j of s.jugadores.filter(j => j.equipo === equipo)) {
      lista.push({ nombre: j.nombre, soyYo: j.soy_yo, amigo: false });
      for (let i = 1; i < j.cantidad; i++) {
        // En la cancha la etiqueta tiene que ser corta: "de Martín"
        lista.push({ nombre: `de ${j.soy_yo ? 'vos' : j.nombre.split(' ')[0]}`, soyYo: j.soy_yo, amigo: true });
      }
    }
    while (lista.length < s.equipos[equipo].cupo) {
      lista.push({ nombre: null, soyYo: false, amigo: false });
    }
    return lista;
  }

  // Lista de cada equipo debajo de la cancha: una línea por reserva y cuántos lugares quedan
  protected planteles = computed(() => {
    const s = this.sala();
    if (!s) {
      return [];
    }
    return (['A', 'B'] as Equipo[]).map(letra => ({
      letra,
      libres: s.equipos[letra].faltan,
      lineas: s.jugadores.filter(j => j.equipo === letra).map(j => ({
        yo: j.soy_yo,
        texto: (j.soy_yo ? 'Vos' : j.nombre) + (j.tipo === 'equipo'
          ? ` · equipo completo (${j.cantidad})`
          : j.cantidad > 1 ? ` + ${j.cantidad - 1} ${j.cantidad === 2 ? 'amigo' : 'amigos'}` : '')
      }))
    }));
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

  // Tocar un lugar libre en la cancha: suma un amigo en ese equipo (una reserva más a su nombre)
  sumarAmigo(equipo: Equipo): void {
    const s = this.sala();
    const usuario = this.auth.usuario();
    if (!s || !usuario) {
      return;
    }
    const precio = pesos(s.precio_por_jugador);
    if (!confirm(`¿Sumar a un amigo al Equipo ${equipo}? Son ${precio} más en el club.`)) {
      return;
    }
    this.sumando.set(true);
    this.error.set('');
    this.api.crearReserva({ turno_id: s.turno_id, usuario_id: usuario.id, tipo: 'individual', equipo, cantidad_jugadores: 1 }).subscribe({
      next: () => {
        this.sumando.set(false);
        this.cargar(true);
      },
      error: err => {
        // 409: ese equipo se llenó mientras tanto o el partido ya empezó
        this.error.set(err.error?.message ?? 'No se pudo sumar a tu amigo.');
        this.sumando.set(false);
        this.cargar(true);
      }
    });
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
