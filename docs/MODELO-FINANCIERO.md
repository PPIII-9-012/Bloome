# Modelo financiero y cálculo monetario (tarjeta 14)

Base común del Track C: ventas, cobros, señas, anulaciones y caja. Esta entrega
no incluye pantallas; deja el modelo, las reglas y las operaciones probadas para
las tarjetas 15 a 20.

## Reglas de dinero (a validar con el centro)

| Regla | Valor adoptado |
|---|---|
| Representación | Centavos enteros en el cálculo, `DECIMAL(12,2)` en la base. Nunca `float`. |
| Entrada de importes | Hasta 2 decimales, punto o coma, sin separador de miles ni signo (`1234,50`). |
| Redondeo | Mitad hacia arriba, **una vez por línea**. Los totales son la suma de líneas. |
| Impuesto | Porcentaje por línea (`tax_rate`), incluido o agregado al precio. Por defecto 0 % incluido (`SALES_DEFAULT_TAX_RATE`, `SALES_TAX_INCLUDED`). |
| Descuento | Por línea, en importe o porcentaje; no puede superar la línea. Se aplica antes del impuesto. |
| Moneda y zona horaria | Una sola moneda y la zona de `APP_TIMEZONE`. |
| Precios | Se toman del catálogo y se copian al detalle; un cambio posterior no altera ventas anteriores. Solo administración puede fijar otro precio. |

Fórmulas, con `base = precio x cantidad - descuento`:

- Impuesto agregado: `tax = round(base x tasa)`, `total = base + tax`.
- Impuesto incluido: `tax = round(base x tasa / (1 + tasa))`, `total = base`, `net = base - tax`.

Ejemplo verificado: 3 x 1.000, 10 % de descuento, 21 %: agregado da 2.700 + 567 = 3.267;
incluido da total 2.700 con impuesto 468,60.

## Tablas

| Tabla | Contenido |
|---|---|
| `sales` | Cliente, empleado y cita opcionales, observaciones; estado `confirmed` o `voided`; subtotal, descuento, impuesto y total. Anulación con motivo, usuario y fecha (la base lo exige). Una cita no puede tener dos ventas vigentes. |
| `sale_items` | Servicio, descripción, cantidad, precio unitario, descuento, tasa, y neto/impuesto/total históricos. |
| `payments` | Dinero recibido. `kind`: `payment` (de una venta) o `deposit` (seña de una cita). `idempotency_key` único. Estado `paid` o `refunded`. |
| `payment_allocations` | A qué se aplica un cobro: una venta **o** una cita (la base lo exige). `released_at` conserva la historia cuando se anula. |
| `cash_sessions` | Cajas. Solo puede haber una abierta (índice único sobre `open_key`). |
| `cash_movements` | Libro de caja de solo agregado. Una corrección es un movimiento inverso (`reverses_id`, uno por movimiento), nunca un `UPDATE`. |
| `expenses`, `expense_categories` | Ya existían; los usará la tarjeta 19. |

## Operaciones (`app/finance.php`)

Todas validan permisos contra la base, corren en una transacción y siguen el orden
de bloqueo caja abierta, venta, cobro.

| Función | Quién | Regla |
|---|---|---|
| `openCashSession` | admin, recepción | Una sola caja abierta. |
| `recordCashMovement` | admin, recepción | Ingreso, gasto o retiro en la caja abierta; no se retira más de lo disponible en ese medio. |
| `createSale` | admin, recepción | Total calculado en el servidor. La cita debe ser del mismo cliente. |
| `recordPayment` | admin, recepción | Exige caja abierta. No supera el saldo. `idempotency_key` evita duplicados por doble clic o reintento. |
| `applyDeposit` | admin, recepción | Aplica una seña a una venta del mismo cliente sin mover caja. |
| `voidSale` | solo admin | Exige motivo. Reintegra cobros directos con movimientos inversos y libera las señas. |
| `saleBalance` | lectura | Total, cobrado, saldo y estado (`unpaid`, `partial`, `paid`, `voided`). |

### Sin doble contabilización

El dinero entra a la caja **una sola vez**, cuando se cobra. Aplicar una seña a una venta
solo crea una asignación; no genera otro movimiento. Si la venta se anula, la seña queda
disponible para otra venta y no sale dinero de la caja. El efectivo esperado es
apertura + entradas en efectivo - salidas en efectivo; los otros medios se informan aparte.

Ejemplo de la hoja de ruta (probado): apertura 1.000, cobro en efectivo 600, cobro con
tarjeta 400 y retiro 100 dan 1.500 de efectivo esperado, no 1.900.

## Decisiones tomadas por defecto

Son supuestos de arranque; conviene confirmarlos antes de operar:

1. Impuesto incluido en el precio y 0 % hasta que se defina la tasa (ver `.env.example`).
2. Solo administración anula ventas y cambia precios; recepción vende y cobra; profesional no accede.
3. No se cobra ni se reintegra sin una caja abierta.
4. Una seña pertenece a una cita y se aplica luego a una venta del mismo cliente.
5. Un reintegro completo por anulación; no hay devolución parcial ni crédito a favor.
6. La tasa de impuesto no está en el catálogo de servicios; se indica por línea hasta que la tarjeta 06 la incorpore.

## Actualizar una base existente

1. Respaldar la base y detener las escrituras (`ALTER TABLE` confirma implícitamente).
2. Ejecutar con una cuenta con permisos de estructura:

   ```sh
   php bin/migrate-finance.php --as-root
   ```

   También la aplica `bin/install.php`. La migración es repetible y conserva cobros y
   asignaciones anteriores; los cobros viejos quedan como `payment` sin clave de idempotencia.
3. Las instalaciones nuevas ya traen el modelo en `database/schema.sql`.

## Pruebas

```sh
php tests/finance.php
```

Crea y elimina su propia base. Verifica 76 casos: redondeo, descuento, impuesto
incluido y agregado, restricciones de la base, permisos, caja, seña sin doble conteo,
pago parcial, sobrepago, reintento y envíos simultáneos, anulación con reversión,
y la migración desde el esquema anterior.
