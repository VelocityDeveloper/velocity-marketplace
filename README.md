# VD Marketplace

Versi: `1.0.0`

`VD Marketplace` adalah addon marketplace untuk `VD Store`.

Plugin ini dipakai jika toko online ingin punya:
- seller
- toko publik per seller
- dashboard seller
- order per seller
- pengiriman per toko
- pesan buyer dan seller
- notifikasi seller

## Syarat utama

`VD Marketplace` wajib dipakai bersama `VD Store`.

Kalau `VD Store` tidak aktif:
- addon tidak akan berjalan

## Fungsi utama

- dashboard seller di halaman profil customer
- profil toko seller
- halaman toko publik seller
- checkout multi-seller
- shipping per toko
- status order seller
- pesan buyer dan seller
- notifikasi seller
- template email marketplace untuk admin dan pembeli

## Cara pakai singkat

1. Aktifkan `VD Store`
2. Aktifkan `VD Marketplace`
3. Atur halaman marketplace yang dibutuhkan
4. Aktifkan akun seller dari `Profil Toko`
5. Tambahkan produk seller dari dashboard seller

## Perilaku penting

- role member marketplace: `vd_member`
- seller aktif ditentukan oleh meta:
  - `_store_is_seller`
- URL toko publik seller memakai `user_login`, contoh:
  - `/store/namauser/`
- setelah checkout, customer diarahkan ke tracking publik:
  - `/tracking-order/?order=INVOICE`

## Customer checkout

- cart digital-only tidak meminta ongkir
- cart campuran fisik + digital tetap didukung
- ringkasan checkout menampilkan thumbnail produk
- blok pengiriman per toko memakai nama toko, bukan nama user

## Shortcode utama

- `[vmp_products]`
- `[vmp_product_card]`
- `[vmp_product_gallery]`
- `[vmp_product_reviews]`
- `[vmp_product_seller_card]`
- `[vmp_premium_badge]`
- `[vmp_recently_viewed]`
- `[vmp_product_filter]`
- `[vmp_rating]`
- `[vmp_add_to_cart]`
- `[vmp_add_to_wishlist]`
- `[vmp_cart]`
- `[vmp_checkout]`
- `[vmp_profile]`
- `[vmp_tracking]`
- `[vmp_store_profile]`

## Helper dan shortcode tambahan

### Badge produk premium

Kalau produk sudah disetujui sebagai premium, kamu bisa tampilkan badge dengan:

- fungsi PHP:
  - `vmp_is_premium_product($post_id)`
  - `vmp_premium_badge_html([...])`
- shortcode:
  - `[vmp_premium_badge]`

Contoh fungsi PHP:

```php
if (vmp_is_premium_product($post_id)) {
    echo vmp_premium_badge_html([
        'post_id' => $post_id,
        'text' => 'Premium',
        'class' => 'badge bg-warning text-dark',
    ]);
}
```

Contoh shortcode:

```text
[vmp_premium_badge post_id="123" text="Produk Premium" class="badge bg-warning text-dark"]
```

Parameter yang didukung:
- `post_id`
- `text`
- `class`

## Catatan

- `VD Marketplace` tidak menggantikan `VD Store`
- data inti produk, order, cart, wishlist, dan kupon tetap mengikuti core `VD Store`
- pengaturan email marketplace tersedia di menu `Pengaturan Marketplace`
- dokumentasi teknis developer ada di file:
  - `DOKUMENTASI-DEVELOPER.md`
