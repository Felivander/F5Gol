import { ChangeDetectionStrategy, Component, computed, input } from '@angular/core';
import { textoTipo } from '../../formato';

// Dibujo plano de una cancha de fútbol 5. Se usa cuando la cancha no tiene fotos.
@Component({
  selector: 'app-cancha-diagrama',
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './cancha-diagrama.html',
  styleUrl: './cancha-diagrama.css'
})
export class CanchaDiagrama {
  tipo = input<string>('Fútbol 5');
  mini = input<boolean>(false);

  protected textoTipo = computed(() => textoTipo(this.tipo()));
}
