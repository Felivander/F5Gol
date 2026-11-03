import { HttpClient, HttpParams } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { Cancha, Club, NuevaReserva, Paginado, Reserva, Turno } from '../models';

// En desarrollo, ng serve redirige /api a Laravel (ver proxy.conf.json)
const API = '/api';

@Injectable({ providedIn: 'root' })
export class Api {
  private http = inject(HttpClient);

  getClubs(page = 1) {
    return this.http.get<Paginado<Club>>(`${API}/clubs`, { params: { page } });
  }

  getClub(id: number) {
    return this.http.get<Club>(`${API}/clubs/${id}`);
  }

  getCancha(id: number) {
    return this.http.get<Cancha>(`${API}/canchas/${id}`);
  }

  getTurnos(canchaId: number, fecha?: string) {
    let params = new HttpParams().set('cancha_id', canchaId);
    if (fecha) {
      params = params.set('fecha', fecha);
    }
    return this.http.get<Turno[]>(`${API}/turnos`, { params });
  }

  crearReserva(reserva: NuevaReserva) {
    return this.http.post<{ message: string; reserva: Reserva }>(`${API}/reservas`, reserva);
  }

  // Filtros de GET /api/reservas: usuario_id, cancha_id, fecha, desde, hasta, page
  getReservas(filtros: Record<string, string | number>) {
    return this.http.get<Paginado<Reserva>>(`${API}/reservas`, { params: filtros });
  }

  bloquearTurno(id: number, bloqueado: boolean) {
    return this.http.patch<{ message: string; turno: Turno }>(`${API}/turnos/${id}/bloqueo`, { bloqueado });
  }

  cancelarReserva(id: number) {
    return this.http.delete<{ message: string }>(`${API}/reservas/${id}`);
  }
}
