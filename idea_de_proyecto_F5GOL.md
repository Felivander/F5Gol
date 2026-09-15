# Idea de Proyecto: F5GOL

**Grupo 4** — Patricio Sandri, Felipe van der Donckt, Lautaro Meza, Julián Dufey
**Cátedra:** Gestión de Desarrollo de Software — Docente: Ignacio Mascali
**UTN Facultad Regional Concordia**

Tablero de gestión (Jira): https://f5golgrupo4.atlassian.net/jira/software/projects/KAN/boards/1

---

## Nombre del proyecto

**F5GOL** — Plataforma web de gestión de turnos y matchmaking para canchas de fútbol 5.

## Planteamiento del problema

Los complejos de fútbol 5 gestionan hoy sus reservas de forma manual (cuadernos, planillas o WhatsApp), lo que genera superposición de turnos, pérdida de tiempo administrativo y una mala experiencia para el jugador, que no puede ver la disponibilidad real fuera del horario de atención.

A esto se suma un problema propio del fútbol 5: es común que un grupo no llegue a juntar los cinco jugadores necesarios, lo que termina en cancelación del turno o en la búsqueda informal de jugadores sueltos por redes, sin garantía de completar el partido.

## Requerimientos del cliente

**Funcionales**
- Carga de canchas con fotos, descripción, capacidad y tarifa.
- Vista de disponibilidad por cancha, fecha y horario (Disponible / Ocupada).
- Reserva de cancha completa desde la web.
- Inscripción individual a un turno (matchmaking) hasta completar el cupo de 5 jugadores.
- Confirmación automática y aviso a los jugadores cuando se completa el cupo.
- Edición y cancelación de reservas propias ("Mis reservas").
- Panel de administración con las reservas del día y de la semana por cancha.
- Registro del estado de pago (pendiente / seña / pagado).
- Registro y login diferenciado para dueños de club y jugadores.
- Bloqueo manual de turnos por mantenimiento.

**No funcionales**
- Uso principal desde el celular (mobile-first).
- Evitar la doble reserva de un mismo turno.
- Respuesta ágil al consultar disponibilidad.
- Cada administrador solo gestiona sus propias canchas.
- Interfaz simple, pocos pasos para reservar.

**Restricciones**
- MVP sin pasarela de pago en línea en esta primera etapa.
- El cliente no tiene personal técnico propio → panel autoexplicativo.
- Debe ser aplicación web, sin necesidad de instalar nada.

## Objetivos

**Objetivo general**
Desarrollar F5GOL, una aplicación web que permita a dueños de complejos de fútbol 5 gestionar sus canchas y reservas, y a los jugadores reservar turnos o completar equipos mediante matchmaking, cubriendo los requerimientos relevados con el cliente.

**Objetivos específicos**
- Relevar y documentar los requerimientos funcionales y no funcionales del cliente.
- Planificar el proyecto en un tablero ágil (Jira), con historias de usuario, tareas y sprints.
- Diseñar la arquitectura y el modelo de datos que soporten la reserva completa y el matchmaking por cupos.
- Implementar primero los módulos de mayor prioridad (disponibilidad, reserva completa, matchmaking).
- Hacer seguimiento del avance por sprint y validar cada entrega con el cliente.

## Solución planteada

Aplicación web desarrollada con **Laravel 13 (PHP)** y base de datos **MySQL**, con dos roles: **Administrador** (dueño del club) y **Jugador**.

| Módulo | Qué resuelve |
|---|---|
| Gestión de canchas | Alta de canchas con fotos, descripción, capacidad y tarifa; bloqueo manual por mantenimiento. |
| Disponibilidad y reservas | Grilla de horarios por cancha en tiempo real; reserva de cancha completa; estado de pago. |
| Matchmaking | Inscripción individual a un turno; cierre y confirmación automática al llegar a 5 jugadores. |
| Mis reservas | Historial, edición y cancelación de reservas propias. |
| Panel de administración | Vista de reservas del día/semana por cancha para el club. |
| Cuentas y accesos | Registro/login por rol con autenticación y autorización de Laravel. |

**Enfoque de gestión:** metodología ágil (Scrum), con sprints cortos priorizando primero los requerimientos de "Alta" prioridad (disponibilidad, reserva completa, matchmaking), dejando pagos en línea y funciones secundarias para etapas posteriores.

**Criterios de aceptación clave**
- Un turno con cupo incompleto de matchmaking nunca se muestra como "Ocupado".
- Un turno ya reservado no puede ser tomado por otro usuario.
- El cupo de jugadores anotados se actualiza sin recargar la página.
- Una cancelación dentro de la ventana permitida libera el turno automáticamente.
