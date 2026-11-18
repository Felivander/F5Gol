import { Component } from '@angular/core';
import { RouterOutlet } from '@angular/router';
import { Header } from './components/header/header';
import { Footer } from './components/footer/footer';
import { DialogoComponente } from './components/dialogo/dialogo';

@Component({
  selector: 'app-root',
  imports: [RouterOutlet, Header, Footer, DialogoComponente],
  templateUrl: './app.html',
  styleUrl: './app.css'
})
export class App {
}
