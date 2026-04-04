# Velocity Marketplace

Plugin marketplace WordPress berbasis REST API + Alpine.js.

Status saat ini:
- versi plugin publik masih `1.0.0`
- masih tahap pembuatan awal
- banyak bagian belum final
- belum dirilis
- arah arsitektur sekarang adalah menjadikan `velocity-marketplace` versi lengkap / superset dari `wp-store`, bukan sistem terpisah yang mengabaikan fondasi data lama

Dokumen ini adalah catatan kerja untuk developer. Kalau ada perubahan struktur, alur, nama shortcode, atau file baru, README ini harus ikut diperbarui supaya orang berikutnya tidak menebak-nebak arsitektur plugin.

## Arah Arsitektur

Plugin ini tidak lagi diposisikan sebagai sistem yang berdiri sendiri tanpa hubungan data dengan `wp-store`.

Target resmi perusahaan:
- `wp-store` = implementasi commerce single-store
- `velocity-marketplace` = implementasi commerce multi-seller / marketplace
- keduanya harus bergerak menuju shared commerce contract yang sama untuk:
  - CPT inti
  - taxonomy produk
  - meta inti produk
  - shortcode publik frontend

Dokumen acuan utamanya ada di:
- `SHARED-CONTRACT.md`

## Perubahan terbaru

- katalog dan archive produk:
  - sort baru `Terlaris` dan `Rating Tertinggi`
  - chip filter aktif + tombol reset
- single product:
  - share sosial via SVG Bootstrap (`WhatsApp`, `Facebook`, `X`, `Telegram`)
  - copy link produk
  - seller card menampilkan `last active`
- halaman akun:
  - blok `Produk yang Baru Dilihat` dipasang di bagian bawah halaman
- order/tracking:
  - timeline status per toko
  - tombol copy untuk invoice, resi, dan rekening transfer
  - tombol lanjut bayar untuk Duitku jika pembayaran belum selesai

## Standar saat ini

Catatan:
- daftar di bawah ini mencerminkan kondisi implementasi saat ini
- beberapa item masih divergen dari target shared contract
- divergence yang wajib dibereskan sudah dirangkum di `SHARED-CONTRACT.md`

- Prefix shortcode utama internal: `vmp_*`
- Canonical shortcode publik lintas plugin: `wp_store_*`
- Post type produk canonical: `store_product`
- Post type order canonical: `store_order`
- Taxonomy kategori produk canonical: `store_product_cat`
- Option settings: `vmp_settings`
- Option pages: `vmp_pages`
- Option db version: `vmp_db_version`
- Penyimpanan pesan: custom table `wp_vmp_messages`
- Penyimpanan ulasan: custom table `wp_vmp_reviews`
- Storage utama:
  - produk: CPT + post meta
  - order: CPT + post meta
  - kupon: CPT + post meta
  - pesan: custom table
  - ulasan: custom table
  - cart: cookie / user meta
  - wishlist: user meta
  - profil user umum: user meta
  - pengaturan kurir toko: user meta
  - role marketplace: `vmp_member`
  - badge star seller: user meta hasil evaluasi otomatis
- Penyimpanan cart:
  - user login: user meta `vmp_cart_items`
  - guest: cookie `vmp_guest_cart`

- Skema profil member:
  - `first_name` / `display_name` / `nickname` (WordPress core)
  - `vmp_member_phone`
  - `vmp_member_address`
  - `vmp_member_province_id`
  - `vmp_member_province`
  - `vmp_member_city_id`
  - `vmp_member_city`
  - `vmp_member_subdistrict_id`
  - `vmp_member_subdistrict`
  - `vmp_member_postcode`
- Meta khusus seller:
  - `vmp_store_name`
  - `vmp_store_phone`
  - `vmp_store_whatsapp`
  - `vmp_store_address`
  - `vmp_store_province_id`
  - `vmp_store_province`
  - `vmp_store_city_id`
  - `vmp_store_city`
  - `vmp_store_subdistrict_id`
  - `vmp_store_subdistrict`
  - `vmp_store_postcode`
  - `vmp_store_description`
  - `vmp_store_avatar_id`
  - `vmp_store_bank_details`
  - `vmp_couriers`
  - `vmp_cod_enabled`
  - `vmp_cod_city_ids`
  - `vmp_cod_city_names`

