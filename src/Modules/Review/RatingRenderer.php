<?php

namespace VelocityMarketplace\Modules\Review;

class RatingRenderer
{
    public static function normalize($rating)
    {
        $rating = max(0, min(5, (float) $rating));
        return round($rating * 2) / 2;
    }

    public static function stars_html($rating, $size = 16, $extra_class = '')
    {
        $rating = self::normalize($rating);
        $size = max(10, (int) $size);
        $extra_class = trim((string) $extra_class);
        $class_attr = trim('bi ' . $extra_class);
        $html = '';

        for ($star = 1; $star <= 5; $star++) {
            if ($rating >= $star) {
                $html .= self::icon('star-fill', $size, $class_attr);
                continue;
            }

            if ($rating === ($star - 0.5)) {
                $html .= self::icon('star-half', $size, trim($class_attr . ' vmp-rating-star-half'));
                continue;
            }

            $html .= self::icon('star-fill', $size, trim($class_attr . ' opacity-25'));
        }

        return $html;
    }

    public static function summary_html($rating, $count = null, $args = [])
    {
        $rating = self::normalize($rating);
        $size = isset($args['size']) ? max(10, (int) $args['size']) : 16;
        $show_value = !isset($args['show_value']) || (bool) $args['show_value'];
        $show_count = !isset($args['show_count']) || (bool) $args['show_count'];
        $class = isset($args['class']) ? trim((string) $args['class']) : '';
        $stars_class = isset($args['stars_class']) ? trim((string) $args['stars_class']) : '';
        $value_class = isset($args['value_class']) ? trim((string) $args['value_class']) : '';
        $count_class = isset($args['count_class']) ? trim((string) $args['count_class']) : '';
        $count_text = isset($args['count_text']) ? (string) $args['count_text'] : __('ulasan', 'velocity-marketplace');

        $parts = [];
        $parts[] = sprintf(
            '<span class="%1$s" aria-label="%2$s">%3$s</span>',
            esc_attr(trim('d-inline-flex align-items-center gap-1 text-warning ' . $stars_class)),
            esc_attr(number_format($rating, 1, ',', '.') . '/5'),
            self::stars_html($rating, $size)
        );

        if ($show_value) {
            $parts[] = sprintf(
                '<span class="%1$s">%2$s/5</span>',
                esc_attr(trim($value_class)),
                esc_html(number_format($rating, 1, ',', '.'))
            );
        }

        if ($show_count && $count !== null) {
            $parts[] = sprintf(
                '<span class="%1$s">%2$s</span>',
                esc_attr(trim($count_class)),
                esc_html(sprintf(__('dari %1$d %2$s', 'velocity-marketplace'), (int) $count, $count_text))
            );
        }

        return sprintf(
            '<span class="%1$s">%2$s</span>',
            esc_attr(trim('d-inline-flex flex-wrap align-items-center gap-1 ' . $class)),
            implode('', $parts)
        );
    }

    public static function product_summary_html($product_id, $args = [])
    {
        $summary = (new ReviewRepository())->product_summary((int) $product_id);

        return self::summary_html(
            (float) ($summary['rating_average'] ?? 0),
            (int) ($summary['review_count'] ?? 0),
            $args
        );
    }

    public static function seller_summary_html($seller_id, $args = [])
    {
        $summary = (new StarSellerService())->summary((int) $seller_id);

        return self::summary_html(
            (float) ($summary['rating_average'] ?? 0),
            (int) ($summary['rating_count'] ?? 0),
            $args
        );
    }

    private static function icon($type, $size, $class_attr)
    {
        $path = $type === 'star-half'
            ? 'M5.354 5.119 7.538.792A.52.52 0 0 1 8 .5c.183 0 .366.097.465.292l2.184 4.327 4.898.696A.54.54 0 0 1 16 6.32a.55.55 0 0 1-.17.445l-3.523 3.356.83 4.73c.078.443-.36.79-.746.592L8 13.187l-4.389 2.256a.5.5 0 0 1-.146.05c-.342.06-.668-.254-.6-.642l.83-4.73L.173 6.765a.55.55 0 0 1-.172-.403.6.6 0 0 1 .085-.302.51.51 0 0 1 .37-.245zM8 12.027a.5.5 0 0 1 .232.056l3.686 1.894-.694-3.957a.56.56 0 0 1 .162-.505l2.907-2.77-4.052-.576a.53.53 0 0 1-.393-.288L8.001 2.223 8 2.226z'
            : 'M3.612 15.443c-.386.198-.824-.149-.746-.592l.83-4.73L.173 6.765c-.329-.314-.158-.888.283-.95l4.898-.696L7.538.792c.197-.39.73-.39.927 0l2.184 4.327 4.898.696c.441.062.612.636.282.95l-3.522 3.356.83 4.73c.078.443-.36.79-.746.592L8 13.187l-4.389 2.256z';

        return sprintf(
            '<svg xmlns="http://www.w3.org/2000/svg" width="%1$d" height="%1$d" fill="currentColor" class="%2$s" viewBox="0 0 16 16" aria-hidden="true"><path d="%3$s"/></svg>',
            (int) $size,
            esc_attr($class_attr),
            esc_attr($path)
        );
    }
}
