<?php

namespace VelocityMarketplace\Frontend;

class Template
{
    public static function render($template, $data = [])
    {
        $file = VMP_PATH . 'templates/' . ltrim($template, '/') . '.php';
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
}
