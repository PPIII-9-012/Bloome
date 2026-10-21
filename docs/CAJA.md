# Caja diaria (tarjeta #19)

Pantalla `?page=caja` (menú "Caja", solo administración y recepción) y `?page=caja-movement` (nuevo movimiento).

- **Una caja abierta por centro**, con efectivo inicial. Se abre desde la pantalla (o desde el cobro, la seña o la anulación cuando hace falta). El arqueo y el cierre llegan con la tarjeta 20.
- **Cobros automáticos**: cada cobro de una venta o seña de una cita entra solo al libro de caja, una vez. Aplicar una seña a una venta no genera otra entrada. Cada fila muestra su **origen** (venta, seña de cita o manual) y el usuario que la registró.
- **Movimientos manuales**: ingreso, gasto o retiro, con medio de pago e importe. El gasto exige categoría y descripción, el retiro exige descripción. Una salida no puede superar lo disponible en ese medio (en efectivo: inicial + entradas − salidas). El formulario lleva clave de operación: el doble clic no duplica el movimiento.
- **Categorías de gasto**: vienen cargadas (Insumos, Servicios, Alquiler, Sueldos y comisiones, Limpieza y mantenimiento, Otros). Administración puede agregar más desde la misma pantalla. Se guardan en `expense_categories`.
- **Corrección** (solo administración): un ingreso, gasto o retiro mal cargado se corrige con un movimiento inverso en la caja abierta, con motivo. El original no se toca y no se puede corregir dos veces. Los cobros y reintegros de ventas no se corrigen acá: se anula la venta.
- **Resumen y filtros**: entradas, salidas y neto por medio de pago, efectivo esperado, y filtros por medio de pago y tipo, con paginación (25 por página). Se consultan también las cajas anteriores.
- La tabla `expenses` heredada del esquema inicial no se usa: los gastos viven en el libro de caja para no contarlos dos veces.

Pruebas: `php tests/cash.php` (HTTP, misma base temporal que `tests/sales.php`) y la migración en `tests/finance.php`.
