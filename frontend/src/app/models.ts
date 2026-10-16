// Tipos que devuelve la API de Laravel (f5gol-backend)

export interface Usuario {
  id: number;
  name: string;
  rol?: string;
  // Solo viene en relaciones como el administrador de un club; el login no lo devuelve
  email?: string;
}

export interface Club {
  id: number;
  administrador_id: number;
  nombre: string;
  direccion: string | null;
  telefono: string | null;
  administrador?: Usuario;
  canchas?: Cancha[];
}

export interface Cancha {
  id: number;
  club_id: number;
  nombre: string;
  tipo: string;
  capacidad_jugadores: number;
  descripcion: string | null;
  tarifa: string;
  estado: string;
  club?: Club;
}

export interface Turno {
  id: number;
  cancha_id: number;
  fecha: string;
  hora_inicio: string;
  hora_fin: string;
  estado: 'disponible' | 'ocupado' | 'bloqueado';
  cupo_maximo: number;
  cupo_actual: number;
}

// Respuesta de paginate() de Laravel
export interface Paginado<T> {
  data: T[];
  current_page: number;
  last_page: number;
  total: number;
}

export interface LoginResponse {
  message: string;
  access_token: string;
  token_type: string;
  user: Usuario;
}
