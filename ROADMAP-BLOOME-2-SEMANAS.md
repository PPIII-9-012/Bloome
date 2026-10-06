# Bloome — Roadmap de desarrollo en 2 semanas

Fecha de elaboración: 5 de octubre de 2026.
Fuente: [draft de Bloome, página SISTEMA](https://www.figma.com/design/I23Px5r3BAfCUpLiHan7ro/Bloome?node-id=0-1).
Se verificaron 44 marcos en la página. WEB no forma parte del alcance de este plan.

## Resultado esperado y condiciones

Entregar al día 10 un piloto funcional en PHP y MySQL: configurar el centro, empleados y servicios; dar de alta clientes; agendar y modificar citas; registrar ventas, señas y cobros; consultar movimientos y cerrar caja; consultar reportes básicos. Todas las acciones incluidas deben guardar datos reales, validar errores y respetar permisos.

El plazo es una estimación ajustada, no una garantía de completar un producto comercial maduro. Supone tres desarrolladores con experiencia en PHP y aplicaciones web, dedicación completa, una sola sede/empresa, una moneda, interfaz en español y acceso a hosting y proveedores al comenzar. Si el equipo tiene que aprender el framework, resolver multitenencia o integrar facturación fiscal, el alcance completo no entra en este plazo.

Las pantallas son una referencia visual, no una especificación completa. Hay tablas vacías, controles sin estados y reglas de negocio todavía sin definir. Los estados de error, vacío, carga, cancelación y éxito se implementan dentro de cada tarea; no se espera a terminar un prototipo de Figma.

Dos semanas significan 10 jornadas laborables efectivas. Los días se numeran para no asumir una fecha de inicio ni disponibilidad en feriados. Si hay menos de 10 jornadas disponibles, se ajusta la fecha o el alcance.

## Equipo y capacidad

| Persona | Responsabilidad principal | Entrega de la que responde |
|---|---|---|
| A | Base técnica, autenticación, clientes y configuración | Sistema accesible, catálogo y clientes listos para usarse |
| B | Empleados, disponibilidad, agenda y comunicaciones | Turnos válidos, edición/cancelación y ficha integrada |
| C | Ventas, pagos, caja y estadísticas | Importes coherentes y cierre conciliado |
| D | Validación funcional puntual | Resolver reglas y aprobar los recorridos; sin tareas críticas de programación |

A, B y C trabajan cada uno tanto la interfaz como el servidor de su módulo. Evitar que toda la interfaz dependa de una sola persona.

Capacidad: 3 personas × 10 días × 8 horas = 240 horas de presencia. Planificar 6 horas efectivas por persona/día: 180 horas para tareas, pruebas e integración. Las 60 horas restantes absorben coordinación, revisiones y cambios de contexto. Dentro de las 180 horas se reservan 18 para contingencias.

| Persona | Funcionalidades | Integración y pruebas | Reserva | Total efectivo |
|---|---:|---:|---:|---:|
| A | 44 h | 10 h | 6 h | 60 h |
| B | 46 h | 8 h | 6 h | 60 h |
| C | 44 h | 10 h | 6 h | 60 h |

Participación de D: máximo 6 horas en total: día 1, 1,5 h; día 5, 1 h; día 9, 2 h; día 10, 1,5 h. El equipo documenta las decisiones entre esas revisiones; D no queda como dependencia cotidiana.

## Decisiones técnicas propuestas

- Un monolito PHP con Laravel y vistas Blade, MySQL con InnoDB, HTML/CSS y JavaScript puntual para calendario, filtros y modales. PHP y MySQL son los requisitos; Laravel es la propuesta para acelerar una implementación que el equipo ya conozca.
- Fijar versiones compatibles con el hosting el día 1 y conservar las dependencias bloqueadas. Evitar una actualización de versión durante estas dos semanas.
- Un solo repositorio, migraciones y datos de prueba reproducibles. Entorno de pruebas publicado desde el día 2.
- Componentes compartidos: menú, tabla con paginado, buscador, formulario, modal, confirmación, aviso y selector de fechas.
- Calendario reutilizable con vistas día/semana/mes. Verificar licencia antes de elegirlo; no depender de vistas de recursos de pago.
- Consultas parametrizadas/ORM, validación en servidor, protección CSRF, sesiones y permisos por acción.
- Importes DECIMAL o centavos enteros; nunca float. Totales calculados en servidor. Precio e impuesto históricos guardados en el detalle de venta.
- Transacciones para venta/pagos/movimientos y para cierre de caja. Una clave de idempotencia evita cobros duplicados por doble clic o reintentos.
- Para citas concurrentes, bloquear una fila estable de empleado dentro de la transacción y comprobar solapamientos antes de insertar. Una validación solo en la interfaz no alcanza.
- Correos mediante un proveedor configurado. SMS mediante adaptador a un solo proveedor, con registro de estado y errores.

Laravel documenta sus requisitos de despliegue y configuración en su [guía oficial](https://laravel.com/framework/docs/deployment). El modelo de transacciones y bloqueos de InnoDB está descrito en la [documentación de MySQL](https://dev.mysql.com/doc/refman/8.0/en/innodb-transaction-model.html). Las decisiones de arquitectura y estimaciones de este documento son propuestas para Bloome.

## Cobertura del diseño

P0: necesario para operar el piloto. P1: incluido en la planificación con implementación acotada; se difiere primero si la ruta crítica se retrasa. Diferir un P1 significa reconocer que esa parte del draft no se entregó.

| Módulo y pantallas del draft | Implementación prevista | Responsable | Prioridad / condición |
|---|---|---|---|
| Login, Recuperar contraseña | Iniciar/cerrar sesión, enlace de recuperación con vencimiento, errores claros | A | P0; correo disponible |
| Main, Top Menu, Side Menu, User Menu | Estructura común, navegación, búsqueda de cliente, perfil y sesión | A | P0; idioma español, traducciones fuera del piloto |
| Clientes, Nuevo, Editar | Lista paginada, búsqueda, alta y modificación; formulario único reutilizado desde agenda | A | P0 |
| Ficha del cliente | Datos, citas, ventas, seña, abonado y saldo; accesos contextuales | A + B/C por sus datos | P0 |
| Importar/exportar clientes | CSV con plantilla fija, validación por fila y reporte de errores; sin importador configurable | A | P1 |
| Agenda | Día/semana/mes, filtros de empleado/categoría/estado, ingresos cobrados y pendientes definidos | B | P0 |
| Nueva cita, Agregar cliente/servicio | Alta/edición/cancelación, duración, profesional y validación de disponibilidad; reutilizar formularios | B | P0; arrastrar y soltar diferible |
| Nueva venta desde agenda y ficha | Mismo flujo compartido; servicios, cantidades si se acuerdan, descuentos, impuestos y total | C | P0 |
| Caja y Buscar venta | Caja diaria, búsqueda, detalle, forma de pago, cliente y empleado | C | P0 |
| Menú de venta: ver/eliminar/ir a ficha | Detalle y ficha; sustituir borrado de venta cobrada por anulación trazable | C | P0 |
| Movimientos de caja y Resumen | Cobros, abonos, entradas/salidas, filtros y resumen por medio de pago | C | P0; formulario de movimiento adicional necesario |
| Arqueo/Cierre | Saldo inicial, efectivo esperado/contado, diferencia y observaciones; bloqueo tras cierre | C | P0; regla de apertura acordada el día 1 |
| Centro y Diseño | Datos del centro, logo validado y color de tema; no editor visual de temas | A | P0 datos; P1 personalización |
| Empleados, Nuevo, Perfil, Editar | Usuarios/empleados, roles fijos, color de citas y edición autorizada | B | P0; permisos base de A |
| Horarios, vacaciones y feriados | Disponibilidad semanal, ausencias y feriados usados al guardar citas | B | P0 |
| Perfil propio, Editar y Vacaciones | Reutilizar ficha de empleado con permisos de autoservicio acotados | B | P0 |
| Categorías y Servicios, altas repetidas | Nombre, referencia, categoría, precio, impuesto, duración, orden y colores | A | P0; conservar relaciones históricas al desactivar |
| SMS y Email desde ficha | Envío individual, validación y registro de intento/resultado | B | P1; credenciales y remitente listos al día 2 |
| Estadísticas de ventas | General, categoría, servicio y género; filtros de fechas, tabla y gráfico simple | C | P1; incluir “sin dato” para género |
| Estadísticas de gastos | General y categoría; requiere categoría de gasto en movimientos | C | P1; alcance añadido para soportar la pantalla |
| Horas totales empleadas | Duración de citas completadas por empleado/período | C | P1; son horas agendadas atendidas, no fichaje real |
| Imprimir y Exportar | Vista imprimible del navegador y CSV para agenda/caja/ventas | B agenda; C finanzas | P1; PDF propio y XLSX avanzado fuera |

Los formularios “Agregar cliente”, “Nuevo cliente” y “Editar cliente” comparten implementación. Lo mismo ocurre con servicios, ventas y perfiles. No se deben desarrollar como sistemas separados por aparecer en distintos marcos.

## Acuerdos obligatorios del día 1

D valida las reglas y A deja una página de decisiones:

1. Roles propuestos: administrador, recepción y profesional; quién ve caja, modifica precios, anula ventas y consulta clientes.
2. Una sede, moneda, zona horaria y criterio para impuestos: incluidos o añadidos. Porcentaje y redondeo explícitos.
3. Una cita ocupa a un empleado; suma de duraciones de servicios, descansos y política ante solapamientos. Excepciones solo con permiso definido.
4. Estados mínimos de cita: pendiente, confirmada, completada, cancelada y ausente; qué estado computa en ingresos y horas.
5. Seña vinculada a cita/cliente y luego aplicada a venta, sin contabilizar dos veces el mismo ingreso. Definir devolución o crédito si se cancela.
6. Venta confirmada, cobros parciales y saldo pendiente. Diferenciar total vendido de dinero efectivamente cobrado.
7. Caja inicial, qué medios afectan efectivo, retiros/gastos y regla para anular antes o después del cierre.
8. Gastos categorizados; significado de estadísticas por género y horas. Evitar inferir género por nombre.
9. CSV esperado, límites iniciales del piloto y estrategia de duplicados: no fusionar clientes automáticamente.
10. Proveedor de correo/SMS, hosting y responsable de credenciales. SMS pendiente de proveedor no se presenta como envío real.

Supuestos de arranque si no existe otra regla: no permitir solapamientos; no borrar ventas cobradas; una caja abierta por centro; personal sin acceso a caja; idioma español. Deben validarse antes de usarse en operación.

## Calendario de ejecución

| Día | Persona A | Persona B | Persona C | Resultado verificable |
|---|---|---|---|---|
| 1 | Repositorio, Laravel, esquema compartido, roles y estructura visual | Mapa de agenda, reglas de disponibilidad y modelos de empleados | Modelo de ventas/pagos/caja y ejemplos de conciliación | Entorno local reproducible, decisiones y contratos de datos cerrados |
| 2 | Login, recuperación, permisos, navegación y despliegue de pruebas | Alta/edición de empleados, perfil y horarios | Migraciones de ventas/pagos/movimientos; cálculo de importes probado | Todos integran sobre MySQL y entorno compartido; verificar proveedores |
| 3 | Clientes: lista, búsqueda, alta y edición | Vacaciones/feriados y calendario con lectura de citas | Venta con detalle, descuento e impuesto usando datos de prueba | Primer recorrido de cliente y primera venta persistida |
| 4 | Servicios/categorías y datos del centro | Crear/editar/cancelar cita, usar catálogos reales y bloquear conflictos | Cobros parciales, seña y asociación cita–venta–pago | Cliente → cita → venta → cobro, integrado en pruebas |
| 5 | Ficha de cliente y búsqueda global; corregir integración | Día/semana/mes y filtros; altas contextuales reutilizadas | Listado/detalle de ventas y resumen básico de caja | Demo con D: flujo principal completo y sin doble contabilización |
| 6 | CSV de clientes y validaciones; completar permisos | Disponibilidad concurrente, perfiles y estados de agenda | Movimientos manuales, categorías de gasto y anulación trazable | Casos negativos y movimientos auditables |
| 7 | Logo/color, revisión de formularios y mensajes | Email/SMS si hay proveedor; impresión/exportación de agenda | Apertura/arqueo/cierre y conciliación por medio de pago | Cierre coherente; bloqueo de nuevas funciones al terminar el día |
| 8 | Integración, permisos y correcciones | Pruebas de agenda, ausencias y navegación | Estadísticas básicas, CSV e impresión de caja/ventas | Versión candidata con reportes simples y pruebas críticas aprobadas |
| 9 | Corregir errores de acceso/clientes; probar respaldo/restauración | Prueba funcional con D y correcciones de agenda | Prueba funcional con D y correcciones de ventas/caja | Aceptación documentada; lista explícita de pendientes |
| 10 | Despliegue, migraciones y verificación del entorno | Datos iniciales y guía de uso | Conciliación final y verificación de reportes | Piloto habilitado solo si pasan criterios; entrega a D |

La funcionalidad de estadísticas del día 8 debe limitarse a consultas sobre el modelo acordado. Si caja no está conciliada al día 7, esas horas pasan a corregir caja y estadísticas se difiere.

Rutina: reunión diaria de 15 minutos, cambios pequeños revisados por otra persona, integración diaria y demostración de un recorrido real al final del día. No reservar la integración para el viernes final.

## Dependencias y contratos para evitar bloqueos

- A entrega esquema base y nombres de campos al terminar el día 1. B y C trabajan con datos de prueba desde el día 2.
- Catálogo y clientes deben estar disponibles al día 4. Hasta entonces B/C consumen los mismos modelos y contratos acordados, sin crear tablas paralelas.
- B expone disponibilidad y operaciones de cita; C recibe el ID de cita al convertirla en venta. A integra las consultas de ambas áreas en la ficha.
- C es dueño de las reglas monetarias. Agenda y estadísticas consumen esos cálculos; no recalculan “ingresos” de otra manera.
- A revisa migraciones compartidas; cada módulo tiene su responsable de interfaz, validación y pruebas.
- Ruta crítica: modelo común → cliente/servicio/empleado → cita → venta/cobro → caja/cierre → aceptación.

## Modelo MySQL inicial

| Área | Tablas propuestas | Relación o regla importante |
|---|---|---|
| Base | centers, users, password_reset_tokens | Usuario pertenece al centro; rol fijo inicialmente |
| Personal | employees, working_hours, employee_leaves, holidays | Horarios de centro y empleado; ausencias excluyen disponibilidad |
| Clientes | clients | Búsqueda por nombre/teléfono/email, baja lógica |
| Catálogo | service_categories, services | Referencia, duración, precio, impuesto y estado activo |
| Agenda | appointments, appointment_services | Cliente y empleado; duración/importe de referencia conservados |
| Ventas | sales, sale_items | Cliente, empleado y cita opcional; estado y precios históricos |
| Cobros | payments, payment_allocations | Seña/cobro y su aplicación; mismo dinero contabilizado una sola vez |
| Caja | cash_sessions, cash_movements, expense_categories | Caja, medio de pago, origen y reversión; cierre inmutable |
| Operación | communication_logs, audit_logs | Autor, fecha, resultado y acciones relevantes |

Ajustar nombres al convenio del equipo. Importes y estados se definen antes de migrar. Índices mínimos en cliente buscable, fecha/empleado de cita, fecha/cliente de venta y caja/fecha de movimiento. Las claves foráneas protegen relaciones; las bajas de catálogos no borran historia.

El diseño de payments/payment_allocations necesita una prueba con seña, saldo y anulación el día 3. Si requiere una contabilidad más compleja, reducir el piloto a reglas financieras acordadas, nunca simular un saldo correcto.

## Pruebas y criterios de entrega

| Recorrido | Debe comprobarse |
|---|---|
| Acceso | Credenciales incorrectas, recuperación vencida, cierre de sesión y restricciones comprobadas en servidor |
| Clientes | Alta, edición, búsqueda, datos inválidos; CSV con fila errónea no produce una importación silenciosamente incorrecta |
| Disponibilidad | Cita fuera de horario, vacaciones, feriado y dos solicitudes simultáneas para el mismo turno |
| Venta y pago | Total calculado en servidor, descuento/impuesto, seña aplicada una vez, pago parcial, reintento sin duplicación |
| Anulación | Motivo y usuario registrados, reversión coherente, sin eliminación del historial |
| Caja | Apertura, entrada/salida, efectivo contado, diferencia y cierre; operaciones posteriores no alteran un cierre |
| Reportes | Separar ventas de cobros; cifras coinciden con datos fuente para el mismo período/estado |
| Comunicaciones | Enviado, pendiente o fallido según respuesta real; no mostrar éxito al fallar proveedor |
| Operación | Persistencia tras recargar, validaciones, mensajes útiles, restauración de respaldo y despliegue reproducible |

Ejemplo de conciliación: apertura de efectivo 1.000; cobro en efectivo 600; cobro con tarjeta 400; retiro en efectivo 100. Efectivo esperado al cierre: 1.500, no 1.900. La tarjeta figura separada. Agregar un caso con seña para verificar que no se duplica al completar el cobro.

La aceptación del día 10 requiere cero fallas críticas de autenticación, permisos, dobles reservas y dinero. Debe poder reproducirse el circuito centro → servicio → empleado → cliente → cita → venta → cobro → cierre con datos persistidos. Si falla una condición crítica, entregar en pruebas y posponer el uso operativo.

## Control del plazo y recortes explícitos

- Día 2: si falta proveedor, mantener preparada la integración y registrar SMS/correo como pendiente; no afirmar entrega de envíos reales.
- Día 5: si el circuito principal no está integrado, usar reserva y quitar P1 antes de abrir nuevas tareas.
- Día 7: detener nuevas funciones y estabilizar.
- Día 9: D prueba con una lista de casos; los defectos críticos bloquean salida.

Orden sugerido para diferir: personalización visual e idiomas adicionales; CSV de importación si requiere limpieza de datos reales; SMS; gráficos y desgloses estadísticos secundarios; mejoras de calendario como arrastrar citas. Mantener una lista visible de cada omisión.

No recortar permisos, validación de turnos, exactitud de importes, trazabilidad de cobros/anulaciones, cierre de caja ni respaldo.

Fuera del piloto salvo sustitución explícita de otras tareas: facturación fiscal, pasarela de pagos, campañas masivas, múltiples sedes/empresas, suscripciones, reservas públicas, aplicación móvil y fichaje laboral. Estos requisitos no se deducen de las pantallas de SISTEMA.

## Entregables del día 10

- Código fuente, dependencias bloqueadas, migraciones y datos iniciales.
- Entorno desplegado, configuración documentada sin secretos en el repositorio.
- Matriz de roles y reglas de agenda/ventas/caja.
- Evidencia de pruebas críticas y conciliación.
- Guía breve: configurar centro, crear cita, cobrar, anular y cerrar caja.
- Procedimiento de respaldo/restauración y recuperación de un despliegue fallido.
- Lista de cobertura por pantalla, limitaciones del piloto y pendientes priorizados.


## Inventario de referencia: 44 marcos verificados

Esta lista conserva los nombres del draft, incluso sus variantes y erratas. Los marcos de navegación y formularios repetidos se implementan mediante componentes compartidos.

| Marco | Referencia |
|---|---|
| Login | [3:2](https://www.figma.com/design/I23Px5r3BAfCUpLiHan7ro/Bloome?node-id=3-2) |
| Top Menu | [6:137](https://www.figma.com/design/I23Px5r3BAfCUpLiHan7ro/Bloome?node-id=6-137) |
| Side Menu | [6:30](https://www.figma.com/design/I23Px5r3BAfCUpLiHan7ro/Bloome?node-id=6-30) |
| Caja | [3:112](https://www.figma.com/design/I23Px5r3BAfCUpLiHan7ro/Bloome?node-id=3-112) |
| Clientes | [8:953](https://www.figma.com/design/I23Px5r3BAfCUpLiHan7ro/Bloome?node-id=8-953) |
| Configuracion - Empresa - Empleados  | [13:2838](https://www.figma.com/design/I23Px5r3BAfCUpLiHan7ro/Bloome?node-id=13-2838) |
| Configuracion - Ventas - Servicios  | [15:503](https://www.figma.com/design/I23Px5r3BAfCUpLiHan7ro/Bloome?node-id=15-503) |
| Configuracion - Agenda - Feriado  | [18:735](https://www.figma.com/design/I23Px5r3BAfCUpLiHan7ro/Bloome?node-id=18-735) |
| Configuracion - Ventas - Categoria | [15:430](https://www.figma.com/design/I23Px5r3BAfCUpLiHan7ro/Bloome?node-id=15-430) |
| Configuracion - Empresa - Empleados - Perfil  | [14:198](https://www.figma.com/design/I23Px5r3BAfCUpLiHan7ro/Bloome?node-id=14-198) |
| Configuracion - Agenda - Horario  | [18:590](https://www.figma.com/design/I23Px5r3BAfCUpLiHan7ro/Bloome?node-id=18-590) |
| Clientes - Ficha del Cliente | [8:1073](https://www.figma.com/design/I23Px5r3BAfCUpLiHan7ro/Bloome?node-id=8-1073) |
| Clientes - Ficha del Cliente - Nueva Venta | [9:1800](https://www.figma.com/design/I23Px5r3BAfCUpLiHan7ro/Bloome?node-id=9-1800) |
| Agenda | [6:692](https://www.figma.com/design/I23Px5r3BAfCUpLiHan7ro/Bloome?node-id=6-692) |
|  Caja - Buscar Venta | [6:425](https://www.figma.com/design/I23Px5r3BAfCUpLiHan7ro/Bloome?node-id=6-425) |
| Caja - Resumen | [6:259](https://www.figma.com/design/I23Px5r3BAfCUpLiHan7ro/Bloome?node-id=6-259) |
| Caja - Cierre  | [6:336](https://www.figma.com/design/I23Px5r3BAfCUpLiHan7ro/Bloome?node-id=6-336) |
| Main | [6:75](https://www.figma.com/design/I23Px5r3BAfCUpLiHan7ro/Bloome?node-id=6-75) |
| Recuperar Contraseña | [3:73](https://www.figma.com/design/I23Px5r3BAfCUpLiHan7ro/Bloome?node-id=3-73) |
| User Menu | [6:230](https://www.figma.com/design/I23Px5r3BAfCUpLiHan7ro/Bloome?node-id=6-230) |
| Caja - Buscar Venta - Menú | [6:493](https://www.figma.com/design/I23Px5r3BAfCUpLiHan7ro/Bloome?node-id=6-493) |
| Caja - Movimientos de Caja | [6:508](https://www.figma.com/design/I23Px5r3BAfCUpLiHan7ro/Bloome?node-id=6-508) |
| Agenda - Nueva Cita | [7:778](https://www.figma.com/design/I23Px5r3BAfCUpLiHan7ro/Bloome?node-id=7-778) |
| Configuración - Empresa -  Centro | [13:2625](https://www.figma.com/design/I23Px5r3BAfCUpLiHan7ro/Bloome?node-id=13-2625) |
| Agenda - Nueva Venta | [7:871](https://www.figma.com/design/I23Px5r3BAfCUpLiHan7ro/Bloome?node-id=7-871) |
| Agenda - Nueva Cita - Agregar Cliente | [7:820](https://www.figma.com/design/I23Px5r3BAfCUpLiHan7ro/Bloome?node-id=7-820) |
| Configuracion - Empresa - Empleados - Nuevo | [13:2899](https://www.figma.com/design/I23Px5r3BAfCUpLiHan7ro/Bloome?node-id=13-2899) |
| Ventas - Categoria - Nuevo | [15:482](https://www.figma.com/design/I23Px5r3BAfCUpLiHan7ro/Bloome?node-id=15-482) |
| Configuracion - Agenda - Feriado - Nuevo | [18:789](https://www.figma.com/design/I23Px5r3BAfCUpLiHan7ro/Bloome?node-id=18-789) |
| Configuracion - Ventas - Servicios - Nuevo | [18:563](https://www.figma.com/design/I23Px5r3BAfCUpLiHan7ro/Bloome?node-id=18-563) |
| Configuracion - Empresa - Empleados - Perfil  - Editar | [14:415](https://www.figma.com/design/I23Px5r3BAfCUpLiHan7ro/Bloome?node-id=14-415) |
| Configuracion - Empresa - Empleados - Perfil  - Vacaciones | [18:953](https://www.figma.com/design/I23Px5r3BAfCUpLiHan7ro/Bloome?node-id=18-953) |
| User Menu - Perfil - Vacaciones | [18:978](https://www.figma.com/design/I23Px5r3BAfCUpLiHan7ro/Bloome?node-id=18-978) |
| User Menu - Perfil | [18:803](https://www.figma.com/design/I23Px5r3BAfCUpLiHan7ro/Bloome?node-id=18-803) |
| User Menu - Perfil - Editar | [18:921](https://www.figma.com/design/I23Px5r3BAfCUpLiHan7ro/Bloome?node-id=18-921) |
| Clientes -  Ficha del Cliente - Editar | [9:1495](https://www.figma.com/design/I23Px5r3BAfCUpLiHan7ro/Bloome?node-id=9-1495) |
| Clientes - Nuevo | [8:1048](https://www.figma.com/design/I23Px5r3BAfCUpLiHan7ro/Bloome?node-id=8-1048) |
| Agenda - Nueva Cita - Agregar Servicio | [7:848](https://www.figma.com/design/I23Px5r3BAfCUpLiHan7ro/Bloome?node-id=7-848) |
| Clientes - Ficha del Cliente - SMS | [9:1465](https://www.figma.com/design/I23Px5r3BAfCUpLiHan7ro/Bloome?node-id=9-1465) |
| Clientes - Ficha del Cliente - Emai | [9:1483](https://www.figma.com/design/I23Px5r3BAfCUpLiHan7ro/Bloome?node-id=9-1483) |
| Clientes - Ficha del Cliente - Nueva Venta - Nuevo Servicio | [10:2438](https://www.figma.com/design/I23Px5r3BAfCUpLiHan7ro/Bloome?node-id=10-2438) |
| Estadisticas | [11:2455](https://www.figma.com/design/I23Px5r3BAfCUpLiHan7ro/Bloome?node-id=11-2455) |
| Configuración | [13:2562](https://www.figma.com/design/I23Px5r3BAfCUpLiHan7ro/Bloome?node-id=13-2562) |
| Configuración - Empresa - Diseño | [13:2756](https://www.figma.com/design/I23Px5r3BAfCUpLiHan7ro/Bloome?node-id=13-2756) |
