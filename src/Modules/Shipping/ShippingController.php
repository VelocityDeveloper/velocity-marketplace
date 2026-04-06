<?php

namespace VelocityMarketplace\Modules\Shipping;

use VelocityMarketplace\Modules\Cart\CartRepository;
use VelocityMarketplace\Support\Settings;
use WP_REST_Request;
use WP_REST_Response;

class ShippingController
{
    public function register_routes()
    {
        register_rest_route('velocity-marketplace/v1', '/shipping/provinces', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'get_provinces'],
                'permission_callback' => '__return_true',
            ],
        ]);

        register_rest_route('velocity-marketplace/v1', '/shipping/cities', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'get_cities'],
                'permission_callback' => '__return_true',
            ],
        ]);

        register_rest_route('velocity-marketplace/v1', '/shipping/subdistricts', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'get_subdistricts'],
                'permission_callback' => '__return_true',
            ],
        ]);

        register_rest_route('velocity-marketplace/v1', '/shipping/checkout-context', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'get_checkout_context'],
                'permission_callback' => '__return_true',
            ],
        ]);

        register_rest_route('velocity-marketplace/v1', '/shipping/calculate', [
            [
                'methods' => ['GET', 'POST'],
                'callback' => [$this, 'calculate_cost'],
                'permission_callback' => '__return_true',
            ],
        ]);

        register_rest_route('velocity-marketplace/v1', '/shipping/waybill', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'get_waybill'],
                'permission_callback' => '__return_true',
            ],
        ]);
    }

    public static function base_url()
    {
        return 'https://ongkir.velocitydeveloper.id/api/v3';
    }

    public function get_provinces(WP_REST_Request $request)
    {
        $api_key = Settings::shipping_api_key();
        if ($api_key === '') {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'API Key ongkir belum diatur.',
            ], 400);
        }

        $cache_key = 'vmp_shipping_provinces';
        $cached = get_transient($cache_key);
        if ($cached !== false) {
            return new WP_REST_Response([
                'success' => true,
                'data' => $cached,
            ], 200);
        }

        $data = $this->remote_get('/destination/province', $api_key);
        if (is_wp_error($data)) {
            return new WP_REST_Response([
                'success' => false,
                'message' => $data->get_error_message(),
            ], 500);
        }

        $items = $this->map_provinces($data);
        if (empty($items)) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Gagal mengambil data provinsi.',
                'raw' => $data,
            ], 500);
        }

        set_transient($cache_key, $items, DAY_IN_SECONDS);

        return new WP_REST_Response([
            'success' => true,
            'data' => $items,
        ], 200);
    }

    public function get_cities(WP_REST_Request $request)
    {
        $api_key = Settings::shipping_api_key();
        if ($api_key === '') {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'API Key ongkir belum diatur.',
            ], 400);
        }

        $province_id = sanitize_text_field((string) $request->get_param('province'));
        if ($province_id === '') {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Parameter province diperlukan.',
            ], 400);
        }

        $cache_key = 'vmp_shipping_cities_' . $province_id;
        $cached = get_transient($cache_key);
        if ($cached !== false) {
            return new WP_REST_Response([
                'success' => true,
                'data' => $cached,
            ], 200);
        }

        $data = $this->remote_get('/destination/city/' . rawurlencode($province_id), $api_key);
        if (is_wp_error($data)) {
            return new WP_REST_Response([
                'success' => false,
                'message' => $data->get_error_message(),
            ], 500);
        }

        $items = $this->map_cities($data);
        if (empty($items)) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Gagal mengambil data kota/kabupaten.',
                'raw' => $data,
            ], 500);
        }

        set_transient($cache_key, $items, DAY_IN_SECONDS);

        return new WP_REST_Response([
            'success' => true,
            'data' => $items,
        ], 200);
    }

    public function get_subdistricts(WP_REST_Request $request)
    {
        $api_key = Settings::shipping_api_key();
        if ($api_key === '') {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'API Key ongkir belum diatur.',
            ], 400);
        }

        $city_id = sanitize_text_field((string) $request->get_param('city'));
        if ($city_id === '') {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Parameter city diperlukan.',
            ], 400);
        }

        $cache_key = 'vmp_shipping_subdistricts_' . $city_id;
        $cached = get_transient($cache_key);
        if ($cached !== false) {
            return new WP_REST_Response([
                'success' => true,
                'data' => $cached,
            ], 200);
        }

        $data = $this->remote_get('/destination/district/' . rawurlencode($city_id), $api_key);
        if (is_wp_error($data)) {
            return new WP_REST_Response([
                'success' => false,
                'message' => $data->get_error_message(),
            ], 500);
        }

        $items = $this->map_subdistricts($data);
        if (empty($items)) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Gagal mengambil data kecamatan.',
                'raw' => $data,
            ], 500);
        }

        set_transient($cache_key, $items, DAY_IN_SECONDS);

        return new WP_REST_Response([
            'success' => true,
            'data' => $items,
        ], 200);
    }

    public function get_checkout_context(WP_REST_Request $request)
    {
        $context = $this->resolve_checkout_context();
        $status = !empty($context['success']) ? 200 : 400;
        return new WP_REST_Response($context, $status);
    }

    public function calculate_cost(WP_REST_Request $request)
    {
        $params = $request->get_json_params();
        if (!is_array($params)) {
            $params = [];
        }

        if (empty($params)) {
            $params = [
                'seller_id' => $request->get_param('seller_id'),
                'destination_subdistrict' => $request->get_param('destination_subdistrict'),
                'courier' => $request->get_param('courier'),
            ];
        }

        $context = $this->resolve_checkout_context();
        if (empty($context['success'])) {
            return new WP_REST_Response($context, 400);
        }

        $seller_id = isset($params['seller_id']) ? (int) $params['seller_id'] : 0;
        $seller_group = $this->find_context_group($context, $seller_id);
        if (!$seller_group) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Seller untuk ongkir tidak ditemukan di keranjang.',
            ], 400);
        }

        $destination_subdistrict = sanitize_text_field((string) ($params['destination_subdistrict'] ?? ''));
        if ($destination_subdistrict === '') {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Kecamatan tujuan wajib dipilih.',
            ], 400);
        }

        $courier = sanitize_text_field((string) ($params['courier'] ?? ''));
        if ($courier === '') {
            $courier_codes = array_map(static function ($row) {
                return (string) ($row['code'] ?? '');
            }, (array) ($seller_group['couriers'] ?? []));
            $courier_codes = array_values(array_filter($courier_codes));
            $courier = implode(':', $courier_codes);
        }

        if ($courier === '') {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Kurir toko belum diatur.',
            ], 400);
        }

        $weight = (int) ($seller_group['weight_grams'] ?? 0);
        if ($weight < 1) {
            $weight = 1;
        }

        $api_key = Settings::shipping_api_key();
        $origin_subdistrict = (string) ($seller_group['origin']['subdistrict_id'] ?? '');
        $cache_key = 'vmp_shipping_cost_' . md5($seller_id . '|' . $origin_subdistrict . '|' . $destination_subdistrict . '|' . $weight . '|' . $courier);
        $cached = get_transient($cache_key);
        if ($cached !== false) {
            return new WP_REST_Response($cached, 200);
        }

        $data = $this->remote_post('/calculate/domestic-cost', $api_key, [
            'origin' => $origin_subdistrict,
            'destination' => $destination_subdistrict,
            'weight' => $weight,
            'courier' => $courier,
            'price' => 'lowest',
        ]);

        if (is_wp_error($data)) {
            return new WP_REST_Response([
                'success' => false,
                'message' => $data->get_error_message(),
            ], 500);
        }

        $services = $this->map_services($data);
        if (empty($services)) {
            $codes = preg_split('/[:,]+/', (string) $courier);
            $codes = array_values(array_filter(array_map('trim', (array) $codes)));

            foreach ($codes as $single_code) {
                $single = $this->remote_post('/calculate/domestic-cost', $api_key, [
                    'origin' => $origin_subdistrict,
                    'destination' => $destination_subdistrict,
                    'weight' => $weight,
                    'courier' => $single_code,
                    'price' => 'lowest',
                ]);

                if (is_wp_error($single)) {
                    continue;
                }

                $services = array_merge($services, $this->map_services($single));
            }
        }

        $services = array_values(array_unique(array_map('serialize', $services)));
        $services = array_map('unserialize', $services);
        if (empty($services)) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Tidak ada layanan pengiriman yang tersedia.',
                'raw' => $data,
            ], 400);
        }

        $payload = [
            'success' => true,
            'data' => [
                'seller_id' => $seller_id,
                'weight_grams' => $weight,
                'services' => $services,
            ],
        ];

        set_transient($cache_key, $payload, DAY_IN_SECONDS);

        return new WP_REST_Response($payload, 200);
    }

    public function get_waybill(WP_REST_Request $request)
    {
        $awb = sanitize_text_field((string) $request->get_param('awb'));
        $courier = sanitize_key((string) $request->get_param('courier'));
        if ($awb === '' || $courier === '') {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Parameter awb dan courier diperlukan.',
            ], 400);
        }

        $data = self::fetch_waybill($awb, $courier);
        if (is_wp_error($data)) {
            return new WP_REST_Response([
                'success' => false,
                'message' => $data->get_error_message(),
            ], 500);
        }

        return new WP_REST_Response([
            'success' => true,
            'data' => $data,
        ], 200);
    }

    private function resolve_checkout_context()
    {
        $api_key = Settings::shipping_api_key();
        if ($api_key === '') {
            return [
                'success' => false,
                'message' => 'API Key ongkir belum diatur di pengaturan marketplace.',
            ];
        }

        $repo = new CartRepository();
        $cart = $repo->get_cart_data();
        $groups = isset($cart['seller_groups']) && is_array($cart['seller_groups']) ? $cart['seller_groups'] : [];
        if (empty($groups)) {
            return [
                'success' => false,
                'message' => 'Keranjang kosong.',
            ];
        }

        $labels = Settings::courier_labels();
        $resolved_groups = [];
        foreach ($groups as $group) {
            if (!is_array($group)) {
                continue;
            }
            $seller_id = isset($group['seller_id']) ? (int) $group['seller_id'] : 0;
            if ($seller_id <= 0) {
                continue;
            }
            $origin = [
                'province_id' => (string) get_user_meta($seller_id, 'vmp_store_province_id', true),
                'province_name' => (string) get_user_meta($seller_id, 'vmp_store_province', true),
                'city_id' => (string) get_user_meta($seller_id, 'vmp_store_city_id', true),
                'city_name' => (string) get_user_meta($seller_id, 'vmp_store_city', true),
                'subdistrict_id' => (string) get_user_meta($seller_id, 'vmp_store_subdistrict_id', true),
                'subdistrict_name' => (string) get_user_meta($seller_id, 'vmp_store_subdistrict', true),
                'postcode' => (string) get_user_meta($seller_id, 'vmp_store_postcode', true),
            ];

            if ($origin['province_id'] === '' || $origin['city_id'] === '' || $origin['subdistrict_id'] === '') {
                $seller = get_userdata($seller_id);
                $seller_name = $seller && $seller->display_name !== '' ? $seller->display_name : ('Seller #' . $seller_id);
                return [
                    'success' => false,
                    'message' => 'Alamat asal toko ' . $seller_name . ' belum lengkap.',
                ];
            }

            $couriers = get_user_meta($seller_id, 'vmp_couriers', true);
            if (!is_array($couriers)) {
                $couriers = [];
            }
            $cod_enabled = !empty(get_user_meta($seller_id, 'vmp_cod_enabled', true));
            $cod_city_ids = get_user_meta($seller_id, 'vmp_cod_city_ids', true);
            $cod_city_names = get_user_meta($seller_id, 'vmp_cod_city_names', true);
            if (!is_array($cod_city_ids)) {
                $cod_city_ids = [];
            }
            if (!is_array($cod_city_names)) {
                $cod_city_names = [];
            }

            $mapped_couriers = [];
            foreach ($couriers as $code) {
                $code = sanitize_key((string) $code);
                if ($code === '') {
                    continue;
                }
                $mapped_couriers[] = [
                    'code' => $code,
                    'name' => $labels[$code] ?? strtoupper($code),
                ];
            }

            if (empty($mapped_couriers)) {
                $seller = get_userdata($seller_id);
                $seller_name = $seller && $seller->display_name !== '' ? $seller->display_name : ('Seller #' . $seller_id);
                return [
                    'success' => false,
                    'message' => 'Kurir toko ' . $seller_name . ' belum dipilih.',
                ];
            }

            $seller = get_userdata($seller_id);
            $group['seller_name'] = $seller && $seller->display_name !== '' ? $seller->display_name : ('Seller #' . $seller_id);
            $group['origin'] = $origin;
            $group['couriers'] = $mapped_couriers;
            $group['cod_enabled'] = $cod_enabled;
            $group['cod_city_ids'] = array_values(array_filter(array_map('strval', $cod_city_ids)));
            $group['cod_city_names'] = array_values(array_filter(array_map('strval', $cod_city_names)));
            $group['weight_grams'] = max(1, (int) ($group['weight_grams'] ?? 0));
            $group['subtotal'] = (float) ($group['subtotal'] ?? 0);
            $group['items_count'] = (int) ($group['items_count'] ?? 0);
            $group['item_keys'] = isset($group['item_keys']) && is_array($group['item_keys']) ? array_values(array_filter(array_map('strval', $group['item_keys']))) : [];
            $resolved_groups[] = $group;
        }

        return [
            'success' => true,
            'data' => [
                'groups' => array_values($resolved_groups),
            ],
        ];
    }

    private function find_context_group($context, $seller_id)
    {
        $groups = isset($context['data']['groups']) && is_array($context['data']['groups'])
            ? $context['data']['groups']
            : [];

        foreach ($groups as $group) {
            if ((int) ($group['seller_id'] ?? 0) === (int) $seller_id) {
                return $group;
            }
        }

        return null;
    }

    private function remote_get($path, $api_key)
    {
        $response = wp_remote_get(self::base_url() . $path, [
            'headers' => [
                'key' => $api_key,
            ],
            'timeout' => 15,
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        $body = wp_remote_retrieve_body($response);
        return json_decode($body, true);
    }

    private function remote_post($path, $api_key, $payload)
    {
        $response = wp_remote_post(self::base_url() . $path, [
            'headers' => [
                'key' => $api_key,
                'Content-Type' => 'application/x-www-form-urlencoded',
            ],
            'body' => $payload,
            'timeout' => 20,
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        $body = wp_remote_retrieve_body($response);
        return json_decode($body, true);
    }

    private function map_provinces($data)
    {
        if (isset($data['data']) && is_array($data['data'])) {
            return array_map(static function ($row) {
                return [
                    'province_id' => (string) ($row['id'] ?? ''),
                    'province' => (string) ($row['name'] ?? ''),
                ];
            }, $data['data']);
        }

        if (isset($data['rajaongkir']['results']) && is_array($data['rajaongkir']['results'])) {
            return $data['rajaongkir']['results'];
        }

        return [];
    }

    private function map_cities($data)
    {
        if (isset($data['data']) && is_array($data['data'])) {
            return array_map(static function ($row) {
                return [
                    'city_id' => (string) ($row['id'] ?? ''),
                    'city_name' => (string) ($row['name'] ?? ''),
                    'type' => (string) ($row['type'] ?? ''),
                    'province' => (string) ($row['province_name'] ?? ''),
                    'postal_code' => (string) ($row['zip_code'] ?? ''),
                ];
            }, $data['data']);
        }

        if (isset($data['rajaongkir']['results']) && is_array($data['rajaongkir']['results'])) {
            return $data['rajaongkir']['results'];
        }

        return [];
    }

    private function map_subdistricts($data)
    {
        if (isset($data['data']) && is_array($data['data'])) {
            return array_map(static function ($row) {
                return [
                    'subdistrict_id' => (string) ($row['id'] ?? ''),
                    'subdistrict_name' => (string) ($row['name'] ?? ''),
                ];
            }, $data['data']);
        }

        if (isset($data['rajaongkir']['results']) && is_array($data['rajaongkir']['results'])) {
            return $data['rajaongkir']['results'];
        }

        return [];
    }

    private function map_services($data)
    {
        $services = [];
        $labels = Settings::courier_labels();

        if (isset($data['data']['couriers']) && is_array($data['data']['couriers'])) {
            foreach ($data['data']['couriers'] as $courier_group) {
                $code = (string) ($courier_group['code'] ?? '');
                $name = (string) ($courier_group['name'] ?? ($labels[$code] ?? strtoupper($code)));
                $rows = isset($courier_group['services']) && is_array($courier_group['services']) ? $courier_group['services'] : [];
                foreach ($rows as $row) {
                    $services[] = [
                        'code' => (string) ($row['code'] ?? $code),
                        'name' => $name,
                        'service' => (string) ($row['service'] ?? ($row['service_code'] ?? '')),
                        'description' => (string) ($row['description'] ?? ($row['service_name'] ?? '')),
                        'cost' => (float) ($row['cost'] ?? ($row['value'] ?? 0)),
                        'etd' => (string) ($row['etd'] ?? ($row['etd_days'] ?? '')),
                    ];
                }
            }
        } elseif (isset($data['data']) && is_array($data['data'])) {
            foreach ($data['data'] as $row) {
                if (isset($row['services']) && is_array($row['services'])) {
                    $code = (string) ($row['courier'] ?? ($row['code'] ?? ''));
                    $name = (string) ($row['name'] ?? ($labels[$code] ?? strtoupper($code)));
                    foreach ($row['services'] as $service_row) {
                        $services[] = [
                            'code' => (string) ($service_row['code'] ?? $code),
                            'name' => (string) ($service_row['name'] ?? $name),
                            'service' => (string) ($service_row['service'] ?? ($service_row['service_code'] ?? '')),
                            'description' => (string) ($service_row['description'] ?? ($service_row['service_name'] ?? '')),
                            'cost' => (float) ($service_row['cost'] ?? ($service_row['value'] ?? 0)),
                            'etd' => (string) ($service_row['etd'] ?? ($service_row['etd_days'] ?? '')),
                        ];
                    }
                } else {
                    $code = (string) ($row['courier'] ?? ($row['code'] ?? ''));
                    $services[] = [
                        'code' => $code,
                        'name' => (string) ($row['name'] ?? ($labels[$code] ?? strtoupper($code))),
                        'service' => (string) ($row['service'] ?? ($row['service_code'] ?? '')),
                        'description' => (string) ($row['description'] ?? ($row['service_name'] ?? '')),
                        'cost' => (float) ($row['cost'] ?? ($row['value'] ?? 0)),
                        'etd' => (string) ($row['etd'] ?? ($row['etd_days'] ?? '')),
                    ];
                }
            }
        }

        return array_values(array_filter($services, static function ($row) {
            return !empty($row['code']) && ((string) ($row['service'] ?? '') !== '');
        }));
    }

    public static function fetch_waybill($awb, $courier)
    {
        $awb = sanitize_text_field((string) $awb);
        $courier = sanitize_key((string) $courier);
        $api_key = Settings::shipping_api_key();

        if ($awb === '' || $courier === '') {
            return new \WP_Error('invalid_waybill', 'Parameter resi dan kurir wajib diisi.');
        }
        if ($api_key === '') {
            return new \WP_Error('missing_shipping_api', 'API Key ongkir belum diatur.');
        }

        $cache_key = 'vmp_shipping_waybill_' . md5($awb . '|' . $courier);
        $cached = get_transient($cache_key);
        if ($cached !== false) {
            return $cached;
        }

        $url = add_query_arg([
            'awb' => $awb,
            'courier' => $courier,
        ], self::base_url() . '/waybill');

        $response = wp_remote_post($url, [
            'headers' => [
                'key' => $api_key,
            ],
            'timeout' => 15,
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        if (!is_array($data)) {
            return new \WP_Error('invalid_waybill_response', 'Gagal mengambil tracking waybill.');
        }

        set_transient($cache_key, $data, HOUR_IN_SECONDS);
        return $data;
    }
}