- Meta agregat produk:
  - `vmp_review_count`
  - `vmp_rating_average`
  - `vmp_sold_count`

## Shortcode resmi

Catatan:
- daftar berikut adalah shortcode utama yang saat ini dipakai `velocity-marketplace`
- untuk kontrak publik lintas plugin perusahaan, shortcode `wp_store_*` tetap dianggap API stabil
- `vmp_*` tetap hidup sebagai namespace modern / internal

- `[vmp_catalog]`
- `[vmp_products]`
- `[vmp_product_card]`
- `[vmp_product_gallery]`
- `[vmp_product_reviews]`
- `[vmp_product_seller_card]`
- `[vmp_related_products]`
- `[vmp_recently_viewed]`
  - default dipakai di bagian bawah halaman akun
- `[vmp_product_filter]`
- `[vmp_thumbnail]`
- `[vmp_price]`
- `[vmp_rating]`
- `[vmp_review_count]`
- `[vmp_sold_count]`
- `[vmp_add_to_cart]`
- `[vmp_add_to_wishlist]`
- `[vmp_cart]`
- `[vmp_cart_page]`
- `[vmp_checkout]`
- `[vmp_profile]`
- `[vmp_tracking]`
- `[vmp_store_profile]`
- `[vmp_messages_icon]`
- `[vmp_notifications_icon]`
- `[vmp_profile_icon]`

### Ringkasan shortcode baru

- `[vmp_product_filter]`
  - render form filter saja
  - cocok untuk Beaver Builder / Themer karena hasil filter memakai query string + `pre_get_posts`
  - sekarang mendukung chip filter aktif + tombol reset
  - sort mendukung:
    - `Terbaru`
    - `Terlaris`
    - `Rating Tertinggi`
    - `Harga Terendah`
    - `Harga Tertinggi`
    - `Nama A-Z`
    - `Nama Z-A`
    - `Paling Banyak Dilihat`

- `[vmp_product_gallery]`
  - render galeri produk lengkap
  - support current single product context tanpa isi `id`

- `[vmp_product_reviews]`
  - render ringkasan dan daftar ulasan produk
  - support current single product context tanpa isi `id`
  - atribut:
    - `id`
    - `limit`

- `[vmp_product_seller_card]`
  - render kartu seller pada halaman produk
  - support current single product context tanpa isi `id`

- `[vmp_related_products]`
  - render daftar produk terkait berdasarkan kategori yang sama
  - exclude produk aktif
  - urutan default: `vmp_sold_count DESC`, lalu `date DESC`
  - atribut:
    - `id`
    - `limit`
    - `title`
  - contoh:
    - `[vmp_related_products]`
    - `[vmp_related_products id="123" limit="8"]`

- `[vmp_recently_viewed]`
  - render daftar produk yang baru dilihat
  - source data dari cookie `vmp_recently_viewed`
  - support current product context untuk exclude item aktif
  - atribut:
    - `limit`
    - `exclude_current="true|false"`
    - `title`
  - contoh:
    - `[vmp_recently_viewed]`
    - `[vmp_recently_viewed limit="4" exclude_current="false"]`

- `[vmp_rating]`
  - renderer rating reusable untuk product, seller, atau nilai custom
  - contoh:
    - `[vmp_rating type="product" id="123"]`
    - `[vmp_rating type="seller" id="45" show_count="false"]`
    - `[vmp_rating type="value" value="4.5" count="12" size="14"]`
  - atribut penting:
    - `type="product|seller|value"`
    - `id`
    - `value`
    - `count`
    - `size`
    - `show_value="true|false"`
    - `show_count="true|false"`
    - `class`
    - `stars_class`
    - `value_class`
    - `count_class`

- `[vmp_review_count]`
  - tampilkan jumlah ulasan produk
  - contoh:
    - `[vmp_review_count id="123"]`

- `[vmp_sold_count]`
  - tampilkan jumlah terjual produk
  - contoh:
    - `[vmp_sold_count id="123"]`

