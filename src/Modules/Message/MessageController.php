<?php

namespace VelocityMarketplace\Modules\Message;

use VelocityMarketplace\Modules\Account\Account;
use WP_REST_Request;
use WP_REST_Response;

class MessageController
{
    public function register_routes()
    {
        register_rest_route('velocity-marketplace/v1', '/messages/contacts', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'contacts'],
                'permission_callback' => [$this, 'check_logged_in'],
            ],
        ]);

        register_rest_route('velocity-marketplace/v1', '/messages/thread', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'thread'],
                'permission_callback' => [$this, 'check_logged_in'],
            ],
        ]);

        register_rest_route('velocity-marketplace/v1', '/messages/send', [
            [
                'methods' => 'POST',
                'callback' => [$this, 'send'],
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

    public function contacts()
    {
        $repo = new MessageRepository();
        $user_id = get_current_user_id();

        return new WP_REST_Response([
            'success' => true,
            'data' => [
                'contacts' => $repo->contacts($user_id),
                'unread_count' => $repo->unread_count($user_id),
            ],
        ], 200);
    }

    public function thread(WP_REST_Request $request)
    {
        $repo = new MessageRepository();
        $user_id = get_current_user_id();
        $contact_id = (int) $request->get_param('contact_id');
        $order_id = (int) $request->get_param('order_id');

        if ($contact_id <= 0) {
            return new WP_REST_Response([
                'success' => true,
                'data' => [
                    'contacts' => $repo->contacts($user_id),
                    'thread' => [],
                    'selected_contact' => null,
                    'selected_order_id' => $order_id,
                    'selected_invoice' => $order_id > 0 ? (string) get_post_meta($order_id, 'vmp_invoice', true) : '',
                    'unread_count' => $repo->unread_count($user_id),
                ],
            ], 200);
        }

        if (!current_user_can('manage_options') && !$repo->can_contact($user_id, $contact_id)) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Percakapan tidak tersedia untuk akun ini.',
            ], 403);
        }

        $repo->mark_thread_read($contact_id, $user_id);
        $contacts = $repo->contacts($user_id);
        $thread = $repo->thread($contact_id, $user_id, 200);
        $selected_contact = $this->selected_contact($repo, $contacts, $contact_id, $user_id);

        return new WP_REST_Response([
            'success' => true,
            'data' => [
                'contacts' => $contacts,
                'thread' => $thread,
                'selected_contact' => $selected_contact,
                'selected_order_id' => $order_id,
                'selected_invoice' => $order_id > 0 ? (string) get_post_meta($order_id, 'vmp_invoice', true) : '',
                'unread_count' => $repo->unread_count($user_id),
            ],
        ], 200);
    }

    public function send(WP_REST_Request $request)
    {
        $repo = new MessageRepository();
        $user_id = get_current_user_id();
        $payload = $request->get_json_params();
        if (!is_array($payload)) {
            $payload = $request->get_params();
        }

        $recipient_id = isset($payload['recipient_id']) ? (int) $payload['recipient_id'] : 0;
        $order_id = isset($payload['order_id']) ? (int) $payload['order_id'] : 0;
        $message = isset($payload['message']) ? (string) $payload['message'] : '';

        if ($recipient_id <= 0 || trim($message) === '') {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Penerima dan isi pesan wajib diisi.',
            ], 400);
        }

        if (!current_user_can('manage_options') && !$repo->can_contact($user_id, $recipient_id)) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Penerima pesan tidak valid untuk akun ini.',
            ], 403);
        }

        $message_id = $repo->send($user_id, $recipient_id, $message, $order_id);
        if ($message_id <= 0) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Pesan gagal dikirim.',
            ], 400);
        }

        $contacts = $repo->contacts($user_id);
        $thread = $repo->thread($recipient_id, $user_id, 200);
        $selected_contact = $this->selected_contact($repo, $contacts, $recipient_id, $user_id);

        return new WP_REST_Response([
            'success' => true,
            'message' => 'Pesan berhasil dikirim.',
            'data' => [
                'contacts' => $contacts,
                'thread' => $thread,
                'selected_contact' => $selected_contact,
                'selected_order_id' => $order_id,
                'selected_invoice' => $order_id > 0 ? (string) get_post_meta($order_id, 'vmp_invoice', true) : '',
                'unread_count' => $repo->unread_count($user_id),
            ],
        ], 200);
    }

    private function selected_contact(MessageRepository $repo, array $contacts, $contact_id, $user_id)
    {
        foreach ($contacts as $contact_row) {
            if ((int) ($contact_row['id'] ?? 0) === (int) $contact_id) {
                return $contact_row;
            }
        }

        $selected_user = get_userdata((int) $contact_id);
        if ($selected_user && (current_user_can('manage_options') || $repo->can_contact($user_id, (int) $contact_id))) {
            return [
                'id' => (int) $contact_id,
                'name' => $selected_user->display_name !== '' ? $selected_user->display_name : $selected_user->user_login,
                'role' => Account::user_role_label((int) $contact_id),
                'last_message' => '',
                'last_created_at' => '',
                'last_order_id' => 0,
                'last_order_invoice' => '',
                'unread_count' => 0,
            ];
        }

        return null;
    }
}
