# Manual básico

## Configurar

El administrador registra el nombre de la tienda y el pie de ticket. Selecciona 58 u 80 mm; después configura el papel y los márgenes en el controlador de impresión. Esta versión se probó generando PDF, no con impresoras físicas. El ticket es un comprobante de venta, no una factura fiscal electrónica.

La restricción IP es opcional. Acepta una dirección válida; usa la IP que WordPress detecta, que puede ser la de un proxy inverso. Un administrador siempre conserva acceso para corregirla.

## Productos e inventario

Registra SKU/código de barras como texto para conservar ceros iniciales, nombre, categoría, costo, precio de venta, existencias, mínimo y porcentaje de impuesto. El impuesto se **añade al precio**: el precio del catálogo es antes de impuestos. Se redondea cada renglón a centavos.

Los productos por pieza solo admiten cantidades enteras; los productos a granel admiten tres decimales en kg. Al editar, el sistema rechaza sobrescribir un stock que haya cambiado mientras tenías abierto el formulario.

Archivar un producto lo retira de la búsqueda de venta sin borrar sus movimientos. Puedes editarlo y volver a marcarlo activo. Para entradas, pérdidas y ajustes utiliza **Movimientos**; registra un motivo. Los movimientos indican usuario y fecha. Las existencias bajas se consultan en Reportes.

El CSV acepta hasta 2 MB y las columnas:

```csv
sku,nombre,categoria,precio_costo,precio_venta,stock,maneja_granel
00001234,Producto ejemplo,Abarrotes,8.50,12.00,20,0
GRANEL01,Arroz,Granel,15.00,30.00,12.500,1
```

Importa con la tienda detenida: el CSV reemplaza el stock absoluto de los SKU existentes. Cada fila es una transacción independiente. Las filas válidas permanecen guardadas si otras tienen errores; el resultado indica cuáles fallaron. La importación conserva el ID del producto y no modifica su impuesto, mínimo o estado actuales. Un producto nuevo usa impuesto 0 y mínimo 5. La exportación del catálogo mantiene las siete columnas; no constituye un respaldo completo.

## Caja

Cada usuario abre un solo turno y cada caja admite un solo turno abierto. Registra el fondo inicial antes de vender. Las entradas y salidas en efectivo requieren un motivo y se incluyen en el arqueo. No registres como entrada el efectivo de ventas: el sistema ya lo incluye.

Al finalizar, cuenta el efectivo e introduce el monto real en **Arqueo y cierre**. El sistema guarda la diferencia contra fondo + pagos en efectivo + entradas − salidas − reintegros. No borra ventas ni detalles. Los abonos de crédito recibidos en efectivo también entran en el cálculo.

Los turnos antiguos se asignan al ID del usuario solo si su nombre visible identifica a una única persona. Los nombres ambiguos requieren conciliación por un técnico; no reasignes datos a ciegas. Los cajeros consultan únicamente sus propios turnos y ventas nuevas; los administradores consultan todo.

## Vender

1. Busca por nombre o escanea un código y pulsa Enter. Los códigos se comparan exactamente, incluidos los ceros iniciales.
2. Para granel, escribe el peso o un importe con `$`, por ejemplo `$15.00`. El servidor calcula el peso y redondea a 0.001 kg; el total definitivo puede variar por ese redondeo. Revísalo antes de cobrar.
3. Modifica cantidades o quita renglones. Espera el total calculado por el servidor. Las promociones por paquete se aplican automáticamente.
4. Selecciona cliente y medio de pago. En efectivo, captura lo recibido. En mixto, las partes en efectivo, tarjeta y transferencia deben sumar exactamente el total, sin cambio.
5. Un administrador puede aplicar un descuento global en dinero antes de impuestos. El sistema reparte el descuento entre los renglones y recalcula impuestos.
6. Confirma el cobro y luego imprime. El cobro no depende de que la impresión tenga éxito. Desde Reportes también puedes abrir y reimprimir el ticket.

F2 enfoca la búsqueda y F8 solicita confirmar cobro. La pantalla se adapta a móviles, aunque se recomienda un monitor para operación continua.

