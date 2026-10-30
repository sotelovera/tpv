# Cambios en 8.2.0

- Descomposición de pos.php 8.1.0 en núcleo, módulos administrativos, recursos y documentación.
- Rol Cajero TPV explícito; eliminación del acceso financiero por permiso read.
- Precios, cobros, cambio, descuentos e impuestos validados en servidor con aritmética entera.
- Bloqueos coordinados y transacciones InnoDB verificadas.
- Idempotencia persistente por ticket y recuperación de respuestas.
- Protección contra stock negativo, cantidades inválidas y crédito excedido.
- Cierres asociados al ID del usuario, sin eliminación de ventas ni detalles.
- Pagos mixtos y registro desglosado de pagos por sesión.
- Registro de entradas/salidas de efectivo, recepción de compras y abonos.
- Devoluciones parciales trazables con reintegro proporcional y restitución de inventario.
- Catálogo con estado activo, mínimo e impuesto; archivo de productos en vez de borrado.
- CSV conserva identificadores y registra los cambios de stock.
- Respaldo SQL con estructura, NULL correctos, tablas nuevas y opciones; descarga antes de generar la página.
- Pantalla POS original en wp-admin con búsqueda, tickets locales pendientes y reimpresión.
- Impresión de 58/80 mm mediante navegador; sin certificación de hardware.
- Corrección de escape en impresión y búsqueda de promociones.
- Corrección del retorno de autorización que impedía usar la pantalla de báscula.
- No se encontró integración Postbox en el archivo recibido; no se añadió ninguna.
