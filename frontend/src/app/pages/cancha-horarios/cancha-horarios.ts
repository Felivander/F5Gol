import { Component, OnInit, computed, inject, input, signal } from '@angular/core';
import { toSignal } from '@angular/core/rxjs-interop';
import { AbstractControl, FormBuilder, ReactiveFormsModule, ValidationErrors, Validators } from '@angular/forms';
import { Router, RouterLink } from '@angular/router';
import { Cancha, GenerarTurnos, RespuestaGenerarTurnos } from '../../models';
import { Icono } from '../../components/icono/icono';
import { Api } from '../../services/api';
import { Auth } from '../../services/auth';

const MAXIMO_DIAS = 90;

// Días en el orden en que se muestran (lunes primero), con el número que usa la API (0 = domingo)
const DIAS = [
  { numero: 1, corto: 'Lun', largo: 'lunes' },
  { numero: 2, corto: 'Mar', largo: 'martes' },
  { numero: 3, corto: 'Mié', largo: 'miércoles' },
  { numero: 4, corto: 'Jue', largo: 'jueves' },
  { numero: 5, corto: 'Vie', largo: 'viernes' },
  { numero: 6, corto: 'Sáb', largo: 'sábado' },
  { numero: 0, corto: 'Dom', largo: 'domingo' }
];

function aTexto(fecha: Date): string {
  const mes = String(fecha.getMonth() + 1).padStart(2, '0');
  const dia = String(fecha.getDate()).padStart(2, '0');
  return `${fecha.getFullYear()}-${mes}-${dia}`;
}

function sumarDias(fecha: Date, dias: number): Date {
  const copia = new Date(fecha);
  copia.setDate(copia.getDate() + dias);
  return copia;
}

function minutos(hora: string): number {
  const [h, m] = hora.split(':').map(Number);
  return h * 60 + m;
}

// Validadores propios del formulario completo (comparan varios campos entre sí)
function rangoDeFechas(grupo: AbstractControl): ValidationErrors | null {
  const { desde, hasta } = grupo.value as { desde: string; hasta: string };
  if (!desde || !hasta) {
    return null;
  }
  if (hasta < desde) {
    return { hastaAntesQueDesde: true };
  }
  const dias = (new Date(`${hasta}T00:00`).getTime() - new Date(`${desde}T00:00`).getTime()) / 86_400_000;
  return dias >= MAXIMO_DIAS ? { rangoMuyLargo: true } : null;
}

function rangoDeHoras(grupo: AbstractControl): ValidationErrors | null {
  const { hora_desde, hora_hasta } = grupo.value as { hora_desde: string; hora_hasta: string };
  return hora_desde && hora_hasta && hora_hasta <= hora_desde ? { horaFinAntes: true } : null;
}

function alMenosUnDia(grupo: AbstractControl): ValidationErrors | null {
  const dias = grupo.value as Record<string, boolean>;
  return Object.values(dias).some(Boolean) ? null : { sinDias: true };
}

// Carga en lote de los horarios de una cancha: /canchas/:id/horarios (solo el dueño del club)
@Component({
  selector: 'app-cancha-horarios',
  imports: [ReactiveFormsModule, RouterLink, Icono],
  templateUrl: './cancha-horarios.html',
  styleUrl: './cancha-horarios.css'
})
export class CanchaHorarios implements OnInit {
  private api = inject(Api);
  private auth = inject(Auth);
  private router = inject(Router);
  private fb = inject(FormBuilder);

  id = input.required<string>();

  protected dias = DIAS;
  protected hoy = aTexto(new Date());

  form = this.fb.nonNullable.group({
    desde: [this.hoy, Validators.required],
    hasta: [aTexto(sumarDias(new Date(), 29)), Validators.required],
    dias: this.fb.nonNullable.group(
      Object.fromEntries(DIAS.map(d => [String(d.numero), true])),
      { validators: alMenosUnDia }
    ),
    hora_desde: ['15:00', Validators.required],
    hora_hasta: ['23:00', Validators.required],
    duracion: [60, Validators.required],
    cupo_maximo: [5, [Validators.required, Validators.min(1), Validators.max(11)]]
  }, { validators: [rangoDeFechas, rangoDeHoras] });

  private valores = toSignal(this.form.valueChanges, { initialValue: this.form.getRawValue() });

  cancha = signal<Cancha | null>(null);
  error = signal('');
  erroresServidor = signal<Record<string, string[]>>({});
  resultado = signal<RespuestaGenerarTurnos | null>(null);
  guardando = signal(false);
  intentoGuardar = signal(false);

