import { Component } from '@angular/core';
import { Logo } from '../logo/logo';

@Component({
  selector: 'app-footer',
  imports: [Logo],
  templateUrl: './footer.html',
  styleUrl: './footer.css'
})
export class Footer {
  protected readonly anioActual = new Date().getFullYear();
}
