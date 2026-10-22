# Importar y exportar clientes (tarjeta #22)

En **Clientes** (solo administración y recepción) hay dos botones nuevos: **Exportar** e **Importar**. El profesional no los ve y recibe 403 si entra por la dirección.

## Plantilla
Archivo CSV en UTF-8 (con o sin BOM), separador `;` o `,`, hasta **1 MB** y **1.000 filas**. Encabezados fijos y en este orden:

`nombre; apellido; email; telefono; fecha_nacimiento; genero; observaciones`

- Obligatorios: `nombre` y `apellido`. Se aplican las mismas reglas que en el formulario de cliente.
- `fecha_nacimiento`: `AAAA-MM-DD` o `DD/MM/AAAA`. `genero`: `Femenino`, `Masculino`, `Otro` (o vacío).
- Se descarga desde **Importar → Descargar plantilla**.

## Cómo se procesa
1. El archivo se valida completo **antes** de guardar. Si el encabezado no coincide, no es UTF-8, está vacío o supera los límites, no se importa nada y se explica por qué.
2. Fila por fila: las válidas se guardan en una sola transacción; las inválidas se listan con su número de fila (la del archivo, contando el encabezado) y qué corregir.
3. **Duplicados, sin fusión automática.** Una fila se omite (y se informa) si coincide con un cliente existente o con una fila anterior en: correo (sin distinguir mayúsculas), teléfono (se comparan los últimos 8 dígitos, así `+54 11 5555-1111` y `(011) 5555 1111` son el mismo), nombre + fecha de nacimiento, o nombre y apellido cuando ninguno de los dos tiene correo, teléfono ni fecha. Los clientes existentes nunca se modifican desde la importación.
4. Al terminar se muestra: importados, omitidos por repetidos y con errores. Corregir y volver a subir el mismo archivo es seguro: lo ya importado se omite como repetido.
5. Queda un registro en la auditoría (`clients.import`) con las cantidades, y también los accesos denegados.

## Exportación
**Exportar** descarga `clientes-AAAA-MM-DD.csv` con las mismas columnas de la plantilla (todos los clientes, ordenados por apellido). Las celdas que empiezan con `=`, `+`, `-` o `@` llevan una comilla delante para que la planilla no las ejecute como fórmula; al importar, esa comilla se quita, de modo que un archivo exportado puede volver a importarse sin cambios. Queda auditada como `clients.export`.

## Pruebas
`php tests/client-import.php` (39): lectura y límites, validación por fila, duplicados, persistencia, exportación segura, permisos y subida HTTP real.
