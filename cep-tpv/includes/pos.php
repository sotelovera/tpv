<?php
defined('ABSPATH') || exit;
function cep_vista_tpv() {
    cep_verificar_acceso_cajero(); global $wpdb;
    $session=$wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}cep_caja_sesiones WHERE usuario_id=%d AND estado='abierta'",get_current_user_id()));
    if (!$session) { echo '<div class="wrap"><h1>Nueva venta</h1><p>Abre tu turno en <a href="'.esc_url(admin_url('admin.php?page=cep-caja')).'">Caja y Arqueo</a>.</p></div>'; return; }
    wp_enqueue_script('cep-pos',plugins_url('assets/pos.js',CEP_FILE),['jquery'],CEP_VERSION,true);
    wp_localize_script('cep-pos','CEP',['ajax'=>admin_url('admin-ajax.php'),'nonce'=>wp_create_nonce('cep_tpv_venta'),'searchNonce'=>wp_create_nonce('cep_tpv_nonce'),'storage'=>'cep_tickets_'.get_current_blog_id().'_'.get_current_user_id(),'admin'=>current_user_can('manage_options')]);
    $clients=$wpdb->get_results("SELECT id,nombre FROM {$wpdb->prefix}cep_clientes WHERE activo=1 ORDER BY nombre LIMIT 1000");
    ?>
    <div class="wrap cep-tpv-screen" id="cep-pos"><h1>Nueva venta <small><?php echo esc_html($session->caja_id); ?></small></h1>
    <p>F2: buscar · F8: cobrar · Los tickets pendientes se guardan en este navegador. No cierres ni cambies el ticket durante un cobro.</p>
    <div id="cep-message" role="status" aria-live="polite"></div>
    <div class="cep-pos-layout"><section class="cep-panel"><label for="cep-search"><strong>Buscar artículo o escanear código</strong></label>
    <input id="cep-search" type="search" placeholder="Nombre o código exacto + Enter" autocomplete="off" autofocus>
    <div id="cep-results" aria-live="polite"></div>
    <h2>Ticket <select id="cep-tickets" aria-label="Seleccionar ticket"></select> <button type="button" class="button" id="cep-new">Nuevo / suspender</button></h2>
    <div id="cep-cart" class="cep-table-scroll"></div>
    <?php if(current_user_can('manage_options')): ?><details><summary>Concepto sin catálogo</summary><label>Nombre <input id="cep-common-name" maxlength="150"></label> <label>Precio <input type="number" id="cep-common-price" min="0.01" step="0.01"></label><button class="button" type="button" id="cep-common">Añadir</button></details><?php endif; ?>
    </section><aside class="cep-panel cep-checkout"><h2>Cobro</h2>
    <label for="cep-client">Cliente</label><select id="cep-client"><option value="0">Público general</option><?php foreach($clients as $c) echo '<option value="'.(int)$c->id.'">'.esc_html($c->nombre).'</option>'; ?></select>
    <?php if(current_user_can('manage_options')): ?><label for="cep-discount">Descuento global ($ antes de impuestos)</label><input id="cep-discount" type="number" min="0" step="0.01" value="0"><?php else: ?><input id="cep-discount" type="hidden" value="0"><?php endif; ?>
    <label for="cep-method">Forma de pago</label><select id="cep-method"><option>Efectivo</option><option>Tarjeta</option><option>Transferencia</option><option>Crédito</option><option>Mixto</option></select>
    <div id="cep-cash"><label for="cep-received">Efectivo recibido</label><input id="cep-received" type="number" min="0" step="0.01"><p>Cambio estimado: $<span id="cep-change">0.00</span></p></div>
    <div id="cep-mixed" hidden><?php foreach(['Efectivo','Tarjeta','Transferencia'] as $m) echo '<label>'.esc_html($m).'<input type="number" min="0" step="0.01" data-payment="'.esc_attr($m).'" value="0"></label>'; ?></div>
    <p>Impuestos añadidos: $<span id="cep-tax">0.00</span></p><p class="cep-total">Total <strong id="cep-total">$0.00</strong></p>
    <button type="button" class="button button-primary button-hero" id="cep-charge">Confirmar cobro · F8</button>
    <button type="button" class="button" id="cep-print" disabled>Imprimir último ticket</button>
    <button type="button" class="button-link-delete" id="cep-cancel">Descartar ticket pendiente</button>
    <p>El cobro se registra antes de imprimir. Si falla la conexión, usa «Reintentar cobro» para recuperar la misma operación.</p>
    <div id="cep-receipt" hidden></div></aside></div></div>
    <?php
}
