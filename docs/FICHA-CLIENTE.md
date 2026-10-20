# Ficha del cliente · primera parte de la tarjeta 13

Ruta `/?page=client&id=N`, solo lectura, para todos los roles con acceso a clientes (mismo alcance que el listado actual de un solo centro). Edición y alta de citas solo para administración/recepción.

## Incluido

- Datos del cliente (teléfono, correo, nacimiento, alta, observaciones) y acceso a editar.
- Historial de citas del cliente, más recientes primero, con servicios, empleado y estado; filtro por fecha de inicio y fin (inclusive), resumen por estado y paginación de 15. Fechas inválidas o invertidas responden 422.
- «Nueva cita» abre el formulario de turnos con el cliente preseleccionado. En la agenda el nombre del cliente enlaza a su ficha; en el listado de clientes, el nombre y «Ver ficha».
- La búsqueda de clientes ahora también encuentra por nombre completo («Lucía Ejemplo» o «Ejemplo Lucía»).

## No incluido (depende de otras tarjetas)

- Seña aplicada, abonado y saldo, ventas y comunicaciones: requieren los modelos de cobros y ventas del Track C (tarjetas 16 y 17). La pantalla muestra «Sin datos de cobros» y no calcula cifras. Nueva venta, SMS y Email aparecen deshabilitados con «Próx.».
- Gráficos del diseño.

Cuando existan ventas y cobros, la ficha debe consultarlos con una consulta común y no recalcular importes en la vista.

## Verificación

12 pruebas HTTP/MySQL nuevas en `tests/appointments.php` (datos, historial, vacío, filtros, 404, permisos de profesional, búsqueda y preselección del cliente). Pendiente: revisión de otra persona y acuerdo con el Track C sobre el contrato de ventas y cobros.
