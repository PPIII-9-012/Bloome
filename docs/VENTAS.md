# Nueva venta y detalle de venta (tarjeta 15)

Flujo único para vender desde la agenda, desde la ficha del cliente o por enlace
directo. Usa las operaciones de `app/finance.php` (ver `docs/MODELO-FINANCIERO.md`):
el navegador nunca envía importes que se guarden sin recalcular.

## Pantallas

| Ruta | Quién | Qué hace |
|---|---|---|
| `/?page=sale-new` | admin, recepción | Formulario. `&client=ID` preselecciona el cliente; `&appointment=ID` carga cliente, empleado y servicios de la cita. |
| `/?page=sale&id=ID` | admin, recepción | Detalle: datos, líneas, descuentos, impuestos, total, cobrado y saldo. |

Los profesionales reciben 403 en ambas. Los datos se leen y escriben siempre en el servidor.

## Formulario

- **Servicios**: una fila por servicio (servicio, cantidad, tipo y valor del descuento). "Agregar
  servicio" suma una fila sin perder lo cargado; admite hasta 30.
- **Calcular total**: vista previa calculada por el servidor, sin guardar nada.
- **Guardar venta**: vuelve a calcular todo, guarda en una transacción y redirige al detalle.
- **Fecha**: se registra con la fecha de hoy (solo informativa en el formulario).
- **Observaciones**: hasta 500 caracteres, se muestran escapadas.
- El formulario **no acepta precios**: rige el del catálogo o, para servicios que ya estaban en la
  cita, el precio acordado en la cita. Un precio enviado a mano se ignora.

## Reglas

- Una cita tiene **una sola venta vigente**. Abrir otra venta de la misma cita lleva a la existente.
- No se vende una cita cancelada ni ausente.
- El cliente de la venta de una cita no se puede cambiar.
- Errores de validación se muestran sin perder lo ingresado (HTTP 422). Un envío sin CSRF devuelve 419.
- Cobrar y anular todavía no tienen pantalla: se ven como "Próx." y llegan con las tarjetas 16 y 18.

## Accesos desde otras pantallas

Se agregan en un parche aparte porque dependen de la agenda (#42) y de la ficha del cliente:

- Agenda: cada turno vigente muestra **Vender** o **Ver venta**.
- Ficha del cliente: botón **Nueva venta**, columna de cada cita con su venta, y la sección
  **Ventas y saldo** con el saldo pendiente. Los profesionales no ven ventas.

## Pruebas

```sh
php tests/sales.php
php tests/payments.php
php tests/voids.php
```

Crea y elimina su propia base. Verifica permisos, vista previa sin guardar, validaciones,
guardado con total del servidor, venta desde una cita con el precio acordado, venta única por
cita y precio no modificable por formulario.

## Cobros y señas (tarjeta #16)

- **Cobrar** (`?page=sale-pay&id=N`): cobro total o parcial de una venta, con medio de pago. El importe se sugiere con el saldo y nunca puede superarlo. Solo administración y recepción.
- **Caja**: sin caja abierta no se cobra. La pantalla ofrece abrirla (efectivo inicial). El arqueo y el cierre llegan con las tarjetas 19 y 20.
- **Doble clic / reintento**: el formulario lleva una clave de operación; reenviarlo no duplica el cobro ni el movimiento de caja. La misma clave con otros datos se rechaza.
- **Seña** (`?page=deposit-new&appointment=N`): el dinero entra a la caja al cobrarla. Se aplica después, total o parcialmente, a una venta del mismo cliente desde la pantalla de cobro. Aplicarla no mueve la caja otra vez.
- Acceso: botón "Cobrar" en el detalle de la venta y enlace "Registrar una seña" en la venta de una cita. El acceso desde la agenda y la ficha va con el parche de enlaces.
- Anular (`?page=sale-void&id=N`, tarjeta 18): solo administración, con motivo de 5 a 255 caracteres. Reintegra los cobros con un movimiento inverso en la caja abierta (si hay cobros y no hay caja, ofrece abrirla) y libera las señas. Nada se borra.
