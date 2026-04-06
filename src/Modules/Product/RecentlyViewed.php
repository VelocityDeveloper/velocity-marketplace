<?php

namespace VelocityMarketplace\Modules\Product;

class RecentlyViewed
{
    public static function track($product_id, $limit = 8)
    {
        \WpStore\Domain\Product\RecentlyViewed::track($product_id, $limit);
    }

    public static function ids($exclude_id = 0, $limit = 8)
    {
        return \WpStore\Domain\Product\RecentlyViewed::ids($exclude_id, $limit);
    }

    public static function items($exclude_id = 0, $limit = 8)
    {
        $items = [];
        foreach (\WpStore\Domain\Product\RecentlyViewed::ids($exclude_id, $limit) as $product_id) {
            $item = ProductData::map_post($product_id);
            if ($item) {
                $items[] = $item;
            }
        }

        return $items;
    }
}

