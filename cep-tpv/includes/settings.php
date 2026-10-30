<?php
defined('ABSPATH') || exit;

function cep_vista_config() {
    if(!current_user_can('manage_options')) {
        wp_die(__('No tienes permisos suficientes para acceder a esta página.'));
    }

    if(isset($_POST['guardar_config'])) {
        check_admin_referer('cep_guardar_config', 'cep_config_nonce');
        $ip=sanitize_text_field(wp_unslash($_POST['ip_oficial'] ?? ''));
        if (isset($_POST['ip_activa']) && !filter_var($ip,FILTER_VALIDATE_IP)) wp_die('Introduce una IP válida antes de activar la restricción.');
        update_option('cep_ticket_ancho', ($_POST['ticket_ancho'] ?? '')==='58'?'58':'80');
        update_option('cep_nombre_tienda', sanitize_text_field(wp_unslash($_POST['nombre_tienda'])));
        update_option('cep_ticket_pie', sanitize_text_field(wp_unslash($_POST['ticket_pie'])));
        update_option('cep_ip_seguridad_activa', isset($_POST['ip_activa']) ? '1' : '0');
        update_option('cep_ip_caja_oficial', sanitize_text_field(wp_unslash($_POST['ip_oficial'])));
        echo '<div class="notice notice-success is-dismissible"><p>Configuración y parámetros de seguridad guardados correctamente.</p></div>';
    }
    $nombre = get_option('cep_nombre_tienda', 'Mi Tienda');
    $pie = get_option('cep_ticket_pie', 'Gracias');
    $ip_activa = get_option('cep_ip_seguridad_activa', '0');
    $ip_oficial = get_option('cep_ip_caja_oficial', isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '');
    $remote_addr = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '127.0.0.1';
    ?>
    <div class="wrap">
        <h1 class="wp-heading-inline">Configuración General y Seguridad</h1>
        <hr class="wp-header-end">

        <form method="POST">
            <?php wp_nonce_field('cep_guardar_config', 'cep_config_nonce'); ?>
            <h2>Datos del establecimiento</h2><p><label>Ancho de ticket <select name="ticket_ancho"><option value="80" <?php selected(get_option('cep_ticket_ancho','80'),'80'); ?>>80 mm</option><option value="58" <?php selected(get_option('cep_ticket_ancho','80'),'58'); ?>>58 mm</option></select></label></p>
            <table class="form-table" role="presentation">
                <tbody>
                    <tr>
                        <th scope="row"><label for="cep-nombre-tienda">Nombre del establecimiento</label></th>
                        <td>
                            <input type="text" id="cep-nombre-tienda" name="nombre_tienda" value="<?php echo esc_attr($nombre); ?>" class="regular-text" required>
                            <p class="description">Aparece en el encabezado del ticket impreso.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="cep-ticket-pie">Mensaje al pie del ticket</label></th>
                        <td>
                            <input type="text" id="cep-ticket-pie" name="ticket_pie" value="<?php echo esc_attr($pie); ?>" class="regular-text" required>
                            <p class="description">Agradecimiento o aviso legal al final del ticket.</p>
                        </td>
                    </tr>
                </tbody>
            </table>

            <hr>

            <h2>Seguridad de acceso por IP (Bloqueo de terminales externas)</h2>
            <table class="form-table" role="presentation">
                <tbody>
                    <tr>
                        <th scope="row">Restricción por IP</th>
                        <td>
                            <fieldset>
                                <legend class="screen-reader-text"><span>Restricción por IP</span></legend>
                                <label for="cep-ip-activa">
                                    <input type="checkbox" name="ip_activa" id="cep-ip-activa" value="1" <?php checked($ip_activa, '1'); ?>>
                                    Activar restricción de IP para el TPV (solo permite operar desde la IP autorizada)
                                </label>
                            </fieldset>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="cep-ip-oficial">IP autorizada de caja / mostrador</label></th>
                        <td>
                            <input type="text" id="cep-ip-oficial" name="ip_oficial" value="<?php echo esc_attr($ip_oficial); ?>" class="regular-text">
                            <p class="description">Tu dirección IP actual detectada en este navegador es: <code><?php echo esc_html($remote_addr); ?></code></p>
                        </td>
                    </tr>
                </tbody>
            </table>

            <p class="submit">
                <button type="submit" name="guardar_config" class="button button-primary">Guardar configuración</button>
            </p>
        </form>
    </div>
    <?php
}