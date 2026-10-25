# Revisión de horarios, vacaciones y feriados — 12/10/2026

## Estado de GitHub consultado

Sin PRs abiertos; único PR histórico: #1, integrado en main. Issues #9 y #10 asignados a DeMonasterio y abiertos. Las ramas remotas publicadas siguen siendo main, develop, feature/02-CompletarBD y feature/04-Password-recovery. Los commits locales #9 (`81aa83c`) y #10 (`58ec661`) aún no aparecen en ramas remotas.

Se trabajó sobre `feature/10-horarios-vacaciones-feriados`, preservando los cambios previos de documentación. No se publicaron commits ni se modificaron issues.

## Correcciones sobre el avance existente

- `app/schedule.php`: validación estricta de horas, tipos y descripciones; validación de toda la semana antes de escribir y transacción para evitar guardados parciales. Se rechazan fechas inexistentes, intervalos invertidos, duración cero, cruce de medianoche, usuarios inactivos y horarios sin configurar. Los límites de inicio/fin son inclusivos.
- `app/employee-routes.php`: redirecciones válidas después de guardar horarios/ausencias, comprobación de empleado existente, restricción de métodos y eliminación de ausencias ligada al usuario de destino.
- `app/schedule-routes.php`: entradas manipuladas no causan errores de tipo; fallos de validación y feriados duplicados devuelven 422 en vez de avisos de éxito.
- `views/employee-profile.php`, `views/schedule.php`, `views/holidays.php`, nuevo `views/schedule-fields.php` y `public/assets/app.css`: formularios sin scripts ni estilos inline, compatibles con CSP. Apertura mediante details/summary nativos. Campos de horas editables sin JavaScript; el servidor ignora las horas de días desmarcados. Profesional/recepción ven su disponibilidad sin acciones administrativas.
- `tests/schedule.php`: base temporal aleatoria y servidor propios, eliminados al finalizar. La suite ya no cambia el horario del centro de desarrollo ni borra feriados por coincidencias de descripción.

No hay cambio de esquema ni migración nueva. Se requiere la migración 010 existente en instalaciones previas que todavía no tengan sus tablas.

## Verificación

- `tests/run.php`: 15 pruebas.
- `tests/http.php`: 18 pruebas.
- `tests/employees.php`: 47 pruebas.
- `tests/schedule.php`: 41 pruebas, con casos de tipos manipulados, guardado parcial, intervalos inválidos, ausencia de horarios, inactividad, CSRF, permisos y redirecciones.
- Sintaxis PHP comprobada.
- Navegador: apertura de Nuevo feriado, perfil y formulario de horarios; horario revisado a 390 px sin desbordamiento horizontal.

## Pendientes reales

El CRUD de turnos #12 todavía no existe en esta rama. `checkAppointmentAvailability` es una validación disponible para integrarlo: no afirmar que evita por sí sola reservas concurrentes ni que bloquea SQL directo por feriados/ausencias. El guardado de turnos deberá comprobar disponibilidad y solapamientos dentro de la transacción y coordinar sus bloqueos con cambios de horarios. Mantener el contrato actual de IDs de usuario hasta integrar la migración de #3.

Las modificaciones de disponibilidad no cancelan ni borran citas históricas o ya programadas. Sigue pendiente decidir cómo advertir conflictos entre citas futuras existentes y nuevos cierres/ausencias. Los errores 422 presentan el motivo en una página de mensaje; la conservación de campos en el formulario puede mejorarse en una iteración posterior.

No se cerró #10: faltan integración con reservas y aceptación del equipo.
