<?php

namespace VelocityMarketplace\Modules\Notification;

use WP_REST_Request;
use WP_REST_Response;

class NotificationController
{
    public function register_routes()
    {
        register_rest_route('velocity-marketplace/v1', '/notifications', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'index'],
                'permission_callback' => [$this, 'check_logged_in'],
            ],
        ]);

        register_rest_route('velocity-marketplace/v1', '/notifications/read-all', [
            [
                'methods' => 'POST',
                'callback' => [$this, 'mark_all_read'],
                'permission_callback' => [$this, 'check_rest_nonce'],
            ],
        ]);

        register_rest_route('velocity-marketplace/v1', '/notifications/(?P<id>[\w-]+)/read', [
            [
                'methods' => 'POST',
                'callback' => [$this, 'mark_read'],
                'permission_callback' => [$this, 'check_rest_nonce'],
            ],
        ]);

        register_rest_route('velocity-marketplace/v1', '/notifications/(?P<id>[\w-]+)', [
            [
                'methods' => 'DELETE',
                'callback' => [$this, 'delete'],
                'permission_callback' => [$this, 'check_rest_nonce'],
            ],
        ]);
    }

    public function check_logged_in()
    {
        return is_user_logged_in();
    }

    public function check_rest_nonce(WP_REST_Request $request)
    {
        if (!is_user_logged_in()) {
            return false;
        }

        $nonce = $request->get_header('x_wp_nonce');
        if (!$nonce) {
            $nonce = $request->get_header('x-wp-nonce');
        }

        return is_string($nonce) && wp_verify_nonce($nonce, 'wp_rest');
    }

    public function index()
    {
        $repo = new NotificationRepository();
        $rows = $repo->all(get_current_user_id());

        return new WP_REST_Response([
            'success' => true,
            'data' => [
                'items' => $rows,
                'unread_count' => $repo->unread_count(get_current_user_id()),
            ],
        ], 200);
    }

    public function mark_all_read()
    {
        $repo = new NotificationRepository();
        $repo->mark_all_read(get_current_user_id());

        return $this->response($repo, 'Semua notifikasi ditandai sudah dibaca.');
    }

    public function mark_read(WP_REST_Request $request)
    {
        $id = sanitize_text_field((string) $request->get_param('id'));
        if ($id === '') {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'ID notifikasi tidak valid.',
            ], 400);
        }

        $repo = new NotificationRepository();
        $repo->mark_read($id, get_current_user_id());

        return $this->response($repo, 'Notifikasi ditandai sudah dibaca.');
    }

    public function delete(WP_REST_Request $request)
    {
        $id = sanitize_text_field((string) $request->get_param('id'));
        if ($id === '') {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'ID notifikasi tidak valid.',
            ], 400);
        }

        $repo = new NotificationRepository();
        $repo->delete($id, get_current_user_id());

        return $this->response($repo, 'Notifikasi dihapus.');
    }

    private function response(NotificationRepository $repo, $message)
    {
        return new WP_REST_Response([
            'success' => true,
            'message' => (string) $message,
            'data' => [
                'items' => $repo->all(get_current_user_id()),
                'unread_count' => $repo->unread_count(get_current_user_id()),
            ],
        ], 200);
    }
}
