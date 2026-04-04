# Shared Commerce Contract

Dokumen ini adalah draft keputusan arsitektur untuk menyatukan pondasi `vd-store` dan `velocity-marketplace`.

Tujuan dokumen ini bukan menjelaskan implementasi detail baris per baris, tetapi menetapkan:
- apa yang akan menjadi contract bersama
- apa yang dipindah ke `vd-store`
- apa yang tetap di `velocity-marketplace`
- urutan refactor supaya sistem yang berjalan tidak rusak

## 1. Keputusan Arsitektur

Keputusan target yang dipakai ke depan:
- `vd-store` menjadi **commerce core**
- `velocity-marketplace` menjadi **marketplace addon**

Model ini setara dengan pola:
- WooCommerce = core e-commerce
- Dokan/WCFM/MVX = addon marketplace

Artinya:
- klien paket toko online cukup memakai `vd-store`
- klien yang upgrade ke marketplace cukup menambah `velocity-marketplace`
- data katalog dasar tidak boleh dipaksa migrasi berat
- shortcode yang sudah terpasang di Beaver Builder / Themer tidak boleh dipaksa ganti

Catatan naming:
- plugin core secara path/slug sekarang adalah `vd-store`
- namespace internal saat ini masih banyak memakai `WpStore\...`
- namespace internal tidak perlu diubah pada sprint awal agar risiko refactor tetap rendah

## 2. Prinsip Non-Negotiable

Prinsip yang harus dianggap tetap:
- contract canonical mengikuti pondasi `vd-store`
- `velocity-marketplace` adalah superset, bukan sistem terpisah
- domain produk harus benar-benar shared
- public shortcode contract harus stabil
- addon marketplace tidak boleh copy-paste fondasi commerce inti
- perubahan besar harus dilakukan bertahap, bukan cutover brutal

## 3. Boundary Core vs Addon

### 3.1 Yang Harus Menjadi Milik `vd-store`

`vd-store` harus menjadi pemilik resmi untuk domain berikut:
- CPT produk, order, coupon
- taxonomy kategori produk
- schema meta inti produk
- query produk dasar
- mapper data produk
- product admin form dasar
- card produk dasar
- thumbnail, price, add to cart, wishlist button
- cart dasar
- wishlist dasar
- checkout dasar
- order dasar
- coupon dasar
- payment gateway abstraction
- shipping basic abstraction single-store
- profile customer dasar
- tracking dasar
- shortcode publik dasar

### 3.2 Yang Tetap Menjadi Milik `velocity-marketplace`

`velocity-marketplace` hanya memegang domain marketplace-specific:
- seller/store profile
- seller dashboard
- multi-seller cart grouping
- shipping group per seller
- order status per toko
- COD per seller
- review seller
- star seller
- message buyer-seller
- payout / settlement seller
- last active seller
- public store page
- metrik seller

### 3.3 Yang Tidak Perlu Dipaksa Identik

Domain berikut tidak harus identik penuh antara single-store dan marketplace:
- order internal marketplace
- shipping grouping per seller
- payout / settlement
- agregat seller
- message / seller communication

Yang penting:
- produk tetap terbaca
- katalog tetap hidup
- shortcode publik dasar tetap hidup

## 4. Canonical Shared Contract

## 4.1 CPT

Canonical CPT yang harus dipakai bersama:
- produk: `store_product`
- order: `store_order`
- coupon: `store_coupon`

Target:
- `vd-store` memakai CPT ini sebagai pemilik resmi
- `velocity-marketplace` tidak lagi punya CPT produk/order/coupon versi sendiri sebagai source utama

## 4.2 Taxonomy

Canonical taxonomy yang harus dipakai bersama:
- kategori produk: `store_product_cat`

Catatan:
- taxonomy kategori produk tidak boleh bercabang lagi menjadi `vmp_product_cat` untuk source utama

## 4.3 Meta Produk Inti

Meta berikut harus dianggap shared product schema:
- `_store_product_type`
- `_store_price`
- `_store_sale_price`
- `_store_flashsale_until`
- `_store_digital_file`
- `_store_sku`
- `_store_stock`
- `_store_min_order`
- `_store_weight_kg`
- `_store_label`
- `_store_gallery_ids`
- `_store_option_name`
- `_store_options`
- `_store_option2_name`
- `_store_advanced_options`

