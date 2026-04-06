<?php

namespace VelocityMarketplace\Modules\Review;

use VelocityMarketplace\Modules\Order\OrderData;
use VelocityMarketplace\Support\Contract;

class ReviewRepository
{
    public function table()
    {
        return ReviewTable::table_name();
    }

    public function find($review_id)
    {
        global $wpdb;

        $review_id = (int) $review_id;
        if ($review_id <= 0) {
            return null;
        }

        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$this->table()} WHERE id = %d LIMIT 1", $review_id),
            ARRAY_A
        );

        return is_array($row) ? $this->normalize_row($row) : null;
    }

    public function find_by_keys($product_id, $order_id, $user_id)
    {
        global $wpdb;

        $product_id = (int) $product_id;
        $order_id = (int) $order_id;
        $user_id = (int) $user_id;
        if ($product_id <= 0 || $order_id <= 0 || $user_id <= 0) {
            return null;
        }

        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$this->table()} WHERE product_id = %d AND order_id = %d AND user_id = %d LIMIT 1",
                $product_id,
                $order_id,
                $user_id
            ),
            ARRAY_A
        );

        return is_array($row) ? $this->normalize_row($row) : null;
    }

    public function can_review($order_id, $product_id, $user_id)
    {
        $order_id = (int) $order_id;
        $product_id = (int) $product_id;
        $user_id = (int) $user_id;

        if ($order_id <= 0 || $product_id <= 0 || $user_id <= 0) {
            return false;
        }

        if (!Contract::is_order($order_id)) {
            return false;
        }

        $owner_id = OrderData::buyer_id($order_id);
        if ($owner_id !== $user_id) {
            return false;
        }

        $status = (string) get_post_meta($order_id, 'vmp_status', true);
        $product_seller_id = 0;

        foreach (OrderData::get_items($order_id) as $item) {
            if ((int) ($item['product_id'] ?? 0) === $product_id) {
                $product_seller_id = (int) ($item['seller_id'] ?? 0);
                break;
            }
        }

        if ($product_seller_id <= 0 && $status === 'completed') {
            foreach (OrderData::get_items($order_id) as $item) {
                if ((int) ($item['product_id'] ?? 0) === $product_id) {
                    return true;
                }
            }

            return false;
        }

        foreach (OrderData::shipping_groups($order_id) as $group) {
            $group_status = OrderData::shipping_group_status($group, $status);
            if ($group_status !== 'completed') {
                continue;
            }

            if ((int) ($group['seller_id'] ?? 0) === $product_seller_id) {
                return true;
            }

            foreach ((array) ($group['items'] ?? []) as $group_item) {
                if ((int) ($group_item['product_id'] ?? 0) === $product_id) {
                    return true;
                }
            }
        }

        return false;
    }

    public function reviews_for_order_user($order_id, $user_id)
    {
        global $wpdb;

        $order_id = (int) $order_id;
        $user_id = (int) $user_id;
        if ($order_id <= 0 || $user_id <= 0) {
            return [];
        }

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$this->table()} WHERE order_id = %d AND user_id = %d ORDER BY created_at DESC",
                $order_id,
                $user_id
            ),
            ARRAY_A
        );

        $mapped = [];
        foreach ((array) $rows as $row) {
            $normalized = $this->normalize_row($row);
            $mapped[(int) $normalized['product_id']] = $normalized;
        }

        return $mapped;
    }

    public function save($data)
    {
        global $wpdb;

        $product_id = isset($data['product_id']) ? (int) $data['product_id'] : 0;
        $order_id = isset($data['order_id']) ? (int) $data['order_id'] : 0;
        $user_id = isset($data['user_id']) ? (int) $data['user_id'] : 0;
        $rating = isset($data['rating']) ? max(1, min(5, (int) $data['rating'])) : 0;
        $title = sanitize_text_field((string) ($data['title'] ?? ''));
        $content = sanitize_textarea_field((string) ($data['content'] ?? ''));
        $image_ids = isset($data['image_ids']) && is_array($data['image_ids'])
            ? array_values(array_filter(array_map('intval', $data['image_ids'])))
            : null;

        if ($product_id <= 0 || $order_id <= 0 || $user_id <= 0 || $rating <= 0 || $content === '') {
            return 0;
        }

        if (!$this->can_review($order_id, $product_id, $user_id)) {
            return 0;
        }

        $seller_id = $this->extract_seller_id($order_id, $product_id);
        $existing = $this->find_by_keys($product_id, $order_id, $user_id);
        $now = current_time('mysql');

        if ($existing) {
            if ($image_ids === null) {
                $image_ids = isset($existing['image_ids']) && is_array($existing['image_ids']) ? $existing['image_ids'] : [];
            }
            $updated = $wpdb->update(
                $this->table(),
                [
                    'seller_id' => $seller_id,
                    'rating' => $rating,
                    'title' => $title,
                    'content' => $content,
                    'image_ids' => $this->image_ids_to_string($image_ids),
                    'updated_at' => $now,
                ],
                ['id' => (int) $existing['id']],
                ['%d', '%d', '%s', '%s', '%s', '%s'],
                ['%d']
            );

            if ($updated === false) {
                return 0;
            }

            $review_id = (int) $existing['id'];
        } else {
            $inserted = $wpdb->insert(
                $this->table(),
                [
                    'product_id' => $product_id,
                    'order_id' => $order_id,
                    'user_id' => $user_id,
                    'seller_id' => $seller_id,
                    'rating' => $rating,
                    'title' => $title,
                    'content' => $content,
                    'image_ids' => $this->image_ids_to_string($image_ids),
                    'is_approved' => 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                ['%d', '%d', '%d', '%d', '%d', '%s', '%s', '%s', '%d', '%s', '%s']
            );

            if (!$inserted) {
                return 0;
            }

            $review_id = (int) $wpdb->insert_id;
        }

        $this->recalculate_product_meta($product_id);
        if ($seller_id > 0) {
            (new StarSellerService())->recalculate($seller_id);
        }

        return $review_id;
    }

    public function product_reviews($product_id, $limit = 20)
    {
        global $wpdb;

        $product_id = (int) $product_id;
        $limit = max(1, min(100, (int) $limit));
        if ($product_id <= 0) {
            return [];
        }

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$this->table()} WHERE product_id = %d AND is_approved = 1 ORDER BY created_at DESC LIMIT %d",
                $product_id,
                $limit
            ),
            ARRAY_A
        );

        $reviews = [];
        foreach ((array) $rows as $row) {
            $normalized = $this->normalize_row($row);
            $user = get_userdata((int) $normalized['user_id']);
            $normalized['user_name'] = $user && $user->display_name !== '' ? $user->display_name : ($user ? $user->user_login : 'Member');
            $normalized['image_urls'] = $this->image_urls($normalized['image_ids']);
            $reviews[] = $normalized;
        }

        return $reviews;
    }

    public function product_summary($product_id)
    {
        $product_id = (int) $product_id;
        if ($product_id <= 0) {
            return ['review_count' => 0, 'rating_average' => 0.0];
        }

        if (!metadata_exists('post', $product_id, 'vmp_review_count') || !metadata_exists('post', $product_id, 'vmp_rating_average')) {
            return $this->recalculate_product_meta($product_id);
        }

        $review_count = (int) get_post_meta($product_id, 'vmp_review_count', true);
        $rating_average = (float) get_post_meta($product_id, 'vmp_rating_average', true);

        return [
            'review_count' => $review_count,
            'rating_average' => $rating_average,
        ];
    }

    public function recalculate_product_meta($product_id)
    {
        global $wpdb;

        $product_id = (int) $product_id;
        if ($product_id <= 0) {
            return ['review_count' => 0, 'rating_average' => 0.0];
        }

        $stats = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT COUNT(*) AS review_count, AVG(rating) AS rating_average
                FROM {$this->table()}
                WHERE product_id = %d AND is_approved = 1",
                $product_id
            ),
            ARRAY_A
        );

        $review_count = isset($stats['review_count']) ? (int) $stats['review_count'] : 0;
        $rating_average = isset($stats['rating_average']) ? round((float) $stats['rating_average'], 2) : 0.0;

        update_post_meta($product_id, 'vmp_review_count', $review_count);
        update_post_meta($product_id, 'vmp_rating_average', $rating_average);

        return [
            'review_count' => $review_count,
            'rating_average' => $rating_average,
        ];
    }

    public function seller_rating_stats($seller_id)
    {
        global $wpdb;

        $seller_id = (int) $seller_id;
        if ($seller_id <= 0) {
            return ['rating_count' => 0, 'rating_average' => 0.0];
        }

        $stats = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT COUNT(*) AS rating_count, AVG(rating) AS rating_average
                FROM {$this->table()}
                WHERE seller_id = %d AND is_approved = 1",
                $seller_id
            ),
            ARRAY_A
        );

        return [
            'rating_count' => isset($stats['rating_count']) ? (int) $stats['rating_count'] : 0,
            'rating_average' => isset($stats['rating_average']) ? round((float) $stats['rating_average'], 2) : 0.0,
        ];
    }

    public function seller_reviews($seller_id, $limit = 6)
    {
        global $wpdb;

        $seller_id = (int) $seller_id;
        $limit = max(1, min(50, (int) $limit));
        if ($seller_id <= 0) {
            return [];
        }

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$this->table()} WHERE seller_id = %d AND is_approved = 1 ORDER BY created_at DESC LIMIT %d",
                $seller_id,
                $limit
            ),
            ARRAY_A
        );

        $reviews = [];
        foreach ((array) $rows as $row) {
            $normalized = $this->normalize_row($row);
            $user = get_userdata((int) $normalized['user_id']);
            $normalized['user_name'] = $user && $user->display_name !== '' ? $user->display_name : ($user ? $user->user_login : 'Member');
            $normalized['image_urls'] = $this->image_urls($normalized['image_ids']);
            $normalized['product_title'] = get_the_title((int) $normalized['product_id']);
            $normalized['product_link'] = get_permalink((int) $normalized['product_id']);
            $reviews[] = $normalized;
        }

        return $reviews;
    }

    public function count_all()
    {
        global $wpdb;

        return (int) $wpdb->get_var("SELECT COUNT(*) FROM {$this->table()}");
    }

    public function admin_reviews($offset = 0, $limit = 20)
    {
        global $wpdb;

        $offset = max(0, (int) $offset);
        $limit = max(1, min(100, (int) $limit));

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$this->table()} ORDER BY created_at DESC LIMIT %d OFFSET %d",
                $limit,
                $offset
            ),
            ARRAY_A
        );

        return array_map([$this, 'normalize_row'], (array) $rows);
    }

    public function set_approved($review_id, $approved)
    {
        global $wpdb;

        $review = $this->find($review_id);
        if (!$review) {
            return false;
        }

        $updated = $wpdb->update(
            $this->table(),
            [
                'is_approved' => $approved ? 1 : 0,
                'updated_at' => current_time('mysql'),
            ],
            ['id' => (int) $review['id']],
            ['%d', '%s'],
            ['%d']
        );

        if ($updated === false) {
            return false;
        }

        $this->recalculate_product_meta((int) $review['product_id']);
        if ((int) $review['seller_id'] > 0) {
            (new StarSellerService())->recalculate((int) $review['seller_id']);
        }

        return true;
    }

    public function delete($review_id)
    {
        global $wpdb;

        $review = $this->find($review_id);
        if (!$review) {
            return false;
        }

        $deleted = $wpdb->delete($this->table(), ['id' => (int) $review['id']], ['%d']);
        if (!$deleted) {
            return false;
        }

        $this->recalculate_product_meta((int) $review['product_id']);
        if ((int) $review['seller_id'] > 0) {
            (new StarSellerService())->recalculate((int) $review['seller_id']);
        }

        return true;
    }

    private function extract_seller_id($order_id, $product_id)
    {
        foreach (OrderData::get_items((int) $order_id) as $item) {
            if ((int) ($item['product_id'] ?? 0) === (int) $product_id) {
                return (int) ($item['seller_id'] ?? 0);
            }
        }

        return (int) get_post_field('post_author', (int) $product_id);
    }

    private function normalize_row($row)
    {
        $row = is_array($row) ? $row : [];

        return [
            'id' => isset($row['id']) ? (int) $row['id'] : 0,
            'product_id' => isset($row['product_id']) ? (int) $row['product_id'] : 0,
            'order_id' => isset($row['order_id']) ? (int) $row['order_id'] : 0,
            'user_id' => isset($row['user_id']) ? (int) $row['user_id'] : 0,
            'seller_id' => isset($row['seller_id']) ? (int) $row['seller_id'] : 0,
            'rating' => isset($row['rating']) ? (int) $row['rating'] : 0,
            'title' => isset($row['title']) ? (string) $row['title'] : '',
            'content' => isset($row['content']) ? (string) $row['content'] : '',
            'image_ids' => $this->parse_image_ids(isset($row['image_ids']) ? $row['image_ids'] : ''),
            'is_approved' => !empty($row['is_approved']),
            'created_at' => isset($row['created_at']) ? (string) $row['created_at'] : '',
            'updated_at' => isset($row['updated_at']) ? (string) $row['updated_at'] : '',
        ];
    }

    private function parse_image_ids($value)
    {
        if (is_array($value)) {
            return array_values(array_filter(array_map('intval', $value)));
        }

        $value = trim((string) $value);
        if ($value === '') {
            return [];
        }

        return array_values(array_filter(array_map('intval', array_map('trim', explode(',', $value)))));
    }

    private function image_ids_to_string($image_ids)
    {
        $image_ids = array_values(array_filter(array_map('intval', (array) $image_ids)));
        return implode(',', $image_ids);
    }

    private function image_urls($image_ids)
    {
        $urls = [];
        foreach ((array) $image_ids as $attachment_id) {
            $url = wp_get_attachment_image_url((int) $attachment_id, 'medium');
            if ($url) {
                $urls[] = $url;
            }
        }

        return $urls;
    }
}
