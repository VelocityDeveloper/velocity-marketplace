<?php

namespace VelocityMarketplace\Modules\Message;

use VelocityMarketplace\Modules\Account\Account;
use VelocityMarketplace\Modules\Order\OrderData;

class MessageRepository
{
    public function send($sender_id, $recipient_id, $message, $order_id = 0)
    {
        global $wpdb;

        $sender_id = (int) $sender_id;
        $recipient_id = (int) $recipient_id;
        $order_id = (int) $order_id;
        $message = trim(wp_kses_post((string) $message));

        if ($sender_id <= 0 || $recipient_id <= 0 || $sender_id === $recipient_id || $message === '') {
            return 0;
        }

        if (!get_userdata($sender_id) || !get_userdata($recipient_id)) {
            return 0;
        }

        $inserted = $wpdb->insert(
            MessageTable::table_name(),
            [
                'sender_id' => $sender_id,
                'recipient_id' => $recipient_id,
                'order_id' => max(0, $order_id),
                'message' => $message,
                'is_read' => 0,
                'created_at' => current_time('mysql'),
            ],
            ['%d', '%d', '%d', '%s', '%d', '%s']
        );

        return $inserted ? (int) $wpdb->insert_id : 0;
    }

    public function all($user_id = 0, $limit = 100)
    {
        global $wpdb;

        $user_id = $user_id > 0 ? (int) $user_id : get_current_user_id();
        $limit = max(1, min(300, (int) $limit));
        if ($user_id <= 0) {
            return [];
        }

        $table = MessageTable::table_name();
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, sender_id, recipient_id, order_id, message, is_read, created_at
                FROM {$table}
                WHERE sender_id = %d OR recipient_id = %d
                ORDER BY created_at DESC, id DESC
                LIMIT %d",
                $user_id,
                $user_id,
                $limit
            ),
            ARRAY_A
        );

        if (empty($rows)) {
            return [];
        }

        $items = [];
        foreach ($rows as $row) {
            $sender_id = isset($row['sender_id']) ? (int) $row['sender_id'] : 0;
            $recipient_id = isset($row['recipient_id']) ? (int) $row['recipient_id'] : 0;
            $order_id = isset($row['order_id']) ? (int) $row['order_id'] : 0;
            $incoming = $recipient_id === $user_id;
            $partner_id = $incoming ? $sender_id : $recipient_id;
            $partner = $partner_id > 0 ? get_userdata($partner_id) : false;

            $items[] = [
                'id' => (int) ($row['id'] ?? 0),
                'message' => (string) ($row['message'] ?? ''),
                'sender_id' => $sender_id,
                'recipient_id' => $recipient_id,
                'partner_id' => $partner_id,
                'partner_name' => $partner ? ($partner->display_name !== '' ? $partner->display_name : $partner->user_login) : 'User',
                'incoming' => $incoming ? 1 : 0,
                'order_id' => $order_id,
                'order_invoice' => $order_id > 0 ? (string) get_post_meta($order_id, 'vmp_invoice', true) : '',
                'is_read' => !empty($row['is_read']) ? 1 : 0,
                'created_at' => (string) ($row['created_at'] ?? ''),
            ];
        }

        return $items;
    }

    public function thread($contact_id, $user_id = 0, $limit = 200)
    {
        global $wpdb;

        $contact_id = (int) $contact_id;
        $user_id = $user_id > 0 ? (int) $user_id : get_current_user_id();
        $limit = max(1, min(500, (int) $limit));
        if ($user_id <= 0 || $contact_id <= 0 || $user_id === $contact_id) {
            return [];
        }

        $table = MessageTable::table_name();
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, sender_id, recipient_id, order_id, message, is_read, created_at
                FROM {$table}
                WHERE (sender_id = %d AND recipient_id = %d) OR (sender_id = %d AND recipient_id = %d)
                ORDER BY created_at ASC, id ASC
                LIMIT %d",
                $user_id,
                $contact_id,
                $contact_id,
                $user_id,
                $limit
            ),
            ARRAY_A
        );

        if (empty($rows)) {
            return [];
        }

        $partner = get_userdata($contact_id);
        $partner_name = $partner ? ($partner->display_name !== '' ? $partner->display_name : $partner->user_login) : 'User';
        $items = [];
        foreach ($rows as $row) {
            $sender_id = isset($row['sender_id']) ? (int) $row['sender_id'] : 0;
            $recipient_id = isset($row['recipient_id']) ? (int) $row['recipient_id'] : 0;
            $order_id = isset($row['order_id']) ? (int) $row['order_id'] : 0;
            $incoming = $recipient_id === $user_id;

            $items[] = [
                'id' => (int) ($row['id'] ?? 0),
                'message' => (string) ($row['message'] ?? ''),
                'sender_id' => $sender_id,
                'recipient_id' => $recipient_id,
                'partner_id' => $contact_id,
                'partner_name' => $partner_name,
                'incoming' => $incoming ? 1 : 0,
                'order_id' => $order_id,
                'order_invoice' => $order_id > 0 ? (string) get_post_meta($order_id, 'vmp_invoice', true) : '',
                'is_read' => !empty($row['is_read']) ? 1 : 0,
                'created_at' => (string) ($row['created_at'] ?? ''),
            ];
        }

        return $items;
    }

    public function unread_count($user_id = 0)
    {
        global $wpdb;

        $user_id = $user_id > 0 ? (int) $user_id : get_current_user_id();
        if ($user_id <= 0) {
            return 0;
        }

        $table = MessageTable::table_name();
        $count = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$table} WHERE recipient_id = %d AND is_read = 0",
                $user_id
            )
        );

        return (int) $count;
    }

    public function mark_read($message_id, $user_id = 0)
    {
        global $wpdb;

        $message_id = (int) $message_id;
        $user_id = $user_id > 0 ? (int) $user_id : get_current_user_id();
        if ($message_id <= 0 || $user_id <= 0) {
            return false;
        }

        $updated = $wpdb->update(
            MessageTable::table_name(),
            ['is_read' => 1],
            [
                'id' => $message_id,
                'recipient_id' => $user_id,
            ],
            ['%d'],
            ['%d', '%d']
        );

        return $updated !== false;
    }

    public function mark_thread_read($contact_id, $user_id = 0)
    {
        global $wpdb;

        $contact_id = (int) $contact_id;
        $user_id = $user_id > 0 ? (int) $user_id : get_current_user_id();
        if ($contact_id <= 0 || $user_id <= 0 || $contact_id === $user_id) {
            return false;
        }

        $updated = $wpdb->query(
            $wpdb->prepare(
                "UPDATE " . MessageTable::table_name() . "
                SET is_read = 1
                WHERE sender_id = %d AND recipient_id = %d AND is_read = 0",
                $contact_id,
                $user_id
            )
        );

        return $updated !== false;
    }

    public function contacts($user_id = 0)
    {
        global $wpdb;

        $user_id = $user_id > 0 ? (int) $user_id : get_current_user_id();
        if ($user_id <= 0) {
            return [];
        }

        $contacts = [];

        $order_query = new \WP_Query([
            'post_type' => 'store_order',
            'post_status' => 'publish',
            'posts_per_page' => 300,
            'fields' => 'ids',
        ]);

        foreach ((array) $order_query->posts as $order_id) {
            $order_id = (int) $order_id;
            if ($order_id <= 0) {
                continue;
            }

            $buyer_id = \VelocityMarketplace\Modules\Order\OrderData::buyer_id($order_id);
            $items = OrderData::get_items($order_id);
            $seller_ids = [];
            foreach ($items as $line) {
                $seller_id = isset($line['seller_id']) ? (int) $line['seller_id'] : 0;
                if ($seller_id > 0) {
                    $seller_ids[] = $seller_id;
                }
            }
            $seller_ids = array_values(array_unique($seller_ids));

            if ($buyer_id === $user_id) {
                foreach ($seller_ids as $seller_id) {
                    $contacts[$seller_id] = $seller_id;
                }
            }

            if (in_array($user_id, $seller_ids, true) && $buyer_id > 0) {
                $contacts[$buyer_id] = $buyer_id;
            }
        }

        $table = MessageTable::table_name();
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT sender_id, recipient_id, order_id, message, is_read, created_at
                FROM {$table}
                WHERE sender_id = %d OR recipient_id = %d",
                $user_id,
                $user_id
            ),
            ARRAY_A
        );

        $contact_stats = [];
        foreach ((array) $rows as $row) {
            $sender_id = isset($row['sender_id']) ? (int) $row['sender_id'] : 0;
            $recipient_id = isset($row['recipient_id']) ? (int) $row['recipient_id'] : 0;
            if ($sender_id > 0 && $sender_id !== $user_id) {
                $contacts[$sender_id] = $sender_id;
            }
            if ($recipient_id > 0 && $recipient_id !== $user_id) {
                $contacts[$recipient_id] = $recipient_id;
            }

            $partner_id = $sender_id === $user_id ? $recipient_id : $sender_id;
            if ($partner_id <= 0 || $partner_id === $user_id) {
                continue;
            }

            if (!isset($contact_stats[$partner_id])) {
                $contact_stats[$partner_id] = [
                    'last_created_at' => '',
                    'last_message' => '',
                    'last_order_id' => 0,
                    'unread_count' => 0,
                ];
            }

            $created_at = (string) ($row['created_at'] ?? '');
            if ($contact_stats[$partner_id]['last_created_at'] === '' || strtotime($created_at) > strtotime((string) $contact_stats[$partner_id]['last_created_at'])) {
                $contact_stats[$partner_id]['last_created_at'] = $created_at;
                $contact_stats[$partner_id]['last_message'] = wp_trim_words(wp_strip_all_tags((string) ($row['message'] ?? '')), 12);
                $contact_stats[$partner_id]['last_order_id'] = (int) ($row['order_id'] ?? 0);
            }

            if ($recipient_id === $user_id && empty($row['is_read'])) {
                $contact_stats[$partner_id]['unread_count']++;
            }
        }

        $result = [];
        foreach (array_values(array_unique($contacts)) as $contact_id) {
            $user = get_userdata((int) $contact_id);
            if (!$user) {
                continue;
            }

            $result[] = [
                'id' => (int) $contact_id,
                'name' => $user->display_name !== '' ? $user->display_name : $user->user_login,
                'role' => $this->role_label($contact_id),
                'last_message' => (string) ($contact_stats[$contact_id]['last_message'] ?? ''),
                'last_created_at' => (string) ($contact_stats[$contact_id]['last_created_at'] ?? ''),
                'last_order_id' => (int) ($contact_stats[$contact_id]['last_order_id'] ?? 0),
                'last_order_invoice' => !empty($contact_stats[$contact_id]['last_order_id']) ? (string) get_post_meta((int) $contact_stats[$contact_id]['last_order_id'], 'vmp_invoice', true) : '',
                'unread_count' => (int) ($contact_stats[$contact_id]['unread_count'] ?? 0),
            ];
        }

        usort($result, static function ($left, $right) {
            $left_time = strtotime((string) ($left['last_created_at'] ?? ''));
            $right_time = strtotime((string) ($right['last_created_at'] ?? ''));
            if ($left_time !== $right_time) {
                return $right_time <=> $left_time;
            }
            return strcmp((string) $left['name'], (string) $right['name']);
        });

        return $result;
    }

    public function can_contact($user_id, $contact_id)
    {
        $user_id = (int) $user_id;
        $contact_id = (int) $contact_id;
        if ($user_id <= 0 || $contact_id <= 0 || $user_id === $contact_id) {
            return false;
        }

        $contact_user = get_userdata($contact_id);
        if ($contact_user) {
            if (Account::is_member($contact_id)) {
                return true;
            }
        }

        foreach ($this->contacts($user_id) as $row) {
            if ((int) ($row['id'] ?? 0) === $contact_id) {
                return true;
            }
        }

        return false;
    }

    private function role_label($user_id)
    {
        $user = get_userdata((int) $user_id);
        if (!$user) {
            return '';
        }

        return Account::user_role_label((int) $user_id);
    }
}

