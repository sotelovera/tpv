# Arquitectura técnica

## Estructura

`pos.php` es el punto de entrada, metadatos y carga de módulos. Se conservan los prefijos `cep_` y tablas de la versión recibida para permitir actualización. Los módulos son funciones con prefijo propio, sin frameworks ni dependencias JavaScript adicionales a jQuery incluido en WordPress.

| Archivo | Responsabilidad |
| --- | --- |
| includes/installation.php | Definición de las ocho tablas originales y opciones iniciales |
| includes/database.php | Actualización aditiva, índices, motores y transacciones verificadas |
| includes/domain.php | Conversión decimal a unidades enteras, sesiones, auditoría e idempotencia |
| includes/security.php | Capacidades, autenticación y restricción IP |
| includes/sales.php | Cotización, promociones, descuentos, impuestos, crédito y cobro |
| includes/cash.php | Apertura, entradas/salidas, arqueo y cierre sin borrar ventas |
| includes/operations.php | Movimientos, compras, abonos, devoluciones y consulta de auditoría |
| includes/catalog.php | Catálogo administrativo y sugerencias de búsqueda |
| includes/search.php | Búsqueda AJAX exacta y por nombre, sin revelar costos al cajero |
| includes/import.php | CSV limitado a 2 MB, actualizaciones conservando ID y movimientos |
| includes/customers.php | Clientes y límites de crédito |
| includes/suppliers.php | Registro y archivo de proveedores |
| includes/promotions.php | Promoción por paquete, una por producto para nuevas modificaciones |
| includes/pos.php | Pantalla de operación exclusivamente en wp-admin |
| includes/admin.php | Menús, clases de pantalla y recursos mediante admin_enqueue_scripts |
| includes/reports.php | Historial, resúmenes, archivos de cierres y reimpresión administrativa |
| includes/backup.php | Respaldo SQL consistente y exportación CSV en admin-post |
| includes/users.php | Consulta de perfiles; la asignación se hace con Usuarios de WordPress |
| includes/scale.php | Pantalla experimental Web Serial heredada |
| includes/settings.php | Nombre, pie, ancho del ticket y restricción IP |
| assets/pos.js | Interacción del TPV, recuperación local y reintentos de cobro |
| assets/admin.css | Presentación administrativa y reglas de impresión |

El servidor es la autoridad de precios, impuestos, descuentos, existencias, pagos y crédito. La cotización no reserva productos; el cobro verifica nuevamente y rechaza un total distinto del confirmado.

## Datos

Se conservan las ocho tablas originales: productos, promociones, caja_sesiones, ventas, venta_detalles, reportes_turno, proveedores y clientes. Se añaden ocho tablas: operaciones, movimientos, pagos, efectivo, devoluciones, auditoria, compras y abonos, todas con prefijo `cep_` y el prefijo del sitio WordPress.

Productos incorpora activo, mínimo, impuesto e imagen_id (este último reservado; no hay interfaz de imágenes en esta versión). Clientes y proveedores incorporan activo. Sesiones incorporan usuario_id; ventas incorpora usuario_id, sesion_id, descuento e impuestos. Se añaden índices por usuario/estado, sesión y usuario/fecha. Los índices originales de SKU, nombres, relaciones y fechas permanecen.

No se eliminan tablas ni se purgan tickets. No se reconstruyen ventas ya borradas por cierres de la versión anterior. Los archivos JSON de esos cierres se conservan y pueden consultarse.

Las relaciones lógicas las valida el servicio. No se añaden claves foráneas a tablas históricas para evitar que datos antiguos huérfanos impidan activar el plugin. Cualquier otro código que escriba directamente en estas tablas puede saltarse esas garantías; no hay integración soportada con escritores externos.

## Atomicidad y reintentos

Cada operación crítica comprueba que las 16 tablas son InnoDB y adquiere un GET_LOCK por base y prefijo, con espera máxima de 10 segundos. Coordina turnos, descuentos de stock, devoluciones y reintentos. También utiliza bloqueos de filas en sesiones, productos y clientes, y actualizaciones condicionales de stock. INSERT, UPDATE y COMMIT relevantes se comprueban; una excepción hace ROLLBACK.

Es una estrategia conservadora para una tienda pequeña, que serializa operaciones financieras por sitio. Requiere medición de carga antes de ampliarse a muchas terminales y no ofrece garantías distribuidas entre varios primarios SQL.

El identificador de ticket identifica el cobro. La tabla operaciones guarda clave única, usuario, huella de la solicitud y respuesta. Un reintento idéntico devuelve la venta registrada; otra carga con la misma clave es rechazada. La interfaz persiste la solicitud antes de enviarla y no permite editarla si desconoce el resultado. No hay purga automática de claves.

Se emplean centavos para dinero y milésimas para peso. Los redondeos son enteros, con comprobación del rango de multiplicación. El descuento se distribuye proporcionalmente entre renglones antes de impuestos. Las devoluciones usan diferencias entre importes acumulados redondeados para que todas las parciales sumen el importe original.

## Permisos

`manage_options`: administración, productos, promociones, descuentos, conceptos libres, compras, abonos y devoluciones. `cep_operate`: ventas, caja propia, historial propio y báscula. El rol `cep_cashier` se crea con read y cep_operate; no se otorga acceso POS a roles generales por tener read.

Las rutas AJAX autenticadas verifican capacidad y nonce. No hay shortcodes, rutas públicas del TPV ni endpoints nopriv. Los respaldos utilizan admin-post, capacidad administrativa y nonce, antes de enviar cabeceras.

## Actualizar y revertir

Cierra y concilia turnos, respalda el sitio completo y prueba la copia antes de actualizar. Desactiva la versión anterior antes de activar 8.2.0. La versión de esquema queda en cep_version_db; las operaciones de actualización son aditivas y repetibles para el esquema recibido.

Para revertir después de operar, no basta reemplazar PHP: el código anterior desconoce pagos mixtos, impuestos y movimientos. Restaura una copia completa anterior y concilia aparte cualquier operación posterior. El ZIP original entregado por la propietaria no se modifica y permanece fuera del paquete distribuible.

## Pruebas reproducibles

El archivo tests/integration.php requiere WordPress instalado con este plugin activo en una base llamada exactamente `cep_test`. Elimina registros TPV de esa base, por lo que exige además `CEP_ALLOW_TEST_RESET=yes`. No lo ejecutes en un sitio real. Ejemplo dentro de un contenedor PHP de pruebas con WordPress y MariaDB ya disponibles:

```sh
CEP_ALLOW_TEST_RESET=yes CEP_WP_LOAD=/var/www/html/wp-load.php php /var/www/html/wp-content/plugins/cep-tpv/tests/integration.php
```

El entorno de esta entrega usó imágenes oficiales wordpress:6.8.3-php8.3-apache y mariadb:11.4, una red Docker aislada y WordPress expuesto solo en loopback para las pruebas. Las verificaciones de navegador se ejecutaron con Playwright y Chromium 154, usando la autenticación de la instalación de pruebas. Se impidieron únicamente las consultas ajenas a las pruebas de noticias/actualizaciones externas de WordPress mediante una fixture fuera del plugin. No se desactivó TLS ni ninguna validación del TPV.

Los resultados de la ejecución están en docs/resultados/. No se incluyen contraseñas, cookies, bases de datos de prueba ni respaldos de clientes en el ZIP.
