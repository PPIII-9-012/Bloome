# Calendario y filtros · #11

Referencia: [tarjeta de Trello](https://trello.com/c/5c5Cf4F0).

Implementado: vistas día, semana (lunes a domingo) y mes; fecha, anterior/siguiente/hoy; filtros combinados de empleado y estado. Las consultas usan IDs de employees y parámetros SQL. Incluye empleados inactivos para consultar el historial. Cada turno aparece una vez con todos sus servicios. El fin del período es exclusivo.

No requiere librerías, licencias pagas ni JavaScript. El mes usa una cuadrícula de siete columnas en escritorio y lista de días en pantallas angostas. Los estados vacíos especifican el período y los filtros. Los permisos de edición siguen siendo administrador/recepción; profesionales consultan según el alcance actual de un solo centro.

## Pendiente para cerrar la tarjeta

- Filtro de categoría: depende del catálogo (#7). El esquema actual no tiene categorías ni relación servicio/categoría; no se inventa un filtro sin datos.
- Revisar escritorio/móvil y accesibilidad visual con el equipo. El acceso al navegador local estuvo bloqueado; las verificaciones hechas son por renderizado y HTTP/MySQL.
- Revisión de otra persona requerida por la tarjeta. Este avance no cierra #11.

## Verificación

`php tests/run.php`: 19 comprobaciones, incluyendo febrero bisiesto, semanas que cruzan año y navegación desde meses de 31 días.

`php tests/appointments.php`: 63 comprobaciones, incluyendo filtros combinados, límite exclusivo, período vacío, navegación con filtros, permisos y rechazo de filtros desconocidos. La suite usa una base temporal y la elimina al terminar.

Este avance se apoya en el PR #41, que conserva el intervalo de citas históricas y los descansos omitidos por formularios antiguos.

## Imprimir y exportar · #26

Desde la agenda, «Vista para imprimir» (`/?page=agenda-print`) y «Exportar CSV» (`/?page=agenda-export`) respetan fecha, vista, empleado y estado de la consulta actual y exigen sesión; los profesionales pueden usarlas con el mismo alcance de lectura que la agenda. Con filtros inválidos responden 422 y no generan archivo.

- La vista imprimible es una tabla agrupada por día; el botón usa `public/assets/print.js` (único script, cargado solo allí, compatible con la política CSP). En impresión se ocultan menús y controles.
- El CSV es UTF-8 con BOM, separador `;` (apertura directa en planillas con configuración regional en español) y fin de línea CRLF. Columnas: Fecha, Inicio, Fin, Cliente, Servicios, Empleado, Estado. No incluye notas.
- Las celdas que empiezan con `=`, `+`, `-`, `@`, tabulación o retorno de carro se prefijan con `'` para evitar que se ejecuten como fórmulas. Un período vacío descarga solo los encabezados.
- Sin PDF propio ni XLSX; la persona imprime o guarda como PDF desde el navegador.

Pruebas: 3 unitarias en `tests/run.php` y 10 HTTP/MySQL en `tests/appointments.php` (formato, protección de fórmulas, omisión de notas, vacío, filtros inválidos, sesión obligatoria y conservación de filtros). Pendiente: revisión de otra persona y revisión visual en el navegador del equipo.
