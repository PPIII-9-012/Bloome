# Altas contextuales · avance de #13

Referencia: https://trello.com/c/WB09g0lE

Desde un turno nuevo o existente, «Crear cliente y volver al turno» reutiliza el formulario de clientes y conserva empleado, fecha/hora, servicios, estado, notas y versión original. El cliente creado queda seleccionado; el turno se reserva únicamente al guardarlo. Cancelar el alta vuelve al borrador sin crear un cliente. La agenda transmite fecha, vista y filtros; guardar, volver o cancelar un turno conserva ese contexto.

Los borradores son privados de la sesión, expiran después de una hora y se limitan a diez. Cerrar sesión los descarta. Guardar el turno consume su borrador. Se mantienen CSRF, roles y comprobación de versión para rechazar cambios concurrentes. Reenviar el formulario contextual después de crear el cliente devuelve al turno sin duplicarlo. Los parámetros de retorno se limitan a la agenda; no se aceptan URLs externas.

Validación: 21 pruebas unitarias, 78 de turnos HTTP/MySQL y 18 HTTP aprobadas. Incluyen datos conservados, error de cliente recuperable, aislamiento de sesión, duplicación, permisos y edición concurrente.

Pendiente: selector/alta autorizada de servicios depende del catálogo; ficha y venta con IDs reales dependen de esos módulos. También falta revisión visual escritorio/móvil y revisión de otra persona. Este avance no cierra la tarjeta. Depende del calendario PR #42 y, transitivamente, de las correcciones PR #41.
