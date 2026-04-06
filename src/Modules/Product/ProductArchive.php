<?php

namespace VelocityMarketplace\Modules\Product;

use VelocityMarketplace\Support\Contract;

class ProductArchive
{
    public function register()
    {
        add_action('pre_get_posts', [$this, 'filter_archive_query']);
    }

    public function filter_archive_query($query)
    {
        if (!($query instanceof \WP_Query) || is_admin() || !$query->is_main_query()) {
            return;
        }

        if (!$query->is_post_type_archive(Contract::PRODUCT_POST_TYPE)) {
            return;
        }

        $product_query = new ProductQuery();
        $filters = $product_query->normalize_filters($_GET);

        if (!$this->needs_marketplace_extension($filters)) {
            return;
        }

        $product_query->apply_to_query($query, $filters);
    }

    private function needs_marketplace_extension(array $filters)
    {
        $sort = (string) ($filters['sort'] ?? 'latest');
        if (in_array($sort, ['sold_desc', 'rating_desc', 'popular'], true)) {
            return true;
        }

        if (!empty($filters['store_type'])) {
            return true;
        }

        if (!empty($filters['store_province_id']) || !empty($filters['store_city_id']) || !empty($filters['store_subdistrict_id'])) {
            return true;
        }

        return false;
    }
}
