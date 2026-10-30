# Crackstenio TPV 8.2.0

Plugin instalable de WordPress, basado en el archivo pos.php 8.1.0 proporcionado por la propietaria. Toda la operación se realiza en wp-admin. No requiere GitHub, WooCommerce ni servicios de pago externos.

**Estado: versión de evaluación con núcleo financiero probado; no certificada para producción ni para comercialización inmediata.** Antes de usar dinero o existencias reales, revisa `docs/INFORME.md` y la lista de validación comercial.

## Instalación sencilla

1. Haz un respaldo completo de WordPress y su base de datos; pruébalo en una copia del sitio.
2. Si tienes instalado el TPV anterior, **desactívalo primero**. No actives dos versiones a la vez: comparten funciones y tablas. No borres su base de datos.
3. Ve a **Plugins → Añadir plugin → Subir plugin** y selecciona el ZIP `cep-tpv-8.2.0.zip` sin descomprimir.
4. Activa **Crackstenio TPV** con un usuario administrador. Las tablas `cep_*` anteriores se conservan y se añaden los campos y registros de la nueva versión.
5. En **TPV Abarrotes → Configuración**, registra el nombre del negocio, pie y ancho del ticket.
6. Crea los productos o importa un CSV. Revisa precios, existencias e impuestos antes de operar.
7. En **Usuarios** de WordPress, asigna el rol **Cajero TPV** a los empleados autorizados. Los suscriptores y otros roles sin `cep_operate` no pueden vender.
8. Abre un turno en **Caja y Arqueo**, registra una venta de prueba y haz un cierre.

Requisitos declarados: WordPress 6.6+, PHP 8.2+ de 64 bits, MySQL/MariaDB con InnoDB y soporte GET_LOCK. Comprobado en WordPress 6.8.3, PHP 8.3.28 y MariaDB 11.4.13. Otras combinaciones requieren validación.

La actualización conserva los datos del archivo 8.1.0 recibido. No se garantiza migración automática desde versiones distintas o esquemas modificados. Una tabla MyISAM bloquea operaciones financieras: un técnico debe respaldarla y convertirla a InnoDB. No se convierte automáticamente.

## Documentación

- `docs/MANUAL.md`: ventas, inventario, caja, crédito, reintegros y respaldos.
- `docs/INFORME.md`: auditoría, cambios, pruebas, límites y lista de preparación comercial.
- `docs/ARQUITECTURA.md`: módulos, datos, transacciones y actualización.
- `docs/CAMBIOS.md`: registro de cambios.
- `tests/integration.php`: suite de integración con protección contra ejecución en una base real.

Desactivar o eliminar el plugin desde WordPress conserva sus tablas y opciones. No hay una desinstalación destructiva automática.
