<?php

namespace VelocityMarketplace\Modules\Product;

use VelocityMarketplace\Support\Contract;

class RelatedProducts
{
    public static function items($product_id, $limit = 4)
    {
        $product_id = (int) $product_id;
        $limit = max(1, min(12, (int) $limit));

        if ($product_id <= 0 || !Contract::is_product($product_id)) {
            return [];
        }

        $items = [];
        foreach (\WpStore\Domain\Product\RelatedProducts::ids($product_id, $limit) as $related_id) {
            $item = ProductData::map_post($related_id);
            if ($item) {
                $items[] = $item;
            }
        }

        usort($items, static function ($left, $right) {
            $left_sold = (int) ($left['sold_count'] ?? 0);
            $right_sold = (int) ($right['sold_count'] ?? 0);
            if ($left_sold !== $right_sold) {
                return $right_sold <=> $left_sold;
            }

            return ((int) ($right['id'] ?? 0)) <=> ((int) ($left['id'] ?? 0));
        });

        return $items;
    }
}