Aturan format:
- `_store_gallery_ids` menyimpan banyak attachment ID
- `_store_options` menyimpan daftar pilihan varian dasar
- `_store_advanced_options` menyimpan array terstruktur, bukan textarea bebas
- format textarea lama tidak boleh dijadikan contract canonical perusahaan

## 4.4 Public Shortcode API

Shortcode berikut harus dianggap canonical public API dan tetap valid:
- `wp_store_shop`
- `wp_store_single`
- `wp_store_related`
- `wp_store_thumbnail`
- `wp_store_price`
- `wp_store_add_to_cart`
- `wp_store_detail`
- `wp_store_cart`
- `wp_store_cart_page`
- `store_cart`
- `wp_store_checkout`
- `store_checkout`
- `wp_store_thanks`
- `store_thanks`
- `wp_store_tracking`
- `store_tracking`
- `wp_store_wishlist`
- `wp_store_add_to_wishlist`
- `wp_store_link_profile`
- `wp_store_products_carousel`
- `wp_store_shipping_checker`
- `wp_store_catalog`
- `wp_store_filters`
- `wp_store_shop_with_filters`
- `wp_store_captcha`
- `wp-store-captcha`
- `store_customer_profile`
- `wp_store_profile`

Catatan:
- shortcode `vmp_*` boleh tetap ada
- tetapi `vmp_*` bukan contract canonical yang wajib dipakai theme/builder
- target akhirnya adalah shortcode dasar dipelihara oleh `vd-store`

## 4.5 Fungsi Global Publik

Fungsi berikut harus tetap tersedia sebagai public API:
- `wps_icon()`
- `wps_label_badge_html()`
- `wps_discount_badge_html()`

Implementasi internal boleh berubah, tetapi signature dan perilaku wajarnya harus tetap kompatibel.

## 4.6 Custom Database

### Shared / Layak Disamakan

Yang layak disamakan jika ingin upgrade lintas plugin mulus:
- tabel cart
- tabel wishlist

Rekomendasi:
- `vd-store` menjadi owner untuk storage cart dan wishlist
- `velocity-marketplace` menambah grouping seller di atas service core, bukan membuat storage cart baru dari nol

### Marketplace-Only

Yang tetap boleh menjadi storage khusus addon:
- shipping groups per seller
- seller review / star seller aggregate
- message buyer-seller
- payout / settlement
- store profile tambahan

## 5. Peta Perubahan Konkret

Bagian ini menjawab pertanyaan: file mana yang dipindah ke `vd-store`, file mana yang dipecah dulu, dan file mana yang tetap di addon.

## 5.1 Dipindah ke `vd-store`

Domain produk dan frontend dasar dari `velocity-marketplace` harus berakhir di `vd-store`.

Sumber saat ini di `velocity-marketplace`:
- `src/Core/PostTypes.php`
- `src/Modules/Product/ProductMeta.php`
- `src/Modules/Product/ProductData.php`
- `src/Modules/Product/ProductQuery.php`
- `src/Modules/Review/RatingRenderer.php`
- `src/Frontend/Template.php`
- `src/Frontend/Assets.php`

Target modul di `vd-store`:
- `src/Core/PostTypes.php`
- `src/Domain/Product/ProductMeta.php`
- `src/Domain/Product/ProductData.php`
- `src/Domain/Product/ProductQuery.php`
- `src/Frontend/RatingRenderer.php`
- `src/Frontend/Template.php`
- `src/Frontend/Assets.php`

Catatan:
- ini bukan sekadar copy file
- owner logic-nya harus berpindah ke `vd-store`
- setelah consumer di addon stabil, versi lama di `velocity-marketplace` baru dipangkas

## 5.2 Dipecah Dulu, Lalu Dipindah Sebagian

File berikut tidak boleh dipindah mentah karena isinya campur core dan marketplace:

### Shortcode

Sumber:
- `src/Frontend/Shortcode.php`

