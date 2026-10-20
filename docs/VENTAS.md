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
```

Crea y elimina su propia base. Verifica permisos, vista previa sin guardar, validaciones,
guardado con total del servidor, venta desde una cita con el precio acordado, venta única por
cita y precio no modificable por formulario.
