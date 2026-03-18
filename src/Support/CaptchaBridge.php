<?php

namespace VelocityMarketplace\Support;

class CaptchaBridge
{
    public static function get_handler()
    {
        global $captcha_handler;
        if (is_object($captcha_handler) && method_exists($captcha_handler, 'verify')) {
            return $captcha_handler;
        }

        return null;
    }

    public static function is_available()
    {
        return self::get_handler() !== null;
    }

    public static function is_active()
    {
        $handler = self::get_handler();
        if (!$handler) {
            return false;
        }

        if (method_exists($handler, 'isActive')) {
            return (bool) $handler->isActive();
        }

        return true;
    }

    public static function render($form_selector = '')
    {
        if (!self::is_available() || !self::is_active()) {
            return '';
        }

        $selector_attr = trim((string) $form_selector);
        $shortcode = '[velocity_captcha';
        if ($selector_attr !== '') {
            $shortcode .= ' form="' . esc_attr($selector_attr) . '"';
        }
        $shortcode .= ']';

        return do_shortcode($shortcode);
    }

    public static function verify_payload($payload = [])
    {
        $handler = self::get_handler();
        if (!$handler) {
            return [
                'success' => true,
                'message' => 'Captcha handler tidak ditemukan.',
            ];
        }

        if (method_exists($handler, 'isActive') && !$handler->isActive()) {
            return [
                'success' => true,
                'message' => 'Captcha tidak aktif.',
            ];
        }

        $gresponse = '';
        $token = '';
        $input = '';
        if (is_array($payload)) {
            $gresponse = isset($payload['g-recaptcha-response']) ? (string) $payload['g-recaptcha-response'] : '';
            if ($gresponse === '' && isset($payload['g_recaptcha_response'])) {
                $gresponse = (string) $payload['g_recaptcha_response'];
            }
            $token = isset($payload['vd_captcha_token']) ? (string) $payload['vd_captcha_token'] : '';
            $input = isset($payload['vd_captcha_input']) ? (string) $payload['vd_captcha_input'] : '';
        }

        $backup_post = $_POST;
        $_POST = is_array($_POST) ? $_POST : [];
        if ($gresponse !== '') {
            $_POST['g-recaptcha-response'] = $gresponse;
        }
        if ($token !== '') {
            $_POST['vd_captcha_token'] = $token;
        }
        if ($input !== '') {
            $_POST['vd_captcha_input'] = $input;
        }

        $verify = $handler->verify($gresponse !== '' ? $gresponse : null);

        $_POST = $backup_post;
        if (!is_array($verify)) {
            return [
                'success' => false,
                'message' => 'Captcha tidak valid.',
            ];
        }

        return [
            'success' => !empty($verify['success']),
            'message' => isset($verify['message']) ? (string) $verify['message'] : '',
        ];
    }
}

