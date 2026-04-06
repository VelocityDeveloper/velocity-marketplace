<?php
$member_profile = (new \VelocityMarketplace\Modules\Profile\ProfileService())->get_member_profile($current_user_id);
$customer_name = (string) ($member_profile['name'] ?? '');
$customer_phone = (string) ($member_profile['phone'] ?? '');
$customer_address = (string) ($member_profile['address'] ?? '');
$customer_subdistrict = (string) ($member_profile['subdistrict_name'] ?? '');
$customer_city = (string) ($member_profile['city_name'] ?? '');
$customer_province = (string) ($member_profile['province_name'] ?? '');
$customer_subdistrict_id = (string) ($member_profile['subdistrict_id'] ?? '');
$customer_city_id = (string) ($member_profile['city_id'] ?? '');
$customer_province_id = (string) ($member_profile['province_id'] ?? '');
$customer_postcode = (string) ($member_profile['postcode'] ?? '');
$customer_email = (string) ($member_profile['email'] ?? '');
$location_state = [
    'province_id' => $customer_province_id,
    'province_name' => $customer_province,
    'city_id' => $customer_city_id,
    'city_name' => $customer_city,
    'subdistrict_id' => $customer_subdistrict_id,
    'subdistrict_name' => $customer_subdistrict,
    'postcode' => $customer_postcode,
];
?>
<div class="card border-0 shadow-sm" x-data='vmpMemberProfileForm(<?php echo wp_json_encode($location_state); ?>)' x-init="init()">
    <div class="card-body">
        <h3 class="h6 mb-3"><?php echo esc_html__('Profil Saya', 'velocity-marketplace'); ?></h3>
        <p class="text-muted small mb-3"><?php echo esc_html__('Simpan data akun dan alamat utama untuk mempercepat proses checkout berikutnya.', 'velocity-marketplace'); ?></p>
        <div class="alert alert-success py-2" x-show="saveMessage" x-text="saveMessage" style="display:none;"></div>
        <div class="alert alert-danger py-2" x-show="saveError" x-text="saveError" style="display:none;"></div>
        <form method="post" class="row g-3" @submit.prevent="submit($event)">
            <input type="hidden" name="vmp_action" value="save_customer_profile">
            <input type="hidden" name="tab" value="account_profile">
            <?php wp_nonce_field('vmp_customer_profile', 'vmp_customer_profile_nonce'); ?>
            <div class="col-md-6">
                <label class="form-label"><?php echo esc_html__('Nama', 'velocity-marketplace'); ?></label>
                <input type="text" name="customer_name" class="form-control" value="<?php echo esc_attr($customer_name); ?>" required>
            </div>
            <div class="col-md-6">
                <label class="form-label"><?php echo esc_html__('Telepon', 'velocity-marketplace'); ?></label>
                <input type="text" name="customer_phone" class="form-control" value="<?php echo esc_attr($customer_phone); ?>" required>
            </div>
            <div class="col-md-6">
                <label class="form-label"><?php echo esc_html__('Email', 'velocity-marketplace'); ?></label>
                <input type="email" class="form-control" value="<?php echo esc_attr($customer_email); ?>" readonly>
                <div class="form-text"><?php echo esc_html__('Perubahan email dikelola dari halaman pengaturan akun WordPress.', 'velocity-marketplace'); ?></div>
            </div>
            <div class="col-md-6">
                <label class="form-label"><?php echo esc_html__('Kode Pos', 'velocity-marketplace'); ?></label>
                <input type="text" name="customer_postcode" class="form-control" x-model="form.postcode">
            </div>
            <div class="col-12">
                <label class="form-label"><?php echo esc_html__('Alamat', 'velocity-marketplace'); ?></label>
                <textarea name="customer_address" class="form-control" rows="3" required><?php echo esc_textarea($customer_address); ?></textarea>
            </div>
            <div class="col-md-4">
                <label class="form-label"><?php echo esc_html__('Provinsi', 'velocity-marketplace'); ?></label>
                <select name="customer_province_id" class="form-select" x-ref="provinceSelect" x-model="form.province_id" @change="onProvinceChange()" :disabled="isLoadingProvinces">
                    <option value=""><?php echo esc_html__('Pilih provinsi', 'velocity-marketplace'); ?></option>
                    <template x-for="prov in provinces" :key="prov.province_id">
                        <option :value="prov.province_id" x-text="prov.province"></option>
                    </template>
                </select>
                <input type="hidden" name="customer_province_name" :value="form.province_name">
            </div>
            <div class="col-md-4">
                <label class="form-label"><?php echo esc_html__('Kota/Kabupaten', 'velocity-marketplace'); ?></label>
                <select name="customer_city_id" class="form-select" x-ref="citySelect" x-model="form.city_id" @change="onCityChange()" :disabled="!form.province_id || isLoadingCities">
                    <option value=""><?php echo esc_html__('Pilih kota atau kabupaten', 'velocity-marketplace'); ?></option>
                    <template x-for="city in cities" :key="city.city_id">
                        <option :value="city.city_id" x-text="(city.type ? city.type + ' ' : '') + city.city_name"></option>
                    </template>
                </select>
                <input type="hidden" name="customer_city_name" :value="form.city_name">
            </div>
            <div class="col-md-4">
                <label class="form-label"><?php echo esc_html__('Kecamatan', 'velocity-marketplace'); ?></label>
                <select name="customer_subdistrict_id" class="form-select" x-ref="subdistrictSelect" x-model="form.subdistrict_id" @change="onSubdistrictChange()" :disabled="!form.city_id || isLoadingSubdistricts">
                    <option value=""><?php echo esc_html__('Pilih kecamatan', 'velocity-marketplace'); ?></option>
                    <template x-for="subdistrict in subdistricts" :key="subdistrict.subdistrict_id">
                        <option :value="subdistrict.subdistrict_id" x-text="subdistrict.subdistrict_name"></option>
                    </template>
                </select>
                <input type="hidden" name="customer_subdistrict_name" :value="form.subdistrict_name">
            </div>
            <div class="col-12">
                <div class="small text-muted" x-show="locationMessage" x-text="locationMessage"></div>
            </div>
            <div class="col-12">
                <button type="submit" class="btn btn-dark" :disabled="saving" x-text="saving ? '<?php echo esc_attr__('Menyimpan...', 'velocity-marketplace'); ?>' : '<?php echo esc_attr__('Simpan Profil', 'velocity-marketplace'); ?>'"><?php echo esc_html__('Simpan Profil', 'velocity-marketplace'); ?></button>
            </div>
        </form>
    </div>
</div>
