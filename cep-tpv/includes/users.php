<?php
defined('ABSPATH') || exit;

function cep_vista_perfiles() {
    $usuarios = get_users();
    ?>
    <div class="wrap">
        <h1 class="wp-heading-inline">Perfiles y Usuarios</h1>
        <hr class="wp-header-end">
        <p class="description">Visualiza los usuarios registrados y los roles asignados que determinan el acceso al TPV o al panel administrativo.</p>

        <div class="notice notice-info inline" style="margin: 16px 0;">
            <p><strong>Niveles de acceso del sistema:</strong></p>
            <ul style="margin: 6px 0 0 18px; list-style: disc;">
                <li><strong>Administrador (Administrator):</strong> Acceso total al ERP, inventario, reportes, cajas, compras, respaldos y configuración general.</li>
                <li><strong>Cajero / Operador:</strong> Acceso enfocado a la interfaz del TPV (Cobro y Ventas), báscula y a la apertura/cierre de su propia caja.</li>
            </ul>
        </div>

        <h2>Usuarios registrados</h2>
        <table class="wp-list-table widefat striped">
            <thead>
                <tr>
                    <th scope="col">Usuario (Login)</th>
                    <th scope="col">Nombre / Perfil</th>
                    <th scope="col">Correo electrónico</th>
                    <th scope="col">Rol en WordPress</th>
                    <th scope="col">Acceso al sistema TPV</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($usuarios as $user): 
                    $roles = implode(', ', $user->roles);
                    $es_admin = user_can($user, 'manage_options');
                ?>
                    <tr>
                        <td><strong><?php echo esc_html($user->user_login); ?></strong></td>
                        <td><?php echo esc_html($user->display_name); ?></td>
                        <td><?php echo esc_html($user->user_email); ?></td>
                        <td><code><?php echo esc_html($roles); ?></code></td>
                        <td>
                            <?php if($es_admin): ?>
                                <span class="cep-stock-ok">Acceso Total (Admin)</span>
                            <?php elseif(user_can($user, 'cep_operate')): ?>
                                <span style="color:#2271b1; font-weight:600;">Acceso a TPV y Turnos</span><?php else: ?>Sin acceso al TPV
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php
}

