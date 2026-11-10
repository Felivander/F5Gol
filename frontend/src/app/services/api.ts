import { HttpClient, HttpParams } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { Cancha, CanchaInput, Club, NuevaReserva, Paginado, Reserva, RespuestaMensaje, Turno } from '../models';

// En desarrollo, ng serve redirige /api a Laravel (ver proxy.conf.json)
const API = '/api';

@Injectable({ providedIn: 'root' })
export class Api {
  private http = inject(HttpClient);

  // buscar filtra por nombre o dirección del club
  getClubs(page = 1, buscar = '') {
    let params = new HttpParams().set('page', page);
    if (buscar) {
      params = params.set('buscar', buscar);
    }
    return this.http.get<Paginado<Club>>(`${API}/clubs`, { params });
  }

  getClub(id: number) {
    return this.http.get<Club>(`${API}/clubs/${id}`);
  }

  getCancha(id: number) {
    return this.http.get<Cancha>(`${API}/canchas/${id}`);
  }

  crearCancha(cancha: CanchaInput) {
    return this.http.post<{ message: string; cancha: Cancha }>(`${API}/canchas`, cancha);
  }

  actualizarCancha(id: number, cancha: Partial<CanchaInput>) {
    return this.http.put<{ message: string; cancha: Cancha }>(`${API}/canchas/${id}`, cancha);
  }

  subirFotoCancha(id: number, foto: File) {
    const datos = new FormData();
    datos.append('foto', foto);
    return this.http.post<{ message: string; cancha: Cancha }>(`${API}/canchas/${id}/fotos`, datos);
  }

  borrarFotoCancha(id: number, indice: number) {
    return this.http.delete<{ message: string; cancha: Cancha }>(`${API}/canchas/${id}/fotos/${indice}`);
  }

  // Soft delete: la cancha queda en "eliminadas" y se puede restaurar
  eliminarCancha(id: number) {
    return this.http.delete<RespuestaMensaje>(`${API}/canchas/${id}`);
  }

  getCanchasEliminadas(clubId: number) {
    const params = new HttpParams().set('club_id', clubId);
    return this.http.get<Paginado<Cancha>>(`${API}/canchas/eliminadas`, { params });
  }

  restaurarCancha(id: number) {
    return this.http.put<RespuestaMensaje>(`${API}/canchas/${id}/restore`, {});
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

  actualizarReserva(id: number, cambios: Partial<Pick<Reserva, 'turno_id' | 'cantidad_jugadores' | 'estado_pago'>>) {
    return this.http.put<{ message: string; reserva: Reserva }>(`${API}/reservas/${id}`, cambios);
  }

  cancelarReserva(id: number) {
    return this.http.delete<{ message: string }>(`${API}/reservas/${id}`);
  }
}
