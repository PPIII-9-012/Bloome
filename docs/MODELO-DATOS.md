# Integración del modelo de datos (#3, #9 y #10)

Esta rama integra `feature/02-CompletarBD` (83fa2c3) conservando empleados,
auditoría, horarios, vacaciones y feriados. La agenda y el inicio leen todos los
servicios de cada cita sin duplicar filas. Incluyen el estado `missed` (Ausente).
Crear y editar citas sigue pendiente en #12.

## Contrato

- `appointments.employee_id` referencia **employees.id**. El usuario se obtiene
  mediante `employees.user_id`; ambos IDs pueden ser diferentes.
- Perfil, permisos, horarios y vacaciones siguen recibiendo **users.id**.
  Antes de llamar a `checkAppointmentAvailability` desde #12, resolver ese usuario.
- `appointment_services` guarda servicio, precio y duración por cita. El seed
  escribe cita y detalle dentro de una misma transacción.
- Las tablas clínicas y financieras del esquema del compañero se integran como
  estructura; sus funcionalidades todavía no están implementadas.

## Actualizar una base existente

1. Detener el servidor web y cualquier proceso que escriba en la base.
2. Crear un respaldo completo con datos, esquema y triggers y comprobar su lectura.
3. Usar una cuenta con permisos para crear/alterar tablas y triggers:

   ```sh
   php bin/migrate-model.php --maintenance
   ```

   En el entorno local preparado se puede agregar `--as-root`, que utiliza
   `MYSQL_ROOT_PASSWORD` privado. No cambia la cuenta usada por la aplicación.
4. Verificar agenda, inicio y login antes de reabrir el servicio.

El migrador conserva IDs, fechas, estados, notas e historial de empleados
inactivos. Mapea cada usuario a su empleado y copia el servicio anterior al detalle.
El modelo antiguo **no guardaba precio ni duración históricos**: los valores
copiados son los del catálogo al migrar, no precios originales de venta.

En bases actualizadas quedan `legacy_employee_user_id` y `service_id` nullable
como trazas. El código nuevo no los usa ni los completa. Instalaciones nuevas no
incluyen esas columnas. Los triggers se actualizan para validar el ID de empleado.

MySQL realiza commits implícitos con DDL: ante un error, mantener el mantenimiento,
corregirlo y repetir. La migración usa un bloqueo exclusivo, admite una preparación
interrumpida y no duplica ni sobrescribe detalles al repetirse. Para volver a la
versión anterior, restaurar el respaldo completo junto con el código anterior;
no basta con cambiar de rama después de convertir los IDs.

`bin/install.php` instala el modelo nuevo. Sobre una base antigua exige
`--maintenance` y aplica la misma conversión; ejecutar solo `schema.sql` no actualiza
las relaciones existentes.

## Pruebas

```sh
php tests/run.php
php tests/employees.php
php tests/schedule.php
php tests/model-migration.php
php tests/http.php
```

Las pruebas de empleados, horarios y migración crean y eliminan sus propias bases
temporales. La suite de migración verifica IDs distintos, reejecución, historial
inactivo, servicios múltiples, triggers y una instalación nueva con demo repetible.
La suite HTTP usa el servidor local configurado y limpia sus registros de prueba.
