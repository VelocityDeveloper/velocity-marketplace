<?php

namespace VelocityMarketplace\Modules\Product;

use VelocityMarketplace\Support\Contract;

class ProductQuery
{
    public function normalize_filters($source = [])
    {
        if (!is_array($source)) {
            $source = [];
        }

        $filters = \WpStore\Domain\Product\ProductQuery::normalize_filters($source);

        return array_merge($filters, [
            'store_type' => sanitize_key((string) ($source['store_type'] ?? '')),
            'store_province_id' => sanitize_text_field((string) ($source['store_province_id'] ?? '')),
            'store_city_id' => sanitize_text_field((string) ($source['store_city_id'] ?? '')),
            'store_subdistrict_id' => sanitize_text_field((string) ($source['store_subdistrict_id'] ?? '')),
        ]);
    }

    public function build_query_args($filters, $overrides = [])
    {
        $filters = $this->normalize_filters(is_array($filters) ? $filters : []);
        $overrides = is_array($overrides) ? $overrides : [];

        $base_overrides = array_merge([
            'post_type' => Contract::PRODUCT_POST_TYPE,
            'post_status' => 'publish',
        ], $overrides);

        $args = \WpStore\Domain\Product\ProductQuery::build_query_args($filters, $base_overrides);

        $author_ids = $this->resolve_author_ids($filters);
        if ($author_ids !== null) {
            if ($filters['author'] > 0) {
                if (!in_array($filters['author'], $author_ids, true)) {
                    $args['author__in'] = [0];
                }
            } else {
                $args['author__in'] = !empty($author_ids) ? $author_ids : [0];
            }
        }

        if ($filters['sort'] === 'sold_desc') {
            $args['meta_key'] = 'vmp_sold_count';
            $args['orderby'] = 'meta_value_num';
            $args['order'] = 'DESC';
        } elseif ($filters['sort'] === 'rating_desc') {
            $args['meta_key'] = 'vmp_rating_average';
            $args['orderby'] = 'meta_value_num';
            $args['order'] = 'DESC';
        } elseif ($filters['sort'] === 'popular') {
            $args['meta_key'] = 'hit';
            $args['orderby'] = 'meta_value_num';
            $args['order'] = 'DESC';
        }

        return $args;
    }

    public function apply_to_query(\WP_Query $query, $filters = [])
    {
        $args = $this->build_query_args($filters);

        foreach ($args as $key => $value) {
            $query->set($key, $value);
        }
    }

    public function label_options()
    {
        return \WpStore\Domain\Product\ProductQuery::label_options();
    }

    public function sort_options()
    {
        $base = \WpStore\Domain\Product\ProductQuery::sort_options();

        return array_merge($base, [
            'sold_desc' => __('Terlaris', 'velocity-marketplace'),
            'rating_desc' => __('Rating Tertinggi', 'velocity-marketplace'),
            'popular' => __('Paling Banyak Dilihat', 'velocity-marketplace'),
        ]);
    }

    public function describe_active_filters($filters = [])
    {
        $filters = $this->normalize_filters($filters);
        $chips = [];

        if ($filters['search'] !== '') {
            $chips[] = [
                'key' => 'search',
                'label' => __('Pencarian', 'velocity-marketplace'),
                'value' => $filters['search'],
            ];
        }

        if ($filters['cat'] > 0) {
            $term = get_term($filters['cat'], Contract::PRODUCT_TAXONOMY);
            if ($term && !is_wp_error($term)) {
                $chips[] = [
                    'key' => 'cat',
                    'label' => __('Kategori', 'velocity-marketplace'),
                    'value' => (string) $term->name,
                ];
            }
        }

        $label_options = $this->label_options();
        if ($filters['label'] !== '' && isset($label_options[$filters['label']])) {
            $chips[] = [
                'key' => 'label',
                'label' => __('Label', 'velocity-marketplace'),
                'value' => (string) $label_options[$filters['label']],
            ];
        }

        $store_type_options = [
            'star_seller' => __('Star Seller', 'velocity-marketplace'),
            'regular' => __('Toko Biasa', 'velocity-marketplace'),
        ];
        if ($filters['store_type'] !== '' && isset($store_type_options[$filters['store_type']])) {
            $chips[] = [
                'key' => 'store_type',
                'label' => __('Jenis Toko', 'velocity-marketplace'),
                'value' => (string) $store_type_options[$filters['store_type']],
            ];
        }

        if ($filters['store_province_id'] !== '' || $filters['store_city_id'] !== '' || $filters['store_subdistrict_id'] !== '') {
            $location_text = __('Lokasi Toko Dipilih', 'velocity-marketplace');
            if ($filters['store_subdistrict_id'] !== '') {
                $location_text = __('Kecamatan Toko Dipilih', 'velocity-marketplace');
            } elseif ($filters['store_city_id'] !== '') {
                $location_text = __('Kota Toko Dipilih', 'velocity-marketplace');
            } elseif ($filters['store_province_id'] !== '') {
                $location_text = __('Provinsi Toko Dipilih', 'velocity-marketplace');
            }

            $chips[] = [
                'key' => 'store_location',
                'label' => __('Lokasi', 'velocity-marketplace'),
                'value' => $location_text,
            ];
        }

        if ($filters['min_price'] !== '') {
            $chips[] = [
                'key' => 'min_price',
                'label' => __('Min', 'velocity-marketplace'),
                'value' => 'Rp ' . number_format((float) $filters['min_price'], 0, ',', '.'),
            ];
        }

        if ($filters['max_price'] !== '') {
            $chips[] = [
                'key' => 'max_price',
                'label' => __('Maks', 'velocity-marketplace'),
                'value' => 'Rp ' . number_format((float) $filters['max_price'], 0, ',', '.'),
            ];
        }

        $sort_options = $this->sort_options();
        if ($filters['sort'] !== '' && $filters['sort'] !== 'latest' && isset($sort_options[$filters['sort']])) {
            $chips[] = [
                'key' => 'sort',
                'label' => __('Urutkan', 'velocity-marketplace'),
                'value' => (string) $sort_options[$filters['sort']],
            ];
        }

        return $chips;
    }

