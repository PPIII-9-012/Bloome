# Bloome · inicio de desarrollo

Primera entrega de código basada en la página SISTEMA del draft. PHP 8.4 + MySQL 8.4, PDO y vistas renderizadas en servidor.

## Estado real

- Login, cierre de sesión, contraseñas con hash y limitación de intentos en MySQL.
- Sesiones, protección CSRF, escape de HTML y permisos de escritura para administrador/recepción.
- Inicio con conteos y citas obtenidos de la base de datos.
- Clientes: listado, búsqueda, paginación, alta y edición con validación en servidor.
- Agenda por día, semana y mes con filtros de empleado y estado; alta, edición y cancelación de turnos con múltiples servicios y control de disponibilidad.
- Esquema inicial, instalador de administrador y datos ficticios opcionales.
- Interfaz adaptable a escritorio y móvil; errores y estados vacíos.

Pendiente: recuperación de contraseña, ficha integral, filtro de categoría del calendario, catálogo completo, caja, ventas, cobros, estadísticas y configuración restante. El menú identifica lo pendiente y no simula operaciones completadas.

El roadmap propone Laravel para el sistema completo. Este primer corte es PHP sin dependencias de Composer para que se pueda inspeccionar y ejecutar fácilmente. Antes de ampliar ventas y caja, decidir si trasladar esta base a Laravel; no mezclar dos arquitecturas en paralelo.

## Arranque con Docker Desktop

1. Copiar configuración:

   ```powershell
   Copy-Item .env.example .env
   ```

2. Editar `.env`: establecer `DB_PASSWORD`, `MYSQL_ROOT_PASSWORD`, `ADMIN_EMAIL`, `ADMIN_NAME` y `ADMIN_PASSWORD`. Usar una contraseña de administrador entre 12 y 72 bytes. No subir `.env` a Git. Los valores se escriben sin comillas; los secretos con `$` deben escaparse según las reglas de Docker Compose.

3. Construir y levantar:

   ```sh
   docker compose up -d --build
   docker compose exec web php bin/install.php
   ```

4. Opcional, cargar cuatro clientes y tres citas **ficticias** para el día en que se ejecuta el comando:

   ```sh
   docker compose exec web php bin/seed-demo.php --confirm
   ```

