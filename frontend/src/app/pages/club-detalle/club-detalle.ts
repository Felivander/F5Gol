import { Component, OnInit, inject, input, signal } from '@angular/core';
import { CurrencyPipe } from '@angular/common';
import { RouterLink } from '@angular/router';
import { Club } from '../../models';
import { Api } from '../../services/api';

@Component({
  selector: 'app-club-detalle',
  imports: [RouterLink, CurrencyPipe],
  templateUrl: './club-detalle.html'
})
export class ClubDetalle implements OnInit {
  private api = inject(Api);

  // Viene del parámetro de ruta /clubes/:id
  id = input.required<string>();

  club = signal<Club | null>(null);
  error = signal('');

  ngOnInit() {
    this.api.getClub(Number(this.id())).subscribe({
      next: club => this.club.set(club),
      error: () => this.error.set('No se encontró el club.')
    });
  }
}
