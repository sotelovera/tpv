<?php
defined('ABSPATH') || exit;

add_action('wp_ajax_cep_buscar_producto', 'cep_ajax_buscar_producto_v7');
function cep_ajax_buscar_producto_v7() {
    cep_verificar_acceso_cajero();
    check_ajax_referer('cep_tpv_nonce', 'nonce');

    global $wpdb;
    $termino = isset($_POST['termino']) ? sanitize_text_field(wp_unslash($_POST['termino'])) : '';
    $termino = trim($termino);
    $tabla = $wpdb->prefix . 'cep_productos';
    $t_promo = $wpdb->prefix . 'cep_promociones';

    if($termino === '') {
        wp_send_json_success([]);
    }

    $productos = $wpdb->get_results($wpdb->prepare(
        "SELECT id, sku, nombre, categoria, precio_venta, precio_costo, stock, maneja_granel
         FROM $tabla
         WHERE activo=1 AND (sku = %s OR nombre LIKE %s)
         ORDER BY CASE WHEN BINARY sku = BINARY %s THEN 0 ELSE 1 END, nombre ASC
         LIMIT 25",
        $termino, '%' . $wpdb->esc_like($termino) . '%', $termino
    ), ARRAY_A);

    if(!is_array($productos)) {
        $productos = [];
    }

    $ids=array_map('absint',array_column($productos,'id')); $promos=[];
    if ($ids) foreach ($wpdb->get_results("SELECT * FROM $t_promo WHERE producto_id IN (".implode(',',$ids).") ORDER BY id",ARRAY_A) as $promo) if (!isset($promos[$promo['producto_id']])) $promos[$promo['producto_id']]=$promo;
    foreach ($productos as &$p) { $p['promo']=$promos[$p['id']] ?? null; if (!current_user_can('manage_options')) unset($p['precio_costo']); } unset($p);

    wp_send_json_success($productos);
}

// Búsqueda exclusiva por código de barras. El SKU se trata siempre como texto puro.
add_action('wp_ajax_cep_buscar_producto_sku', 'cep_ajax_buscar_producto_sku_v7');
function cep_ajax_buscar_producto_sku_v7() {
    cep_verificar_acceso_cajero();
    check_ajax_referer('cep_tpv_nonce', 'nonce');

    global $wpdb;

    $sku = isset($_POST['sku']) ? wp_unslash($_POST['sku']) : '';
    $sku = trim((string) $sku);
    $sku = preg_replace('/[^0-9A-Za-z._\-]/', '', $sku);

    $tabla = $wpdb->prefix . 'cep_productos';
    $t_promo = $wpdb->prefix . 'cep_promociones';

    if ($sku === '') {
        wp_send_json_error('Código de barras vacío.');
    }

    // 1) Coincidencia exacta, carácter por carácter.
    $producto = $wpdb->get_row(
        $wpdb->prepare("SELECT id, sku, nombre, categoria, precio_venta, precio_costo, stock, maneja_granel FROM $tabla WHERE activo=1 AND BINARY sku = BINARY %s LIMIT 1", $sku),
        ARRAY_A
    );

    if (!$producto) {
        wp_send_json_error('Producto no encontrado para el código de barras: ' . $sku);
    }

    $producto['promo'] = $wpdb->get_row(
        $wpdb->prepare("SELECT * FROM $t_promo WHERE producto_id = %d", $producto['id']),
        ARRAY_A
    );

    $producto['_codigo_escaneado'] = $sku;
    $producto['_sku_guardado'] = (string) $producto['sku'];

    if (!current_user_can('manage_options')) unset($producto['precio_costo']);
    wp_send_json_success($producto);
}