- `[vmp_add_to_cart]`
  - render tombol tambah keranjang reusable
  - support current single product context tanpa isi `id`
  - atribut:
    - `id`
    - `text`
    - `class`
    - `style="popup|inline"`
  - perilaku:
    - `popup`
      - default
      - jika produk punya opsi, pilihan muncul lewat modal
    - `inline`
      - jika produk punya opsi, pilihan tampil langsung di atas tombol
      - cocok untuk Beaver Builder / Themer yang ingin layout form lebih terbuka
  - contoh:
    - `[vmp_add_to_cart id="123"]`
    - `[vmp_add_to_cart id="123" style="inline" class="btn btn-dark w-100"]`

- `[vmp_cart]`
  - render icon trigger cart
  - panel offcanvas cart hanya dimuat sekali di footer

- `[vmp_cart_page]`
  - render halaman keranjang penuh

- `[vmp_messages_icon]`, `[vmp_notifications_icon]`, `[vmp_profile_icon]`
  - shortcut icon untuk header / builder
  - cocok dipakai lebih dari satu kali karena markup-nya ringan

## Halaman default

Installer akan membuat page ini jika belum ada:

- `catalog` -> `[vmp_catalog]`
- `cart` -> `[vmp_cart_page]`
- `checkout` -> `[vmp_checkout]`
- `account` -> `[vmp_profile]`
- `order-tracking` -> `[vmp_tracking]`
- `store` -> `[vmp_store_profile]`

## Struktur folder

### Root

- `velocity-marketplace.php`
  - bootstrap plugin
  - define konstanta `VMP_VERSION`, `VMP_PATH`, `VMP_URL`
  - autoload class dari `src`
  - activation / deactivation hook

- `README.md`
  - catatan struktur, shortcode, dan alur plugin
  - wajib ikut diupdate kalau struktur berubah

- `README-FRONTEND.md`
  - peta file JavaScript frontend dan custom admin page
  - titik awal kalau ingin memahami asset `Alpine.js + REST API`

### `assets/`

- `assets/css/frontend.css`
  - styling frontend umum
  - katalog, single product, checkout, store profile, dll

- `assets/css/dashboard.css`
  - styling dashboard account / seller

- `assets/js/frontend-shared.js`
  - helper shared frontend
  - request REST, formatter, helper wilayah, helper cart

- `assets/js/frontend-catalog.js`
  - logic katalog dan advance filter

- `assets/js/frontend-cart.js`
  - logic halaman keranjang

- `assets/js/frontend-checkout.js`
  - logic checkout, shipping, coupon, dan submit order

- `assets/js/frontend-profile.js`
  - logic profil member dan profil toko

- `assets/js/frontend-ui.js`
  - helper UI lintas halaman
  - add to cart/wishlist global
  - galeri produk

- `assets/js/frontend.js`
  - placeholder legacy internal
  - tidak dipakai sebagai asset aktif

- `assets/js/dashboard.js`
  - behavior ringan dashboard
  - focus composer pesan
  - auto scroll thread pesan ke bawah

- `assets/js/media.js`
  - integrasi WordPress media library untuk frontend seller
  - featured image produk
  - gallery produk
  - avatar toko

- `assets/img/no-image.webp`
  - fallback image default

### `src/Core/`

- `Plugin.php`
  - pusat boot plugin
  - load core, API, dan frontend

- `Installer.php`
  - create role
  - create default pages
  - seed default settings

- `PostTypes.php`
  - register taxonomy `vmp_product_cat`
  - register CPT `vmp_product`
  - register CPT `vmp_order`

- `SettingsPage.php`
  - halaman pengaturan plugin di wp-admin
  - currency, payment method, status default, API key ongkir

- `Upgrade.php`
  - version gate upgrade
  - jalankan installer
  - create message table
  - create review table

### `src/Frontend/`

- `Assets.php`
  - enqueue CSS/JS
  - localize `vmpSettings`
  - deteksi shortcode page untuk memutuskan asset mana yang dimuat

- `Shortcode.php`
  - daftar semua shortcode resmi `vmp_*`
  - render halaman page-level
  - render blok produk reusable
  - render helper shortcode rating / review count / sold count

- `Template.php`
  - helper locate dan render template
  - override archive/single produk default plugin

### `src/Modules/Account/`

