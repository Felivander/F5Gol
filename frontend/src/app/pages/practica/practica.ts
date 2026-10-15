import { Component } from '@angular/core';
import { FormsModule } from '@angular/forms';

@Component({
  selector: 'app-practica',
  imports: [FormsModule],
  templateUrl: './practica.html',
  styleUrl: './practica.css'
})
export class Practica {
  // Prueba 1: interpolación
  nombre = 'Pablo';

  // Prueba 6: listas con @for
  alumnos = [
    'Pablo',
    'Juan',
    'María',
    'Carlos'
  ];

  // Pruebas 2 y 3: evento click enviando un valor al método
  cambiarNombre(nombreNuevo: string) {
    this.nombre = nombreNuevo;
  }

  // Prueba 4: input con ngModel
  saludar() {
    console.log('Nombre:', this.nombre);
  }
}
