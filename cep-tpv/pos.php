<?php
/**
 * Plugin Name: Crackstenio TPV
 * Description: Punto de venta integrado en wp-admin. Ventas, inventario, crédito y caja con trazabilidad.
 * Version: 8.2.0
 * Requires at least: 6.6
 * Requires PHP: 8.2
 * Author: Nereyda Ixchel Tuyín López
 */
defined('ABSPATH') || exit;
if (function_exists('cep_instalar_tablas_v7')) {
    add_action('admin_notices',function () { echo '<div class="notice notice-error"><p>Desactiva la versión anterior del TPV antes de activar esta versión. Los datos se conservan.</p></div>'; });
    return;
}
define('CEP_FILE', __FILE__);
define('CEP_VERSION', '8.2.0');
foreach (['installation','database','security','domain','sales','cash','operations','admin','search','pos','catalog','import','suppliers','customers','promotions','users','reports','backup','scale','settings'] as $module) {
    require_once __DIR__ . '/includes/' . $module . '.php';
}
register_activation_hook(__FILE__, 'cep_upgrade');
add_action('admin_init', function () {
    if (current_user_can('manage_options') && get_option('cep_version_db') !== CEP_VERSION) cep_upgrade();
});