- `Account.php`
  - login/register integration ke halaman bawaan WordPress
  - assign role tunggal `vmp_member` saat register
  - helper cek member marketplace dan akses jual
  - logout frontend

- `Actions.php`
  - router tipis untuk action frontend berbasis `POST`/`GET`
  - delegasi ke handler per domain

- `Handlers/`
  - `BaseActionHandler.php`
    - helper common redirect, notice, sanitasi dasar
  - `ProductActionHandler.php`
    - simpan/hapus produk member
  - `OrderActionHandler.php`
    - update order toko
    - upload bukti transfer
  - `ProfileActionHandler.php`
    - fallback submit profil via form klasik
  - `WishlistActionHandler.php`
    - hapus item wishlist
  - `NotificationActionHandler.php`
    - aksi notifikasi
  - `MessageActionHandler.php`
    - kirim pesan
  - `ReviewActionHandler.php`
    - submit ulasan dan upload foto review

### `src/Modules/Captcha/`

- `CaptchaBridge.php`
  - bridge ke plugin `velocity-addons`
  - dipakai untuk render / verify captcha

- `CaptchaController.php`
  - REST endpoint captcha jika dibutuhkan oleh frontend

### `src/Modules/Cart/`

- `CartController.php`
  - REST API cart

- `CartRepository.php`
  - persistence cart ke user meta / cookie
  - hydrate item cart

### `src/Modules/Checkout/`

- `CheckoutController.php`
  - REST API checkout
  - validasi payload
  - buat order `vmp_order`
  - validasi kupon
  - validasi COD per kota per toko
  - create invoice Duitku jika metode pembayaran `duitku` dipilih dan gateway tersedia

### `src/Modules/Payment/`

- `DuitkuGateway.php`
  - helper availability gateway Duitku dari plugin `velocity-addons`
  - bridge create invoice ke `Velocity_Addons_Duitku`

- `DuitkuCallbackListener.php`
  - listener action `velocity_duitku_callback`
  - sinkronisasi callback gateway ke status `vmp_order`

### `src/Modules/Coupon/`

- `CouponAdmin.php`
  - metabox dan kolom admin untuk kupon/voucher
  - kupon disimpan sebagai CPT `vmp_coupon`

- `CouponController.php`
  - REST preview kupon saat checkout

- `CouponService.php`
  - cari kupon berdasarkan kode
  - validasi minimal belanja, periode aktif, dan batas penggunaan
  - hitung diskon nominal / persen
  - simpan shipping groups
  - reduce stock
  - notifikasi order

### `src/Modules/Message/`

- `MessageRepository.php`
  - simpan pesan
  - ambil daftar kontak
  - ambil thread per kontak
  - unread per kontak
  - mark thread read
  - validasi bisa chat atau tidak

- `MessageTable.php`
  - create table `wp_vmp_messages`

### `src/Modules/Notification/`

- `NotificationRepository.php`
  - notifikasi internal selain chat
  - order, pembayaran, premium, dll

Catatan:
- pesan baru sengaja tidak lagi masuk notification repository untuk meringankan sistem

### `src/Modules/Review/`

- `ReviewAdmin.php`
  - halaman wp-admin untuk moderasi ulasan
  - setujui, sembunyikan, hapus ulasan

- `ReviewRepository.php`
  - simpan ulasan produk
  - validasi verified purchase / status grup toko selesai
  - agregat rating produk
  - agregat rating seller
  - simpan foto review

- `RatingRenderer.php`
  - helper renderer rating reusable
  - dipakai oleh template PHP dan shortcode
  - support `star-fill`, `star-half`, dan pembulatan rating ke langkah `0.5`

- `ReviewTable.php`
  - create table `wp_vmp_reviews`

- `StarSellerAdmin.php`
  - override manual star seller di edit user wp-admin
  - mode: otomatis / paksa aktif / paksa nonaktif

- `StarSellerService.php`
  - hitung badge star seller dari:
  - order selesai
  - rating rata-rata
  - jumlah ulasan minimum
  - cancel rate

### `src/Modules/Order/`

- `OrderAdmin.php`
  - wp-admin UI untuk `vmp_order`
  - list column
  - metabox detail order
  - pengiriman per seller
  - edit kurir/resi per seller

