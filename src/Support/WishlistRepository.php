<?php

namespace VelocityMarketplace\Support;

class WishlistRepository
{
    const USER_META_KEY = 'vmp_wishlist';

    public function get_ids($user_id = 0)
    {
        $user_id = $user_id > 0 ? (int) $user_id : get_current_user_id();
        if ($user_id <= 0) {
            return [];
        }

        $raw = get_user_meta($user_id, self::USER_META_KEY, true);
        if (!is_array($raw)) {
            $raw = [];
        }

        $ids = [];
        foreach ($raw as $id) {
            $id = (int) $id;
            if ($id > 0 && get_post_type($id) === 'vmp_product') {
                $ids[] = $id;
            }
        }

        return array_values(array_unique($ids));
    }

    public function has($product_id, $user_id = 0)
    {
        $product_id = (int) $product_id;
        if ($product_id <= 0) {
            return false;
        }

        return in_array($product_id, $this->get_ids($user_id), true);
    }

    public function add($product_id, $user_id = 0)
    {
        $product_id = (int) $product_id;
        $user_id = $user_id > 0 ? (int) $user_id : get_current_user_id();
        if ($user_id <= 0 || $product_id <= 0 || get_post_type($product_id) !== 'vmp_product') {
            return false;
        }

        $ids = $this->get_ids($user_id);
        if (!in_array($product_id, $ids, true)) {
            $ids[] = $product_id;
        }

        return (bool) update_user_meta($user_id, self::USER_META_KEY, array_values(array_unique($ids)));
    }

    public function remove($product_id, $user_id = 0)
    {
        $product_id = (int) $product_id;
        $user_id = $user_id > 0 ? (int) $user_id : get_current_user_id();
        if ($user_id <= 0 || $product_id <= 0) {
            return false;
        }

        $ids = array_values(array_filter($this->get_ids($user_id), function ($id) use ($product_id) {
            return (int) $id !== $product_id;
        }));

        return (bool) update_user_meta($user_id, self::USER_META_KEY, $ids);
    }

    public function toggle($product_id, $user_id = 0)
    {
        if ($this->has($product_id, $user_id)) {
            $this->remove($product_id, $user_id);
            return false;
        }

        $this->add($product_id, $user_id);
        return true;
    }
}

