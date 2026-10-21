# Empleados y perfil — issue #9

## Estado revisado el 11 de octubre de 2026

- Rama de trabajo: `feature/09-empleados-perfil`, desde `develop` (`7b496b8`), sin cambios locales previos.
- `origin/main` contiene el merge #1 (`0437b3b`). No había PRs abiertos.
- Issues #3, #4 y #9 abiertos. #4 no tiene implementación adicional publicada en las ramas revisadas; se reutilizan roles, CSRF y autenticación existentes.
- `origin/feature/02-CompletarBD` (`83fa2c3`) propone `employees` y reemplaza la FK de citas por `employees.id`, además de servicios múltiples. Solo modifica el esquema, sin migración ni adaptación de las consultas actuales.
- Se reutiliza la definición de `employees` de esa rama (incluidos campos opcionales y restricciones), agregando `appointment_color`. No se importan funcionalidades de ventas, caja, horarios o vacaciones.
- Trello `https://trello.com/c/Cg1UAdPo` devolvió 401; su estado actual no está verificado. No se modificaron tarjetas ni issues.

## Comportamiento y permisos

Solo el administrador puede listar, crear, editar, activar y desactivar empleados. Cada empleado tiene un único usuario; las altas guardan ambos en una transacción. Nombre completo conserva el campo `users.name` existente. Correo único, rol validado, contraseña inicial hasheada (12–72 bytes), teléfono opcional y color hexadecimal.

Todos los roles acceden al perfil propio, cuyo usuario se obtiene exclusivamente de la sesión. Solo se admiten cambios en nombre, correo y teléfono. IDs ajenos y campos protegidos se rechazan con 403. Cambiar el rol o estado de un usuario requiere la ruta administrativa y comprobación de permisos en servidor. No se ofrece restablecimiento de contraseñas en este módulo.

Se vuelven a leer usuario y empleado en cada solicitud. Ambos deben estar activos para acceder. Desactivar sincroniza los dos estados, conserva las citas y bloquea sesiones abiertas. Las escrituras vuelven a comprobar al actor dentro de la transacción. Se bloquean usuarios/empleados en orden estable para impedir que dos solicitudes simultáneas eliminen al último administrador con acceso efectivo.

`employee_audit` registra actor, usuario, acción y fecha de las altas y ediciones. Es una bitácora acotada al módulo, no reemplaza la auditoría general pendiente del issue #4; no guarda contraseñas ni sus hashes.

## Instalación y migración

Con una copia de seguridad y escrituras detenidas durante el despliegue:

```powershell
.\.tools\php\php.exe bin/migrate-employees.php
```

La migración es repetible: crea las tablas si faltan, agrega el color si la tabla del compañero ya existe y vincula usuarios existentes sin modificar sus citas. MySQL hace commit implícito en DDL: si un paso falla, corregir la causa y repetir antes de habilitar escrituras.

Requiere permisos para crear tablas, alterar `employees` y crear triggers. Con binary logging, MySQL puede requerir permisos adicionales (error 1419). En este entorno local se aplicó con:

```powershell
.\.tools\php\php.exe bin/migrate-employees.php --as-root
```

Esta opción CLI usa `MYSQL_ROOT_PASSWORD` del entorno solo para la migración; no cambia `.env`, los permisos de la cuenta web ni `log_bin_trust_function_creators`. En otros despliegues se puede ejecutar con una cuenta de migración mediante `DB_USERNAME`/`DB_PASSWORD`. Para instalaciones nuevas, `bin/install.php` incluye esta migración y admite la misma opción `--as-root` si corresponde al entorno.

Los triggers `appointments_active_employee_insert` y `appointments_active_employee_update` impiden insertar, reasignar, reprogramar o reabrir una cita cancelada para un empleado inactivo, incluso mediante SQL directo. Usan lectura bloqueante para coordinarse con desactivaciones. Las modificaciones que solo registran el estado/notas del historial permanecen permitidas.

## Contrato para integrar #3 y #4