- `OrderData.php`
  - helper status order
  - helper query order seller
  - helper shipping groups
  - helper seller items

### `src/Modules/Product/`

- `ProductController.php`
  - REST API daftar / detail produk
  - filter produk:
  - nama
  - kategori
  - label
  - rentang harga
  - jenis toko
  - urutan harga / nama / populer

- `ProductData.php`
  - mapper data produk untuk frontend/API
  - fallback image
  - gallery
  - harga aktif
  - opsi produk
  - ringkasan rating HTML siap pakai untuk loop katalog/archive
  - sold count produk

- `ProductFields.php`
  - schema field produk
  - register meta produk
  - render/save field reusable

- `ProductMetaBox.php`
  - admin metabox produk
  - memakai schema dari `ProductFields`

### `src/Modules/Shipping/`

- `ShippingController.php`
  - API lokasi: provinsi, kota, kecamatan
  - hitung ongkir
  - waybill / tracking resi
  - shipping context multi seller
  - integrasi layanan wilayah, ongkir, dan pelacakan pengiriman
  - expose data COD per kota dari toko

### `src/Modules/Wishlist/`

- `WishlistController.php`
  - REST API wishlist

- `WishlistRepository.php`
  - simpan / ambil wishlist user

### `src/Support/`

- `Settings.php`
  - helper baca settings plugin
  - helper URL profile dan store profile
  - courier label

## Struktur template

### `templates/`

- `catalog.php`
  - halaman katalog utama
  - advance filter produk
  - loop produk via REST + Alpine
  - menerima `rating_html` dari mapper produk

- `cart.php`
  - halaman keranjang

- `checkout.php`
  - halaman checkout
  - shipping multi toko
  - prefill dari profil akun
  - kupon / voucher
  - COD per kota per toko

- `profile.php`
  - router dashboard account
  - menentukan tab yang dirender

- `tracking.php`
  - tracking publik via invoice

- `archive-product.php`
  - archive default `vmp_product`
  - filter query string native
  - cocok untuk Beaver Themer

- `single-product.php`
  - single product default
  - sekarang bertindak sebagai komposer layout
  - memanggil block reusable:
    - `product-gallery.php`
    - `product-seller-card.php`
    - `product-reviews.php`
  - deskripsi produk memakai `the_content()` WordPress
  - tombol add to cart / wishlist tetap inline karena masih satu alur dengan opsi produk

- `product-gallery.php`
  - block reusable galeri produk

- `product-seller-card.php`
  - block reusable kartu seller di halaman produk

- `product-reviews.php`
  - block reusable ringkasan + daftar ulasan produk

- `store-profile.php`
  - profil toko publik
  - tombol pesan
  - info toko:
  - alamat pengiriman
  - kota COD
  - ulasan toko
  - terakhir aktif
  - total produk
  - tanggal bergabung
  - daftar produk member
  - ringkasan rating toko dan ulasan toko

### `templates/account/`

- `orders.php`
  - riwayat belanja member
  - detail invoice
  - upload bukti transfer
  - tracking per toko
  - form ulasan produk setelah order selesai
  - upload foto review

- `profile.php`
  - profil akun umum
  - alamat default checkout dan data toko publik

- `wishlist.php`
  - daftar wishlist

- `tracking.php`
  - tracking di dashboard login
  - detail pembayaran
  - upload bukti transfer dari menu tracking

- `messages.php`
  - inbox pesan per kontak/thread

- `notifications.php`
  - daftar notifikasi sistem

### `templates/seller/`

- `home.php`
  - dashboard order toko
  - update status, resi, catatan toko

- `products.php`
  - tambah/edit/hapus produk member

- `profile.php`
  - pengaturan toko
  - avatar, kurir, deskripsi

- `report.php`
  - laporan toko

## Alur sistem ringkas

### Produk

1. Member input produk dari dashboard
2. `Actions.php` simpan post `vmp_product`
3. `ProductFields.php` simpan meta produk
4. `ProductController.php` expose produk ke katalog/frontend

### Cart

