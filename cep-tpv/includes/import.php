<?php
defined('ABSPATH') || exit;

function cep_vista_importar_excel() {
    if(!current_user_can('manage_options')) {
        wp_die('No tienes permisos suficientes para importar catálogos de productos.');
    }

    global $wpdb;
    $tabla_productos = $wpdb->prefix . 'cep_productos';
    $mensaje_sistema = '';
    $tipo_alerta = '';
    $detalles_errores = [];

    if ( $_SERVER['REQUEST_METHOD'] === 'POST' && isset( $_FILES['archivo_csv'] ) ) {
        if ( ! isset( $_POST['cep_import_nonce'] ) || ! wp_verify_nonce( $_POST['cep_import_nonce'], 'cep_ejecutar_importacion' ) ) {
            $mensaje_sistema = 'Error de seguridad: Token CSRF no válido.';
            $tipo_alerta = 'error';
        } else {
            $archivo = $_FILES['archivo_csv'];

            if ( $archivo['error'] === UPLOAD_ERR_OK ) {
                $extension = strtolower( pathinfo( $archivo['name'], PATHINFO_EXTENSION ) );

                if ( $extension === 'csv' && $archivo['size'] <= 2097152 && is_uploaded_file($archivo['tmp_name']) ) {
                    $ruta_temporal = $archivo['tmp_name'];

                    if ( ( $handle = fopen( $ruta_temporal, 'r' ) ) !== FALSE ) {
                        $num_fila = 0;
                        $importados = 0;

                        fgetcsv( $handle, 0, ',', '"', '' );

                        while ( ( $fila = fgetcsv( $handle, 0, ',', '"', '' ) ) !== FALSE ) {
                            $num_fila++;

                            if ( empty( array_filter( $fila ) ) ) {
                                continue;
                            }

                            $sku           = sanitize_text_field( trim( $fila[0] ?? '' ) );
                            $nombre        = sanitize_text_field( trim( $fila[1] ?? '' ) );
                            $categoria     = sanitize_text_field( trim( $fila[2] ?? 'General' ) );
                            try {
                                $precio_costo=cep_decimal(cep_units($fila[3] ?? '0'));
                                $precio_venta=cep_decimal(cep_units($fila[4] ?? '0'));
                                $stock=cep_decimal(cep_units($fila[5] ?? '0',3),3);
                                $maneja_granel=($fila[6] ?? '0')==='1'?1:0;
                                if (!$sku || !$nombre || strlen($sku)>50 || strlen($nombre)>150 || (!$maneja_granel && cep_units($stock,3)%1000)) throw new RuntimeException('Campos inválidos.');
                                $resultado=cep_transaction(function () use ($wpdb,$tabla_productos,$sku,$nombre,$categoria,$precio_costo,$precio_venta,$stock,$maneja_granel) {
                                    $old=$wpdb->get_row($wpdb->prepare("SELECT * FROM $tabla_productos WHERE sku=%s FOR UPDATE",$sku));
                                    $data=compact('sku','nombre','categoria','precio_costo','precio_venta','stock','maneja_granel');
                                    if ($old) { cep_check($wpdb->update($tabla_productos,$data,['id'=>$old->id])); $id=$old->id; }
                                    else $id=cep_insert('cep_productos',$data);
                                    $delta=cep_units($stock,3)-($old?cep_units($old->stock,3):0);
                                    if ($delta) cep_movement($id,$delta,'Importación CSV');
                                    cep_audit('importar_producto',['id'=>$id]); return true;
                                });
                            } catch (Throwable $e) { $detalles_errores[]="Fila $num_fila: ".$e->getMessage(); continue; }

                            if ( false === $resultado ) {
                                $detalles_errores[] = "Fila $num_fila (SKU: $sku): Error al guardar en base de datos.";
                            } else {
                                $importados++;
                            }
                        }
                        fclose( $handle );

                        $mensaje_sistema = "Proceso finalizado. Se importaron/actualizaron $importados productos correctamente.";
                        $tipo_alerta = 'success';
                    } else {
                        $mensaje_sistema = 'No se pudo leer el archivo CSV temporal.';
                        $tipo_alerta = 'error';
                    }
                } else {
                    $mensaje_sistema = 'Formato no válido. Sube obligatoriamente un archivo con extensión .csv';
                    $tipo_alerta = 'error';
                }
            } else {
                $mensaje_sistema = 'Ocurrió un error al subir el archivo.';
                $tipo_alerta = 'error';
            }
        }
    }
    ?>
    <div class="wrap">
        <h1 class="wp-heading-inline">Importación Masiva de Productos (CSV)</h1>
        <hr class="wp-header-end">
        <p>Actualiza o registra productos con un CSV de hasta 2 MB. El stock es absoluto: importa con la tienda detenida para evitar sobrescribir ventas recientes. Los identificadores se conservan.</p>

        <?php if ( ! empty( $mensaje_sistema ) ): ?>
            <div class="notice notice-<?php echo esc_attr( $tipo_alerta ); ?> is-dismissible">
                <p><?php echo esc_html( $mensaje_sistema ); ?></p>
                <?php if ( ! empty( $detalles_errores ) ): ?>
                    <ul style="max-height: 150px; overflow-y: auto; margin-bottom: 10px;">
                        <?php foreach ( $detalles_errores as $err ): ?>
                            <li><?php echo esc_html( $err ); ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data">
            <?php wp_nonce_field( 'cep_ejecutar_importacion', 'cep_import_nonce' ); ?>
            <table class="form-table" role="presentation">
                <tbody>
                    <tr>
                        <th scope="row"><label for="archivo_csv">Seleccionar archivo (.csv)</label></th>
                        <td>
                            <input type="file" name="archivo_csv" id="archivo_csv" accept=".csv" required>
                            <p class="description">Guarda tu archivo en formato <strong>CSV (delimitado por comas)</strong>. Estructura esperada de columnas: <code>sku, nombre, categoria, precio_costo, precio_venta, stock, maneja_granel</code>.</p>
                        </td>
                    </tr>
                </tbody>
            </table>
            <?php submit_button( 'Procesar e importar CSV' ); ?>
        </form>
    </div>
    <?php
}

