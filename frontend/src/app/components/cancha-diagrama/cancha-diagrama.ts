import { Component, input } from '@angular/core';
import { CommonModule, UpperCasePipe } from '@angular/common';

@Component({
  selector: 'app-cancha-diagrama',
  standalone: true,
  imports: [CommonModule, UpperCasePipe],
  templateUrl: './cancha-diagrama.html',
  styleUrl: './cancha-diagrama.css'
})
export class CanchaDiagrama {
  nombre = input<string>('');
  tipo = input<string>('Fútbol 5');
  mostrarJugadores = input<boolean>(false);
  mini = input<boolean>(false);
}