    private function author_ids_for_store_type($store_type)
    {
        $store_type = sanitize_key((string) $store_type);
        if (!in_array($store_type, ['star_seller', 'regular'], true)) {
            return [];
        }

        $users = get_users([
            'fields' => ['ID'],
            'role__in' => ['vmp_member', 'administrator'],
            'number' => -1,
        ]);

        $author_ids = [];
        foreach ((array) $users as $user) {
            $user_id = isset($user->ID) ? (int) $user->ID : 0;
            if ($user_id <= 0) {
                continue;
            }

            $is_star = !empty(get_user_meta($user_id, 'vmp_is_star_seller', true));
            if ($store_type === 'star_seller' && $is_star) {
                $author_ids[] = $user_id;
            }
            if ($store_type === 'regular' && !$is_star) {
                $author_ids[] = $user_id;
            }
        }

        return array_values(array_unique($author_ids));
    }

    private function author_ids_for_store_location(array $filters)
    {
        $meta_query = ['relation' => 'AND'];

        if (!empty($filters['store_province_id'])) {
            $meta_query[] = [
                'key' => 'vmp_store_province_id',
                'value' => (string) $filters['store_province_id'],
                'compare' => '=',
            ];
        }

        if (!empty($filters['store_city_id'])) {
            $meta_query[] = [
                'key' => 'vmp_store_city_id',
                'value' => (string) $filters['store_city_id'],
                'compare' => '=',
            ];
        }

        if (!empty($filters['store_subdistrict_id'])) {
            $meta_query[] = [
                'key' => 'vmp_store_subdistrict_id',
                'value' => (string) $filters['store_subdistrict_id'],
                'compare' => '=',
            ];
        }

        if (count($meta_query) === 1) {
            return [];
        }

        $users = get_users([
            'fields' => ['ID'],
            'role__in' => ['vmp_member', 'administrator'],
            'number' => -1,
            'meta_query' => $meta_query,
        ]);

        $author_ids = [];
        foreach ((array) $users as $user) {
            $user_id = isset($user->ID) ? (int) $user->ID : 0;
            if ($user_id > 0) {
                $author_ids[] = $user_id;
            }
        }

        return array_values(array_unique($author_ids));
    }

    private function resolve_author_ids(array $filters)
    {
        $groups = [];

        if ($filters['store_type'] !== '') {
            $groups[] = $this->author_ids_for_store_type($filters['store_type']);
        }

        if (
            $filters['store_province_id'] !== ''
            || $filters['store_city_id'] !== ''
            || $filters['store_subdistrict_id'] !== ''
        ) {
            $groups[] = $this->author_ids_for_store_location($filters);
        }

        if (empty($groups)) {
            return null;
        }

        $resolved = null;
        foreach ($groups as $group) {
            $group = array_values(array_unique(array_map('intval', (array) $group)));
            if ($resolved === null) {
                $resolved = $group;
                continue;
            }

            $resolved = array_values(array_intersect($resolved, $group));
        }

        return $resolved === null ? null : array_values(array_unique($resolved));
    }

    private function normalize_numeric_filter($value)
    {
        if ($value === null || $value === '') {
            return '';
        }

        return (float) $value;
    }
}
