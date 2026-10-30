<?php
defined('ABSPATH') || exit;

function cep_vista_promociones() {
    if(!current_user_can('manage_options')) {
        wp_die('No tienes permisos suficientes para acceder al motor de promociones.');
    }

    global $wpdb;
    $t_promo = $wpdb->prefix . 'cep_promociones';
    $t_prod = $wpdb->prefix . 'cep_productos';

    $mensaje = '';
    $modo_edicion = false;
    $promo_editar = null;

    if(isset($_GET['accion'], $_GET['id']) && $_GET['accion'] === 'eliminar') {
        $id_eliminar = intval($_GET['id']);
        $nonce = isset($_GET['_wpnonce']) ? sanitize_text_field(wp_unslash($_GET['_wpnonce'])) : '';

        if($id_eliminar > 0 && wp_verify_nonce($nonce, 'cep_eliminar_promocion_' . $id_eliminar)) {
            cep_transaction(function () use ($wpdb,$t_promo,$id_eliminar) { cep_check($wpdb->delete($t_promo,['id'=>$id_eliminar])); cep_audit('eliminar_promocion',['id'=>$id_eliminar]); });
            $mensaje = '<div class="notice notice-success is-dismissible"><p>Promoción eliminada correctamente.</p></div>';
        } else {
            $mensaje = '<div class="notice notice-error is-dismissible"><p>No fue posible validar la eliminación de la promoción.</p></div>';
        }
    }

    if (isset($_POST['guardar_promo'])) {
        check_admin_referer('cep_guardar_promocion','cep_promocion_nonce');
        try {
            $v=wp_unslash($_POST);
            cep_transaction(function () use ($v,$wpdb,$t_promo,$t_prod) {
                $id=absint($v['promo_id'] ?? 0); $product=absint($v['producto_id'] ?? 0);
                $qty=cep_units($v['cantidad'] ?? '',3);
                if (!$qty || $qty%1000 || !$wpdb->get_var($wpdb->prepare("SELECT id FROM $t_prod WHERE id=%d AND activo=1 AND maneja_granel=0",$product))) throw new RuntimeException('Selecciona un producto activo por pieza y una cantidad entera positiva.');
                $duplicate=$wpdb->get_var($wpdb->prepare("SELECT id FROM $t_promo WHERE producto_id=%d AND id<>%d LIMIT 1",$product,$id));
                if ($duplicate) throw new RuntimeException('Este producto ya tiene una promoción. Edita la existente.');
                $data=['producto_id'=>$product,'tipo_promo'=>'Paquete Mayoreo','cantidad_requerida'=>intdiv($qty,1000),'precio_oferta'=>cep_decimal(cep_units($v['precio_oferta'] ?? ''))];
                if ($id) cep_check($wpdb->update($t_promo,$data,['id'=>$id])); else $id=cep_insert('cep_promociones',$data);
                cep_audit('promocion',['id'=>$id,'producto'=>$product]);
            });
            $mensaje='<div class="notice notice-success"><p>Promoción guardada.</p></div>';
        } catch (Throwable $e) { $mensaje='<div class="notice notice-error"><p>'.esc_html($e->getMessage()).'</p></div>'; }
    }

    if(isset($_GET['accion'], $_GET['id']) && $_GET['accion'] === 'editar') {
        $id_editar = intval($_GET['id']);
        if($id_editar > 0) {
            $promo_editar = $wpdb->get_row($wpdb->prepare(
                "SELECT p.*, pr.nombre, pr.sku FROM $t_promo p LEFT JOIN $t_prod pr ON p.producto_id = pr.id WHERE p.id = %d",
                $id_editar
            ));
            if($promo_editar) {
                $modo_edicion = true;
            } else {
                $mensaje = '<div class="notice notice-error is-dismissible"><p>La promoción seleccionada no existe.</p></div>';
            }
        }
    }

    $promos = $wpdb->get_results("SELECT p.*, pr.nombre, pr.sku FROM $t_promo p LEFT JOIN $t_prod pr ON p.producto_id = pr.id ORDER BY p.id DESC");
    if(!is_array($promos)) $promos = [];
    ?>
    <div class="wrap">
        <h1 class="wp-heading-inline">Motor de Promociones y Mayoreo</h1>
        <hr class="wp-header-end">

        <?php echo $mensaje; ?>

        <h2><?php echo $modo_edicion ? 'Editar promoción' : 'Crear oferta por mayoreo'; ?></h2>
        <form method="POST">
            <?php wp_nonce_field('cep_guardar_promocion', 'cep_promocion_nonce'); ?>
            <input type="hidden" name="promo_id" value="<?php echo $modo_edicion ? intval($promo_editar->id) : 0; ?>">

            <table class="form-table" role="presentation">
                <tbody>
                    <tr>
                        <th scope="row"><label for="busqueda-prod-promo">Buscar producto</label></th>
                        <td>
                            <div style="position:relative;max-width:400px;">
                                <input type="text" id="busqueda-prod-promo" class="regular-text" placeholder="Escribe SKU o nombre..." autocomplete="off">
                                <div id="resultados-busqueda-promo" class="cep-search-suggestions" style="top:100%;" hidden></div>
                            </div>
                            <p class="description">Escribe al menos un carácter para buscar productos en catálogo.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="lbl-producto-seleccionado">Producto seleccionado</label></th>
                        <td>
                            <input type="text" id="lbl-producto-seleccionado" readonly class="regular-text" style="background:#f6f7f7;" placeholder="Ningún producto seleccionado..." value="<?php echo $modo_edicion ? esc_attr('[' . $promo_editar->sku . '] ' . $promo_editar->nombre) : ''; ?>">
                            <input type="hidden" name="producto_id" id="input-producto-id" value="<?php echo $modo_edicion ? intval($promo_editar->producto_id) : ''; ?>" required>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="tipo-promo">Tipo de promoción</label></th>
                        <td>
                            <input type="text" id="tipo-promo" name="tipo_promo" class="regular-text" value="<?php echo $modo_edicion ? esc_attr($promo_editar->tipo_promo) : 'Paquete Mayoreo'; ?>" required>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="cantidad-promo">Cantidad requerida</label></th>
                        <td>
                            <input type="number" id="cantidad-promo" name="cantidad" class="small-text" value="<?php echo $modo_edicion ? intval($promo_editar->cantidad_requerida) : 3; ?>" min="1" required>
                            <span class="description">piezas</span>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="precio-oferta-promo">Precio de oferta por el paquete ($)</label></th>
                        <td>
                            <input type="number" id="precio-oferta-promo" step="0.01" min="0" name="precio_oferta" class="small-text" value="<?php echo $modo_edicion ? esc_attr(number_format((float)$promo_editar->precio_oferta, 2, '.', '')) : ''; ?>" required>
                        </td>
                    </tr>
                </tbody>
            </table>

            <p class="submit">
                <button type="submit" name="guardar_promo" class="button button-primary">
                    <?php echo $modo_edicion ? 'Actualizar promoción' : 'Guardar promoción'; ?>
                </button>
                <?php if($modo_edicion): ?>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=cep-promociones')); ?>" class="button">Cancelar</a>
                <?php endif; ?>
            </p>
        </form>

        <script>
            jQuery(document).ready(function($) {
                const esc = value => $('<div>').text(String(value)).html().replaceAll('\"','&quot;').replaceAll("'",'&#39;');
                $('#busqueda-prod-promo').on('keyup', function() {
                    let termino = $(this).val().trim();
                    if(termino.length < 1) {
                        $('#resultados-busqueda-promo').prop('hidden', true).empty();
                        return;
                    }
                    $.post(ajaxurl, { action: 'cep_buscar_producto', termino: termino, nonce: <?php echo wp_json_encode(wp_create_nonce('cep_tpv_nonce')); ?> }, function(response) {
                        if(response.success && response.data.length > 0) {
                            let html = '';
                            response.data.forEach(p => {
                                html += `<button type="button" class="cep-search-suggestion item-resultado-promo" data-id="${p.id}" data-nombre="${esc(p.nombre)}" data-sku="${esc(p.sku)}">
                                    <span class="cep-suggestion-main">
                                        <span class="cep-suggestion-name">${esc(p.nombre)}</span>
                                        <span class="cep-suggestion-sku">SKU: ${esc(p.sku)}</span>
                                    </span>
                                    <span class="cep-suggestion-price">$${parseFloat(p.precio_venta).toFixed(2)}</span>
                                </button>`;
                            });
                            $('#resultados-busqueda-promo').html(html).prop('hidden', false);
                        } else {
                            $('#resultados-busqueda-promo').prop('hidden', true).empty();
                        }
                    });
                });

                $(document).on('click', '.item-resultado-promo', function() {
                    $('#input-producto-id').val($(this).data('id'));
                    $('#lbl-producto-seleccionado').val(`[${$(this).data('sku')}] ${$(this).data('nombre')}`);
                    $('#resultados-busqueda-promo').prop('hidden', true).empty();
                    $('#busqueda-prod-promo').val('');
                });
            });
        </script>

        <hr>

        <h2>Promociones registradas</h2>
        <table class="wp-list-table widefat striped">
            <thead>
                <tr>
                    <th scope="col" style="width:160px;">SKU</th>
                    <th scope="col">Producto</th>
                    <th scope="col">Tipo</th>
                    <th scope="col" style="width:110px;">Cant. Req.</th>
                    <th scope="col" style="width:110px;">Precio Oferta</th>
                    <th scope="col" style="width:140px;">Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php if(empty($promos)): ?>
                    <tr><td colspan="6">No hay promociones registradas.</td></tr>
                <?php else: foreach($promos as $p): ?>
                    <tr>
                        <td><code><?php echo esc_html($p->sku); ?></code></td>
                        <td><strong><?php echo esc_html($p->nombre); ?></strong></td>
                        <td><?php echo esc_html($p->tipo_promo); ?></td>
                        <td><?php echo intval($p->cantidad_requerida); ?> pzas</td>
                        <td>$<?php echo number_format((float)$p->precio_oferta, 2); ?></td>
                        <td>
                            <a href="<?php echo esc_url(admin_url('admin.php?page=cep-promociones&accion=editar&id=' . intval($p->id))); ?>" class="button button-small">Editar</a>
                            <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin.php?page=cep-promociones&accion=eliminar&id=' . intval($p->id)), 'cep_eliminar_promocion_' . intval($p->id))); ?>" class="button button-small" onclick="return confirm('¿Estás seguro de eliminar esta promoción?');">Eliminar</a>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
    <?php
}

