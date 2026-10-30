<?php
defined('ABSPATH') || exit;
function cep_upgrade() {
    global $wpdb;
    cep_instalar_tablas_v7();
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    $cc = $wpdb->get_charset_collate();
    $schemas = [
        'cep_operaciones' => "id bigint unsigned NOT NULL AUTO_INCREMENT, clave varchar(64) NOT NULL, usuario_id bigint unsigned NOT NULL, huella char(64) NOT NULL, respuesta longtext NOT NULL, fecha datetime NOT NULL, PRIMARY KEY (id), UNIQUE KEY clave (clave)",
        'cep_movimientos' => "id bigint unsigned NOT NULL AUTO_INCREMENT, producto_id bigint unsigned NOT NULL, cantidad decimal(10,3) NOT NULL, motivo varchar(200) NOT NULL, venta_id bigint unsigned NOT NULL DEFAULT 0, usuario_id bigint unsigned NOT NULL, fecha datetime NOT NULL, PRIMARY KEY (id), KEY producto_fecha (producto_id,fecha), KEY venta_id (venta_id)",
        'cep_pagos' => "id bigint unsigned NOT NULL AUTO_INCREMENT, venta_id bigint unsigned NOT NULL, sesion_id bigint unsigned NOT NULL, metodo varchar(30) NOT NULL, importe decimal(10,2) NOT NULL, PRIMARY KEY (id), KEY sesion_metodo (sesion_id,metodo), KEY venta_id (venta_id)",
        'cep_efectivo' => "id bigint unsigned NOT NULL AUTO_INCREMENT, sesion_id bigint unsigned NOT NULL, importe decimal(10,2) NOT NULL, motivo varchar(200) NOT NULL, usuario_id bigint unsigned NOT NULL, fecha datetime NOT NULL, PRIMARY KEY (id), KEY sesion_id (sesion_id)",
        'cep_devoluciones' => "id bigint unsigned NOT NULL AUTO_INCREMENT, venta_id bigint unsigned NOT NULL, detalle_id bigint unsigned NOT NULL, cantidad decimal(10,3) NOT NULL, importe decimal(10,2) NOT NULL, motivo varchar(200) NOT NULL, sesion_id bigint unsigned NOT NULL, usuario_id bigint unsigned NOT NULL, fecha datetime NOT NULL, PRIMARY KEY (id), KEY detalle_id (detalle_id), KEY venta_id (venta_id)",
        'cep_auditoria' => "id bigint unsigned NOT NULL AUTO_INCREMENT, usuario_id bigint unsigned NOT NULL, evento varchar(50) NOT NULL, datos longtext NOT NULL, fecha datetime NOT NULL, PRIMARY KEY (id), KEY evento_fecha (evento,fecha)",
        'cep_compras' => "id bigint unsigned NOT NULL AUTO_INCREMENT, proveedor_id bigint unsigned NOT NULL, producto_id bigint unsigned NOT NULL, cantidad decimal(10,3) NOT NULL, costo decimal(10,2) NOT NULL, referencia varchar(100) NOT NULL, usuario_id bigint unsigned NOT NULL, fecha datetime NOT NULL, PRIMARY KEY (id), KEY proveedor_fecha (proveedor_id,fecha)",
        'cep_abonos' => "id bigint unsigned NOT NULL AUTO_INCREMENT, cliente_id bigint unsigned NOT NULL, sesion_id bigint unsigned NOT NULL, importe decimal(10,2) NOT NULL, usuario_id bigint unsigned NOT NULL, fecha datetime NOT NULL, PRIMARY KEY (id), KEY cliente_id (cliente_id)",
    ];
    foreach ($schemas as $name => $columns) {
        dbDelta("CREATE TABLE {$wpdb->prefix}$name (\n" . preg_replace('/, (?![^()]*\))/', ",\n", $columns) . "\n) ENGINE=InnoDB $cc;");
    }
    $add = [
        'cep_productos' => ['activo'=>'tinyint NOT NULL DEFAULT 1','stock_minimo'=>'decimal(10,3) NOT NULL DEFAULT 5.000','impuesto'=>'decimal(5,2) NOT NULL DEFAULT 0.00','imagen_id'=>'bigint unsigned NOT NULL DEFAULT 0'],
        'cep_clientes' => ['activo'=>'tinyint NOT NULL DEFAULT 1'],
        'cep_proveedores' => ['activo'=>'tinyint NOT NULL DEFAULT 1'],
        'cep_caja_sesiones' => ['usuario_id'=>'bigint unsigned NOT NULL DEFAULT 0'],
        'cep_ventas' => ['usuario_id'=>'bigint unsigned NOT NULL DEFAULT 0','sesion_id'=>'bigint unsigned NOT NULL DEFAULT 0','descuento'=>'decimal(10,2) NOT NULL DEFAULT 0.00','impuestos'=>'decimal(10,2) NOT NULL DEFAULT 0.00'],
    ];
    $ok = true;
    foreach ($add as $name => $cols) foreach ($cols as $col => $type) {
        $table = $wpdb->prefix . $name;
        if (!$wpdb->get_var($wpdb->prepare("SHOW COLUMNS FROM `$table` LIKE %s", $col))) {
            if ($wpdb->query("ALTER TABLE `$table` ADD `$col` $type") === false) $ok = false;
        }
    }
    foreach (['cep_caja_sesiones'=>['usuario_estado'=>'usuario_id,estado'],'cep_ventas'=>['sesion_id'=>'sesion_id','usuario_fecha'=>'usuario_id,fecha']] as $suffix=>$indexes) {
        $table=$wpdb->prefix.$suffix;
        foreach ($indexes as $name=>$cols) if (!$wpdb->get_var($wpdb->prepare("SHOW INDEX FROM `$table` WHERE Key_name=%s",$name))) {
            if ($wpdb->query("ALTER TABLE `$table` ADD INDEX `$name` ($cols)")===false) $ok=false;
        }
    }
    foreach (array_keys($schemas) as $suffix) if (!$wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s',$wpdb->esc_like($wpdb->prefix.$suffix)))) $ok=false;
    // Preserve legacy ownership only when the display name identifies exactly one user.
    $sessions = $wpdb->get_results("SELECT id,cajero FROM {$wpdb->prefix}cep_caja_sesiones WHERE usuario_id=0");
    foreach ($sessions as $s) {
        $ids = $wpdb->get_col($wpdb->prepare("SELECT ID FROM {$wpdb->users} WHERE display_name=%s", $s->cajero));
        if (count($ids) === 1) $wpdb->update($wpdb->prefix.'cep_caja_sesiones', ['usuario_id'=>(int)$ids[0]], ['id'=>$s->id]);
    }
    $role = get_role('administrator');
    if ($role) $role->add_cap('cep_operate');
    add_role('cep_cashier', 'Cajero TPV', ['read'=>true,'cep_operate'=>true]);
    if ($ok && !$wpdb->last_error) update_option('cep_version_db', CEP_VERSION);
}
function cep_check($result) {
    if ($result === false) throw new RuntimeException('No se pudo guardar la operación. No se aplicaron cambios.');
    return $result;
}
function cep_insert($table, $data) {
    global $wpdb;
    cep_check($wpdb->insert($wpdb->prefix.$table, $data));
    return (int)$wpdb->insert_id;
}
function cep_audit($event, $data) {
    cep_insert('cep_auditoria', ['usuario_id'=>get_current_user_id(),'evento'=>$event,'datos'=>wp_json_encode($data),'fecha'=>current_time('mysql')]);
}
function cep_transaction(callable $fn) {
    global $wpdb;
    $tables = ['productos','promociones','caja_sesiones','ventas','venta_detalles','clientes','proveedores','reportes_turno','operaciones','movimientos','pagos','efectivo','devoluciones','auditoria','compras','abonos'];
    $names=array_map(fn($suffix)=>$wpdb->prefix.'cep_'.$suffix,$tables);
    $placeholders=implode(',',array_fill(0,count($names),'%s'));
    $engines=$wpdb->get_results($wpdb->prepare("SELECT TABLE_NAME,ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN ($placeholders)",$names),OBJECT_K);
    foreach ($names as $name) {
        if (!isset($engines[$name]) || strtoupper((string)$engines[$name]->ENGINE)!=='INNODB') throw new RuntimeException('La tabla '.$name.' requiere InnoDB. Solicita a tu administrador un respaldo y conversión antes de operar.');
    }
    // One bounded application lock coordinates stock, cash closure and retries across terminals.
    $lock = 'cep_'.md5(DB_NAME.$wpdb->prefix);
    if ((int)$wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s,10)', $lock)) !== 1) throw new RuntimeException('El TPV está ocupado. Reintenta la misma operación.');
    try {
        cep_check($wpdb->query('START TRANSACTION'));
        $result = $fn();
        cep_check($wpdb->query('COMMIT'));
        return $result;
    } catch (Throwable $e) {
        $wpdb->query('ROLLBACK');
        throw $e;
    } finally {
        $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)', $lock));
    }
}
