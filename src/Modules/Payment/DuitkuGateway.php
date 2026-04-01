<?php

namespace VelocityMarketplace\Modules\Payment;

class DuitkuGateway
{
    public static function is_available()
    {
        return class_exists('\Velocity_Addons_Duitku') && \Velocity_Addons_Duitku::is_active();
    }

    public static function create_invoice(array $params)
    {
        if (!self::is_available()) {
            return new \WP_Error('duitku_not_available', __('Gateway Duitku tidak tersedia.', 'velocity-marketplace'));
        }

        $gateway = new \Velocity_Addons_Duitku();
        return $gateway->createInvoice($params);
    }
}
