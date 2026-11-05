import { Component, inject } from '@angular/core';
import { Router, RouterLink, RouterLinkActive } from '@angular/router';
import { Auth } from '../../services/auth';

@Component({
  selector: 'app-header',
  imports: [RouterLink, RouterLinkActive],
  templateUrl: './header.html',
  styleUrl: './header.css'
})
export class Header {
  protected auth = inject(Auth);
  private router = inject(Router);

  salir() {
    this.auth.logout().subscribe({
      complete: () => this.router.navigate(['/clubes']),
      error: () => this.router.navigate(['/clubes'])
    });
  }
}
