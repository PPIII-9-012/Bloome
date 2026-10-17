# Turnos — #12

Administración y recepción pueden crear, editar y cancelar turnos desde la agenda.
Profesionales mantienen consulta; las escrituras se rechazan en PHP y dentro de
la transacción. Todos los formularios usan CSRF y funcionan sin JavaScript inline.

La duración es la suma de los servicios. PHP obtiene precio y duración desde MySQL;
no acepta importes enviados por el navegador. Al editar conserva las instantáneas
de los servicios existentes. Servicios nuevos deben estar activos. Cancelar
conserva la cita y sus detalles, libera el horario y registra el actor en
`appointment_audit`. Los demás estados ocupan el intervalo.

El guardado bloquea las cuentas en el mismo orden que la administración de
empleados, y el empleado antes de comprobar conflictos. Es un bloqueo deliberadamente
amplio para este piloto de un centro: serializa las escrituras de turnos. Las
consultas de conflictos son lecturas bloqueantes; dos procesos que reservan el
mismo horario no pueden aprobar ambos. Los intervalos adyacentes sí se permiten.
Una versión del formulario evita sobrescribir cambios de otra sesión.

Horarios, vacaciones y feriados se comprueban dentro de la transacción. Las
ediciones exclusivamente históricas conservan datos aunque el empleado ya no
atienda. Reabrir una cita cancelada o moverla vuelve a validar disponibilidad.

## Instalación y pruebas

La nueva tabla de auditoría se instala con el migrador existente, siguiendo
`docs/MODELO-DATOS.md`: respaldo, detener escrituras y ejecutar
`php bin/migrate-model.php --maintenance` con permisos de migración.

`php tests/appointments.php` crea una base temporal y un servidor aislado, prueba
HTTP y MySQL y ejecuta dos procesos PHP concurrentes. No carga citas de prueba
en la base de la aplicación. Ejecutar además las suites existentes.

## Alcance pendiente

- #38: descansos/jornada partida. El modelo actual tiene un intervalo continuo
  por empleado y día. Este requisito de la tarjeta Trello aún no está cubierto.
- #11: vistas de semana/mes y filtros, asignado como siguiente tarea.
- Catálogo y recuperación de contraseña permanecen con su responsable actual.
- El PR se entrega en borrador: no cerrar #12 ni marcar su tarjeta Hecho antes
  de resolver descansos y obtener revisión de otra persona.
