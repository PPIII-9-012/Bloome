# Bloome · inicio de desarrollo

Primera entrega de código basada en la página SISTEMA del draft. PHP 8.4 + MySQL 8.4, PDO y vistas renderizadas en servidor.

## Estado real

- Login, cierre de sesión, contraseñas con hash y limitación de intentos en MySQL.
- Sesiones, protección CSRF, escape de HTML y permisos de escritura para administrador/recepción.
- Inicio con conteos y citas obtenidos de la base de datos.
- Clientes: listado, búsqueda, paginación, alta y edición con validación en servidor.
- Agenda diaria de **consulta** con selector de fecha y estados.
- Esquema inicial, instalador de administrador y datos ficticios opcionales.
- Interfaz adaptable a escritorio y móvil; errores y estados vacíos.

Pendiente: recuperación de contraseña, ficha integral, crear/editar citas, conflictos de disponibilidad, múltiples servicios por cita, empleados/roles administrables, caja, ventas, cobros, estadísticas y configuración. El menú identifica lo pendiente y no simula operaciones completadas.

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
```

Las pruebas unitarias no necesitan MySQL. Comprobar además con MySQL real: instalación, acceso, alta/edición, búsqueda, persistencia al recargar, agenda y permisos. El SQL se mantiene idempotente para instalar una base nueva; no sustituye un sistema de migraciones para futuras modificaciones de estructura.

Con el servidor local iniciado y `.env` configurado, `php tests/http.php` ejecuta 18 pruebas HTTP contra `127.0.0.1:8000` y la misma base MySQL. Crea y limpia un cliente y un usuario profesional de prueba. Usar únicamente en desarrollo, con credenciales de administrador. El resultado verificado y el lanzador específico de esta PC están documentados en AVANCE.md.

Los usuarios profesionales tienen acceso de lectura al listado de clientes y agenda en este piloto de un solo centro. Ese alcance debe revisarse antes de usar datos personales reales. No hay aislamiento entre múltiples centros.

Próximo incremento: ficha de cliente y CRUD de citas con transacciones y validación de solapamientos, horarios y ausencias. No habilitar reservas operativas hasta completar esas reglas.