El modelo integrado usa **`appointments.employee_id` → `employees.id`** y servicios múltiples en `appointment_services`. Perfil, horarios y vacaciones conservan IDs de usuario. Para actualizar una base anterior, seguir [el procedimiento de migración](MODELO-DATOS.md); no basta con ejecutar `schema.sql` ni el migrador de empleados.

Al integrar #4, conectar `canManageEmployees` y `employee_audit` con su política y auditoría compartidas, manteniendo las pruebas de autorización.

## Verificación

```powershell
.\.tools\php\php.exe tests/run.php
.\.tools\php\php.exe tests/http.php
.\.tools\php\php.exe tests/employees.php
```

- 15 pruebas unitarias/renderizado y 18 HTTP/MySQL existentes.
- 47 pruebas nuevas HTTP/MySQL: migración repetible, alta y duplicados, campos inválidos, búsqueda y escape, CSRF, permisos para profesional/recepción, perfil ajeno, elevación de privilegios, inactividad de usuario o empleado, reactivación, conservación de historial, triggers y concurrencia del último administrador.
- `tests/employees.php` usa las credenciales de migración locales para crear una base temporal `bloome_test_employees_<aleatorio>`, inicia un servidor PHP aislado y elimina únicamente esa base y su proceso al finalizar. No modifica los datos de la aplicación. Necesita permiso de crear/eliminar esa base y triggers.
- Capturas de referencia revisadas: 06, 10, 27, 31, 34 y 35. Se omiten horarios/vacaciones fuera de alcance y se conserva nombre completo del modelo existente.
- Revisión visual retomada después del reinicio de la PC: terminal y navegador operativos. Verificados listado, alta, perfil y edición; listado y edición propia/administrativa revisados a 390 px. A 390 px el listado y la edición propia no desbordan el documento; la tabla tiene desplazamiento interno. Listado de escritorio verificado a 1280 px. El ajuste móvil de formularios y barra superior está incluido en el código actual.

La revisión visual del alcance #9 quedó realizada. No cerrar #9 ni marcar Hecho antes de la aceptación del equipo y la revisión de sus dependencias. Los nuevos controles de horarios/vacaciones de #10 no forman parte de esta validación.

## Archivos del incremento

- `app/employees.php`: validación, permisos y persistencia transaccional.
- `app/employee-routes.php`: rutas de empleados y perfil propio.
- `app/employee-migration.php`, `database/migrations/009-employees.sql`, `bin/migrate-employees.php`: migración repetible y triggers.
- `app/bootstrap.php`, `public/index.php`: carga del módulo, autenticación por estado efectivo y consultas de agenda con color.
- `database/schema.sql`, `bin/install.php`: instalación nueva con empleados y auditoría.
- `views/employees.php`, `views/employee-form.php`, `views/employee-profile.php`: listado, formulario y perfil.
- `views/layout.php`, `views/appointment-list.php`, `public/assets/app.css`: navegación, color de citas y presentación adaptable.
- `tests/employees.php`: pruebas aisladas HTTP/MySQL y de concurrencia.
- `README.md`, `AVANCE.md`, este documento: uso, resultados y dependencias pendientes.

## Reanudación tras reinicio — 11/10/2026

- Estado encontrado: `feature/09-empleados-perfil` apunta a `81aa83c`; la rama activa `feature/10-horarios-vacaciones-feriados` apunta a `58ec661` e incluye #9. Se preservaron esos commits y la rama activa.
- Reejecutadas sobre el estado actual: 15 pruebas unitarias, 18 HTTP/MySQL y 47 de empleados; las 80 pasan.
- Trello consultado con sesión del navegador: tarjeta `08 · P0 · B · Gestión de empleados y perfil propio`, lista **En desarrollo**, asignada a Juan Manuel De Monasterio. Descripción y aceptación confirmadas; no se modificó la tarjeta.
- El 401 de la consulta directa y el bloqueo del navegador descritos arriba corresponden a la revisión inicial; ya no impiden esta revisión por navegador.