5. Abrir [Bloome local](http://localhost:8000) y usar las credenciales configuradas. La carga de demo se registra y no se repite.

El instalador no cambia contraseñas de usuarios existentes. La base se conserva en un volumen. `docker compose down` detiene el entorno sin borrar los datos; no usar `down -v` si se necesitan conservar.

El código se copia a la imagen: después de editar, ejecutar `docker compose up -d --build`. Cambiar las variables de contraseña de MySQL después de inicializar el volumen no cambia automáticamente los usuarios de esa base.

## Arranque sin Docker

Requiere PHP 8.4 con `pdo_mysql` y MySQL 8.4. Crear una base `bloome` con utf8mb4 y un usuario con permisos sobre esa base. Copiar y configurar `.env` con `DB_HOST=127.0.0.1` y las credenciales correspondientes.

```sh
php bin/install.php
php bin/seed-demo.php --confirm
php -S 127.0.0.1:8000 -t public public/router.php
```

El segundo comando es opcional. El servidor embebido y el Dockerfile son para desarrollo local, no para producción. En un despliegue real usar PHP-FPM/Apache, HTTPS, raíz pública en `public/`, secretos fuera del repositorio y `APP_SECURE_COOKIE=1`. Mantener PHP/MySQL actualizados dentro de la versión elegida.

## Organización

```text
app/          Conexión, funciones comunes y validación
bin/          Instalación y datos de ejemplo (solo CLI)
database/     Esquema MySQL inicial
public/       Punto de entrada y assets públicos
views/        Vistas PHP y estructura compartida
tests/        Pruebas de reglas y renderizado
```

Rutas iniciales: `/?page=login`, `/?page=dashboard`, `/?page=clients`, `/?page=client-new`, `/?page=client-edit&id=1`, `/?page=agenda`. Cerrar sesión requiere POST y CSRF.

## Verificación

```sh
php tests/run.php
php tests/finance.php   # modelo financiero; necesita MySQL y MYSQL_ROOT_PASSWORD
php tests/sales.php     # nueva venta y detalle (HTTP); mismas credenciales
php tests/payments.php  # cobros parciales, señas y caja (HTTP); mismas credenciales
php tests/voids.php     # anulación de ventas (HTTP); mismas credenciales
php tests/sales-list.php  # listado y búsqueda de ventas (HTTP); mismas credenciales
php tests/reports.php      # informes imprimibles y CSV de ventas y caja (HTTP); mismas credenciales
php tests/stats.php        # estadísticas, género del cliente y conciliación con ventas (HTTP); mismas credenciales
php tests/cash.php   # caja diaria, movimientos y gastos (HTTP); mismas credenciales
php tests/cash-close.php  # arqueo y cierre de caja (HTTP); mismas credenciales
```

Las pruebas unitarias no necesitan MySQL. Comprobar además con MySQL real: instalación, acceso, alta/edición, búsqueda, persistencia al recargar, agenda y permisos. El SQL se mantiene idempotente para instalar una base nueva; no sustituye un sistema de migraciones para futuras modificaciones de estructura.

Con el servidor local iniciado y `.env` configurado, `php tests/http.php` ejecuta 18 pruebas HTTP contra `127.0.0.1:8000` y la misma base MySQL. Crea y limpia un cliente y un usuario profesional de prueba. Usar únicamente en desarrollo, con credenciales de administrador. El resultado verificado y el lanzador específico de esta PC están documentados en AVANCE.md.

Los usuarios profesionales tienen acceso de lectura al listado de clientes y agenda en este piloto de un solo centro. Ese alcance debe revisarse antes de usar datos personales reales. No hay aislamiento entre múltiples centros.



## Permisos por rol y auditoría

Los permisos se definen en `permissions` y se asignan a roles mediante
`role_permissions`; no son columnas de `users`. La aplicación comprueba los
permisos en el servidor antes de mostrar el listado o procesar altas y
ediciones de clientes. Las acciones exitosas y los intentos de escritura
denegados se registran en `audit_logs`.

Roles iniciales:
- `admin`: todos los permisos cargados en `permissions`.
- `reception`: consulta, alta y edición de clientes.
- `professional`: consulta de clientes, sin alta ni edición.

Para aplicar estas tablas y sus asignaciones en una base existente, ejecutá
el instalador habitual del proyecto después de verificar `.env` y hacer un
respaldo de la base. El esquema usa `CREATE TABLE IF NOT EXISTS` e
`INSERT IGNORE`, por lo que no elimina datos existentes.
Para reservar, configurar los horarios reales del centro y de cada empleado, descansos y ausencias. No se crean horarios de trabajo automáticamente. Calendario y filtros (#11) en revisión: ver [alcance y pendientes](docs/CALENDARIO.md).

## Empleados y perfil

Gestión administrativa en `/?page=employees`; perfil propio desde el nombre de usuario en la barra superior. Antes de usar esta versión sobre una base existente, seguir [la migración del modelo](docs/MODELO-DATOS.md), con respaldo y escrituras detenidas. Ver [instalación, permisos y compatibilidad con el issue #3](docs/EMPLEADOS.md).

## Turnos en desarrollo

Alta, edición y cancelación disponibles para administración/recepción desde la agenda, con varios servicios y control transaccional de solapamientos. Ver [alcance, instalación y pendientes](docs/TURNOS.md). Descansos/jornada partida implementados con un descanso por día; ver [configuración y migración](docs/DESCANSOS.md). Pendiente revisión del equipo; las vistas de semana/mes y filtros de empleado/estado están implementados en el avance de #11; categoría y revisión visual pendientes.
