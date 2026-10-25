# Avance de implementación

Este documento describe código existente. No representa el cierre del roadmap de dos semanas.

## Implementado

| Área | Estado |
|---|---|
| Base PHP + conexión MySQL | PDO, consultas preparadas, utf8mb4 y zona horaria de la aplicación |
| Acceso | Login, hash de contraseña, sesión regenerada, logout y limitación de intentos |
| Navegación | Estructura compartida, menú activo e interfaz adaptable |
| Inicio | Conteos y citas consultados en la base |
| Clientes | Listar, buscar, paginar, crear y editar; validación y confirmación |
| Agenda | Consultar citas por fecha y visualizar estado/servicio/profesional |
| Instalación | Esquema inicial, administrador configurable y ejemplos optativos |

## Verificado en esta PC

- PHP 8.4.26: sintaxis de los archivos y 15 pruebas unitarias/renderizado.
- MySQL 8.0.46: instalación en una instancia aislada del proyecto, puerto 3308. No se modificó la instancia habitual del equipo.
- 18 pruebas HTTP/MySQL: login, CSRF, alta, persistencia, edición, búsqueda, HTML escapado, fechas, cliente inexistente, cierre de sesión y denegación de escritura a un profesional.
- Interfaz revisada en navegador: acceso, inicio, listado y formulario. Distribución de escritorio y adaptación al panel estrecho.
- Docker configurado para PHP 8.4 + MySQL 8.4; no ejecutado en esta PC porque Docker no está instalado.

Los clientes y citas precargados son ficticios. Los registros temporales creados por las pruebas automáticas se limpian al terminar.

## Para abrir el entorno local preparado

Abrir http://127.0.0.1:8000. Credenciales en las variables ADMIN_EMAIL y ADMIN_PASSWORD de `.env` (archivo privado, ignorado por Git).

Si se reinició la PC:

```powershell
powershell -File bin/start-local.ps1
```

En otra PC seguir README.md. Los ejecutables y la base local en `.tools/` no forman parte del código a compartir. El script local presupone la ruta de MySQL de esta PC.

## Próximo bloque

1. Decidir continuidad de arquitectura: pasar a Laravel antes de crecer o mantener este monolito PHP.
2. Ficha de cliente con citas y operaciones relacionadas.
3. Alta/edición/cancelación de citas y disponibilidad transaccional.
4. Catálogo y gestión de empleados.
5. Ventas, pagos y caja siguiendo el roadmap y sus reglas de conciliación.

La agenda actual tiene una sola relación de servicio por cita y usa usuarios como empleados. Son simplificaciones de esta primera entrega y requieren migraciones antes de soportar los flujos completos del diseño.

## Incremento de empleados y perfil (11/10/2026)

Implementado en `feature/09-empleados-perfil`: listado con búsqueda/paginación, alta, edición, rol, color en agenda, activación/desactivación y perfil propio limitado. Permisos PHP, CSRF, consultas preparadas y transacciones; bloqueo del último administrador con prueba concurrente. Migración 009 aplicada en la base local, sin cambiar las relaciones de citas ni eliminar historial.

Detalle de archivos, migración, contrato con la rama de base de datos y aceptación pendiente: [docs/EMPLEADOS.md](docs/EMPLEADOS.md). La dependencia #3 sigue abierta y propone cambiar la FK de citas; #4 sigue abierta. Trello no pudo consultarse (401), y el navegador automatizado está bloqueado por `setup refresh had errors`.

### Verificación retomada tras reinicio

Terminal y navegador funcionando. Revisión visual de empleados/perfil realizada en escritorio y móvil; corregido el desbordamiento de formularios y barra superior. Las 80 pruebas de #9 vuelven a pasar sobre `58ec661`, que incluye los avances posteriores de #10. Trello consultado: la tarjeta de empleados sigue en **En desarrollo**. No se cambió su estado ni se cerró el issue. Ver `docs/EMPLEADOS.md`.

## Revisión del avance #10 — 12/10/2026

Corregidos formularios bloqueados por CSP, redirecciones del perfil, guardados parciales y validación de disponibilidad. Pruebas de horarios trasladadas a una base temporal aislada. Total verificado: 121 pruebas (15 + 18 + 47 + 41). Detalles y pendientes de integración con turnos en [docs/HORARIOS.md](docs/HORARIOS.md). Sin nuevas migraciones; cambios locales en `feature/10-horarios-vacaciones-feriados`.

## 13 de octubre de 2026 — integración del modelo

- Publicados PR #35 (empleados, base develop) y #36 (horarios, base empleados).
- Rama `codex/integracion-modelo-empleados-citas`: merge de `feature/02-CompletarBD` 83fa2c3, conservando autoría, auditoría y disponibilidad.
- Citas vinculadas a employees.id y detalle appointment_services; agenda/inicio, instalador, demo y triggers adaptados.
- Migración local aplicada con servidor detenido y respaldo privado en `.tools/backups/model-20261013-032106.sql`. Las 3 citas conservaron IDs, fechas, estados, notas y creación; se generaron sus 3 detalles.
- 138 verificaciones aprobadas: 15 unitarias/renderizado, 18 HTTP, 47 empleados, 41 horarios y 17 de migración/instalación nueva. Sintaxis PHP revisada en los archivos modificados.
- Contraseña del administrador local renovada con autorización; valor privado en `.env` y hash actualizado. Credencial retirada de la documentación vigente.
- Ver `docs/MODELO-DATOS.md` antes de actualizar otra base. Crear/editar citas y resolver solapamientos sigue pendiente en #12. Issues y Trello no se cerraron.

## 15 de octubre de 2026 — tareas y primer incremento de turnos

- GitHub #12 y #11 asignados a DeMonasterio. Tarjetas correspondientes de Trello asignadas a Juan; turnos movida a En desarrollo y vistas conservada en backlog.
- Nuevo issue #38 para descansos/jornada partida: el modelo actual solo admite horario continuo.
- Nuevo formulario de alta/edición/cancelación con servicios múltiples, duración calculada, permisos, CSRF, historial, auditoría y control de formularios obsoletos.
- 43 pruebas aisladas nuevas, incluida concurrencia con dos procesos; 138 pruebas previas aprobadas. Total: 181.
- Respaldo local `.tools/backups/model-20261015-113100.sql`; tabla de auditoría instalada. Historial local de 3 citas intacto. Formulario revisado en navegador de escritorio.
- Entrega en borrador: descansos y revisión del equipo pendientes. #12 permanece abierto. Detalles en docs/TURNOS.md.

## 18 de octubre de 2026 — descansos y jornada partida (#38)

- Rama `codex/38-descansos-jornada-partida`, desde develop con #35, #36, #37 y #39 integrados.
- Descanso opcional por empleado y día; horario continuo si ambos campos están vacíos. Validación de límites, permisos y guardado semanal atómico.
- Turnos que cruzan el descanso se rechazan al crear/reprogramar/reabrir; límites adyacentes permitidos. Historial conservado.
- Migración repetible aplicada localmente tras respaldo `.tools/backups/model-20261018-210935.sql`; tres citas intactas.
- 210 pruebas aprobadas: 15 unitarias, 47 empleados, 59 horarios, 51 turnos, 20 migración y 18 HTTP. Incluye proceso concurrente que espera un cambio de descanso y rechaza la reserva.
- Revisión visual pendiente por bloqueo de acceso del navegador. Pruebas HTTP de campos y permisos aprobadas. Procedimiento en docs/DESCANSOS.md.
