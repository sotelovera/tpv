# Informe de entrega: Crackstenio TPV 8.2.0

Fecha de trabajo: 30 de octubre de 2026 (UTC). Origen: archivo `pos.php` de 182 881 bytes, versión declarada 8.1.0, suministrado en esta conversación. No se utilizó GitHub para desarrollar ni entregar el plugin.

## Resultado y alcance

Se entrega un ZIP instalable con código modular, una pantalla de ventas integrada en wp-admin y un núcleo de ventas, caja e inventario con pruebas reales. **No se declara listo para producción ni para comercializar sin las validaciones pendientes de este informe.** Se trabajó sobre una copia del archivo recibido: el adjunto original permanece intacto. No hubo acceso a la base real de la tienda.

## Diagnóstico inicial

El proyecto recibido era un único archivo de unas 3 476 líneas que combinaba instalación, SQL, endpoints, interfaz, estilos, JavaScript, impresión y respaldo. No incluía carpetas adicionales, dependencias externas declaradas, pruebas, manual ni proceso de empaquetado.

Existían catálogo con SKU y granel, CSV, clientes con crédito, proveedores básicos, promociones por paquete, múltiples tickets en memoria, cobro AJAX, apertura/cierre, reportes, respaldo y una prueba de báscula serial. No existían un registro de compras, libro de movimientos, pagos mixtos, devoluciones, impuestos configurables ni idempotencia persistente.

Problemas confirmados en la inspección:

- Menús y endpoints permitían operar con `read`, incluyendo usuarios ajenos a la tienda.
- Las sesiones se identificaban por nombre visible, que puede repetirse o cambiar.
- La venta iniciaba una transacción sin verificar los motores y descontaba stock sin comprobar disponibilidad; tampoco comprobaba COMMIT.
- El precio por importe de granel se recibía del navegador sin derivar una cantidad consistente.
- No había protección de servidor contra registrar dos veces un cobro.
- El efectivo recibido solo se comprobaba en JavaScript.
- Las piezas aceptaban cantidades fraccionarias; algunos renglones inválidos se omitían en vez de rechazar el ticket.
- El cierre consultaba ventas antes de iniciar su transacción y después borraba las ventas originales y sus detalles.
- La apertura podía competir con otra apertura sobre la misma caja.
- El CSV utilizaba REPLACE, que puede borrar y recrear productos con un ID distinto.
- Las actualizaciones absolutas de stock no advertían sobre una venta concurrente.
- Los abonos de deuda no tenían historial ni vínculo con el efectivo del turno.
- La impresión volvía a calcular importes en JavaScript y podía diferir de promociones o granel guardados; interpolaba datos sin escape apropiado.
- El buscador de promociones interpolaba atributos HTML sin escape suficiente.
- El respaldo se intentaba descargar después de generar la página administrativa, no contenía estructuras y trataba NULL como texto vacío.
- La función de acceso al cajero no devolvía true; una comprobación posterior impedía utilizar la pantalla de báscula incluso a usuarios autorizados.
- La búsqueda SKU tenía heurísticas que podían confundir códigos diferentes al eliminar ceros.
- La búsqueda por texto hacía una consulta adicional de promociones por producto y devolvía costos a cajeros.

Se buscó `postbox` sin distinguir mayúsculas en todo el archivo y en el plugin resultante: **cero referencias en el código**. No había integración que eliminar; no se alteraron funciones legítimas del núcleo de WordPress. Tampoco había frontend público, shortcode ni integración real con WooCommerce.

## Decisiones y cambios

Se conservaron tablas y prefijos existentes y se separaron 20 módulos PHP, el punto de entrada, recursos y documentación. Se mantuvo el nombre y autoría originales. No se introdujeron frameworks.

Se sustituyeron los núcleos de cobro, caja, reportes y respaldo; no se mantuvo una segunda implementación activa de esos flujos. La presentación del catálogo, proveedores, clientes, promociones, ajustes y báscula se extrajo y corrigió donde correspondía.

Se añadió rol Cajero TPV, control por ID del usuario, aritmética entera, cotización del servidor, total confirmado, reintentos persistentes, bloqueos, rollback, auditoría, pagos por sesión, movimientos, compras, abonos y devoluciones. El cierre conserva las ventas. El catálogo archiva productos en vez de borrarlos y la importación mantiene sus IDs.

La pantalla POS se rediseñó con búsqueda, carrito editable con importes, cliente, total destacado, impuestos, descuento administrativo, efectivo/cambio, pagos mixtos, confirmación de cobro, recuperación local y atajos F2/F8. Se conserva la entrada de granel por peso o importe. La impresión usa los importes guardados y puede repetirse dentro de wp-admin.

## Inventario de funcionalidades entregadas

