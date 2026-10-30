<?php
defined('ABSPATH') || exit;

function cep_vista_tpv_bascula() {
    if(!cep_verificar_acceso_cajero()) {
        wp_die(__('No tienes permisos suficientes para acceder a esta página.'));
    }
    ?>
    <div class="wrap">
        <h1 class="wp-heading-inline">Conexión de Báscula USB / Serial</h1>
        <hr class="wp-header-end">

        <h2>Báscula electrónica</h2>
        <p class="description">Conecta tu báscula electrónica mediante Web Serial API compatible (Google Chrome / Microsoft Edge).</p>

        <div style="margin: 20px 0;">
            <button type="button" id="btn-conectar-bascula" class="button button-primary button-large">
                <span class="dashicons dashicons-admin-plugins" style="vertical-align:middle;margin-right:4px;"></span> Conectar Báscula USB
            </button>
        </div>

        <table class="form-table" role="presentation">
            <tbody>
                <tr>
                    <th scope="row">Peso en tiempo real</th>
                    <td>
                        <span id="peso-lectura" style="font-size:28px;font-weight:600;color:#2271b1;font-family:monospace;">0.000</span>
                        <strong style="font-size:16px;">Kg</strong>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
    <script>
        document.getElementById('btn-conectar-bascula').addEventListener('click', async () => {
            try {
                if (!navigator.serial || !window.isSecureContext) throw new Error('Requiere HTTPS y un navegador con Web Serial.');
                const port = await navigator.serial.requestPort();
                await port.open({ baudRate: 9600 });
                alert("Báscula conectada correctamente.");
                
                const textDecoder = new TextDecoderStream();
                const readableStreamClosed = port.readable.pipeTo(textDecoder.writable);
                const reader = textDecoder.readable.getReader();

                while (true) {
                    const { value, done } = await reader.read();
                    if (done) break;
                    if (value) {
                        document.getElementById('peso-lectura').innerText = value.trim();
                    }
                }
            } catch (error) { 
                alert("Error de conexión con la báscula: " + error); 
            }
        });
    </script>
    <?php
}

