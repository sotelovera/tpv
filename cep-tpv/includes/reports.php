<?php
defined('ABSPATH') || exit;
function cep_vista_reportes() {
    cep_verificar_acceso_cajero(); global $wpdb;
    $admin=current_user_can('manage_options'); $uid=get_current_user_id();
    $sale_id=absint($_GET['venta'] ?? 0);
    if ($sale_id) {
        $sale=$wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}cep_ventas WHERE id=%d",$sale_id));
        if (!$sale || (!$admin && (int)$sale->usuario_id!==$uid)) wp_die('No tienes acceso a esta venta.',403);
        $details=json_decode($sale->detalles_json,true);
        if (!is_array($details)) $details=[];
        $width=get_option('cep_ticket_ancho','80')==='58'?'50mm':'72mm';
        echo '<div class="wrap"><p class="cep-no-print"><a href="'.esc_url(admin_url('admin.php?page=cep-reportes')).'">Volver al historial</a> <button class="button" onclick="window.print()">Imprimir ticket</button></p><div id="cep-receipt"><article class="cep-printed-ticket" style="width:'.esc_attr($width).'"><h2>'.esc_html(get_option('cep_nombre_tienda','Mi tienda')).'</h2><p>'.esc_html($sale->folio).'<br>'.esc_html($sale->fecha).'<br>'.esc_html($sale->cajero).'</p><hr>';
        foreach ($details as $d) echo '<p><strong>'.esc_html($d['producto'] ?? '').'</strong><br>'.esc_html($d['cantidad'] ?? '').' '.esc_html($d['unidad'] ?? '').' × $'.esc_html($d['precio_unitario'] ?? '').'<br>$'.esc_html($d['subtotal'] ?? '').'</p>';
        echo '<hr><p>Descuento: $'.esc_html($sale->descuento).'<br>Impuestos: $'.esc_html($sale->impuestos).'<br><strong>Total: $'.esc_html($sale->total).'</strong><br>'.esc_html($sale->metodo_pago).'</p><p>'.esc_html(get_option('cep_ticket_pie','Gracias')).'</p></article></div></div>'; return;
    }
    $from=sanitize_text_field(wp_unslash($_GET['desde'] ?? wp_date('Y-m-d')));
    $to=sanitize_text_field(wp_unslash($_GET['hasta'] ?? wp_date('Y-m-d')));
    foreach ([$from,$to] as $date) if (!preg_match('/^\d{4}-\d{2}-\d{2}$/D',$date) || !checkdate((int)substr($date,5,2),(int)substr($date,8,2),(int)substr($date,0,4))) wp_die('Fecha inválida.');
    if ($from>$to) wp_die('Rango de fechas inválido.');
    $filter=$wpdb->prepare('fecha BETWEEN %s AND %s',$from.' 00:00:00',$to.' 23:59:59');
    if (!$admin) $filter.=$wpdb->prepare(' AND usuario_id=%d',$uid);
    echo '<div class="wrap"><h1>Historial de ventas y reportes</h1><form method="get"><input type="hidden" name="page" value="cep-reportes"><label>Desde <input type="date" name="desde" value="'.esc_attr($from).'"></label> <label>Hasta <input type="date" name="hasta" value="'.esc_attr($to).'"></label> <button class="button">Consultar</button></form><p>Las ventas se conservan al cerrar el turno. Los reintegros se muestran por separado.</p>';
    cep_table($wpdb->get_results("SELECT metodo_pago,COUNT(*) tickets,SUM(total) total FROM {$wpdb->prefix}cep_ventas WHERE $filter GROUP BY metodo_pago",ARRAY_A));
    if ($admin) {
        echo '<h2>Ventas por empleado</h2>'; cep_table($wpdb->get_results("SELECT usuario_id,cajero,COUNT(*) tickets,SUM(total) total FROM {$wpdb->prefix}cep_ventas WHERE $filter GROUP BY usuario_id,cajero",ARRAY_A));
        echo '<h2>Productos vendidos</h2>'; cep_table($wpdb->get_results("SELECT d.producto_id,d.producto,SUM(d.cantidad) cantidad,SUM(d.subtotal) total FROM {$wpdb->prefix}cep_venta_detalles d INNER JOIN {$wpdb->prefix}cep_ventas v ON v.id=d.venta_id WHERE v.$filter GROUP BY d.producto_id,d.producto ORDER BY total DESC LIMIT 100",ARRAY_A));
        echo '<h2>Existencias bajas</h2>'; cep_table($wpdb->get_results("SELECT id,sku,nombre,stock,stock_minimo FROM {$wpdb->prefix}cep_productos WHERE activo=1 AND stock<=stock_minimo ORDER BY stock LIMIT 100",ARRAY_A));
    }
    $sales=$wpdb->get_results("SELECT id,folio,total,metodo_pago,cajero,fecha FROM {$wpdb->prefix}cep_ventas WHERE $filter ORDER BY id DESC LIMIT 100",ARRAY_A);
    echo '<h2>Últimas 100 ventas del periodo</h2>';
    foreach ($sales as $sale) {
        echo '<details class="cep-operation"><summary>'.esc_html('#'.$sale['id'].' · '.$sale['folio'].' · $'.$sale['total'].' · '.$sale['fecha']).'</summary>';
        cep_table([$sale]);
        cep_table($wpdb->get_results($wpdb->prepare("SELECT id,producto_id,sku,producto,cantidad,unidad,precio_unitario,subtotal FROM {$wpdb->prefix}cep_venta_detalles WHERE venta_id=%d",$sale['id']),ARRAY_A));
        echo '<p><a class="button" href="'.esc_url(admin_url('admin.php?page=cep-reportes&venta='.(int)$sale['id'])).'">Ver / imprimir ticket</a></p></details>';
    }
    echo '<h2>Últimos reintegros</h2>';
    $own=$admin?'':$wpdb->prepare(' WHERE usuario_id=%d',$uid);
    cep_table($wpdb->get_results("SELECT * FROM {$wpdb->prefix}cep_devoluciones $own ORDER BY id DESC LIMIT 100",ARRAY_A));
    echo '<h2>Últimos cierres</h2>';
    $own=$admin?'':$wpdb->prepare(' WHERE sesion_id IN (SELECT id FROM '.$wpdb->prefix.'cep_caja_sesiones WHERE usuario_id=%d)',$uid);
    $rows=$wpdb->get_results("SELECT id,sesion_id,caja_id,cajero,fecha_cierre,total_ventas,efectivo_cierre,diferencia FROM {$wpdb->prefix}cep_reportes_turno $own ORDER BY id DESC LIMIT 50",ARRAY_A);
    cep_table($rows);
    foreach ($rows as $row) {
        $json=$wpdb->get_var($wpdb->prepare("SELECT ventas_json FROM {$wpdb->prefix}cep_reportes_turno WHERE id=%d",$row['id']));
        $archived=json_decode($json,true);
        echo '<details class="cep-operation"><summary>Contenido archivado del cierre #'.(int)$row['id'].'</summary>';
        $flat=[];
        foreach (($archived['ventas'] ?? []) as $v) $flat[]=['folio'=>$v['folio'] ?? '', 'fecha'=>$v['fecha'] ?? '', 'metodo'=>$v['metodo_pago'] ?? '', 'total'=>$v['total'] ?? ''];
        cep_table($flat); echo '</details>';
    }
    echo '<p>El respaldo SQL incluye también los cierres históricos y su contenido archivado.</p></div>';
}
