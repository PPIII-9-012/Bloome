# Estadísticas (tarjeta #27)

Menú en **Estadísticas** (solo administración y recepción; el profesional recibe 403) con siete informes de solo lectura, cada uno con tabla y gráfico simple:

- **Ventas:** general, por categoría, por servicios y por género.
- **Gastos:** general y por categoría.
- **Otros:** horas totales empleadas.

Todos filtran por período (`from` y `to`, inclusivos). Sin fechas se usa el mes en curso. Hasta 31 días la serie general va por día; en períodos más largos, por mes. Los informes de ventas aceptan además categoría, servicio y género del cliente, con la opción **Sin dato**. El de gastos acepta categoría (o **Sin categoría**) y el de horas, empleado.

## De dónde salen las cifras

- **Vendido:** total de las ventas vigentes con fecha de venta dentro del período. Las anuladas no suman.
- **Cobrado:** lo cobrado de esas ventas (cobros pagados con asignación vigente, sin pasar del total de cada venta). Es el mismo cálculo que el listado de Ventas, y una prueba verifica que los totales coinciden. **Pendiente** = vendido − cobrado.
- **Por categoría, servicio o género:** el vendido sale de los ítems de cada venta. Lo cobrado de una venta se reparte entre sus ítems según su importe, con el método del mayor resto, así que las filas suman exactamente el total. Un ítem de un servicio sin categoría figura como **Sin dato**.
- **Gastos:** movimientos de caja de tipo gasto, netos de sus correcciones. No incluye retiros ni reintegros a clientes. Un grupo que queda en cero por una corrección no se muestra.
- **Horas:** duración de las citas **completadas** del período (por fecha de inicio) por empleado. No es un fichaje de entrada y salida.

## Género del cliente

Para el informe por género, `clients` tiene la columna opcional `gender` (`female`, `male`, `other`). Se carga en el alta y la edición del cliente (también en el alta desde la agenda) y se muestra en la ficha. Los clientes existentes quedan en **Sin dato**.

Para una base existente, `bin/install.php` agrega la columna con `migrateClientGender()` (idempotente). Como toda migración con `ALTER TABLE`, conviene respaldar antes y volver a correr `.\.tools\php\php.exe bin/install.php` después de actualizar.

## Pruebas

`php tests/stats.php`: reparto proporcional, conciliación con el listado de ventas, filtros, gastos con correcciones, horas, migración repetible, permisos, páginas HTTP y el campo de género en clientes.
