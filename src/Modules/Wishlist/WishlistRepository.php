<?php

namespace VelocityMarketplace\Modules\Wishlist;

use VelocityMarketplace\Support\Contract;

class WishlistRepository
{
    public function get_ids($user_id = 0)
    {
        $core = $this->core_service();
        $ids = [];
        foreach ((array) $core->get_raw_items() as $id) {
            if (is_array($id)) {
                $id = isset($id['id']) ? (int) $id['id'] : 0;
            }
            $id = (int) $id;
            if ($id > 0 && Contract::is_product($id)) {
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
        if ($user_id <= 0 || $product_id <= 0 || !Contract::is_product($product_id)) {
            return false;
        }

        $this->core_service()->add_item($product_id, []);
        return true;
    }

    public function remove($product_id, $user_id = 0)
    {
        $product_id = (int) $product_id;
        $user_id = $user_id > 0 ? (int) $user_id : get_current_user_id();
        if ($user_id <= 0 || $product_id <= 0) {
            return false;
        }

        $this->core_service()->remove_item($product_id, []);
        return true;
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

    private function core_service()
    {
        return new \WpStore\Domain\Wishlist\WishlistService();
    }
}


