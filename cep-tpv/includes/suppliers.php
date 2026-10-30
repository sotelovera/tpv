<?php
defined('ABSPATH') || exit;

function cep_vista_proveedores() {
    if(!current_user_can('manage_options')) {
        wp_die('No tienes permisos suficientes para acceder a la gestión de proveedores.');
    }

    global $wpdb;
    $t_prov = $wpdb->prefix . 'cep_proveedores';
    $mensaje = '';

    if(isset($_GET['accion'], $_GET['id']) && $_GET['accion'] === 'eliminar') {
        $id_eliminar = intval($_GET['id']);
        $nonce = isset($_GET['_wpnonce']) ? sanitize_text_field(wp_unslash($_GET['_wpnonce'])) : '';
        if($id_eliminar > 0 && wp_verify_nonce($nonce, 'cep_eliminar_prov_' . $id_eliminar)) {
            $wpdb->update($t_prov, ['activo'=>0], ['id'=>$id_eliminar]);
            $mensaje = '<div class="notice notice-success is-dismissible"><p>Proveedor archivado correctamente.</p></div>';
        } else {
            $mensaje = '<div class="notice notice-error is-dismissible"><p>No fue posible validar la eliminación del proveedor.</p></div>';
        }
    }

    if(isset($_POST['guardar_proveedor'])) {
        if(!isset($_POST['cep_prov_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['cep_prov_nonce'])), 'cep_guardar_proveedor')) {
            $mensaje = '<div class="notice notice-error is-dismissible"><p>Error de seguridad: Token CSRF no válido.</p></div>';
        } else {
            $empresa = sanitize_text_field(wp_unslash($_POST['empresa'] ?? ''));
            $contacto = sanitize_text_field(wp_unslash($_POST['contacto'] ?? ''));
            $telefono = sanitize_text_field(wp_unslash($_POST['telefono'] ?? ''));
            $email = sanitize_email(wp_unslash($_POST['email'] ?? ''));

            if(empty($empresa)) {
                $mensaje = '<div class="notice notice-error is-dismissible"><p>El nombre de la empresa proveedora es obligatorio.</p></div>';
            } else {
                $wpdb->insert($t_prov, [
                    'nombre_empresa' => $empresa,
                    'contacto' => $contacto,
                    'telefono' => $telefono,
                    'email' => $email
                ], ['%s', '%s', '%s', '%s']);
                $mensaje = '<div class="notice notice-success is-dismissible"><p>Proveedor registrado correctamente.</p></div>';
            }
        }
    }

    $proveedores = $wpdb->get_results("SELECT * FROM $t_prov WHERE activo=1 ORDER BY id DESC");
    if(!is_array($proveedores)) $proveedores = [];
    ?>
    <div class="wrap">
        <h1 class="wp-heading-inline">Gestión de Proveedores</h1>
        <hr class="wp-header-end">

        <?php echo $mensaje; ?>

        <h2>Agregar proveedor</h2>
        <form method="POST">
            <?php wp_nonce_field('cep_guardar_proveedor', 'cep_prov_nonce'); ?>
            <table class="form-table" role="presentation">
                <tbody>
                    <tr>
                        <th scope="row"><label for="cep-prov-empresa">Empresa / Distribuidor</label></th>
                        <td><input type="text" id="cep-prov-empresa" name="empresa" class="regular-text" required></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="cep-prov-contacto">Nombre del agente / Contacto</label></th>
                        <td><input type="text" id="cep-prov-contacto" name="contacto" class="regular-text"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="cep-prov-telefono">Teléfono</label></th>
                        <td><input type="text" id="cep-prov-telefono" name="telefono" class="regular-text"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="cep-prov-email">Correo electrónico</label></th>
                        <td><input type="email" id="cep-prov-email" name="email" class="regular-text"></td>
                    </tr>
                </tbody>
            </table>
            <p class="submit">
                <button type="submit" name="guardar_proveedor" class="button button-primary">Guardar proveedor</button>
            </p>
        </form>

        <hr>

        <h2>Proveedores registrados</h2>
        <table class="wp-list-table widefat striped">
            <thead>
                <tr>
                    <th scope="col">Empresa</th>
                    <th scope="col">Contacto</th>
                    <th scope="col">Teléfono</th>
                    <th scope="col">Email</th>
                    <th scope="col" style="width:120px;">Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php if(empty($proveedores)): ?>
                    <tr><td colspan="5">No hay proveedores registrados.</td></tr>
                <?php else: foreach($proveedores as $prov): ?>
                    <tr>
                        <td><strong><?php echo esc_html($prov->nombre_empresa); ?></strong></td>
                        <td><?php echo esc_html($prov->contacto ?: '—'); ?></td>
                        <td><?php echo esc_html($prov->telefono ?: '—'); ?></td>
                        <td><?php echo esc_html($prov->email ?: '—'); ?></td>
                        <td>
                            <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin.php?page=cep-proveedores&accion=eliminar&id=' . intval($prov->id)), 'cep_eliminar_prov_' . intval($prov->id))); ?>" class="button button-small" onclick="return confirm('¿Estás seguro de eliminar este proveedor?');">Archivar</a>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
    <?php
}

