<?php
defined('ABSPATH') || exit;

function cep_vista_clientes() {
    if(!current_user_can('manage_options')) {
        wp_die('No tienes permisos suficientes para acceder a la administración de clientes y créditos.');
    }

    global $wpdb;
    $t_cli = $wpdb->prefix . 'cep_clientes';
    $mensaje = '';
    $modo_edicion = false;
    $cliente_editar = null;

    if(isset($_GET['accion'], $_GET['id']) && $_GET['accion'] === 'eliminar') {
        $id_eliminar = intval($_GET['id']);
        $nonce = isset($_GET['_wpnonce']) ? sanitize_text_field(wp_unslash($_GET['_wpnonce'])) : '';

        if($id_eliminar > 0 && wp_verify_nonce($nonce, 'cep_eliminar_cliente_' . $id_eliminar)) {
            $cliente = $wpdb->get_row($wpdb->prepare("SELECT saldo_actual FROM $t_cli WHERE id = %d", $id_eliminar));
            if(!$cliente) {
                $mensaje = '<div class="notice notice-error is-dismissible"><p>El cliente no existe.</p></div>';
            } elseif(floatval($cliente->saldo_actual) > 0.0001) {
                $mensaje = '<div class="notice notice-error is-dismissible"><p>No se puede eliminar un cliente con deuda pendiente.</p></div>';
            } else {
                $wpdb->update($t_cli, ['activo'=>0], ['id'=>$id_eliminar]);
                $mensaje = '<div class="notice notice-success is-dismissible"><p>Cliente archivado correctamente.</p></div>';
            }
        } else {
            $mensaje = '<div class="notice notice-error is-dismissible"><p>No fue posible validar la eliminación del cliente.</p></div>';
        }
    }

    if(isset($_POST['guardar_cliente'])) {
        if(!isset($_POST['cep_cliente_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['cep_cliente_nonce'])), 'cep_guardar_cliente')) {
            $mensaje = '<div class="notice notice-error is-dismissible"><p>La solicitud no pudo ser validada.</p></div>';
        } else {
            $cliente_id = isset($_POST['cliente_id']) ? intval($_POST['cliente_id']) : 0;
            $nombre = isset($_POST['nombre']) ? sanitize_text_field(wp_unslash($_POST['nombre'])) : '';
            $telefono = isset($_POST['telefono']) ? sanitize_text_field(wp_unslash($_POST['telefono'])) : '';
            $limite = isset($_POST['limite']) ? max(0, floatval($_POST['limite'])) : 0;

            if($nombre === '') {
                $mensaje = '<div class="notice notice-error is-dismissible"><p>El nombre del cliente es obligatorio.</p></div>';
            } elseif($cliente_id > 0) {
                $actualizado = $wpdb->update($t_cli, [
                    'nombre' => $nombre,
                    'telefono' => $telefono,
                    'limite_credito' => $limite
                ], ['id' => $cliente_id], ['%s','%s','%f'], ['%d']);
                $mensaje = $actualizado !== false
                    ? '<div class="notice notice-success is-dismissible"><p>Cliente actualizado correctamente. Su deuda actual no fue modificada.</p></div>'
                    : '<div class="notice notice-error is-dismissible"><p>No fue posible actualizar el cliente.</p></div>';
            } else {
                $insertado = $wpdb->insert($t_cli, [
                    'nombre' => $nombre,
                    'telefono' => $telefono,
                    'limite_credito' => $limite,
                    'saldo_actual' => 0.00
                ], ['%s','%s','%f','%f']);
                $mensaje = $insertado !== false
                    ? '<div class="notice notice-success is-dismissible"><p>Cliente registrado correctamente.</p></div>'
                    : '<div class="notice notice-error is-dismissible"><p>No fue posible registrar el cliente.</p></div>';
            }
        }
    }

    if (isset($_POST['abonar_credito'])) {
        $mensaje='<div class="notice notice-error"><p>Registra abonos en Movimientos para asociarlos a un turno y conservar su trazabilidad.</p></div>';
    }
    if(isset($_GET['accion'], $_GET['id']) && $_GET['accion'] === 'editar') {
        $id_editar = intval($_GET['id']);
        if($id_editar > 0) {
            $cliente_editar = $wpdb->get_row($wpdb->prepare("SELECT * FROM $t_cli WHERE id = %d", $id_editar));
            if($cliente_editar) $modo_edicion = true;
        }
    }

    $clientes = $wpdb->get_results("SELECT * FROM $t_cli WHERE activo=1 ORDER BY id DESC");
    if(!is_array($clientes)) $clientes = [];
    ?>
    <div class="wrap">
        <p><a class="button" href="<?php echo esc_url(admin_url('admin.php?page=cep-operaciones')); ?>">Registrar abono en Movimientos</a></p><h1 class="wp-heading-inline">Control de Clientes y Créditos (Libreta Digital / Fiado)</h1>
        <hr class="wp-header-end">
        <p class="description">Administra clientes, límites de crédito, saldos y abonos desde la interfaz de administración de WordPress.</p>

        <?php echo $mensaje; ?>

        <h2><?php echo $modo_edicion ? 'Editar cliente' : 'Registrar cliente'; ?></h2>
        <form method="post">
            <?php wp_nonce_field('cep_guardar_cliente', 'cep_cliente_nonce'); ?>
            <input type="hidden" name="cliente_id" value="<?php echo $modo_edicion ? intval($cliente_editar->id) : 0; ?>">
            <table class="form-table" role="presentation">
                <tbody>
                    <tr>
                        <th scope="row"><label for="cep-cliente-nombre">Nombre completo</label></th>
                        <td><input id="cep-cliente-nombre" type="text" name="nombre" class="regular-text" required value="<?php echo $modo_edicion ? esc_attr($cliente_editar->nombre) : ''; ?>"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="cep-cliente-telefono">Teléfono</label></th>
                        <td><input id="cep-cliente-telefono" type="text" name="telefono" class="regular-text" value="<?php echo $modo_edicion ? esc_attr($cliente_editar->telefono) : ''; ?>"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="cep-cliente-limite">Límite de crédito autorizado ($)</label></th>
                        <td>
                            <input id="cep-cliente-limite" type="number" step="0.01" min="0" name="limite" class="small-text" value="<?php echo $modo_edicion ? esc_attr($cliente_editar->limite_credito) : '1000.00'; ?>" required>
                        </td>
                    </tr>
                </tbody>
            </table>
            <p class="submit">
                <button type="submit" name="guardar_cliente" class="button button-primary"><?php echo $modo_edicion ? 'Actualizar cliente' : 'Guardar cliente'; ?></button>
                <?php if($modo_edicion): ?>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=cep-clientes')); ?>" class="button">Cancelar</a>
                <?php endif; ?>
            </p>
        </form>

        <hr>

        <h2>Clientes registrados</h2>
        <table class="wp-list-table widefat striped">
            <thead>
                <tr>
                    <th scope="col" style="width:70px;">ID</th>
                    <th scope="col">Nombre</th>
                    <th scope="col">Teléfono</th>
                    <th scope="col">Límite de crédito</th>
                    <th scope="col">Deuda actual</th>
                    <th scope="col">Crédito disponible</th>
                    <th scope="col" style="width:140px;">Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php if(empty($clientes)): ?>
                    <tr><td colspan="7">No hay clientes registrados.</td></tr>
                <?php else: foreach($clientes as $c):
                    $disponible = max(0, floatval($c->limite_credito) - floatval($c->saldo_actual));
                    $editar_url = admin_url('admin.php?page=cep-clientes&accion=editar&id=' . intval($c->id));
                    $eliminar_url = wp_nonce_url(admin_url('admin.php?page=cep-clientes&accion=eliminar&id=' . intval($c->id)), 'cep_eliminar_cliente_' . intval($c->id));
                ?>
                    <tr>
                        <td>#<?php echo intval($c->id); ?></td>
                        <td><strong><?php echo esc_html($c->nombre); ?></strong></td>
                        <td><?php echo esc_html($c->telefono ?: '—'); ?></td>
                        <td>$<?php echo number_format($c->limite_credito, 2); ?></td>
                        <td>$<?php echo number_format($c->saldo_actual, 2); ?></td>
                        <td>$<?php echo number_format($disponible, 2); ?></td>
                        <td>
                            <a href="<?php echo esc_url($editar_url); ?>" class="button button-small">Editar</a>
                            <a href="<?php echo esc_url($eliminar_url); ?>" class="button button-small" onclick="return confirm('¿Estás seguro de eliminar este cliente? Si tiene deuda pendiente, el sistema impedirá la eliminación.');">Archivar</a>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
    <?php
}

