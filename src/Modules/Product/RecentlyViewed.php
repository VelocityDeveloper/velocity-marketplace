<?php

namespace VelocityMarketplace\Modules\Product;

use VelocityMarketplace\Support\Contract;

class RecentlyViewed
{
    const COOKIE_NAME = 'vmp_recently_viewed';

    public static function track($product_id, $limit = 8)
    {
        $product_id = (int) $product_id;
        $limit = max(1, min(24, (int) $limit));

        if ($product_id <= 0 || !Contract::is_product($product_id) || headers_sent()) {
            return;
        }

        $ids = self::ids(0, $limit);
        $ids = array_values(array_filter(array_map('intval', $ids)));
        $ids = array_values(array_diff($ids, [$product_id]));
        array_unshift($ids, $product_id);
        $ids = array_slice($ids, 0, $limit);

        self::write_cookie($ids);
    }

    public static function ids($exclude_id = 0, $limit = 8)
    {
        $exclude_id = (int) $exclude_id;
        $limit = max(1, min(24, (int) $limit));
        $raw = isset($_COOKIE[self::COOKIE_NAME]) ? (string) wp_unslash($_COOKIE[self::COOKIE_NAME]) : '';
        if ($raw === '') {
            return [];
        }

        $ids = array_values(array_filter(array_map('intval', explode(',', $raw))));
        $filtered = [];
        foreach ($ids as $id) {
            if ($id <= 0 || !Contract::is_product($id)) {
                continue;
            }
            if ($exclude_id > 0 && $id === $exclude_id) {
                continue;
            }
            if (!in_array($id, $filtered, true)) {
                $filtered[] = $id;
            }
            if (count($filtered) >= $limit) {
                break;
            }
        }

        return $filtered;
    }

    public static function items($exclude_id = 0, $limit = 8)
    {
        $items = [];
        foreach (self::ids($exclude_id, $limit) as $product_id) {
            $item = ProductData::map_post($product_id);
            if ($item) {
                $items[] = $item;
            }
        }

        return $items;
    }

    private static function write_cookie(array $ids)
    {
        $value = implode(',', array_values(array_filter(array_map('intval', $ids))));
        $_COOKIE[self::COOKIE_NAME] = $value;

        $domain = defined('COOKIE_DOMAIN') && COOKIE_DOMAIN ? COOKIE_DOMAIN : '';
        $paths = [defined('COOKIEPATH') && COOKIEPATH ? COOKIEPATH : '/'];
        if (defined('SITECOOKIEPATH') && SITECOOKIEPATH && !in_array(SITECOOKIEPATH, $paths, true)) {
            $paths[] = SITECOOKIEPATH;
        }

        foreach ($paths as $path) {
            setcookie(self::COOKIE_NAME, $value, [
                'expires' => time() + (30 * DAY_IN_SECONDS),
                'path' => $path,
                'domain' => $domain,
                'secure' => is_ssl(),
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
        }
    }
}

