<?php
defined('ABSPATH') || exit;

add_action('wp_ajax_cep_inventario_sugerencias', 'cep_ajax_inventario_sugerencias_v7');
function cep_ajax_inventario_sugerencias_v7() {
    if ( ! current_user_can('manage_options') ) {
        wp_send_json_error('Sin permisos.', 403);
    }

    if ( ! isset($_POST['nonce']) || ! wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'cep_inventario_sugerencias') ) {
        wp_send_json_error('Solicitud no válida.', 403);
    }

    global $wpdb;
    $tabla = $wpdb->prefix . 'cep_productos';
    $termino = isset($_POST['termino']) ? sanitize_text_field(wp_unslash($_POST['termino'])) : '';
    $termino = trim($termino);

    if ($termino === '' || mb_strlen($termino) < 2) {
        wp_send_json_success([]);
    }

    $like = '%' . $wpdb->esc_like($termino) . '%';
    $productos = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT id, sku, nombre, precio_venta, stock
             FROM $tabla
             WHERE sku LIKE %s OR nombre LIKE %s
             ORDER BY CASE
                 WHEN sku = %s THEN 0
                 WHEN nombre LIKE %s THEN 1
                 ELSE 2
             END, nombre ASC
             LIMIT 8",
            $like, $like, $termino, $like
        ),
        ARRAY_A
    );

    if (!is_array($productos)) {
        $productos = [];
    }

    wp_send_json_success($productos);
}

