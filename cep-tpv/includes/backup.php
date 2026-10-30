<?php
defined('ABSPATH') || exit;
function cep_vista_respaldos() {
    if (!current_user_can('manage_options')) wp_die('Sin permisos.');
    echo '<div class="wrap"><h1>Respaldo completo del TPV</h1><p>Descarga tablas, datos y opciones del TPV. Guarda el archivo fuera del servidor: contiene información comercial y de clientes. La restauración SQL debe realizarla un administrador en una base de datos de prueba antes de reemplazar datos reales.</p><form method="post" action="'.esc_url(admin_url('admin-post.php')).'">';
    wp_nonce_field('cep_backup');
    echo '<input type="hidden" name="action" value="cep_backup"><button class="button button-primary">Descargar respaldo SQL</button></form><h2>Exportar catálogo</h2><form method="post" action="'.esc_url(admin_url('admin-post.php')).'">';
    wp_nonce_field('cep_catalog_export');
    echo '<input type="hidden" name="action" value="cep_catalog_export"><button class="button">Descargar productos CSV</button></form></div>';
}
add_action('admin_post_cep_backup',function () {
    if (!current_user_can('manage_options')) wp_die('Sin permisos.',403);
    check_admin_referer('cep_backup'); global $wpdb;
    // Buffer a consistent snapshot before sending response headers or SQL.
    $tmp=fopen('php://temp/maxmemory:5242880','w+');
    try {
        cep_transaction(function () use ($wpdb,$tmp) {
            fwrite($tmp,"-- Respaldo TPV; restaurar primero en un entorno de prueba.\nSET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\n");
            $tables=$wpdb->get_col($wpdb->prepare('SHOW TABLES LIKE %s',$wpdb->esc_like($wpdb->prefix.'cep_').'%'));
            foreach ($tables as $table) {
                if (!preg_match('/^[A-Za-z0-9_]+$/D',$table)) throw new RuntimeException('Tabla inválida.');
                $create=$wpdb->get_row("SHOW CREATE TABLE `$table`",ARRAY_N);
                if (!$create) throw new RuntimeException('No se pudo respaldar la estructura.');
                fwrite($tmp,"DROP TABLE IF EXISTS `$table`;\n".$create[1].";\n");
                for ($offset=0;;$offset+=500) {
                    $rows=$wpdb->get_results($wpdb->prepare("SELECT * FROM `$table` LIMIT 500 OFFSET %d",$offset),ARRAY_A);
                    if ($wpdb->last_error) throw new RuntimeException('No se pudo completar el respaldo.');
                    foreach ($rows as $row) {
                        $values=array_map(function ($v) { return $v===null?'NULL':"CONVERT(X'".bin2hex((string)$v)."' USING utf8mb4)"; },$row);
                        fwrite($tmp,"INSERT INTO `$table` (`".implode('`,`',array_keys($row))."`) VALUES (".implode(',',$values).");\n");
                    }
                    if (count($rows)<500) break;
                }
            }
            $options=$wpdb->get_results($wpdb->prepare("SELECT option_name,option_value FROM {$wpdb->options} WHERE option_name LIKE %s",$wpdb->esc_like('cep_').'%'));
            foreach ($options as $o) fwrite($tmp,"INSERT INTO `{$wpdb->options}` (option_name,option_value,autoload) VALUES (CONVERT(X'".bin2hex($o->option_name)."' USING utf8mb4),CONVERT(X'".bin2hex($o->option_value)."' USING utf8mb4),'off') ON DUPLICATE KEY UPDATE option_value=VALUES(option_value);\n");
            fwrite($tmp,"SET FOREIGN_KEY_CHECKS=1;\n");
        });
    } catch (Throwable $e) { fclose($tmp); wp_die(esc_html($e->getMessage())); }
    nocache_headers(); header('Content-Type: application/sql; charset=utf-8'); header('Content-Disposition: attachment; filename=tpv-respaldo-'.gmdate('Ymd-His').'.sql');
    rewind($tmp); fpassthru($tmp); fclose($tmp); exit;
});
add_action('admin_post_cep_catalog_export',function () {
    if (!current_user_can('manage_options')) wp_die('Sin permisos.',403);
    check_admin_referer('cep_catalog_export'); global $wpdb;
    nocache_headers(); header('Content-Type: text/csv; charset=utf-8'); header('Content-Disposition: attachment; filename=productos.csv');
    $out=fopen('php://output','w'); fputcsv($out,['sku','nombre','categoria','precio_costo','precio_venta','stock','maneja_granel'],',','"','');
    for ($offset=0;;$offset+=500) {
        $rows=$wpdb->get_results($wpdb->prepare("SELECT sku,nombre,categoria,precio_costo,precio_venta,stock,maneja_granel FROM {$wpdb->prefix}cep_productos ORDER BY id LIMIT 500 OFFSET %d",$offset),ARRAY_A);
        foreach ($rows as $row) {
            // Prefix formula-leading text to prevent spreadsheet formula execution.
            foreach ($row as &$v) if (preg_match('/^[=+\-@\t\r]/',(string)$v)) $v="'".$v;
            unset($v); fputcsv($out,$row,',','"','');
        }
        if (count($rows)<500) break;
    }
    fclose($out); exit;
});
