<?php

namespace VelocityMarketplace\Support;

class NotificationRepository
{
    const USER_META_KEY = 'vmp_notifications';
    const MAX_ITEMS = 100;

    public function all($user_id = 0)
    {
        $user_id = $user_id > 0 ? (int) $user_id : get_current_user_id();
        if ($user_id <= 0) {
            return [];
        }

        $rows = get_user_meta($user_id, self::USER_META_KEY, true);
        if (!is_array($rows)) {
            $rows = [];
        }

        $normalized = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $id = isset($row['id']) ? sanitize_text_field((string) $row['id']) : '';
            if ($id === '') {
                continue;
            }

            $normalized[] = [
                'id' => $id,
                'type' => isset($row['type']) ? sanitize_key((string) $row['type']) : 'info',
                'title' => isset($row['title']) ? sanitize_text_field((string) $row['title']) : '',
                'message' => isset($row['message']) ? sanitize_text_field((string) $row['message']) : '',
                'url' => isset($row['url']) ? esc_url_raw((string) $row['url']) : '',
                'created_at' => isset($row['created_at']) ? sanitize_text_field((string) $row['created_at']) : current_time('mysql'),
                'is_read' => !empty($row['is_read']) ? 1 : 0,
            ];
        }

        usort($normalized, function ($a, $b) {
            return strcmp((string) $b['created_at'], (string) $a['created_at']);
        });

        return $normalized;
    }

    public function add($user_id, $type, $title, $message, $url = '')
    {
        $user_id = (int) $user_id;
        if ($user_id <= 0) {
            return false;
        }

        $rows = $this->all($user_id);
        array_unshift($rows, [
            'id' => function_exists('wp_generate_uuid4') ? wp_generate_uuid4() : uniqid('vmp_', true),
            'type' => sanitize_key((string) $type),
            'title' => sanitize_text_field((string) $title),
            'message' => sanitize_text_field((string) $message),
            'url' => esc_url_raw((string) $url),
            'created_at' => current_time('mysql'),
            'is_read' => 0,
        ]);

        $rows = array_slice($rows, 0, self::MAX_ITEMS);
        return (bool) update_user_meta($user_id, self::USER_META_KEY, $rows);
    }

    public function unread_count($user_id = 0)
    {
        $count = 0;
        foreach ($this->all($user_id) as $row) {
            if (empty($row['is_read'])) {
                $count++;
            }
        }
        return $count;
    }

    public function mark_read($notification_id, $user_id = 0)
    {
        $notification_id = sanitize_text_field((string) $notification_id);
        if ($notification_id === '') {
            return false;
        }

        $user_id = $user_id > 0 ? (int) $user_id : get_current_user_id();
        if ($user_id <= 0) {
            return false;
        }

        $rows = $this->all($user_id);
        $changed = false;
        foreach ($rows as &$row) {
            if ($row['id'] === $notification_id && empty($row['is_read'])) {
                $row['is_read'] = 1;
                $changed = true;
                break;
            }
        }
        unset($row);

        if (!$changed) {
            return false;
        }

        return (bool) update_user_meta($user_id, self::USER_META_KEY, $rows);
    }

    public function mark_all_read($user_id = 0)
    {
        $user_id = $user_id > 0 ? (int) $user_id : get_current_user_id();
        if ($user_id <= 0) {
            return false;
        }

        $rows = $this->all($user_id);
        $changed = false;
        foreach ($rows as &$row) {
            if (empty($row['is_read'])) {
                $row['is_read'] = 1;
                $changed = true;
            }
        }
        unset($row);

        if (!$changed) {
            return false;
        }

        return (bool) update_user_meta($user_id, self::USER_META_KEY, $rows);
    }

    public function delete($notification_id, $user_id = 0)
    {
        $notification_id = sanitize_text_field((string) $notification_id);
        if ($notification_id === '') {
            return false;
        }

        $user_id = $user_id > 0 ? (int) $user_id : get_current_user_id();
        if ($user_id <= 0) {
            return false;
        }

        $rows = $this->all($user_id);
        $filtered = array_values(array_filter($rows, function ($row) use ($notification_id) {
            return isset($row['id']) && (string) $row['id'] !== $notification_id;
        }));

        if (count($filtered) === count($rows)) {
            return false;
        }

        return (bool) update_user_meta($user_id, self::USER_META_KEY, $filtered);
    }
}

