<?php
defined('ABSPATH') || exit;
// Integer cents and thousandths: no binary floating point in financial decisions.
function cep_units($value, $places=2) {
    if (!is_scalar($value) || !preg_match('/^\d{1,'.(10-$places).'}(?:\.\d{1,'.$places.'})?$/D', (string)$value)) throw new InvalidArgumentException('Importe o cantidad inválida. Usa números positivos y punto decimal.');
    $parts = explode('.', (string)$value);
    return (int)$parts[0] * (10 ** $places) + (int)str_pad($parts[1] ?? '', $places, '0');
}
function cep_decimal($units, $places=2) {
    $sign = $units < 0 ? '-' : '';
    $units = abs($units);
    return $sign.intdiv($units,10 ** $places).'.'.str_pad((string)($units % (10 ** $places)), $places, '0', STR_PAD_LEFT);
}
function cep_round_ratio($n, $d) { return intdiv($n + intdiv($d,2), $d); }
function cep_session() {
    global $wpdb;
    $s = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}cep_caja_sesiones WHERE usuario_id=%d AND estado='abierta' ORDER BY id DESC LIMIT 1 FOR UPDATE",get_current_user_id()));
    if (!$s) throw new RuntimeException('Abre tu turno en Caja y Arqueo antes de operar.');
    return $s;
}
function cep_idempotent($key, $payload, callable $fn) {
    global $wpdb;
    if (!is_string($key) || !preg_match('/^[a-zA-Z0-9_-]{16,64}$/D', $key)) throw new InvalidArgumentException('Falta el identificador de operación. Recarga la pantalla.');
    $hash = hash('sha256',wp_json_encode($payload));
    return cep_transaction(function () use ($wpdb,$key,$payload,$fn,$hash) {
        $old = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}cep_operaciones WHERE clave=%s",$key));
        if ($old) {
            if ((int)$old->usuario_id !== get_current_user_id() || !hash_equals($old->huella,$hash)) throw new RuntimeException('Este identificador ya corresponde a otra operación.');
            return json_decode($old->respuesta,true);
        }
        $response=$fn();
        cep_insert('cep_operaciones',['clave'=>$key,'usuario_id'=>get_current_user_id(),'huella'=>$hash,'respuesta'=>wp_json_encode($response),'fecha'=>current_time('mysql')]);
        return $response;
    });
}
function cep_movement($product,$qty,$reason,$sale=0) {
    cep_insert('cep_movimientos',['producto_id'=>$product,'cantidad'=>cep_decimal($qty,3),'motivo'=>$reason,'venta_id'=>$sale,'usuario_id'=>get_current_user_id(),'fecha'=>current_time('mysql')]);
}
function cep_ajax(callable $fn, $admin=false) {
    cep_verificar_acceso_cajero();
    if ($admin) cep_verificar_acceso_admin();
    check_ajax_referer('cep_tpv_venta','nonce');
    try { $r=$fn(); } catch (Throwable $e) { wp_send_json_error($e->getMessage(),400); }
    wp_send_json_success($r);
}

function cep_multiply($a,$b) { if ($a<0 || $b<0 || ($b && $a>intdiv(PHP_INT_MAX,$b))) throw new InvalidArgumentException('El importe excede el rango de cálculo seguro.'); return $a*$b; }
