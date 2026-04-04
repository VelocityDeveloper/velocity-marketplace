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

        if (!$query->is_post_type_archive(Contract::PRODUCT_POST_TYPE) && !$query->is_post_type_archive(Contract::LEGACY_PRODUCT_POST_TYPE)) {
            return;
        }

        $filters = (new ProductQuery())->normalize_filters($_GET);
        (new ProductQuery())->apply_to_query($query, $filters);
    }
}