1. User klik add to cart
2. `frontend.js` hit REST cart
3. `CartController.php` proses request
4. `CartRepository.php` simpan item
5. jika user login, cart tersimpan di `vmp_cart_items` dan tetap terbawa lintas device
6. jika guest, cart tersimpan di cookie `vmp_guest_cart`

Catatan:
- merge cart guest ke cart user saat login belum ada

### Checkout

1. User buka checkout
2. `frontend.js` load cart + shipping context
3. alamat default diisi dari profil customer
4. user bisa pakai kupon bila valid
5. user pilih service ongkir per seller atau COD jika tersedia di kota tujuan
6. `CheckoutController.php` buat `vmp_order`
7. jika metode pembayaran `duitku` dipilih dan plugin gateway aktif:
   - marketplace membuat invoice Duitku
   - payment URL disimpan ke meta order
   - buyer diarahkan ke halaman pembayaran Duitku

### Order per toko

1. order marketplace bisa berisi lebih dari satu toko
2. status pengiriman sekarang disimpan per `shipping_group`
3. seller hanya mengubah status grup tokonya sendiri
4. buyer melihat ringkasan order per toko, bukan hanya status global
5. buyer bisa klik `Pesanan Diterima` hanya jika grup toko statusnya `Dikirim`
6. saat buyer konfirmasi diterima:
   - status grup toko menjadi `Selesai`
   - timestamp `received_at` disimpan
   - `vmp_sold_count` produk di grup toko itu ditambah sekali

### Pesan

1. User masuk dari tombol pesan produk/order/profil toko
2. tab `Pesan` buka thread berdasarkan `message_to`
3. `MessageRepository.php` ambil thread
4. unread thread di-clear saat thread dibuka
5. kirim pesan diproses oleh `Actions.php`

### Review dan Star Seller

1. Member hanya bisa memberi ulasan dari order miliknya yang grup toko produknya sudah `completed`
2. Satu produk hanya punya satu ulasan per user per order
3. Ulasan masuk ke table `wp_vmp_reviews`
4. Setelah ulasan masuk, meta agregat produk diperbarui:
   - `vmp_review_count`
   - `vmp_rating_average`
5. Produk yang sudah selesai dan belum direview akan menampilkan tombol `Berikan Penilaian`
6. Setelah ulasan tersimpan, form review disembunyikan dan diganti ringkasan ulasan tersimpan
7. Ulasan bisa menyimpan sampai 3 foto review
8. Setelah ulasan masuk atau status order berubah, `StarSellerService` hitung ulang badge seller
9. Admin bisa override hasil star seller tanpa mematikan hitung otomatis

## Catatan maintenance

- Jangan tambah alias shortcode baru tanpa alasan kuat.
- Kalau ada file baru atau modul baru, update README ini.
- Kalau ada rename folder/file, update bagian struktur di README.
- Kalau ada perubahan alur checkout, shipping, message, atau dashboard, update bagian `Alur sistem ringkas`.
- Kalau storage berubah, misalnya order pindah ke custom table, update bagian `Standar saat ini`.

## Catatan perubahan yang perlu diperhatikan developer berikutnya

- Prefix shortcode final saat ini: `vmp_*`
- Role marketplace final saat ini: `vmp_member`
- Pesan tidak lagi membuat notifikasi internal
- Tracking publik tersedia lewat page tracking
- Message memakai custom table, bukan CPT
- Order title baru tidak lagi memakai prefix kata `Order`
- Status order buyer sekarang dibaca per toko / per `shipping_group`
- Buyer confirm `Pesanan Diterima` menambah `vmp_sold_count` produk terkait
- Renderer rating sekarang dipusatkan di `RatingRenderer`
- Shortcode rating/count baru tersedia untuk kebutuhan Beaver Builder / Themer
- Single product mulai dipecah ke block reusable agar native template dan Beaver Themer bisa berbagi renderer yang sama
- Deskripsi produk tidak lagi punya shortcode khusus; default theme memakai `the_content()` dan Beaver Builder bisa memakai shortcode konten bawaan
- Meta profil user sekarang disatukan:
  - semua member membaca key yang sama
  - hanya `vmp_couriers` yang khusus toko
- Meta WordPress inti seperti `first_name` dan `display_name` tetap dipakai apa adanya, tidak diprefix ulang
