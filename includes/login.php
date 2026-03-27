<?php
/**
 * ============================================================
 * Module: Custom Login Page
 * ============================================================
 *
 * Migrated from: Snippet 4 — "Custom Login Page"
 *
 * Changes from the original:
 * • Removed cd_login_config() — now uses cfd_get_config()
 * • All cd_ prefixes → cfd_
 * • Login CSS extracted to assets/css/login.css
 * • Logout redirect removed (handled by roles-and-access.php)
 * • Custom ?action=logout handler kept here (Perfmatters compat)
 * ============================================================
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// ═══════════════════════════════════════════════════════════
// 1. ENQUEUE LOGIN PAGE STYLES
// ═══════════════════════════════════════════════════════════

add_action( 'wp_enqueue_scripts', 'cfd_enqueue_login_styles' );

function cfd_enqueue_login_styles(): void {
    $config = cfd_get_config();
    if ( ! is_page( $config['login_slug'] ) ) {
        return;
    }

    wp_enqueue_style(
        'cfd-login',
        CFD_URL . 'assets/css/login.css',
        array(),
        CFD_VERSION
    );
}

// ═══════════════════════════════════════════════════════════
// 2. SMART LOGIN FORM SHORTCODE
// ═══════════════════════════════════════════════════════════

add_shortcode( 'cd_login_form', 'cfd_render_login_form_smart' );

function cfd_render_login_form_smart(): string {
    if ( is_user_logged_in() && ! cfd_is_bricks_builder() ) {
        $config = cfd_get_config();
        wp_safe_redirect( home_url( $config['login_redirect'] ) );
        exit;
    }

    $action = isset( $_GET['action'] ) ? sanitize_key( $_GET['action'] ) : '';

    switch ( $action ) {
        case 'lostpassword':
        case 'retrievepassword':
            return cfd_render_lost_password_form();
        case 'rp':
        case 'resetpass':
            return cfd_render_reset_password_form();
        default:
            return cfd_render_login_form_only();
    }
}

// ── View 1: Login form ──────────────────────────────────────
function cfd_render_login_form_only(): string {
    $config      = cfd_get_config();
    $redirect_to = isset( $_GET['redirect_to'] )
        ? esc_url( $_GET['redirect_to'] )
        : home_url( $config['login_redirect'] );

    // Handle login POST directly on this page (avoids wp-login.php 301 issues).
    $error_msg = '';
    if ( $_SERVER['REQUEST_METHOD'] === 'POST' && isset( $_POST['cfd_login_nonce'] ) ) {
        if ( ! wp_verify_nonce( $_POST['cfd_login_nonce'], 'cfd-login' ) ) {
            $error_msg = 'Solicitud no válida. Intenta de nuevo.';
        } else {
            $username = sanitize_text_field( $_POST['log'] ?? '' );
            $password = $_POST['pwd'] ?? '';
            $remember = ! empty( $_POST['rememberme'] );

            if ( empty( $username ) || empty( $password ) ) {
                $error_msg = 'Por favor, introduce tu correo electrónico y contraseña.';
            } else {
                $user = wp_signon( array(
                    'user_login'    => $username,
                    'user_password' => $password,
                    'remember'      => $remember,
                ), is_ssl() );

                if ( is_wp_error( $user ) ) {
                    $error_msg = 'Correo electrónico o contraseña incorrectos.';
                } else {
                    wp_safe_redirect( $redirect_to );
                    exit;
                }
            }
        }
    }

    // Also show errors from query string (e.g. after password reset).
    if ( empty( $error_msg ) ) {
        $error_msg = cfd_get_login_error_message();
    }

    ob_start();
    if ( $error_msg ) {
        echo '<div class="cd-login-error">' . esc_html( $error_msg ) . '</div>';
    }
    ?>
    <form name="loginform" id="loginform" action="" method="post">
        <p class="login-username">
            <label for="user_login">Correo electrónico</label>
            <input type="text" name="log" id="user_login" class="input" value="" size="20" required />
        </p>
        <p class="login-password">
            <label for="user_pass">Contraseña</label>
            <input type="password" name="pwd" id="user_pass" class="input" value="" size="20" required />
        </p>
        <p class="login-remember"><label><input name="rememberme" type="checkbox" id="rememberme" value="forever" checked="checked" /> Recuérdame</label></p>
        <p class="login-submit">
            <input type="submit" name="wp-submit" id="wp-submit" class="button button-primary" value="Entrar a mi espacio ✨" />
            <input type="hidden" name="redirect_to" value="<?php echo esc_attr( $redirect_to ); ?>" />
        </p>
        <?php wp_nonce_field( 'cfd-login', 'cfd_login_nonce' ); ?>
    </form>
    <?php
    return ob_get_clean();
}

// ── View 2: Lost password ───────────────────────────────────
function cfd_render_lost_password_form(): string {
    $config   = cfd_get_config();
    $page_url = home_url( '/' . $config['login_slug'] . '/' );
    $errors   = '';

    if ( $_SERVER['REQUEST_METHOD'] === 'POST' && isset( $_POST['cd_lost_password_nonce'] ) ) {
        if ( ! wp_verify_nonce( $_POST['cd_lost_password_nonce'], 'cd_lost_password' ) ) {
            $errors = '<div class="cd-login-error">Error de seguridad. Recarga la página e inténtalo de nuevo.</div>';
        } else {
            $user_login = sanitize_text_field( $_POST['user_login'] ?? '' );

            if ( empty( $user_login ) ) {
                $errors = '<div class="cd-login-error">Por favor, ingresa tu correo electrónico.</div>';
            } else {
                $result = retrieve_password( $user_login );

                if ( is_wp_error( $result ) ) {
                    $errors = '<div class="cd-login-error">No encontramos una cuenta con ese correo.</div>';
                } else {
                    $success_url = add_query_arg( array( 'action' => 'lostpassword', 'sent' => 'true' ), $page_url );
                    wp_safe_redirect( $success_url );
                    exit;
                }
            }
        }
    }

    if ( isset( $_GET['sent'] ) && $_GET['sent'] === 'true' ) {
        return '<div class="cd-login-success">📧 Te enviamos un enlace para restablecer tu contraseña. Revisa tu correo (y la carpeta de spam).</div>'
             . '<p class="cd-login-back"><a href="' . esc_url( $page_url ) . '">← Volver al inicio de sesión</a></p>';
    }

    ob_start();
    echo $errors;
    ?>
    <form method="post" class="cd-lost-password-form" action="">
        <?php wp_nonce_field( 'cd_lost_password', 'cd_lost_password_nonce' ); ?>
        <p>
            <label for="user_login">Correo electrónico</label>
            <input type="text" name="user_login" id="user_login" autocomplete="email" required />
        </p>
        <p class="login-submit">
            <input type="submit" id="wp-submit" value="Enviar enlace de recuperación" />
        </p>
    </form>
    <p class="cd-login-back"><a href="<?php echo esc_url( $page_url ); ?>">← Volver al inicio de sesión</a></p>
    <?php
    return ob_get_clean();
}

// ── View 3: Reset password ──────────────────────────────────
function cfd_render_reset_password_form(): string {
    $config   = cfd_get_config();
    $page_url = home_url( '/' . $config['login_slug'] . '/' );

    $rp_key   = isset( $_GET['key'] )   ? sanitize_text_field( $_GET['key'] )   : '';
    $rp_login = isset( $_GET['login'] ) ? sanitize_text_field( $_GET['login'] ) : '';

    if ( empty( $rp_key ) || empty( $rp_login ) ) {
        return '<div class="cd-login-error">Enlace inválido o expirado.</div>'
             . '<p class="cd-login-back"><a href="' . esc_url( add_query_arg( 'action', 'lostpassword', $page_url ) ) . '">Solicitar un nuevo enlace</a></p>';
    }

    $user = check_password_reset_key( $rp_key, $rp_login );

    if ( is_wp_error( $user ) ) {
        return '<div class="cd-login-error">Este enlace ya expiró o no es válido. Solicita uno nuevo.</div>'
             . '<p class="cd-login-back"><a href="' . esc_url( add_query_arg( 'action', 'lostpassword', $page_url ) ) . '">Solicitar un nuevo enlace</a></p>';
    }

    $errors = '';

    if ( $_SERVER['REQUEST_METHOD'] === 'POST' && isset( $_POST['cd_reset_nonce'] ) ) {
        if ( ! wp_verify_nonce( $_POST['cd_reset_nonce'], 'cd_reset_password' ) ) {
            $errors = '<div class="cd-login-error">Error de seguridad. Recarga la página e inténtalo de nuevo.</div>';
        } else {
            $pass1 = $_POST['pass1'] ?? '';
            $pass2 = $_POST['pass2'] ?? '';

            if ( empty( $pass1 ) || empty( $pass2 ) ) {
                $errors = '<div class="cd-login-error">Por favor, completa ambos campos.</div>';
            } elseif ( $pass1 !== $pass2 ) {
                $errors = '<div class="cd-login-error">Las contraseñas no coinciden.</div>';
            } elseif ( strlen( $pass1 ) < 8 ) {
                $errors = '<div class="cd-login-error">La contraseña debe tener al menos 8 caracteres.</div>';
            } else {
                reset_password( $user, $pass1 );
                wp_safe_redirect( add_query_arg( 'login', 'reset', $page_url ) );
                exit;
            }
        }
    }

    ob_start();
    echo $errors;
    ?>
    <form method="post" class="cd-reset-password-form" action="">
        <?php wp_nonce_field( 'cd_reset_password', 'cd_reset_nonce' ); ?>
        <input type="hidden" name="rp_key" value="<?php echo esc_attr( $rp_key ); ?>" />
        <input type="hidden" name="rp_login" value="<?php echo esc_attr( $rp_login ); ?>" />
        <p>
            <label for="pass1">Nueva contraseña</label>
            <input type="password" name="pass1" id="pass1" autocomplete="new-password" required />
        </p>
        <p>
            <label for="pass2">Confirmar contraseña</label>
            <input type="password" name="pass2" id="pass2" autocomplete="new-password" required />
        </p>
        <p class="login-submit">
            <input type="submit" id="wp-submit" value="Guardar nueva contraseña" />
        </p>
    </form>
    <?php
    return ob_get_clean();
}

// ═══════════════════════════════════════════════════════════
// 3. LOGIN ERROR/SUCCESS SHORTCODE
// ═══════════════════════════════════════════════════════════

add_shortcode( 'cd_login_error', 'cfd_render_login_error' );

function cfd_render_login_error(): string {
    if ( isset( $_GET['login'] ) && $_GET['login'] === 'reset' ) {
        return '<div class="cd-login-success">✅ Contraseña actualizada. Ya puedes iniciar sesión.</div>';
    }

    if ( ! isset( $_GET['login'] ) ) {
        return '';
    }

    $messages = array(
        'failed'       => 'Correo o contraseña incorrectos. Inténtalo de nuevo.',
        'empty'        => 'Por favor, completa todos los campos.',
        'invalidcombo' => 'No encontramos una cuenta con ese correo.',
    );

    $key = sanitize_key( $_GET['login'] );
    if ( ! isset( $messages[ $key ] ) ) {
        return '';
    }

    return '<div class="cd-login-error">' . esc_html( $messages[ $key ] ) . '</div>';
}

// ═══════════════════════════════════════════════════════════
// 4. INTERCEPT FAILED LOGINS
// ═══════════════════════════════════════════════════════════

add_action( 'wp_login_failed', 'cfd_login_failed_redirect' );

function cfd_login_failed_redirect( $username ): void {
    $config    = cfd_get_config();
    $login_url = home_url( '/' . $config['login_slug'] . '/' );
    $login_url = add_query_arg( 'login', 'failed', $login_url );
    wp_safe_redirect( $login_url );
    exit;
}

add_filter( 'authenticate', 'cfd_login_empty_redirect', 1, 3 );

function cfd_login_empty_redirect( $user, $username, $password ) {
    $config    = cfd_get_config();
    $login_url = home_url( '/' . $config['login_slug'] . '/' );

    $referer = wp_get_referer();
    if ( ! $referer || strpos( $referer, $config['login_slug'] ) === false ) {
        return $user;
    }

    if ( empty( $username ) || empty( $password ) ) {
        $login_url = add_query_arg( 'login', 'empty', $login_url );
        wp_safe_redirect( $login_url );
        exit;
    }

    return $user;
}

// ═══════════════════════════════════════════════════════════
// 5. CUSTOM LOGOUT (Perfmatters compatibility)
// ═══════════════════════════════════════════════════════════

function cfd_get_logout_url(): string {
    $config = cfd_get_config();
    return add_query_arg( array(
        'action'   => 'logout',
        '_wpnonce' => wp_create_nonce( 'cfd-logout' ),
    ), home_url( '/' . $config['login_slug'] . '/' ) );
}

// Shortcode: outputs just the URL (for Bricks link fields).
// NOTE: Shortcode tag kept as 'cd_logout_url' for backward
// compatibility with existing Bricks templates.
add_shortcode( 'cd_logout_url', function() {
    return esc_url( cfd_get_logout_url() );
} );

add_action( 'template_redirect', 'cfd_handle_custom_logout' );

function cfd_handle_custom_logout(): void {
    $config = cfd_get_config();

    if ( ! is_page( $config['login_slug'] ) ) {
        return;
    }

    $action = isset( $_GET['action'] ) ? sanitize_key( $_GET['action'] ) : '';
    if ( $action !== 'logout' ) {
        return;
    }

    $nonce_valid = isset( $_GET['_wpnonce'] )
        ? wp_verify_nonce( sanitize_text_field( $_GET['_wpnonce'] ), 'cfd-logout' )
        : false;

    if ( ! $nonce_valid ) {
        wp_safe_redirect( home_url( '/' . $config['login_slug'] . '/' ) );
        exit;
    }

    wp_logout();
    wp_safe_redirect( home_url( '/' . $config['login_slug'] . '/' ) );
    exit;
}

// ═══════════════════════════════════════════════════════════
// 6. REDIRECT wp-login.php TO CUSTOM PAGE
// ═══════════════════════════════════════════════════════════

add_action( 'login_init', 'cfd_redirect_wp_login' );

function cfd_redirect_wp_login(): void {
    $config = cfd_get_config();
    $action = isset( $_GET['action'] ) ? sanitize_key( $_GET['action'] ) : '';

    $backend_only = array( 'postpass', 'confirmaction' );
    if ( in_array( $action, $backend_only, true ) ) {
        return;
    }

    if ( $_SERVER['REQUEST_METHOD'] === 'POST' ) {
        return;
    }

    $login_url = home_url( '/' . $config['login_slug'] . '/' );

    $password_actions = array( 'lostpassword', 'retrievepassword', 'resetpass', 'rp' );
    if ( in_array( $action, $password_actions, true ) ) {
        $mapped_action = ( $action === 'resetpass' ) ? 'rp' : $action;
        $mapped_action = ( $action === 'retrievepassword' ) ? 'lostpassword' : $mapped_action;
        $login_url = add_query_arg( 'action', $mapped_action, $login_url );

        if ( isset( $_GET['key'] ) ) {
            $login_url = add_query_arg( 'key', rawurlencode( $_GET['key'] ), $login_url );
        }
        if ( isset( $_GET['login'] ) ) {
            $login_url = add_query_arg( 'login', rawurlencode( $_GET['login'] ), $login_url );
        }
    }

    if ( isset( $_GET['redirect_to'] ) ) {
        $login_url = add_query_arg( 'redirect_to', urlencode( $_GET['redirect_to'] ), $login_url );
    }

    wp_safe_redirect( $login_url );
    exit;
}

// ═══════════════════════════════════════════════════════════
// 7. REWRITE PASSWORD RESET EMAIL
// ═══════════════════════════════════════════════════════════

add_filter( 'retrieve_password_notification_email', 'cfd_rewrite_reset_email', 10, 4 );

function cfd_rewrite_reset_email( $defaults, $key, $user_login, $user_data ) {
    $config   = cfd_get_config();
    $page_url = home_url( '/' . $config['login_slug'] . '/' );

    $reset_url = add_query_arg( array(
        'action' => 'rp',
        'key'    => $key,
        'login'  => rawurlencode( $user_login ),
    ), $page_url );

    $site_name = wp_specialchars_decode( get_option( 'blogname' ), ENT_QUOTES );

    $message  = 'Alguien ha solicitado restablecer la contraseña de la siguiente cuenta:' . "\r\n\r\n";
    $message .= 'Sitio: ' . $site_name . "\r\n";
    $message .= 'Usuario: ' . $user_login . "\r\n\r\n";
    $message .= 'Si no hiciste esta solicitud, ignora este correo.' . "\r\n\r\n";
    $message .= 'Para restablecer tu contraseña, haz clic aquí:' . "\r\n\r\n";
    $message .= $reset_url . "\r\n";

    $defaults['message'] = $message;
    $defaults['subject'] = '[' . $site_name . '] Restablecer contraseña';

    return $defaults;
}

add_filter( 'lostpassword_url', 'cfd_custom_lostpassword_url', 10, 2 );

function cfd_custom_lostpassword_url( $lostpassword_url, $redirect ) {
    $config = cfd_get_config();
    $url    = home_url( '/' . $config['login_slug'] . '/?action=lostpassword' );

    if ( ! empty( $redirect ) ) {
        $url = add_query_arg( 'redirect_to', urlencode( $redirect ), $url );
    }

    return $url;
}