  // Horarios de un día, para mostrar la vista previa ("15:00, 16:00, ...")
  horariosDelDia = computed(() => {
    const v = this.valores();
    if (!v.hora_desde || !v.hora_hasta || !v.duracion) {
      return [] as string[];
    }
    const lista: string[] = [];
    for (let inicio = minutos(v.hora_desde); inicio + v.duracion <= minutos(v.hora_hasta); inicio += v.duracion) {
      lista.push(`${String(Math.floor(inicio / 60)).padStart(2, '0')}:${String(inicio % 60).padStart(2, '0')}`);
    }
    return lista;
  });

  // Cantidad de días del rango que caen en los días de la semana elegidos
  diasDelRango = computed(() => {
    const v = this.valores();
    if (!v.desde || !v.hasta || v.hasta < v.desde || this.form.hasError('rangoMuyLargo')) {
      return 0;
    }
    const elegidos = new Set(Object.entries(v.dias ?? {}).filter(([, si]) => si).map(([n]) => Number(n)));
    let cantidad = 0;
    for (let fecha = new Date(`${v.desde}T00:00`); aTexto(fecha) <= v.hasta; fecha = sumarDias(fecha, 1)) {
      if (elegidos.has(fecha.getDay())) {
        cantidad++;
      }
    }
    return cantidad;
  });

  totalEstimado = computed(() => this.diasDelRango() * this.horariosDelDia().length);

  ngOnInit(): void {
    this.api.getCancha(Number(this.id())).subscribe({
      next: cancha => {
        if (cancha.club?.administrador_id !== this.auth.usuario()?.id) {
          this.router.navigate(['/canchas', cancha.id]);
          return;
        }
        this.cancha.set(cancha);
        this.form.controls.cupo_maximo.setValue(Math.max(1, Math.floor(cancha.capacidad_jugadores / 2)));
      },
      error: () => this.error.set('No se encontró la cancha.')
    });
  }

  // Elige un grupo de días de un toque
  elegirDias(numeros: number[]): void {
    const valores = Object.fromEntries(DIAS.map(d => [String(d.numero), numeros.includes(d.numero)]));
    this.form.controls.dias.setValue(valores);
  }

  // Mensaje de error del formulario o del servidor para un campo
  errorDe(campo: 'desde' | 'hasta' | 'dias' | 'hora_hasta' | 'cupo_maximo'): string {
    const servidor = this.erroresServidor()[campo]?.[0];
    if (servidor) {
      return servidor;
    }
    if (!this.intentoGuardar() && !this.form.controls[campo].touched) {
      return '';
    }
    switch (campo) {
      case 'desde':
        return this.form.value.desde && this.form.value.desde < this.hoy ? 'La fecha de inicio no puede ser anterior a hoy.' : '';
      case 'hasta':
        if (this.form.hasError('hastaAntesQueDesde')) {
          return 'La fecha de fin tiene que ser igual o posterior a la de inicio.';
        }
        return this.form.hasError('rangoMuyLargo') ? `Se pueden cargar hasta ${MAXIMO_DIAS} días por vez.` : '';
      case 'dias':
        return this.form.controls.dias.hasError('sinDias') ? 'Elegí al menos un día de la semana.' : '';
      case 'hora_hasta':
        return this.form.hasError('horaFinAntes') ? 'La hora de fin tiene que ser posterior a la de inicio.' : '';
      case 'cupo_maximo':
        return this.form.controls.cupo_maximo.invalid ? 'El cupo tiene que ser entre 1 y 11 jugadores.' : '';
    }
  }

  generar(): void {
    this.intentoGuardar.set(true);
    this.form.markAllAsTouched();
    const cancha = this.cancha();
    if (!cancha || this.form.invalid || this.errorDe('desde') || this.horariosDelDia().length === 0) {
      return;
    }

    const v = this.form.getRawValue();
    const datos: GenerarTurnos = {
      cancha_id: cancha.id,
      desde: v.desde,
      hasta: v.hasta,
      dias: Object.entries(v.dias).filter(([, si]) => si).map(([n]) => Number(n)),
      hora_desde: v.hora_desde,
      hora_hasta: v.hora_hasta,
      duracion: Number(v.duracion),
      cupo_maximo: v.cupo_maximo
    };

    this.guardando.set(true);
    this.error.set('');
    this.erroresServidor.set({});
    this.resultado.set(null);

    this.api.generarTurnos(datos).subscribe({
      next: respuesta => {
        this.resultado.set(respuesta);
        this.guardando.set(false);
      },
      error: err => {
        this.erroresServidor.set(err.error?.errors ?? {});
        this.error.set(err.status === 422 ? 'Revisá los datos marcados.' : (err.error?.message ?? 'No se pudieron crear los turnos.'));
        this.guardando.set(false);
      }
    });
  }
}
