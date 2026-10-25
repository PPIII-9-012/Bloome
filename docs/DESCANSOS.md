# Descansos y jornada partida — #38

En Empleados → perfil → Configuración del horario, administración puede indicar
inicio y fin de un descanso para cada día. Un descanso divide la jornada en dos
tramos de atención; por ejemplo, 09:00–18:00 con descanso 13:00–15:00 permite
reservar de 09:00 a 13:00 y de 15:00 a 18:00. Esta entrega admite **un descanso
por empleado y día**. Ambos campos vacíos significan jornada continua.

El descanso debe quedar estrictamente dentro de la jornada y terminar después
de su inicio. Solo se guarda si están presentes ambas horas. La semana completa
se valida antes de escribir; un día inválido no deja cambios parciales. Los días
sin atención eliminan las horas y el descanso. Profesionales pueden consultar
su horario, pero solo administración puede modificarlo; se mantienen CSRF y
los controles de permisos del módulo.

El turno puede terminar al comenzar el descanso o empezar al terminarlo. No
puede cruzarlo ni quedar dentro de él. La regla se aplica al crear, mover o
reabrir una cita cancelada. La duración contempla todos los servicios.

Guardar un descanso no cancela ni modifica reservas existentes. Administración
debe revisar esas citas en la agenda. Las ediciones de notas históricas siguen
permitidas. No se añade cancelación automática ni múltiples descansos diarios.

## Migración

Seguir el mantenimiento de `docs/MODELO-DATOS.md`: crear respaldo completo,
detener escrituras y ejecutar `php bin/migrate-model.php --maintenance` con
permisos de migración (`--as-root` disponible en el entorno local).

Se agregan atómicamente `break_start` y `break_end`, ambas TIME nullable, a
`employee_schedules`. Los horarios previos quedan continuos. Repetir la
migración conserva descansos ya configurados. `bin/install.php` y el migrador
de horarios también incluyen este paso. Ante un error, mantener el servicio
detenido y corregir/reintentar antes de habilitar escrituras.

## Verificación

Las suites `tests/schedule.php`, `tests/appointments.php` y
`tests/model-migration.php` cubren HTTP, límites exactos, descansos inválidos,
guardados atómicos, conservación del historial y migración repetida. Una prueba
con otro proceso PHP comprueba que la reserva espera el bloqueo del horario y
rechaza el turno al leer un descanso confirmado concurrentemente.

La revisión visual de esta entrega queda pendiente: el navegador automatizado
rechazó el acceso local por su política de URL. El renderizado y los controles
se verificaron por HTTP, sin modificar la política del navegador.
