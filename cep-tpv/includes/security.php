<?php
defined('ABSPATH') || exit;

function cep_verificar_ip_autorizada() {
    if(get_option('cep_ip_seguridad_activa') !== '1') {
        return true;
    }
    if(current_user_can('manage_options')) {
        return true;
    }
    $ip_permitida = trim(get_option('cep_ip_caja_oficial', ''));
    if(empty($ip_permitida)) {
        return false;
    }
    $ip_actual = isset($_SERVER['REMOTE_ADDR']) ? trim($_SERVER['REMOTE_ADDR']) : '';
    return ($ip_actual === $ip_permitida);
}

function cep_verificar_acceso_cajero() {
    if(!is_user_logged_in()) {
        wp_send_json_error('Sesión no válida o expirada. Por favor, inicia sesión de nuevo.', 401);
    }
    if(!(current_user_can('cep_operate') || current_user_can('manage_options'))) {
        wp_send_json_error('No tienes permisos suficientes para operar el TPV.', 403);
    }
    if(!cep_verificar_ip_autorizada()) {
        $ip_actual = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field($_SERVER['REMOTE_ADDR']) : 'desconocida';
        wp_send_json_error('Acceso denegado: Esta terminal (IP ' . $ip_actual . ') no está autorizada para operar el TPV.', 403);
    }
    return true;
}

function cep_verificar_acceso_admin() {
    if(!is_user_logged_in()) {
        wp_send_json_error('Sesión requerida.', 401);
    }
    if(!current_user_can('manage_options')) {
        wp_send_json_error('Permisos de administrador requeridos.', 403);
    }
}

// ==========================================
// 3. AJAX ENDPOINTS PARA EL TPV Y PRODUCTOS
// ==========================================
