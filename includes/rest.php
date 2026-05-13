<?php
/**
 * ============================================================
 * Module: REST API
 * ============================================================
 *
 * Routes:
 *   POST  /wp-json/cfd/v1/restore/<id>
 *     Untrashes a post — used by the undo-toast on the listing page.
 *     Requires: nonce_action 'cfd_restore_<id>', cap edit_post on <id>,
 *               post must be in user's manageable_cpts, must be trashed.
 *
 * Phase 2 namespace foundation. Future endpoints (autosave, optimistic
 * patches, etc.) live here.
 * ============================================================
 */

if (!defined('ABSPATH')) {
    exit;
}

add_action('rest_api_init', 'cfd_register_rest_routes');

function cfd_register_rest_routes(): void
{
    register_rest_route('cfd/v1', '/restore/(?P<id>\d+)', array(
        'methods'             => 'POST',
        'callback'            => 'cfd_rest_restore_post',
        'permission_callback' => 'cfd_rest_restore_permission',
        'args'                => array(
            'id' => array(
                'required'          => true,
                'validate_callback' => function ($v) {
                    return is_numeric($v) && (int) $v > 0;
                },
                'sanitize_callback' => 'absint',
            ),
        ),
    ));
}

function cfd_rest_restore_permission(WP_REST_Request $request)
{
    if (!is_user_logged_in()) {
        return new WP_Error('cfd_not_logged_in', 'Login required.', array('status' => 401));
    }
    $post_id = (int) $request['id'];
    if ($post_id < 1) {
        return new WP_Error('cfd_bad_id', 'Invalid post ID.', array('status' => 400));
    }

    // X-WP-Nonce header carries the standard REST cookie nonce (auto-injected
    // by wp.apiFetch / wp_localize_script when we pass nonce: wpApiSettings.nonce).
    // We additionally verify our action-specific nonce to scope the request.
    $action_nonce = $request->get_header('X-CFD-Nonce');
    if (!$action_nonce || !wp_verify_nonce($action_nonce, 'cfd_restore_' . $post_id)) {
        return new WP_Error('cfd_bad_nonce', 'Invalid nonce.', array('status' => 403));
    }

    if (!current_user_can('edit_post', $post_id)) {
        return new WP_Error('cfd_cap', 'You cannot edit this post.', array('status' => 403));
    }

    $post = get_post($post_id);
    if (!$post) {
        return new WP_Error('cfd_no_post', 'Post not found.', array('status' => 404));
    }

    $user_config = cfd_get_user_config();
    if (!in_array($post->post_type, $user_config['manageable_cpts'], true)) {
        return new WP_Error('cfd_not_manageable', 'Post type not manageable.', array('status' => 403));
    }

    return true;
}

function cfd_rest_restore_post(WP_REST_Request $request): WP_REST_Response
{
    $post_id = (int) $request['id'];
    $post = get_post($post_id);

    if (!$post) {
        return new WP_REST_Response(array('ok' => false, 'error' => 'not_found'), 404);
    }
    if ($post->post_status !== 'trash') {
        // Nothing to undo — already restored or never trashed.
        return new WP_REST_Response(array('ok' => true, 'status' => $post->post_status, 'already' => true), 200);
    }

    wp_untrash_post($post_id);
    $post_after = get_post($post_id);

    return new WP_REST_Response(array(
        'ok'     => true,
        'id'     => $post_id,
        'status' => $post_after ? $post_after->post_status : 'unknown',
    ), 200);
}
