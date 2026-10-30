<?php
defined('ABSPATH') || exit;
function cep_sale($input, $quote=false) {
    global $wpdb;
    $items=$input['carrito'] ?? [];
    if (!is_array($items) || !$items || count($items)>200) throw new InvalidArgumentException('El ticket debe contener entre 1 y 200 renglones.');
    $session=cep_session();
    $details=[]; $deductions=[]; $base=0;
    foreach ($items as $item) {
        if (!is_array($item)) throw new InvalidArgumentException('Renglón inválido.');
        $qty=cep_units($item['cantidad'] ?? '',3);
        if ($qty<=0) throw new InvalidArgumentException('La cantidad debe ser mayor que cero.');
        $common=!empty($item['esComun']);
        $id=$common ? 0 : absint($item['id'] ?? 0);
        if ($common) {
            if (!current_user_can('manage_options')) throw new RuntimeException('Solo un administrador puede vender conceptos sin catálogo.');
            $name=sanitize_text_field($item['nombre'] ?? '');
            if (!$name || strlen($name)>150 || $qty%1000) throw new InvalidArgumentException('Concepto o cantidad inválida.');
            $price=cep_units($item['precio_venta'] ?? ''); $sku='COMUN'; $bulk=false; $tax=0;
        } else {
            $p=$wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}cep_productos WHERE id=%d FOR UPDATE",$id));
            if (!$p || !$p->activo) throw new RuntimeException('El producto no existe o está inactivo.');
            $name=$p->nombre; $sku=$p->sku; $bulk=(bool)$p->maneja_granel;
            $price=cep_units($p->precio_venta); $tax=cep_units($p->impuesto);
            if ($tax>10000) throw new RuntimeException('Impuesto fuera de rango.');
            if (!$bulk && $qty%1000) throw new InvalidArgumentException('Los productos por pieza requieren cantidades enteras.');
            if ($bulk && ($item['modoGranel'] ?? '')==='importe') {
                $amount=cep_units($item['importeGranel'] ?? '');
                if (!$price || !$amount) throw new InvalidArgumentException('Precio o importe de granel inválido.');
                $qty=cep_round_ratio(cep_multiply($amount,1000),$price);
                if ($qty<=0) throw new InvalidArgumentException('El importe es menor a la unidad mínima de peso.');
            }
            $deductions[$id]=($deductions[$id] ?? 0)+$qty;
            if ($deductions[$id]>cep_units($p->stock,3)) throw new RuntimeException('Existencias insuficientes: '.$name);
        }
        if (!$price) throw new InvalidArgumentException('El precio debe ser mayor que cero.');
        $subtotal=cep_round_ratio(cep_multiply($qty,$price),1000);
        if (!$common && !$bulk) {
            $promo=$wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}cep_promociones WHERE producto_id=%d ORDER BY id LIMIT 1",$id));
            if ($promo && (int)$promo->cantidad_requerida>0) {
                $n=intdiv($qty,1000); $req=(int)$promo->cantidad_requerida;
                $subtotal=intdiv($n,$req)*cep_units($promo->precio_oferta)+($n%$req)*$price;
            }
        }
        $base+=$subtotal;
        $details[]=['producto_id'=>$id,'sku'=>$sku,'producto'=>$name,'cantidad'=>cep_decimal($qty,3),'unidad'=>$bulk?'kg':'pieza','precio_unitario'=>cep_decimal($price),'forma_venta_granel'=>$bulk?'Por peso':'','importe_solicitado'=>null,'subtotal'=>cep_decimal($subtotal),'tasa'=>$tax];
    }
    $discount=cep_units($input['descuento'] ?? '0');
    if ($discount && !current_user_can('manage_options')) throw new RuntimeException('Los descuentos requieren permiso de administrador.');
    if (!$base || $discount>$base) throw new InvalidArgumentException('Total o descuento inválido.');
    // Allocate the discount cumulatively; last line absorbs the rounding remainder.
    $acc=0; $allocated=0; $taxes=0;
    foreach ($details as &$d) {
        $raw=cep_units($d['subtotal']); $acc+=$raw;
        $target=cep_round_ratio(cep_multiply($acc,$discount),$base);
        $net=$raw-($target-$allocated); $allocated=$target;
        $tax=cep_round_ratio(cep_multiply($net,$d['tasa']),10000);
        $d['descuento']=cep_decimal($raw-$net); $d['impuestos']=cep_decimal($tax);
        $d['subtotal']=cep_decimal($net+$tax); unset($d['tasa']); $taxes+=$tax;
    } unset($d);
    $total=$base-$discount+$taxes;
    if ($total<=0 || $total>9999999999) throw new InvalidArgumentException('El total está fuera del rango permitido.');
    if ($quote) return ['total'=>cep_decimal($total),'descuento'=>cep_decimal($discount),'impuestos'=>cep_decimal($taxes),'detalles'=>$details];
    if (isset($input['total_confirmado']) && cep_units($input['total_confirmado'])!==$total) throw new RuntimeException('El precio cambió. Actualiza el ticket y confirma el nuevo total.');
    $method=$input['metodo_pago'] ?? 'Efectivo';
    if (!in_array($method,['Efectivo','Tarjeta','Transferencia','Crédito','Mixto'],true)) throw new InvalidArgumentException('Método de pago inválido.');
    $client=absint($input['cliente_id'] ?? 0);
    if ($client && !$wpdb->get_var($wpdb->prepare("SELECT id FROM {$wpdb->prefix}cep_clientes WHERE id=%d AND activo=1",$client))) throw new RuntimeException('El cliente no existe.');
    $payments=[]; $change=0;
    if ($method==='Mixto') {
        $sum=0;
        foreach (['Efectivo','Tarjeta','Transferencia'] as $m) {
            $amount=cep_units($input['pagos'][$m] ?? '0');
            if ($amount) $payments[$m]=$amount;
            $sum+=$amount;
        }
        if ($sum!==$total) throw new InvalidArgumentException('Los pagos mixtos deben sumar exactamente el total.');
    } elseif ($method==='Crédito') {
        $c=$wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}cep_clientes WHERE id=%d FOR UPDATE",$client));
        if (!$c || cep_units($c->saldo_actual)+$total>cep_units($c->limite_credito)) throw new RuntimeException('Cliente inválido o límite de crédito excedido.');
        cep_check($wpdb->update($wpdb->prefix.'cep_clientes',['saldo_actual'=>cep_decimal(cep_units($c->saldo_actual)+$total)],['id'=>$client]));
        $payments[$method]=$total;
    } else {
        if ($method==='Efectivo') {
            $received=cep_units($input['efectivo_recibido'] ?? '0');
            if ($received<$total) throw new InvalidArgumentException('El efectivo recibido es insuficiente.');
            $change=$received-$total;
        }
        $payments[$method]=$total;
    }
    $folio='TPV-'.strtoupper(str_replace('-','',wp_generate_uuid4()));
    $id=cep_insert('cep_ventas',['folio'=>$folio,'total'=>cep_decimal($total),'metodo_pago'=>$method,'cajero'=>wp_get_current_user()->display_name,'usuario_id'=>get_current_user_id(),'sesion_id'=>$session->id,'caja_id'=>$session->caja_id,'cliente_id'=>$client,'descuento'=>cep_decimal($discount),'impuestos'=>cep_decimal($taxes),'detalles_json'=>wp_json_encode($details),'fecha'=>current_time('mysql')]);
    foreach ($details as &$d) {
        $row=$d; unset($row['descuento'],$row['impuestos']); $row['venta_id']=$id;
        $d['detalle_id']=cep_insert('cep_venta_detalles',$row);
    } unset($d);
    foreach ($deductions as $pid=>$qty) {
        if ($wpdb->query($wpdb->prepare("UPDATE {$wpdb->prefix}cep_productos SET stock=stock-%s WHERE id=%d AND stock>=%s",cep_decimal($qty,3),$pid,cep_decimal($qty,3)))!==1) throw new RuntimeException('No se pudo reservar el inventario.');
        cep_movement($pid,-$qty,'Venta',$id);
    }
    foreach ($payments as $m=>$amount) cep_insert('cep_pagos',['venta_id'=>$id,'sesion_id'=>$session->id,'metodo'=>$m,'importe'=>cep_decimal($amount)]);
    cep_audit('venta',['id'=>$id,'total'=>cep_decimal($total)]);
    return ['venta_id'=>$id,'folio'=>$folio,'total'=>cep_decimal($total),'descuento'=>cep_decimal($discount),'impuestos'=>cep_decimal($taxes),'cambio'=>cep_decimal($change),'metodo_pago'=>$method,'pagos'=>array_map('cep_decimal',$payments),'detalles'=>$details,'caja_id'=>$session->caja_id,'fecha'=>current_time('mysql'),'cajero'=>wp_get_current_user()->display_name,'tienda'=>get_option('cep_nombre_tienda','Mi tienda'),'pie'=>get_option('cep_ticket_pie','Gracias'),'ancho'=>get_option('cep_ticket_ancho','80')];
}
add_action('wp_ajax_cep_procesar_venta',function () {
    cep_ajax(function () {
        $input=wp_unslash($_POST);
        $input['carrito']=json_decode($input['carrito'] ?? '',true);
        $input['pagos']=json_decode($input['pagos'] ?? '{}',true);
        unset($input['nonce'],$input['action']);
        $key=$input['operacion'] ?? ''; unset($input['operacion']);
        return cep_idempotent($key,$input,function () use ($input) { return cep_sale($input); });
    });
});

add_action('wp_ajax_cep_cotizar',function () {
    cep_ajax(function () {
        $input=wp_unslash($_POST); $input['carrito']=json_decode($input['carrito'] ?? '',true);
        return cep_transaction(function () use ($input) { return cep_sale($input,true); });
    });
});