**Nuevo / suspender** guarda el ticket actual y abre otro. Usa el selector para recuperarlo. Los tickets pendientes se guardan por usuario y sitio en este navegador, no se sincronizan entre equipos y no reservan stock. No borres el almacenamiento ni cambies de navegador si tienes un cobro sin confirmar. Se conserva hasta un máximo de 20 tickets por navegador.

Si falla la conexión durante el cobro, pulsa **Reintentar cobro**: se conserva la carga y el identificador originales. No captures la misma venta de nuevo. Los controles del ticket quedan bloqueados mientras el resultado es incierto. Si la sesión expira, inicia sesión con el mismo usuario y vuelve a esta pantalla. Un cambio en otra pestaña bloquea esta pestaña y solicita recarga.

Los datos del último ticket y los pendientes permanecen en el almacenamiento del navegador del empleado. Usa perfiles de navegador separados en equipos compartidos y limpia esos datos al retirar una terminal, después de conciliar sus cobros.

Tarjeta y transferencia son registros manuales de pagos confirmados fuera del plugin; no se conectan a terminales bancarias ni procesan tarjetas.

## Crédito y clientes

El administrador registra clientes y límites en **Clientes / Crédito**. La venta a crédito necesita un cliente existente y saldo disponible. El servidor vuelve a comprobar el límite al guardar.

Los abonos se registran en **Movimientos → Abono de crédito en efectivo**, con turno abierto; se asocian al cliente, usuario y caja. No pueden superar la deuda pendiente. El formulario anterior se sustituyó por un acceso al flujo trazable de Movimientos.

Archivar clientes conserva sus datos; no se permite archivar un cliente con deuda. Esta versión no incluye una pantalla para reactivar clientes o proveedores archivados: requiere intervención técnica controlada.

## Compras y proveedores

Registra proveedores y consulta sus IDs. En **Movimientos → Recepción de compra**, indica proveedor, producto, cantidad, costo unitario y referencia. La recepción aumenta stock, actualiza el costo actual y conserva el registro de compra. No hay órdenes de compra ni cuentas por pagar.

## Devoluciones

Solo un administrador puede registrarlas y necesita un turno abierto. Consulta la venta en Reportes y utiliza el **ID del renglón**, no el ID del producto, en Movimientos. Introduce cantidad y motivo; repite para otros renglones si devuelves toda la venta.

El sistema limita la devolución a la cantidad aún no devuelta, calcula el reintegro proporcional con ajuste de centavos, devuelve las existencias y registra la auditoría. Las ventas de contado, tarjeta, transferencia o mixtas se reintegran **en efectivo** y requieren efectivo disponible. No realiza reembolsos bancarios automáticos.

Las ventas a crédito reducen el saldo pendiente. Si ese saldo ya es menor que el reintegro, se bloquea y requiere conciliación manual; no se inventa un saldo a favor. Los tickets eliminados por cierres de la versión anterior solo tienen su archivo histórico: no admiten devolución automática.

## Reportes, respaldo y recuperación

Filtra ventas por fecha. Los reportes muestran ventas brutas; los reintegros están separados. Los productos más vendidos usan importes de renglón que incluyen impuesto y descuento. No deben interpretarse como utilidad neta. El historial visual presenta hasta 100 ventas por periodo, 100 productos y 50 cierres recientes.

**Respaldos → Descargar respaldo SQL** incluye todas las tablas `cep_*`, sus estructuras y opciones `cep_*`. Contiene datos sensibles: guárdalo de forma privada. No incluye usuarios de WordPress, archivos multimedia ni otros plugins; para migrar de sitio necesitas además el respaldo completo de WordPress.

Un técnico debe ensayar la restauración en una base de datos aparte con la misma tabla de opciones y prefijo. El SQL reemplaza las tablas TPV de destino y conserva NULL y texto mediante codificación hexadecimal. No lo ejecutes directamente sobre producción sin un respaldo completo comprobado y un procedimiento de reversión. No hay botón de restauración destructiva en el plugin.

## Periféricos

Un lector que funciona como teclado puede introducir códigos en la búsqueda; el flujo fue probado con entrada de teclado automatizada, no con lectores físicos. La pantalla Báscula conserva una conexión Web Serial experimental a 9600 baudios; necesita HTTPS y un navegador compatible. No se ha validado ningún modelo, protocolo de peso o dispositivo. Usa captura manual hasta probar y calibrar el equipo concreto. No se promete compatibilidad USB genérica.
