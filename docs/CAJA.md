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

## Arqueo y cierre de caja (tarjeta #20)

`?page=caja-close` (administración o recepción):

- **Esperado**: efectivo inicial + entradas en efectivo − salidas en efectivo. Tarjetas, transferencias y otros medios no entran en el arqueo: se muestran **separados**, con su neto, para conciliarlos aparte contra el posnet o el banco. Ejemplo: inicial 1000 + efectivo 600 + tarjeta 400 − retiro 100 = efectivo esperado 1500 y tarjeta 400 aparte.
- **Contado y diferencia**: se ingresa el efectivo contado (importe válido, sin signo) y "Ver diferencia" muestra lo que falta o sobra sin cerrar. Si la diferencia no es cero, la observación es obligatoria (5 a 255 caracteres).
- **Cierre**: en una transacción con la caja bloqueada. Guarda esperado, contado, diferencia, observación, resumen por medio de pago, usuario y fecha. Los cobros y movimientos que llegan a la vez esperan o encuentran la caja cerrada.
- **Una caja cerrada no se reabre ni se modifica**: no entran cobros, movimientos ni correcciones. Lo posterior (por ejemplo la anulación de una venta) se reintegra en la caja siguiente, y el cierre anterior queda idéntico.
- Las señas cuentan una sola vez: entran al cobrarse y aplicarlas a una venta no mueve la caja.
- **A validar con el centro**: si el efectivo del reintegro de una anulación supera lo que hay en caja, hoy se registra igual y el esperado queda en negativo. Se puede agregar un aviso o un bloqueo si lo prefieren.

Pruebas: `php tests/cash-close.php` y la migración en `tests/finance.php`.