function cep_vista_inventario_general() {
    if(!current_user_can('manage_options')) {
        wp_die('No tienes permisos suficientes para acceder a la gestión de inventario.');
    }

    global $wpdb;
    $tabla = $wpdb->prefix . 'cep_productos';
    $mensaje = '';

    try {
        if (isset($_GET['accion'],$_GET['id']) && $_GET['accion']==='eliminar') {
            $id=absint($_GET['id']); check_admin_referer('cep_eliminar_producto_'.$id);
            cep_transaction(function () use ($wpdb,$tabla,$id) {
                cep_check($wpdb->update($tabla,['activo'=>0],['id'=>$id])); cep_audit('archivar_producto',['id'=>$id]);
            });
            $mensaje='<div class="notice notice-success"><p>Producto archivado. Se conservan sus movimientos y ventas.</p></div>';
        }
        if (isset($_POST['guardar_prod'])) {
            check_admin_referer('cep_guardar_producto','cep_prod_nonce');
            $v=wp_unslash($_POST);
            cep_transaction(function () use ($wpdb,$tabla,$v) {
                $sku=sanitize_text_field($v['sku'] ?? ''); $nombre=sanitize_text_field($v['nombre'] ?? '');
                if (!$sku || !$nombre || strlen($sku)>50 || strlen($nombre)>150) throw new RuntimeException('SKU o nombre inválido.');
                $id=absint($v['prod_id'] ?? 0);
                $old=$id?$wpdb->get_row($wpdb->prepare("SELECT * FROM $tabla WHERE id=%d FOR UPDATE",$id)):null;
                if ($id && !$old) throw new RuntimeException('El producto ya no existe.');
                $stock=cep_units($v['stock'] ?? '',3); $bulk=isset($v['granel'])?1:0;
                if (!$bulk && $stock%1000) throw new RuntimeException('Las piezas requieren existencias enteras.');
                if ($old && cep_units($v['stock_original'] ?? '',3)!==cep_units($old->stock,3)) throw new RuntimeException('Las existencias cambiaron. Recarga antes de editar.');
                $tax=cep_units($v['impuesto'] ?? '0');
                if ($tax>10000) throw new RuntimeException('Impuesto fuera del rango 0–100.');
                $data=['sku'=>$sku,'nombre'=>$nombre,'categoria'=>sanitize_text_field($v['categoria'] ?? 'General'),'precio_costo'=>cep_decimal(cep_units($v['precio_costo'] ?? '0')),'precio_venta'=>cep_decimal(cep_units($v['precio_venta'] ?? '')),'stock'=>cep_decimal($stock,3),'maneja_granel'=>$bulk,'activo'=>isset($v['activo'])?1:0,'stock_minimo'=>cep_decimal(cep_units($v['stock_minimo'] ?? '5',3),3),'impuesto'=>cep_decimal($tax)];
                if ($id) cep_check($wpdb->update($tabla,$data,['id'=>$id])); else $id=cep_insert('cep_productos',$data);
                $delta=$stock-($old?cep_units($old->stock,3):0);
                if ($delta) cep_movement($id,$delta,'Alta / edición de catálogo');
                cep_audit('producto',['id'=>$id]);
            });
            $mensaje='<div class="notice notice-success"><p>Producto guardado.</p></div>';
        }
    } catch (Throwable $e) { $mensaje='<div class="notice notice-error"><p>'.esc_html($e->getMessage()).'</p></div>'; }

    $editando = null;
    if(isset($_GET['accion']) && $_GET['accion'] === 'editar' && isset($_GET['id'])) {
        $id_editar = intval($_GET['id']);
        $editando = $wpdb->get_row($wpdb->prepare("SELECT * FROM $tabla WHERE id = %d", $id_editar));
    }

    $busqueda_inventario = isset($_GET['buscar_producto']) ? sanitize_text_field(wp_unslash($_GET['buscar_producto'])) : '';
    $pagina_inventario = isset($_GET['paged_inventario']) ? max(1, intval($_GET['paged_inventario'])) : 1;
    $por_pagina_inventario = 50;
    $offset_inventario = ($pagina_inventario - 1) * $por_pagina_inventario;

    $where = '';
    $query_args = [];
    $count_args = [];
    if($busqueda_inventario !== '') {
        $like = '%' . $wpdb->esc_like($busqueda_inventario) . '%';
        $where = " WHERE sku LIKE %s OR nombre LIKE %s ";
        $query_args = [$like, $like];
        $count_args = [$like, $like];
    }

    if(!empty($count_args)) {
        $total_productos = intval($wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $tabla $where", $count_args)));
        $sql_productos = "SELECT * FROM $tabla $where ORDER BY id DESC LIMIT %d OFFSET %d";
        $productos = $wpdb->get_results($wpdb->prepare($sql_productos, array_merge($query_args, [$por_pagina_inventario, $offset_inventario])));
    } else {
        $total_productos = intval($wpdb->get_var("SELECT COUNT(*) FROM $tabla"));
        $productos = $wpdb->get_results($wpdb->prepare("SELECT * FROM $tabla ORDER BY id DESC LIMIT %d OFFSET %d", $por_pagina_inventario, $offset_inventario));
    }
    if(!is_array($productos)) $productos = [];
    $total_paginas_inventario = max(1, (int) ceil($total_productos / $por_pagina_inventario));
    if($pagina_inventario > $total_paginas_inventario) {
        $pagina_inventario = $total_paginas_inventario;
    }
    ?>
    <div class="wrap">
        <h1 class="wp-heading-inline">Inventario</h1>
        <hr class="wp-header-end">

        <?php echo $mensaje; ?>

        <h2><?php echo $editando ? 'Editar producto' : 'Registrar nuevo producto'; ?></h2>
        <form method="POST">
            <?php wp_nonce_field('cep_guardar_producto', 'cep_prod_nonce'); ?>
            <input type="hidden" name="prod_id" value="<?php echo $editando ? intval($editando->id) : 0; ?>">
            <input type="hidden" name="stock_original" value="<?php echo esc_attr($editando->stock ?? '0'); ?>">
            <p><label><input type="checkbox" name="activo" <?php checked(!$editando || $editando->activo); ?>> Producto activo</label></p>
            <p><label>Existencia mínima <input type="number" name="stock_minimo" min="0" step="0.001" value="<?php echo esc_attr($editando->stock_minimo ?? '5'); ?>"></label></p>
            <p><label>Impuesto añadido (%) <input type="number" name="impuesto" min="0" max="100" step="0.01" value="<?php echo esc_attr($editando->impuesto ?? '0'); ?>"></label></p>
            <table class="form-table" role="presentation">
                <tbody>
                    <tr>
                        <th scope="row"><label for="cep-sku">SKU / Código de barras</label></th>
                        <td>
                            <input id="cep-sku" type="text" name="sku" class="regular-text" value="<?php echo $editando ? esc_attr($editando->sku) : ''; ?>" required autocomplete="off" placeholder="Ej. 7501234567890">
                            <p class="description">Código de barras o identificador único del producto.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="cep-nombre">Nombre del producto</label></th>
                        <td>
                            <input id="cep-nombre" type="text" name="nombre" class="regular-text" value="<?php echo $editando ? esc_attr($editando->nombre) : ''; ?>" required placeholder="Ej. Refresco de cola 600 ml">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="cep-categoria">Categoría</label></th>
                        <td>
                            <input id="cep-categoria" type="text" name="categoria" class="regular-text" value="<?php echo $editando ? esc_attr($editando->categoria) : 'Abarrotes'; ?>" placeholder="Abarrotes">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="cep-stock">Stock inicial / actual</label></th>
                        <td>
                            <input id="cep-stock" type="number" step="any" name="stock" class="small-text" value="<?php echo $editando ? esc_attr($editando->stock) : '20'; ?>" required min="0">
                            <span class="description">Cantidad disponible (admite decimales para productos a granel)</span>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="cep-costo">Costo ($)</label></th>
                        <td>
                            <input id="cep-costo" type="number" step="0.01" min="0" name="precio_costo" class="small-text" value="<?php echo $editando ? esc_attr($editando->precio_costo) : ''; ?>" required>
                            <span class="description">Costo unitario de compra</span>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="cep-venta">Precio de venta ($)</label></th>
                        <td>
                            <input id="cep-venta" type="number" step="0.01" min="0" name="precio_venta" class="small-text" value="<?php echo $editando ? esc_attr($editando->precio_venta) : ''; ?>" required>
                            <span class="description">Precio de venta al público (o precio por Kg en granel)</span>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Venta a granel</th>
                        <td>
                            <fieldset>
                                <legend class="screen-reader-text"><span>Venta a granel</span></legend>
                                <label for="cep-granel">
                                    <input type="checkbox" name="granel" id="cep-granel" value="1" <?php echo ($editando && $editando->maneja_granel == 1) ? 'checked' : ''; ?>>
                                    Este producto se despacha por peso (Kg) o importe
                                </label>
                            </fieldset>
                        </td>
                    </tr>
                </tbody>
            </table>

            <p class="submit">
                <button type="submit" name="guardar_prod" class="button button-primary">
                    <?php echo $editando ? 'Actualizar producto' : 'Guardar producto'; ?>
                </button>
                <?php if($editando): ?>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=cep-inventario')); ?>" class="button">Cancelar edición</a>
                <?php endif; ?>
            </p>
        </form>

        <hr>

        <h2>Catálogo de productos</h2>
        <form method="GET" id="cep-inventory-search-form" style="margin-bottom:12px;">
            <input type="hidden" name="page" value="cep-inventario">
            <p class="search-box">
                <label class="screen-reader-text" for="cep-buscar-producto">Buscar producto:</label>
                <span class="cep-autocomplete-wrap">
                    <input type="search" id="cep-buscar-producto" name="buscar_producto" value="<?php echo esc_attr($busqueda_inventario); ?>" placeholder="Nombre, SKU o código..." autocomplete="off">
                    <div id="cep-search-suggestions" class="cep-search-suggestions" role="listbox" hidden></div>
                </span>
                <input type="submit" class="button" value="Buscar producto">
                <?php if($busqueda_inventario !== ''): ?>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=cep-inventario')); ?>" class="button">Limpiar búsqueda</a>
                <?php endif; ?>
            </p>
        </form>

        <table class="wp-list-table widefat striped">
            <thead>
                <tr>
                    <th scope="col" style="width:160px;">SKU / Código</th>
                    <th scope="col">Producto</th>
                    <th scope="col" style="width:130px;">Categoría</th>
                    <th scope="col" style="width:100px;">Costo</th>
                    <th scope="col" style="width:100px;">Venta</th>
                    <th scope="col" style="width:110px;">Stock</th>
                    <th scope="col" style="width:80px;">Granel</th>
                    <th scope="col" style="width:140px;">Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php if(empty($productos)): ?>
                    <tr>
                        <td colspan="8">
                            <?php echo $busqueda_inventario !== '' ? 'No se encontraron productos que coincidan con la búsqueda.' : 'No hay productos registrados en el inventario.'; ?>
                        </td>
                    </tr>
                <?php else: foreach($productos as $p):
                    $stock_num = floatval($p->stock);
                    $stock_bajo = $stock_num < 5;
                ?>
                    <tr>
                        <td><code><?php echo esc_html($p->sku); ?></code></td>
                        <td>
                            <strong><?php echo esc_html($p->nombre); ?></strong>
                            <?php if($stock_bajo): ?> <span class="cep-low-stock-badge">Stock bajo</span><?php endif; ?>
                        </td>
                        <td><?php echo esc_html($p->categoria); ?></td>
                        <td>$<?php echo number_format($p->precio_costo, 2); ?></td>
                        <td><strong>$<?php echo number_format($p->precio_venta, 2); ?></strong></td>
                        <td>
                            <span class="<?php echo $stock_bajo ? 'cep-stock-low' : 'cep-stock-ok'; ?>">
                                <?php echo $p->maneja_granel ? number_format($stock_num, 3) . ' kg' : intval($stock_num) . ' pza'; ?>
                            </span>
                        </td>
                        <td><?php echo $p->maneja_granel ? '<span class="cep-status-yes">Sí</span>' : '<span class="cep-status-no">No</span>'; ?></td>
                        <td>
                            <a href="<?php echo esc_url(admin_url('admin.php?page=cep-inventario&accion=editar&id=' . intval($p->id))); ?>" class="button button-small">Editar</a>
                            <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin.php?page=cep-inventario&accion=eliminar&id=' . intval($p->id)), 'cep_eliminar_producto_' . intval($p->id))); ?>" class="button button-small" onclick="return confirm('¿Estás seguro de eliminar este producto del inventario?');">Eliminar</a>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>

        <?php if($total_paginas_inventario > 1): ?>
            <div class="tablenav bottom" style="margin-top:10px;">
                <div class="tablenav-pages">
                    <span class="displaying-num"><?php echo number_format_i18n($total_productos); ?> elementos</span>
                    <?php
                    $base_url = admin_url('admin.php?page=cep-inventario');
                    if($busqueda_inventario !== '') $base_url = add_query_arg('buscar_producto', rawurlencode($busqueda_inventario), $base_url);
                    $prev_url = add_query_arg('paged_inventario', max(1, $pagina_inventario - 1), $base_url);
                    $next_url = add_query_arg('paged_inventario', min($total_paginas_inventario, $pagina_inventario + 1), $base_url);
                    ?>
                    <?php if($pagina_inventario > 1): ?>
                        <a class="prev-page button" href="<?php echo esc_url($prev_url); ?>"><span class="screen-reader-text">Página anterior</span><span aria-hidden="true">&lsaquo;</span></a>
                    <?php endif; ?>
                    <span class="paging-input">
                        <span class="current-page"><?php echo number_format_i18n($pagina_inventario); ?></span> de <span class="total-pages"><?php echo number_format_i18n($total_paginas_inventario); ?></span>
                    </span>
                    <?php if($pagina_inventario < $total_paginas_inventario): ?>
                        <a class="next-page button" href="<?php echo esc_url($next_url); ?>"><span class="screen-reader-text">Página siguiente</span><span aria-hidden="true">&rsaquo;</span></a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script>
    (function(){
        const input = document.getElementById('cep-buscar-producto');
        const suggestions = document.getElementById('cep-search-suggestions');
        const form = document.getElementById('cep-inventory-search-form');
        if(!input || !suggestions || !form) return;

        const ajaxUrl = <?php echo wp_json_encode(admin_url('admin-ajax.php')); ?>;
        const nonce = <?php echo wp_json_encode(wp_create_nonce('cep_inventario_sugerencias')); ?>;
        let timer = null;
        let request = null;

        function closeSuggestions(){
            suggestions.innerHTML = '';
            suggestions.hidden = true;
        }

        function showSuggestions(items){
            suggestions.innerHTML = '';
            if(!items || !items.length){ closeSuggestions(); return; }
            items.forEach(function(item){
                const button = document.createElement('button');
                button.type = 'button';
                button.className = 'cep-search-suggestion';
                button.setAttribute('role','option');
                button.innerHTML = '<span class="cep-suggestion-main"><span class="cep-suggestion-name"></span><span class="cep-suggestion-sku"></span></span><span class="cep-suggestion-price"></span>';
                button.querySelector('.cep-suggestion-name').textContent = item.nombre || '';
                button.querySelector('.cep-suggestion-sku').textContent = 'SKU / Código: ' + (item.sku || '');
                button.querySelector('.cep-suggestion-price').textContent = '$' + Number(item.precio_venta || 0).toFixed(2);
                button.addEventListener('mousedown', function(e){ e.preventDefault(); });
                button.addEventListener('click', function(){
                    input.value = item.sku || item.nombre || '';
                    closeSuggestions();
                    form.submit();
                });
                suggestions.appendChild(button);
            });
            suggestions.hidden = false;
        }

        function searchSuggestions(term){
            term = String(term || '').trim();
            if(term.length < 2){ closeSuggestions(); return; }
            if(request && request.readyState !== 4) request.abort();
            request = jQuery.post(ajaxUrl, {
                action: 'cep_inventario_sugerencias',
                termino: term,
                nonce: nonce
            }).done(function(response){
                if(response && response.success) showSuggestions(response.data || []);
            });
        }

        input.addEventListener('input', function(){
            clearTimeout(timer);
            timer = setTimeout(function(){ searchSuggestions(input.value); }, 180);
        });
        input.addEventListener('keydown', function(e){
            if(e.key === 'Escape') closeSuggestions();
        });
        document.addEventListener('click', function(e){
            if(!e.target.closest('.cep-autocomplete-wrap')) closeSuggestions();
        });
        form.addEventListener('submit', function(){ closeSuggestions(); });
    })();
    </script>
    <?php
}

