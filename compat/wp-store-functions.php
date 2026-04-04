<?php

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('wps_icon')) {
    function wps_icon($args = [])
    {
        return \VelocityMarketplace\Compat\WpStoreBridge::icon_html($args);
    }
}

if (!function_exists('wps_label_badge_html')) {
    function wps_label_badge_html($product_id)
    {
        return \VelocityMarketplace\Compat\WpStoreBridge::label_badge_html((int) $product_id);
    }
}

if (!function_exists('wps_discount_badge_html')) {
    function wps_discount_badge_html($product_id)
    {
        return \VelocityMarketplace\Compat\WpStoreBridge::discount_badge_html((int) $product_id);
    }
}
