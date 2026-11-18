import { Component, input } from '@angular/core';

// Botón "Ingresar con Google". Es un link común (no HttpClient) porque el navegador
// tiene que ir hasta Google y volver: Laravel hace las redirecciones.
// En desarrollo, /api pasa por proxy.conf.json hacia Laravel.
@Component({
  selector: 'app-boton-google',
  templateUrl: './boton-google.html',
  styleUrl: './boton-google.css'
})
export class BotonGoogle {
  texto = input('Ingresar con Google');
}
