<?php
defined('ABSPATH') || exit;

add_action( 'admin_menu', 'cep_registrar_menu_v7' );
function cep_registrar_menu_v7() {
    $cap_admin = 'manage_options';
    $cap_cajero = 'cep_operate';

    add_menu_page( 'TPV Abarrotes', 'TPV Abarrotes', $cap_cajero, 'cep-tpv', 'cep_vista_tpv', 'dashicons-store', 2 );
    add_submenu_page( 'cep-tpv', 'Nueva Venta', 'Nueva Venta', $cap_cajero, 'cep-tpv', 'cep_vista_tpv' );
    add_submenu_page( 'cep-tpv', 'Inventario General', 'Inventario', $cap_admin, 'cep-inventario', 'cep_vista_inventario_general' );
    add_submenu_page( 'cep-tpv', 'Importar Productos CSV', 'Importar CSV', $cap_admin, 'cep-importar-excel', 'cep_vista_importar_excel' );
    add_submenu_page( 'cep-tpv', 'Proveedores y Compras', 'Proveedores', $cap_admin, 'cep-proveedores', 'cep_vista_proveedores' );
    add_submenu_page( 'cep-tpv', 'Clientes y Créditos (Fiado)', 'Clientes / Crédito', $cap_admin, 'cep-clientes', 'cep_vista_clientes' );
    add_submenu_page( 'cep-tpv', 'Promociones Mayoreo', 'Promociones', $cap_admin, 'cep-promociones', 'cep_vista_promociones' );
    add_submenu_page( 'cep-tpv', 'Control de Caja', 'Caja y Arqueo', $cap_cajero, 'cep-caja', 'cep_vista_caja' );
    add_submenu_page( 'cep-tpv', 'Perfiles y Usuarios', 'Perfiles de Usuario', $cap_admin, 'cep-perfiles', 'cep_vista_perfiles' );
    add_submenu_page( 'cep-tpv', 'Ventas del día', 'Ventas del día', $cap_cajero, 'cep-reportes', 'cep_vista_reportes' );
    add_submenu_page( 'cep-tpv', 'Respaldos Base de Datos', 'Respaldos', $cap_admin, 'cep-respaldos', 'cep_vista_respaldos' );
    add_submenu_page( 'cep-tpv', 'Báscula USB / Serial', 'Báscula', $cap_cajero, 'cep-tpv-bascula', 'cep_vista_tpv_bascula' );
    add_submenu_page( 'cep-tpv', 'Configuración General', 'Configuración', $cap_admin, 'cep-config', 'cep_vista_config' );
}

add_filter('admin_body_class', 'cep_admin_body_class_v7');
function cep_admin_body_class_v7($classes) {
    if(isset($_GET['page']) && strpos(sanitize_key(wp_unslash($_GET['page'])), 'cep-') === 0) {
        $classes .= ' cep-plugin-admin ';
    }
    if(isset($_GET['page']) && sanitize_key(wp_unslash($_GET['page'])) === 'cep-tpv') {
        $classes .= ' cep-tpv-screen ';
    }
    return $classes;
}

add_action('admin_enqueue_scripts', function ($hook) {
    if (strpos($hook, 'cep-') === false) return;
    wp_enqueue_style('cep-admin', plugins_url('assets/admin.css', CEP_FILE), [], CEP_VERSION);
    wp_enqueue_script('jquery');
});