| Área | Implementado y alcance |
| --- | --- |
| Venta | Búsqueda por nombre y código exacto, piezas/granel, cantidades, paquetes, descuento global administrativo, impuestos añadidos, cliente y confirmación |
| Cobro | Efectivo/cambio, tarjeta manual, transferencia manual, crédito y mixto con suma exacta; reintentos sin duplicado |
| Pendientes | Hasta 20 tickets locales por usuario/sitio/navegador; no sincronizados ni con reserva de stock |
| Catálogo | Alta, edición, archivo/reactivación de productos, SKU, categoría textual, costo, precio, mínimo e impuesto |
| Inventario | Entradas, salidas, pérdidas con motivo, movimientos por venta/devolución, alertas por mínimo |
| Caja | Fondo, entradas/salidas, arqueo, diferencia, cierre, historial, responsable por ID y una sesión por usuario/caja |
| Clientes | Alta, edición, teléfono, límite y deuda; archivo sin borrar historia; abonos efectivos con turno |
| Proveedores/compras | Registro y archivo, recepción de un producto por operación, cantidad/costo/referencia y actualización de stock |
| Devoluciones | Por renglón y cantidad, parcial o total repitiendo renglones, motivo, reintegro efectivo o reducción de crédito, stock y auditoría |
| Tickets | Nombre y pie configurables, líneas, impuesto/descuento/total, medio de pago, cambio en el comprobante de cobro, reimpresión administrativa y ancho 58/80 |
| Reportes | Fecha, ventas por método y empleado, productos vendidos, stock bajo, historial, reintegros separados y cierres archivados |
| Seguridad | Capacidades, nonces, consultas preparadas para entradas, validación financiera de servidor y escape de salidas sensibles |
| Datos | 16 tablas InnoDB verificadas en pruebas, migración aditiva, vínculos lógicos, índices y respaldo SQL completo del TPV |

## Archivos

Se creó `cep-tpv/pos.php` como cargador, más los 20 archivos descritos en `ARQUITECTURA.md`, `assets/pos.js`, `assets/admin.css`, `tests/integration.php`, LEEME y documentación. El inventario exacto del ZIP está en `MANIFIESTO.txt`.

El único archivo de entrada, pos.php, fue refactorizado en la copia de distribución. No se modificó ni eliminó el adjunto original. No se eliminaron archivos del usuario ni tablas de su sitio. Se reemplazaron las implementaciones originales dentro de la nueva distribución, no mediante carga simultánea de versiones.

## Base de datos y migración

Ocho tablas originales conservadas y ocho nuevas: operaciones, movimientos, pagos, efectivo, devoluciones, auditoría, compras y abonos. Campos añadidos: activo/mínimo/impuesto/imagen_id en productos; activo en clientes/proveedores; usuario_id en sesiones; usuario_id, sesion_id, descuento e impuestos en ventas. imagen_id está reservado para una ampliación y no implica un módulo multimedia terminado.

La migración se ensayó instalando el código original en otra base con productos, crédito, una venta, un turno y un reporte histórico. Se verificó conservación de IDs, SKU, cantidades, deuda, venta y JSON archivado. La asignación de turnos antiguos solo se hace con un nombre visible inequívoco. No se aplicó una migración a datos reales del usuario.

Las operaciones financieras se rechazan si falta una tabla o no es InnoDB. No se convierte MyISAM automáticamente. No hay purga ni desinstalación destructiva automática. Se requiere respaldo del sitio antes de actualizar; los pasos están en LEEME y MANUAL.

## Pruebas y evidencia

Entorno ejecutado: WordPress 6.8.3, PHP 8.3.28 de 64 bits, MariaDB 11.4.13 y Chromium 154 con Playwright. Instalación/activación reales en contenedores aislados; consultas y sesiones autenticadas reales. No se conectaron equipos físicos ni la base de una tienda.

| Grupo | Resultado |
| --- | --- |
| Integración de servicios | 39 comprobaciones aprobadas: ventas, exactitud, stock, crédito, pagos, devoluciones, movimientos, caja, rollback y actualización repetible |
| Navegador POS/admin | 25 comprobaciones aprobadas: 13 pantallas con HTTP 200, búsqueda, texto escapado, suspensión/recuperación, cobro, PDF, CSRF, respaldo, móvil y JavaScript |
| Formularios administrativos | 6 comprobaciones aprobadas: alta, ID, importación real, conservación de ID/stock, reimpresión y rechazo anónimo |
| Concurrencia | 4 comprobaciones aprobadas: última pieza vendida una vez y reintentos simultáneos sin doble descuento |
| Actualización desde original | 9 comprobaciones aprobadas: esquema, datos conservados, repetibilidad, rechazo MyISAM y operación tras restaurar InnoDB |
| Permisos HTTP | 3 comprobaciones aprobadas: suscriptor sin acceso a menú, búsqueda y cobro |

