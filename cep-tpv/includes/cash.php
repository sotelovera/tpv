<?php
defined('ABSPATH') || exit;
function cep_open($station,$fund) {
    global $wpdb;
    $station=sanitize_text_field($station); $fund=cep_units($fund);
    if (!$station || strlen($station)>50) throw new InvalidArgumentException('Nombre de caja inválido.');
    $busy=$wpdb->get_var($wpdb->prepare("SELECT id FROM {$wpdb->prefix}cep_caja_sesiones WHERE estado='abierta' AND (caja_id=%s OR usuario_id=%d)",$station,get_current_user_id()));
    if ($busy) throw new RuntimeException('La caja o el usuario ya tiene un turno abierto.');
    $id=cep_insert('cep_caja_sesiones',['caja_id'=>$station,'usuario_id'=>get_current_user_id(),'cajero'=>wp_get_current_user()->display_name,'fondo_inicial'=>cep_decimal($fund),'estado'=>'abierta','fecha_apertura'=>current_time('mysql')]);
    cep_audit('apertura',['sesion'=>$id,'fondo'=>cep_decimal($fund)]);
    return ['sesion_id'=>$id];
}
function cep_cash_totals($session) {
    global $wpdb;
    $sid=(int)$session->id;
    $rows=$wpdb->get_results($wpdb->prepare("SELECT metodo,SUM(importe) importe FROM {$wpdb->prefix}cep_pagos WHERE sesion_id=%d GROUP BY metodo",$sid));
    $totals=['Efectivo'=>0,'Tarjeta'=>0,'Transferencia'=>0,'Crédito'=>0];
    foreach ($rows as $r) $totals[$r->metodo]=cep_units($r->importe);
    // Include pre-upgrade operational sales, bounded by opening time; no inference when names are ambiguous.
    $old=$wpdb->get_results($wpdb->prepare("SELECT metodo_pago,SUM(total) importe FROM {$wpdb->prefix}cep_ventas WHERE sesion_id=0 AND caja_id=%s AND cajero=%s AND fecha >= %s GROUP BY metodo_pago",$session->caja_id,$session->cajero,$session->fecha_apertura));
    foreach ($old as $r) if (isset($totals[$r->metodo_pago])) $totals[$r->metodo_pago]+=cep_units($r->importe);
    $movements=$wpdb->get_col($wpdb->prepare("SELECT importe FROM {$wpdb->prefix}cep_efectivo WHERE sesion_id=%d",$sid));
    $extra=0;
    foreach ($movements as $m) $extra+=($m[0]==='-' ? -cep_units(substr($m,1)) : cep_units($m));
    return ['pagos'=>$totals,'movimientos'=>$extra,'esperado'=>cep_units($session->fondo_inicial)+$totals['Efectivo']+$extra];
}
function cep_close($count) {
    global $wpdb;
    $count=cep_units($count); $s=cep_session(); $totals=cep_cash_totals($s); $p=$totals['pagos'];
    $sales=$wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}cep_ventas WHERE sesion_id=%d OR (sesion_id=0 AND caja_id=%s AND cajero=%s AND fecha >= %s) ORDER BY id",$s->id,$s->caja_id,$s->cajero,$s->fecha_apertura),ARRAY_A);
    $data=['sesion_id'=>$s->id,'caja_id'=>$s->caja_id,'cajero'=>$s->cajero,'fecha_apertura'=>$s->fecha_apertura,'fecha_cierre'=>current_time('mysql'),'fondo_inicial'=>$s->fondo_inicial,'ventas_efectivo'=>cep_decimal($p['Efectivo']),'ventas_tarjeta'=>cep_decimal($p['Tarjeta']),'ventas_transferencia'=>cep_decimal($p['Transferencia']),'ventas_credito'=>cep_decimal($p['Crédito']),'total_ventas'=>cep_decimal(array_sum($p)),'efectivo_cierre'=>cep_decimal($count),'diferencia'=>cep_decimal($count-$totals['esperado']),'cantidad_ventas'=>count($sales),'ventas_json'=>wp_json_encode(['ventas'=>$sales,'esperado_efectivo'=>cep_decimal($totals['esperado'])])];
    $report=cep_insert('cep_reportes_turno',$data);
    $update=$data; foreach (['sesion_id','ventas_credito','total_ventas','cantidad_ventas','ventas_json'] as $key) unset($update[$key]);
    $update['estado']='cerrada';
    cep_check($wpdb->update($wpdb->prefix.'cep_caja_sesiones',$update,['id'=>$s->id]));
    cep_audit('cierre',['sesion'=>$s->id,'reporte'=>$report,'diferencia'=>$data['diferencia']]);
    return ['reporte_id'=>$report,'diferencia'=>$data['diferencia']];
}
function cep_cash_move($amount,$reason,$out=false) {
    $s=cep_session(); $n=cep_units($amount); $reason=sanitize_text_field($reason);
    if (!$n || !$reason || strlen($reason)>200) throw new InvalidArgumentException('Introduce un importe positivo y un motivo de hasta 200 caracteres.');
    if ($out && $n>cep_cash_totals($s)['esperado']) throw new RuntimeException('Efectivo disponible insuficiente.');
    cep_insert('cep_efectivo',['sesion_id'=>$s->id,'importe'=>cep_decimal($out?-$n:$n),'motivo'=>$reason,'usuario_id'=>get_current_user_id(),'fecha'=>current_time('mysql')]);
    cep_audit('efectivo',['sesion'=>$s->id,'salida'=>$out,'importe'=>cep_decimal($n),'motivo'=>$reason]);
    return ['registrado'=>true];
}
function cep_vista_caja() {
    cep_verificar_acceso_cajero(); global $wpdb;
    echo '<div class="wrap"><h1>Caja y arqueo</h1>';
    if ($_SERVER['REQUEST_METHOD']==='POST') {
        check_admin_referer('cep_cash','cep_nonce');
        try {
            $input=wp_unslash($_POST); $key=$input['operacion'] ?? '';
            unset($input['cep_nonce'],$input['_wp_http_referer'],$input['operacion']);
            $r=cep_idempotent($key,$input,function () use ($input) {
                switch ($input['tipo'] ?? '') {
                    case 'abrir': return cep_open($input['caja_id'] ?? '',$input['importe'] ?? '');
                    case 'cerrar': return cep_close($input['importe'] ?? '');
                    case 'entrada': case 'salida': return cep_cash_move($input['importe'] ?? '',$input['motivo'] ?? '',$input['tipo']==='salida');
                    default: throw new InvalidArgumentException('Operación inválida.');
                }
            });
            echo '<div class="notice notice-success"><p>Operación registrada. '.esc_html(isset($r['diferencia'])?'Diferencia de cierre: $'.$r['diferencia']:'').'</p></div>';
        } catch (Throwable $e) { echo '<div class="notice notice-error"><p>'.esc_html($e->getMessage()).'</p></div>'; }
    }
    $s=$wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}cep_caja_sesiones WHERE usuario_id=%d AND estado='abierta'",get_current_user_id()));
    $types=$s?['entrada'=>'Entrada de efectivo','salida'=>'Salida de efectivo','cerrar'=>'Arqueo y cierre']:['abrir'=>'Abrir turno'];
    if ($s) echo '<p>Turno #'.(int)$s->id.' · '.esc_html($s->caja_id).' · Fondo $'.esc_html($s->fondo_inicial).'</p>';
    foreach ($types as $type=>$label) {
        echo '<form method="post" class="cep-operation"><h2>'.esc_html($label).'</h2>';
        wp_nonce_field('cep_cash','cep_nonce');
        echo '<input type="hidden" name="operacion" value="'.esc_attr(wp_generate_uuid4()).'"><input type="hidden" name="tipo" value="'.esc_attr($type).'">';
        if ($type==='abrir') echo '<p><label>Caja <input name="caja_id" maxlength="50" required value="Caja 01"></label></p>';
        echo '<p><label>'.($type==='cerrar'?'Efectivo contado':($type==='abrir'?'Fondo inicial':'Importe')).' $ <input name="importe" type="number" min="0" step="0.01" required></label></p>';
        if (in_array($type,['entrada','salida'],true)) echo '<p><label>Motivo <input name="motivo" maxlength="200" required class="regular-text"></label></p>';
        echo '<button class="button button-primary" onclick="return confirm(\'¿Confirmar esta operación de caja?\')">'.esc_html($label).'</button></form>';
    }
    $where=current_user_can('manage_options')?'':$wpdb->prepare(' WHERE usuario_id=%d',get_current_user_id());
    $rows=$wpdb->get_results("SELECT id,caja_id,cajero,fondo_inicial,estado,fecha_apertura,diferencia FROM {$wpdb->prefix}cep_caja_sesiones $where ORDER BY id DESC LIMIT 50",ARRAY_A);
    echo '<h2>Últimos turnos</h2>'; cep_table($rows); echo '</div>';
}
