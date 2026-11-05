import { HttpClient, HttpParams } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { Cancha, Club, NuevaCancha, Paginado, RespuestaCancha, RespuestaMensaje, Turno } from '../models';

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

  crearCancha(datos: NuevaCancha) {
    return this.http.post<RespuestaCancha>(`${API}/canchas`, datos);
  }

  actualizarCancha(id: number, datos: NuevaCancha) {
    return this.http.put<RespuestaCancha>(`${API}/canchas/${id}`, datos);
  }

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
}
