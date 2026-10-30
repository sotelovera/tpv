<?php
defined('ABSPATH') || exit;

function cep_instalar_tablas_v7() {
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();
    require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

    $sql_productos = "CREATE TABLE {$wpdb->prefix}cep_productos (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        sku varchar(50) NOT NULL,
        nombre varchar(150) NOT NULL,
        categoria varchar(50) DEFAULT 'General',
        precio_venta decimal(10,2) NOT NULL,
        precio_costo decimal(10,2) NOT NULL,
        stock decimal(10,3) NOT NULL DEFAULT 0.000,
        maneja_granel tinyint(1) DEFAULT 0,
        PRIMARY KEY  (id),
        UNIQUE KEY sku_idx (sku),
        KEY nombre_idx (nombre),
        KEY categoria_idx (categoria)
    ) $charset_collate;";
    dbDelta( $sql_productos );

    $sql_promos = "CREATE TABLE {$wpdb->prefix}cep_promociones (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        producto_id mediumint(9) NOT NULL,
        tipo_promo varchar(30) NOT NULL,
        cantidad_requerida int DEFAULT 1,
        precio_oferta decimal(10,2) NOT NULL,
        PRIMARY KEY  (id),
        KEY producto_id_idx (producto_id)
    ) $charset_collate;";
    dbDelta( $sql_promos );

    $sql_caja = "CREATE TABLE {$wpdb->prefix}cep_caja_sesiones (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        caja_id varchar(50) DEFAULT 'Caja 01',
        cajero varchar(100),
        fondo_inicial decimal(10,2) NOT NULL,
        efectivo_cierre decimal(10,2) DEFAULT 0.00,
        ventas_efectivo decimal(10,2) DEFAULT 0.00,
        ventas_tarjeta decimal(10,2) DEFAULT 0.00,
        ventas_transferencia decimal(10,2) DEFAULT 0.00,
        diferencia decimal(10,2) DEFAULT 0.00,
        estado varchar(20) DEFAULT 'abierta',
        fecha_apertura datetime DEFAULT CURRENT_TIMESTAMP,
        fecha_cierre datetime DEFAULT NULL,
        PRIMARY KEY  (id),
        KEY estado_cajero_idx (cajero, estado),
        KEY caja_id_idx (caja_id)
    ) $charset_collate;";
    dbDelta( $sql_caja );

    $sql_ventas = "CREATE TABLE {$wpdb->prefix}cep_ventas (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        folio varchar(50) NOT NULL,
        total decimal(10,2) NOT NULL,
        metodo_pago varchar(30) DEFAULT 'Efectivo',
        cajero varchar(100),
        caja_id varchar(50) DEFAULT 'Caja 01',
        cliente_id mediumint(9) DEFAULT 0,
        detalles_json longtext DEFAULT NULL,
        fecha datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        KEY caja_fecha_idx (caja_id, fecha),
        KEY cliente_idx (cliente_id)
    ) $charset_collate;";
    dbDelta( $sql_ventas );

    // Detalle de cada ticket: permite consultar los productos vendidos posteriormente.
    $sql_venta_detalles = "CREATE TABLE {$wpdb->prefix}cep_venta_detalles (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        venta_id mediumint(9) NOT NULL,
        producto_id mediumint(9) DEFAULT 0,
        sku varchar(50) DEFAULT '',
        producto varchar(150) NOT NULL,
        cantidad decimal(10,3) NOT NULL DEFAULT 0.000,
        unidad varchar(20) DEFAULT 'pieza',
        precio_unitario decimal(10,2) NOT NULL DEFAULT 0.00,
        forma_venta_granel varchar(20) DEFAULT '',
        importe_solicitado decimal(10,2) DEFAULT NULL,
        subtotal decimal(10,2) NOT NULL DEFAULT 0.00,
        PRIMARY KEY (id),
        KEY venta_id_idx (venta_id),
        KEY producto_id_idx (producto_id)
    ) $charset_collate;";
    dbDelta( $sql_venta_detalles );

    // Copia permanente del reporte de cada turno cerrado. Se guarda antes de
    // limpiar las ventas operativas para que el historial pueda consultarse e imprimirse.
    $sql_reportes_turno = "CREATE TABLE {$wpdb->prefix}cep_reportes_turno (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        sesion_id mediumint(9) NOT NULL,
        caja_id varchar(50) DEFAULT '',
        cajero varchar(100) DEFAULT '',
        fecha_apertura datetime DEFAULT NULL,
        fecha_cierre datetime DEFAULT NULL,
        fondo_inicial decimal(10,2) DEFAULT 0.00,
        ventas_efectivo decimal(10,2) DEFAULT 0.00,
        ventas_tarjeta decimal(10,2) DEFAULT 0.00,
        ventas_transferencia decimal(10,2) DEFAULT 0.00,
        ventas_credito decimal(10,2) DEFAULT 0.00,
        total_ventas decimal(10,2) DEFAULT 0.00,
        efectivo_cierre decimal(10,2) DEFAULT 0.00,
        diferencia decimal(10,2) DEFAULT 0.00,
        cantidad_ventas int DEFAULT 0,
        ventas_json longtext,
        PRIMARY KEY (id),
        UNIQUE KEY sesion_id_idx (sesion_id)
    ) $charset_collate;";
    dbDelta( $sql_reportes_turno );

    $sql_proveedores = "CREATE TABLE {$wpdb->prefix}cep_proveedores (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        nombre_empresa varchar(150) NOT NULL,
        contacto varchar(100),
        telefono varchar(30),
        email varchar(100),
        PRIMARY KEY  (id)
    ) $charset_collate;";
    dbDelta( $sql_proveedores );

    $sql_clientes = "CREATE TABLE {$wpdb->prefix}cep_clientes (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        nombre varchar(150) NOT NULL,
        telefono varchar(30),
        limite_credito decimal(10,2) DEFAULT 0.00,
        saldo_actual decimal(10,2) DEFAULT 0.00,
        PRIMARY KEY  (id)
    ) $charset_collate;";
    dbDelta( $sql_clientes );

    add_option( 'cep_nombre_tienda', 'Abarrotes El Crackstenio' );
    add_option( 'cep_ticket_pie', '¡Gracias por su compra! Vuelva pronto.' );
    add_option( 'cep_ip_seguridad_activa', '0' );

}


