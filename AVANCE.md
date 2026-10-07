# Avance de implementación

Este documento describe código existente. No representa el cierre del roadmap de dos semanas.

## Implementado

| Área | Estado |
|---|---|
| Base PHP + conexión MySQL | PDO, consultas preparadas, utf8mb4 y zona horaria de la aplicación |
| Acceso | Login, hash de contraseña, sesión regenerada, logout y limitación de intentos |
| Navegación | Estructura compartida, menú activo e interfaz adaptable |
| Inicio | Conteos y citas consultados en la base |
| Clientes | Listar, buscar, paginar, crear y editar; validación y confirmación |
| Agenda | Consultar citas por fecha y visualizar estado/servicio/profesional |
| Instalación | Esquema inicial, administrador configurable y ejemplos optativos |

## Verificado en esta PC

- PHP 8.4.26: sintaxis de los archivos y 15 pruebas unitarias/renderizado.
- MySQL 8.0.46: instalación en una instancia aislada del proyecto, puerto 3308. No se modificó la instancia habitual del equipo.
- 18 pruebas HTTP/MySQL: login, CSRF, alta, persistencia, edición, búsqueda, HTML escapado, fechas, cliente inexistente, cierre de sesión y denegación de escritura a un profesional.
- Interfaz revisada en navegador: acceso, inicio, listado y formulario. Distribución de escritorio y adaptación al panel estrecho.
- Docker configurado para PHP 8.4 + MySQL 8.4; no ejecutado en esta PC porque Docker no está instalado.

Los clientes y citas precargados son ficticios. Los registros temporales creados por las pruebas automáticas se limpian al terminar.

## Para abrir el entorno local preparado

Abrir http://127.0.0.1:8000. Credenciales en las variables ADMIN_EMAIL y ADMIN_PASSWORD de `.env` (archivo privado, ignorado por Git).

Si se reinició la PC:

```powershell
powershell -File bin/start-local.ps1
```

En otra PC seguir README.md. Los ejecutables y la base local en `.tools/` no forman parte del código a compartir. El script local presupone la ruta de MySQL de esta PC.

## Próximo bloque

1. Decidir continuidad de arquitectura: pasar a Laravel antes de crecer o mantener este monolito PHP.
2. Ficha de cliente con citas y operaciones relacionadas.
3. Alta/edición/cancelación de citas y disponibilidad transaccional.
4. Catálogo y gestión de empleados.
5. Ventas, pagos y caja siguiendo el roadmap y sus reglas de conciliación.

La agenda actual tiene una sola relación de servicio por cita y usa usuarios como empleados. Son simplificaciones de esta primera entrega y requieren migraciones antes de soportar los flujos completos del diseño.
