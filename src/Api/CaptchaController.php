<?php

namespace VelocityMarketplace\Api;

use VelocityMarketplace\Support\CaptchaBridge;
use WP_REST_Request;
use WP_REST_Response;

class CaptchaController
{
    public function register_routes()
    {
        register_rest_route('velocity-marketplace/v1', '/captcha/info', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'info'],
                'permission_callback' => '__return_true',
            ],
        ]);

        register_rest_route('velocity-marketplace/v1', '/captcha/verify', [
            [
                'methods' => 'POST',
                'callback' => [$this, 'verify'],
                'permission_callback' => '__return_true',
            ],
        ]);
    }

    public function info(WP_REST_Request $request)
    {
        return new WP_REST_Response([
            'available' => CaptchaBridge::is_available(),
            'active' => CaptchaBridge::is_active(),
        ], 200);
    }

    public function verify(WP_REST_Request $request)
    {
        $payload = $request->get_json_params();
        if (!is_array($payload)) {
            $payload = [];
        }

        $verify = CaptchaBridge::verify_payload($payload);
        $code = !empty($verify['success']) ? 200 : 400;

        return new WP_REST_Response($verify, $code);
    }
}

