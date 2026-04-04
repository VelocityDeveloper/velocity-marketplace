<?php

namespace VelocityMarketplace\Frontend;

use VelocityMarketplace\Support\Contract;

class Template
{
    public function register()
    {
        add_filter('template_include', [$this, 'template_include'], 20);
    }

    public static function render($template, $data = [])
    {
        $file = self::locate($template);
        if (!file_exists($file)) {
            return '';
        }

        if (is_array($data)) {
            extract($data, EXTR_SKIP);
        }

        ob_start();
        include $file;
        return ob_get_clean();
    }

    public function template_include($template)
    {
        if (is_post_type_archive(Contract::PRODUCT_POST_TYPE) || is_post_type_archive(Contract::LEGACY_PRODUCT_POST_TYPE)) {
            $theme_template = locate_template([
                'archive-store_product.php',
                'archive-vmp_product.php',
                'velocity-marketplace/archive-product.php',
            ]);

            return $theme_template !== '' ? $theme_template : self::locate('archive-product');
        }

        if (is_singular(Contract::PRODUCT_POST_TYPE) || is_singular(Contract::LEGACY_PRODUCT_POST_TYPE)) {
            $theme_template = locate_template([
                'single-store_product.php',
                'single-vmp_product.php',
                'velocity-marketplace/single-product.php',
            ]);

            return $theme_template !== '' ? $theme_template : self::locate('single-product');
        }

        return $template;
    }

    public static function locate($template)
    {
        return VMP_PATH . 'templates/' . ltrim($template, '/') . '.php';
    }
}