Yang pindah ke `vd-store`:
- shortcode katalog
- shortcode produk
- shortcode thumbnail/price
- add to cart dasar
- wishlist button dasar
- cart page
- checkout dasar
- tracking dasar
- profile customer dasar

Yang tetap di addon:
- shortcode seller/store specific
- shortcode marketplace-specific yang benar-benar bergantung seller layer

### Product Fields dan Product Metabox

Sumber:
- `src/Modules/Product/ProductFields.php`
- `src/Modules/Product/ProductMetaBox.php`

Yang pindah ke `vd-store`:
- schema produk canonical
- normalizer field
- admin field dasar

Yang tetap di addon:
- field marketplace-only seperti premium request jika memang bukan domain core

### Cart

Sumber:
- `src/Modules/Cart/CartRepository.php`
- `src/Modules/Cart/CartController.php`

Yang pindah ke `vd-store`:
- service cart dasar
- storage cart canonical
- wishlist canonical jika disepakati

Yang tetap di addon:
- grouping item per seller
- shipping context per seller

### Checkout dan Order

Sumber:
- `src/Modules/Checkout/CheckoutController.php`
- sebagian `src/Modules/Order/*`

Yang pindah ke `vd-store`:
- order service dasar
- checkout single-store dasar
- payment abstraction dasar

Yang tetap di addon:
- split order per toko
- shipping groups per seller
- status order per toko

## 5.3 Tetap di `velocity-marketplace`

File dan domain berikut tetap menjadi milik addon:
- `src/Modules/Shipping/*`
- `src/Modules/Message/*`
- `src/Modules/Notification/*`
- `src/Modules/Profile/*`
- `src/Modules/Review/*` yang seller-aware
- `src/Modules/Account/*` bagian seller dashboard
- template store profile
- template seller dashboard
- template order per toko

## 6. Hook Core yang Wajib Disiapkan di `vd-store`

Agar addon tidak copy-paste core, `vd-store` harus menyediakan hook dan extension point yang cukup.

Hook minimum yang perlu ada:
- `vd_store_product_payload`
- `vd_store_product_query_args`
- `vd_store_cart_item_payload`
- `vd_store_cart_items_grouped`
- `vd_store_before_create_order`
- `vd_store_after_create_order`
- `vd_store_order_items_payload`
- `vd_store_checkout_shipping_context`
- `vd_store_payment_completed`
- `vd_store_profile_tabs`
- `vd_store_profile_panels`

Tanpa hook seperti ini, addon akan cenderung membuat flow sendiri dan boundary core/addon akan gagal.

## 7. Sprint Plan Refactor

Refactor harus dijalankan bertahap. Jangan memindahkan semua domain sekaligus.

## Sprint 0 - Freeze Contract

Tujuan:
- menyepakati contract canonical perusahaan

Pekerjaan:
- finalkan dokumen ini
- salin versi final ke repo `vd-store`
- sepakati naming:
  - CPT
  - taxonomy
  - meta produk
  - shortcode publik
  - boundary core vs addon

Output:
- tidak ada lagi perubahan nama contract tanpa keputusan tim

## Sprint 1 - Product Core

Tujuan:
- seluruh domain produk canonical pindah ke `vd-store`

Pekerjaan:
- buat `ProductMeta`, `ProductData`, `ProductQuery` canonical di `vd-store`
- sinkronkan metabox/admin produk di `vd-store`
- `velocity-marketplace` mulai consume helper produk dari `vd-store`

Output:
- dua plugin membaca produk dari satu sumber

## Sprint 2 - Public Product API

Tujuan:
- shortcode produk dasar resmi dimiliki `vd-store`

Pekerjaan:
- pindahkan logic shortcode katalog/produk ke `vd-store`
- pindahkan template routing archive/single produk ke `vd-store`
- `velocity-marketplace` berhenti menjadi owner untuk domain shortcode dasar

Output:
- builder/theme cukup bicara ke public API core

## Sprint 3 - Cart & Wishlist Core

Tujuan:
- cart dan wishlist dasar resmi dimiliki `vd-store`

Pekerjaan:
- buat service cart canonical di `vd-store`
- standarkan storage cart dan wishlist
- `velocity-marketplace` hanya menambah grouping seller di atas service core