**Total: 86 comprobaciones funcionales aprobadas.** Adicionalmente se comprobó sintaxis de los 22 archivos PHP y de pos.js. El respaldo generado por la pantalla se restauró correctamente en una base aparte con tabla de opciones compatible; se recuperaron ventas, claves de operación, devoluciones y opciones. En la restauración final se compararon las 16 tablas TPV y coincidieron todos los checksums de datos. Se generó un PDF de ticket de prueba. Las salidas se incluyen en `docs/resultados/`.

Los intentos iniciales detectaron problemas reales de permisos de lectura de archivos y registro temprano del submenú de Movimientos; fueron corregidos. La primera ejecución del navegador usó una aserción demasiado débil para pantallas denegadas: se cambió a exigir HTTP 200 y se repitió. También se ajustaron pruebas para cantidades normalizadas a tres decimales y la redirección normal de suscriptores al perfil. Los resultados incluidos corresponden a ejecuciones posteriores exitosas; no se cuentan los intentos fallidos como aprobados.

No se ejecutó un analizador de estándares WordPress/PHPStan ni una auditoría de penetración externa. La sintaxis y las pruebas funcionales no sustituyen esa revisión. No se probó MySQL, PHP 8.2/8.4, otras versiones WordPress, multisite ni sitios con otros plugins.

## Pendientes y limitaciones conocidas

- Falta validar una migración sobre una copia completa de la base real, incluidos nombres ambiguos, saldos previos y posibles datos huérfanos.
- No hay recuperación automática de ventas que el plugin anterior ya hubiera borrado; el contenido de sus reportes JSON se conserva.
- No hay integración bancaria, CFDI, facturación fiscal electrónica, contabilidad certificada ni devolución automática a tarjeta/transferencia.
- Las devoluciones de crédito ya abonado requieren conciliación manual. Los reintegros de contado se hacen en efectivo. La reimpresión histórica muestra el total y medio guardados; el cambio recibido se conserva en la respuesta del cobro, no en una columna histórica independiente de venta.
- Faltan un módulo de variantes/atributos, imágenes/descripciones de producto, logotipo y datos fiscales completos en ticket, categorías jerárquicas, múltiples almacenes y transferencias.
- El catálogo tiene búsqueda y paginación; los reportes se limitan a los registros recientes indicados y no incluyen un constructor avanzado, CSV de todos los reportes ni utilidad por costo histórico. El costo actual no se confunde con utilidad contable.
- Faltan búsqueda avanzada/historial dedicado de clientes, edición/reactivación de proveedores y reactivación administrativa de clientes archivados.
- No hay órdenes de compra, compras de múltiples renglones en un solo documento, cuentas por pagar ni conciliación bancaria.
- Los formularios de Movimientos solicitan IDs; funcionan pero necesitan selectores y más pruebas de usabilidad para reducir el aprendizaje de empleados.
- Los tickets suspendidos son locales, no compartidos entre terminales. El cobro requiere conexión con WordPress; no hay ventas offline.
- La coordinación financiera serializa operaciones por sitio. Solo se probó concurrencia pequeña; falta prueba sostenida de volumen y catálogos grandes.
- La pantalla Báscula es experimental. No se comprobó ningún lector, impresora térmica ni báscula físicos. Se necesita protocolo/calibración específicos y validación de 58/80 mm con el equipo real.
- La distribución conserva parte de los formularios administrativos heredados. Se recomienda una revisión adicional de seguridad/estándares y accesibilidad antes de venderla.

## Lista de preparación comercial

| Comprobación | Estado de esta entrega |
| --- | --- |
| ZIP instalable y activación en WordPress de pruebas | Comprobado |
| Operación exclusivamente administrativa y sin integración Postbox | Comprobado en el código recibido y entregado |
| Permisos de cajero y rechazo de suscriptores | Comprobado |
| Transacciones, stock, crédito e idempotencia de cobro | Comprobado en el entorno descrito |
| Devoluciones parciales, arqueo y conservación de ventas | Comprobado en el entorno descrito |
| Migración desde esquema original con datos sintéticos | Comprobado |
| Respaldo descargable y restauración aislada | Comprobado con datos de prueba |
| Impresión de comprobante en navegador/PDF | Comprobado; hardware pendiente |
| Migración y conciliación de los datos reales | Pendiente antes de producción |
| Impresoras, lectores y básculas concretos | Pendiente |
| Auditoría independiente, accesibilidad y estándares | Pendiente |
| Carga sostenida, otras versiones y convivencia con plugins del sitio | Pendiente |
| Revisión fiscal/comercial y funciones faltantes anteriores | Pendiente antes de comercializar |

## Entrega e instalación

Archivo: `cep-tpv-8.2.0.zip`. Instala desde **Plugins → Añadir plugin → Subir plugin**, después de respaldar y desactivar el anterior. No subas el archivo pos.php aislado: la versión nueva necesita los módulos del ZIP. El manual básico y los pasos de actualización están incluidos. Para reutilizar el entorno de desarrollo de esta conversación se guardaron instrucciones de imágenes Docker y arranque en el borrador del entorno; esto no publica el entorno ni afecta a la instalación en tu WordPress.
