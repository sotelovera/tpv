<?php
defined('ABSPATH') || exit;
function cep_table($rows) {
    if (!$rows) { echo '<p>No hay registros.</p>'; return; }
    echo '<div class="cep-table-scroll"><table class="widefat striped"><thead><tr>';
    foreach (array_keys((array)$rows[0]) as $k) echo '<th>'.esc_html(str_replace('_',' ',$k)).'</th>';
    echo '</tr></thead><tbody>';
    foreach ($rows as $row) { echo '<tr>'; foreach ((array)$row as $v) echo '<td>'.esc_html((string)$v).'</td>'; echo '</tr>'; }
    echo '</tbody></table></div>';
}
function cep_return($detail_id,$quantity,$reason) {
    global $wpdb;
    $s=cep_session(); $qty=cep_units($quantity,3); $reason=sanitize_text_field($reason);
    if (!$qty || !$reason || strlen($reason)>200) throw new InvalidArgumentException('Cantidad o motivo inválido.');
    $d=$wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}cep_venta_detalles WHERE id=%d FOR UPDATE",$detail_id));
    if (!$d) throw new RuntimeException('No existe el renglón de venta. Los tickets archivados antes de esta versión requieren revisión manual.');
    $sale=$wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}cep_ventas WHERE id=%d FOR UPDATE",$d->venta_id));
    if (!$sale) throw new RuntimeException('La venta original no está disponible.');
    $already=(string)($wpdb->get_var($wpdb->prepare("SELECT SUM(cantidad) FROM {$wpdb->prefix}cep_devoluciones WHERE detalle_id=%d",$detail_id)) ?? '0');
    $old=cep_units($already,3); $sold=cep_units($d->cantidad,3);
    if ($old+$qty>$sold) throw new RuntimeException('La devolución supera la cantidad pendiente.');
    if ($d->unidad==='pieza' && $qty%1000) throw new InvalidArgumentException('Devuelve piezas enteras.');
    $line=cep_units($d->subtotal);
    $refund=cep_round_ratio(($old+$qty)*$line,$sold)-cep_round_ratio($old*$line,$sold);
    if ($refund<=0) throw new RuntimeException('El reintegro redondea a cero; agrupa las cantidades a devolver.');
    if ($sale->metodo_pago==='Crédito') {
        $c=$wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}cep_clientes WHERE id=%d FOR UPDATE",$sale->cliente_id));
        if (!$c || cep_units($c->saldo_actual)<$refund) throw new RuntimeException('El crédito ya fue abonado. Se requiere conciliación manual antes de devolver.');
        cep_check($wpdb->update($wpdb->prefix.'cep_clientes',['saldo_actual'=>cep_decimal(cep_units($c->saldo_actual)-$refund)],['id'=>$c->id]));
    } else {
        if ($refund>cep_cash_totals($s)['esperado']) throw new RuntimeException('Efectivo insuficiente para el reintegro.');
        cep_insert('cep_efectivo',['sesion_id'=>$s->id,'importe'=>cep_decimal(-$refund),'motivo'=>'Devolución venta #'.$sale->id,'usuario_id'=>get_current_user_id(),'fecha'=>current_time('mysql')]);
    }
    if ($d->producto_id) {
        if ($wpdb->query($wpdb->prepare("UPDATE {$wpdb->prefix}cep_productos SET stock=stock+%s WHERE id=%d",cep_decimal($qty,3),$d->producto_id))!==1) throw new RuntimeException('No se encuentra el producto original.');
        cep_movement($d->producto_id,$qty,'Devolución: '.$reason,$sale->id);
    }
    $id=cep_insert('cep_devoluciones',['venta_id'=>$sale->id,'detalle_id'=>$detail_id,'cantidad'=>cep_decimal($qty,3),'importe'=>cep_decimal($refund),'motivo'=>$reason,'sesion_id'=>$s->id,'usuario_id'=>get_current_user_id(),'fecha'=>current_time('mysql')]);
    cep_audit('devolucion',['id'=>$id,'reintegro'=>cep_decimal($refund),'medio'=>$sale->metodo_pago==='Crédito'?'saldo de crédito':'efectivo']);
    return ['devolucion_id'=>$id,'reintegro'=>cep_decimal($refund)];
}
function cep_stock_change($input) {
    global $wpdb;
    $id=absint($input['producto_id'] ?? 0); $qty=cep_units($input['cantidad'] ?? '',3);
    $reason=sanitize_text_field($input['motivo'] ?? '');
    if (!$qty || !$reason || strlen($reason)>200) throw new InvalidArgumentException('Cantidad o motivo inválido.');
    $p=$wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}cep_productos WHERE id=%d FOR UPDATE",$id));
    if (!$p || (!$p->maneja_granel && $qty%1000)) throw new RuntimeException('Producto o cantidad inválidos.');
    $out=($input['tipo'] ?? '')==='salida_stock'; $delta=$out?-$qty:$qty;
    if (cep_units($p->stock,3)+$delta<0) throw new RuntimeException('No hay existencias suficientes.');
    if (($input['tipo'] ?? '')==='compra') {
        $provider=absint($input['proveedor_id'] ?? 0); $cost=cep_units($input['costo'] ?? '');
        if (!$wpdb->get_var($wpdb->prepare("SELECT id FROM {$wpdb->prefix}cep_proveedores WHERE id=%d",$provider))) throw new RuntimeException('Proveedor no válido.');
        cep_insert('cep_compras',['proveedor_id'=>$provider,'producto_id'=>$id,'cantidad'=>cep_decimal($qty,3),'costo'=>cep_decimal($cost),'referencia'=>sanitize_text_field($input['referencia'] ?? ''),'usuario_id'=>get_current_user_id(),'fecha'=>current_time('mysql')]);
        cep_check($wpdb->update($wpdb->prefix.'cep_productos',['precio_costo'=>cep_decimal($cost)],['id'=>$id]));
    }
    cep_check($wpdb->query($wpdb->prepare("UPDATE {$wpdb->prefix}cep_productos SET stock=stock+%s WHERE id=%d",cep_decimal($delta,3),$id)));
    cep_movement($id,$delta,$reason); cep_audit('inventario',['producto'=>$id,'cantidad'=>cep_decimal($delta,3),'motivo'=>$reason]);
    return ['registrado'=>true];
}
function cep_customer_payment($id,$amount) {
    global $wpdb;
    $s=cep_session(); $n=cep_units($amount);
    $c=$wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}cep_clientes WHERE id=%d FOR UPDATE",$id));
    if (!$c || !$n || $n>cep_units($c->saldo_actual)) throw new RuntimeException('Cliente inválido o abono superior al saldo.');
    cep_check($wpdb->update($wpdb->prefix.'cep_clientes',['saldo_actual'=>cep_decimal(cep_units($c->saldo_actual)-$n)],['id'=>$id]));
    cep_insert('cep_abonos',['cliente_id'=>$id,'sesion_id'=>$s->id,'importe'=>cep_decimal($n),'usuario_id'=>get_current_user_id(),'fecha'=>current_time('mysql')]);
    cep_insert('cep_efectivo',['sesion_id'=>$s->id,'importe'=>cep_decimal($n),'motivo'=>'Abono cliente #'.$id,'usuario_id'=>get_current_user_id(),'fecha'=>current_time('mysql')]);
    cep_audit('abono',['cliente'=>$id,'importe'=>cep_decimal($n)]);
    return ['registrado'=>true];
}
add_action('admin_menu',function () {
    add_submenu_page('cep-tpv','Movimientos y devoluciones','Movimientos','manage_options','cep-operaciones','cep_operations_page');
},20);
function cep_operations_page() {
    if (!current_user_can('manage_options')) wp_die('Sin permisos.');
    global $wpdb;
    echo '<div class="wrap"><h1>Movimientos, compras y devoluciones</h1><p>Los reintegros de ventas de contado se entregan en efectivo. Las ventas a crédito reducen la deuda pendiente.</p>';
    if ($_SERVER['REQUEST_METHOD']==='POST') {
        check_admin_referer('cep_ops','cep_nonce');
        try {
            $input=wp_unslash($_POST); $key=$input['operacion'] ?? ''; unset($input['cep_nonce'],$input['_wp_http_referer'],$input['operacion']);
            $result=cep_idempotent($key,$input,function () use ($input) {
                if (($input['tipo'] ?? '')==='devolver') return cep_return(absint($input['detalle_id'] ?? 0),$input['cantidad'] ?? '',$input['motivo'] ?? '');
                if (($input['tipo'] ?? '')==='abono') return cep_customer_payment(absint($input['cliente_id'] ?? 0),$input['importe'] ?? '');
                if (!in_array($input['tipo'] ?? '',['entrada_stock','salida_stock','compra'],true)) throw new RuntimeException('Operación inválida.');
                return cep_stock_change($input);
            });
            echo '<div class="notice notice-success"><p>Movimiento registrado. '.esc_html(isset($result['reintegro'])?'Reintegro: $'.$result['reintegro']:'').'</p></div>';
        } catch (Throwable $e) { echo '<div class="notice notice-error"><p>'.esc_html($e->getMessage()).'</p></div>'; }
    }
    $forms=[
        'entrada_stock'=>['Entrada de inventario',['producto_id'=>'ID del producto','cantidad'=>'Cantidad','motivo'=>'Motivo']],
        'salida_stock'=>['Salida / pérdida',['producto_id'=>'ID del producto','cantidad'=>'Cantidad','motivo'=>'Motivo']],
        'compra'=>['Recepción de compra',['producto_id'=>'ID del producto','proveedor_id'=>'ID del proveedor','cantidad'=>'Cantidad','costo'=>'Costo unitario','referencia'=>'Factura / referencia','motivo'=>'Motivo']],
        'abono'=>['Abono de crédito en efectivo',['cliente_id'=>'ID del cliente','importe'=>'Importe']],
        'devolver'=>['Devolver un renglón de venta',['detalle_id'=>'ID del renglón (consulta Historial)','cantidad'=>'Cantidad','motivo'=>'Motivo']],
    ];
    foreach ($forms as $type=>[$label,$fields]) {
        echo '<form method="post" class="cep-operation"><h2>'.esc_html($label).'</h2>'; wp_nonce_field('cep_ops','cep_nonce');
        echo '<input type="hidden" name="operacion" value="'.esc_attr(wp_generate_uuid4()).'"><input type="hidden" name="tipo" value="'.esc_attr($type).'">';
        foreach ($fields as $key=>$title) echo '<p><label>'.esc_html($title).' <input name="'.esc_attr($key).'" required maxlength="100"></label></p>';
        echo '<button class="button button-primary" onclick="return confirm(\'¿Confirmar movimiento?\')">Registrar</button></form>';
    }
    echo '<h2>Últimos movimientos de existencias</h2>'; cep_table($wpdb->get_results("SELECT * FROM {$wpdb->prefix}cep_movimientos ORDER BY id DESC LIMIT 100",ARRAY_A));
    echo '<h2>Últimas compras</h2>'; cep_table($wpdb->get_results("SELECT * FROM {$wpdb->prefix}cep_compras ORDER BY id DESC LIMIT 50",ARRAY_A));
    echo '<h2>Auditoría reciente</h2>'; cep_table($wpdb->get_results("SELECT * FROM {$wpdb->prefix}cep_auditoria ORDER BY id DESC LIMIT 50",ARRAY_A)); echo '</div>';
}