Output:
- jalur upgrade toko online ke marketplace lebih mulus

## Sprint 4 - Order & Checkout Core

Tujuan:
- checkout dasar dan order dasar resmi dimiliki `vd-store`

Pekerjaan:
- buat `OrderService` di `vd-store`
- refactor checkout controller dasar
- siapkan hook untuk seller layer
- payment gateway dasar pindah ke core

Output:
- core e-commerce lengkap dan bisa berdiri sendiri

## Sprint 5 - Marketplace Addon Refactor

Tujuan:
- `velocity-marketplace` resmi menjadi addon, bukan core kedua

Pekerjaan:
- plugin addon wajib mengecek `vd-store` aktif
- hapus ownership domain dasar dari addon
- fokuskan addon hanya pada seller layer dan multi-seller flow

Output:
- boundary core vs addon menjadi bersih

## Sprint 6 - Cleanup & Cutover

Tujuan:
- membersihkan shim transisi dan menyiapkan release stabil

Pekerjaan:
- kurangi bridge compat yang tidak perlu
- rapikan dokumentasi
- siapkan migrator ringan jika masih ada data lama
- lakukan regression test:
  - `vd-store` saja
  - `vd-store + velocity-marketplace`

Output:
- fondasi stabil untuk dua paket produk

## 8. Risiko dan Mitigasi

### Risiko 1 - Refactor terlalu besar dalam satu langkah

Dampak:
- produk, cart, atau checkout bisa rusak sekaligus

Mitigasi:
- refactor per sprint
- selalu dual-read sebelum single-write
- jangan sentuh order/cart sebelum product core stabil

### Risiko 2 - Addon tetap copy core

Dampak:
- boundary gagal
- bug diperbaiki dua kali

Mitigasi:
- siapkan hook core yang cukup
- addon harus consume service core, bukan copy logic

### Risiko 3 - Theme/Builder bergantung pada shortcode yang berubah

Dampak:
- template Beaver Builder / Themer pecah

Mitigasi:
- `wp_store_*` tetap canonical public API
- jangan ganti nama shortcode publik seenaknya

### Risiko 4 - Order lama sulit disatukan

Dampak:
- migrasi order jadi mahal dan rawan salah

Mitigasi:
- fokus shared contract pada domain produk dan public API
- order lama tidak perlu dipaksa identik penuh

## 9. Keputusan yang Perlu Disetujui Tim

Poin berikut perlu diputuskan tegas sebelum sprint implementasi berjalan:

1. Apakah `vd-store` resmi menjadi commerce core perusahaan?
2. Apakah `velocity-marketplace` resmi menjadi addon yang bergantung pada `vd-store`?
3. Apakah canonical CPT/taxonomy/meta produk mengikuti schema `store_*`?
4. Apakah shortcode `wp_store_*` ditetapkan sebagai public API resmi jangka panjang?
5. Apakah cart dan wishlist juga akan disamakan di level storage?
6. Apakah namespace internal `WpStore\...` tetap dipertahankan sementara pada fase awal?
7. Apakah order lama dianggap legacy domain yang tidak perlu dipaksa identik penuh?

## 10. Status Repo Saat Ini

Dokumen ini perlu dibaca bersama fakta implementasi saat ini:
- sebagian refactor canonical `store_*` sudah mulai diterapkan di `velocity-marketplace`
- bridge compat untuk `wp_store_*` sudah mulai ada
- posisi saat ini masih transisi
- target akhir belum selesai

Artinya:
- repo saat ini belum final
- dokumen ini adalah acuan untuk menata ulang refactor berikutnya agar tidak menyimpang

## 11. Kesimpulan

Kesimpulan arsitektur yang diusulkan:
- `vd-store` menjadi fondasi commerce tunggal
- `velocity-marketplace` menjadi addon marketplace di atas fondasi itu
- contract bersama difokuskan pada domain produk dan public API dasar
- seller layer, multi-seller order, dan shipping groups tetap menjadi concern addon

Jika tim menyetujui dokumen ini, implementasi harus mengikuti sprint plan di atas dan menghindari refactor brutal di luar boundary yang sudah disepakati.
