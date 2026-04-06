# Shared Commerce Contract

Dokumen ini adalah keputusan arsitektur untuk menyatukan pondasi `vd-store` dan `velocity-marketplace`.

Tujuan dokumen ini bukan menjelaskan implementasi detail baris per baris, tetapi menetapkan:
- apa yang akan menjadi contract bersama
- apa yang dipindah ke `vd-store`
- apa yang tetap di `velocity-marketplace`
- urutan refactor supaya sistem yang berjalan tidak rusak

Status runtime saat ini:
- `vd-store` adalah core wajib
- `velocity-marketplace` adalah addon wajib-bergantung pada `vd-store`
- jika `vd-store` tidak aktif, `velocity-marketplace` tidak boleh boot
- addon tidak boleh lagi mendaftarkan route, shortcode, atau alias compat sebagai pengganti core
- bridge compat runtime lama sudah dipangkas dari jalur aktif addon
- addon tidak lagi menyimpan jalur fallback `class_exists()` untuk order/payment core di runtime normal
- gateway dasar seperti Duitku sekarang dipanggil langsung lewat layer payment milik `vd-store`

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
- addon marketplace harus gagal-boot dengan jelas jika core tidak aktif

Catatan naming:
- plugin core secara path/slug sekarang adalah `vd-store`
- namespace internal saat ini masih banyak memakai `WpStore\...`
- namespace internal tidak perlu diubah pada sprint awal agar risiko refactor tetap rendah

## 2. Prinsip Non-Negotiable

Prinsip yang harus dianggap tetap:
- contract canonical mengikuti pondasi `vd-store`
- `velocity-marketplace` adalah superset, bukan sistem terpisah
- domain produk harus benar-benar shared
- schema form produk frontend boleh dipakai addon, tetapi admin metabox `store_product` harus mengikuti `vd-store` lewat `ProductMetaBoxes` / `ProductSchema`, bukan box custom addon
- public shortcode contract harus stabil
- addon marketplace tidak boleh copy-paste fondasi commerce inti
- addon marketplace tidak boleh hidup sendiri sebagai core bayangan
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

## 4.3.a Meta Item Order Canonical

Untuk item order, canonical yang harus dipakai adalah:
- `_store_order_items`

Aturan:
- `velocity-marketplace` tidak lagi menulis `vmp_items` untuk order baru
- addon tidak lagi membaca `vmp_items` sebagai fallback runtime
- item order marketplace harus selalu dibaca dari `_store_order_items`
- UI addon harus membaca item order lewat helper `OrderData::get_items()`, bukan langsung dari `vmp_items`

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
- `velocity-marketplace` tidak lagi boleh mendaftarkan alias compat `wp_store_*` jika `vd-store` tidak aktif
- legacy bridge hanya boleh dipertahankan sebagai kode transisi internal, bukan jalur runtime final

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

Keputusan implementasi saat ini:
- raw cart canonical tetap disimpan di kolom `cart` pada tabel `store_carts`
- `shipping_data` tetap dipakai untuk snapshot shipping/checkout dasar milik core
- snapshot marketplace per seller **tidak** ditumpuk ke `shipping_data`
- snapshot marketplace disimpan terpisah di kolom baru `marketplace_snapshot`

Alasan memilih kolom baru:
- `shipping_data` adalah domain checkout/shipping dasar, bukan domain seller grouping
- cart marketplace butuh snapshot yang berbeda tujuan dan berbeda lifecycle
- memisahkan kolom membuat boundary core vs addon lebih jelas
- debugging dan migrasi jauh lebih mudah daripada satu kolom menanggung dua arti

Schema snapshot marketplace yang dipakai:
- `schema_version`
- `generated_at`
- `source`
- `cart_hash`
- `group_count`
- `groups`

Isi setiap `group` minimal:
- `seller_id`
- `subtotal`
- `items_count`
- `weight_kg`
- `weight_grams`
- `item_keys`

Catatan penting:
- `seller_name` dan `seller_url` **tidak disimpan** di snapshot
- data display seller harus diambil runtime dari `seller_id`
- detail item penuh **tidak disimpan ulang** di snapshot
- snapshot hanya menyimpan referensi item (`item_keys`) dan hasil komputasi agregat per seller
- tujuan snapshot adalah mempercepat flow marketplace, bukan membuat duplikasi cart kedua

Aturan lifecycle snapshot:
- snapshot dibangun dari raw cart canonical
- snapshot dipakai oleh addon untuk cart marketplace dan flow checkout multi-seller
- setiap kali raw cart berubah, snapshot dianggap stale dan harus dihapus
- setiap kali raw cart berubah, `shipping_data` core juga dianggap stale dan harus dihapus
- addon boleh membangun ulang snapshot saat cart dibaca
- snapshot dianggap valid hanya jika `cart_hash` cocok dengan raw cart saat ini
- source of truth tetap raw cart canonical, bukan snapshot marketplace
- checkout marketplace harus memakai `item_keys` dari snapshot untuk membentuk shipping group
- checkout marketplace tidak perlu regroup seller penuh dari nol jika snapshot masih valid

Alasan desain final ini:
- jika snapshot hanya menyalin ulang isi `cart`, maka kolom `cart` saja sebenarnya sudah cukup
- snapshot hanya layak ada jika menyimpan hasil turunan yang berguna, misalnya agregat subtotal/berat per seller dan referensi item per seller
- data display seperti nama toko atau URL toko lebih aman dihitung runtime agar tidak cepat basi

### Marketplace-Only

Yang tetap boleh menjadi storage khusus addon:
- shipping groups per seller
- seller review / star seller aggregate
- message buyer-seller
- payout / settlement
- store profile tambahan

## 4.7 Dependency Runtime Final

Keputusan final:
- `velocity-marketplace` wajib mendeteksi `vd-store` saat `plugins_loaded`
- jika `vd-store` tidak aktif:
  - addon tidak boleh memanggil `Plugin::run()`
  - addon tidak boleh mendaftarkan REST route
  - addon tidak boleh mendaftarkan shortcode
  - addon tidak boleh memuat alias fungsi compat sebagai pengganti core
  - admin harus menerima notice yang jelas

Aturan aktivasi:
- aktivasi `velocity-marketplace` harus gagal jika `vd-store` tidak aktif
- plugin harus menonaktifkan dirinya sendiri dan menampilkan pesan dependency yang jelas

Alasan:
- ini menutup ambiguitas arsitektur
- ini mencegah `velocity-marketplace` kembali bertindak sebagai core kedua
- ini membuat boundary core vs addon benar-benar ditegakkan, bukan hanya didokumentasikan

## 4.8 Ownership Settings

Keputusan final untuk pengaturan:
- `vd-store` adalah source of truth untuk pengaturan commerce inti
- `velocity-marketplace` hanya menyimpan pengaturan yang benar-benar marketplace-specific

Pengaturan yang harus dibaca dari `vd-store`:
- `currency_symbol`
- `payment_methods`
- `store_bank_accounts`
- `rajaongkir_api_key`
- halaman inti:
  - `page_catalog`
  - `page_cart`
  - `page_checkout`
  - `page_profile`
  - `page_tracking`

Pengaturan yang tetap boleh hidup di `velocity-marketplace`:
- `seller_product_status`
- `email_admin_recipient`
- `email_template_admin_order`
- `email_template_customer_order`
- `email_template_status_update`

Aturan:
- addon tidak boleh lagi punya form admin sendiri untuk mata uang, metode pembayaran, rekening bank, atau API key pengiriman
- halaman settings addon harus memberi arah jelas ke halaman settings `vd-store` untuk pengaturan inti
- karena plugin ini diposisikan sebagai plugin baru, tidak perlu jalur cleanup khusus untuk key lama `vmp_settings`

## 5. Status Implementasi Saat Ini

### 5.1 Yang Sudah Menjadi Milik `vd-store`

Domain berikut sudah diposisikan sebagai core:
- schema produk canonical `_store_*`
- meta reader/writer produk
- mapper data produk
- query produk dasar
- related products dasar
- recently viewed dasar
- shortcode publik dasar `wp_store_*`
- cart foundation
- wishlist foundation
- order foundation
- checkout foundation dasar
- payment abstraction dasar
- gateway Duitku dasar
- callback payment dasar
- ownership setting inti:
  - simbol mata uang
  - metode pembayaran
  - rekening bank
  - API key pengiriman
  - halaman inti
- layar admin order utama
- kolom list order utama
- item order dasar di admin
- ringkasan customer/payment/order dasar di admin

Implikasi:
- `velocity-marketplace` tidak lagi boleh menjadi owner produk/cart/order dasar
- addon hanya mengonsumsi service atau data dari core lalu menambahkan lapisan marketplace

### 5.2 Yang Tetap Menjadi Milik `velocity-marketplace`

Domain berikut tetap addon-only:
- seller/store profile
- seller dashboard
- seller metrics
- store page publik
- shipping group per seller
- split order context per seller
- COD per seller
- review seller
- star seller
- message buyer-seller
- payout / settlement
- agregat marketplace seperti `vmp_sold_count`, `vmp_rating_average`, `vmp_review_count`
- metabox fulfillment marketplace di admin order
- shipping group per seller di admin order
- seller receipt / seller note / seller status di admin order

### 5.3 Status Runtime Saat Ini

Boundary utama yang sekarang sudah aktif di runtime:
- `VD Marketplace` wajib bergantung pada `VD Store`
- addon tidak lagi mendaftarkan CPT/taxonomy produk, order, dan kupon
- addon tidak lagi menjalankan bridge `wp-store` lama
- addon tidak lagi membaca `vmp_product`, `vmp_order`, `vmp_coupon`, `vmp_product_cat`
- cart dan wishlist addon sekarang langsung memakai service core `vd-store`
- query/template produk dan order sekarang memakai canonical `store_*`

Bagian yang masih tinggal audit lanjutan:
- audit end-to-end `cart -> shipping -> checkout -> order`
- rapikan dokumen README agar tidak lagi menyebut jalur lama

## 5. Peta Perubahan Konkret

Bagian ini menjawab pertanyaan: file mana yang dipindah ke `vd-store`, file mana yang dipecah dulu, dan file mana yang tetap di addon.

## 5.1 Dipindah ke `vd-store`

Domain produk dan frontend dasar dari `velocity-marketplace` harus berakhir di `vd-store`.

Sumber saat ini di `velocity-marketplace`:
- `src/Modules/Product/ProductMeta.php`
- `src/Modules/Product/ProductData.php`
- `src/Modules/Product/ProductQuery.php`
- `src/Modules/Review/RatingRenderer.php`
- `src/Frontend/Template.php`
- `src/Frontend/Assets.php`

Target modul di `vd-store`:
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
- schema field produk dan admin metabox `store_product` sekarang ada di `vd-store`

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

## 8. Penjelasan Sederhana Perubahan yang Sudah Terjadi

Bagian ini sengaja ditulis dengan bahasa sederhana agar tim non-implementer tetap bisa mengikuti arah perubahan.

### 8.1 Gambaran Besar

Sebelumnya:
- `vd-store` dan `velocity-marketplace` sama-sama punya pondasi e-commerce sendiri
- produk, cart, checkout, dan order masih banyak berdiri di dua tempat
- akibatnya boundary tidak rapi dan upgrade dari toko online ke marketplace berisiko mahal

Sekarang arah yang dipakai adalah:
- `vd-store` menjadi pondasi dasar
- `VD Marketplace` memakai pondasi itu
- `VD Marketplace` hanya menambah fitur seller, split toko, dan pengiriman per seller

Dengan kata lain:
- `vd-store` = mesin utama
- `VD Marketplace` = addon yang membuat mesin itu bisa jadi marketplace

### 8.2 Apa yang Sudah Dipindah ke `vd-store`

#### Produk

Produk sekarang mulai dipusatkan ke `vd-store`.

Artinya:
- schema produk dasar sudah punya tempat resmi di core
- pembacaan harga, stok, berat, galeri, opsi, dan label tidak lagi menyebar liar di banyak file
- query produk dasar juga mulai dibaca dari satu sumber

Secara praktis:
- `vd-store` sekarang menjadi pemilik utama untuk data produk dasar
- `VD Marketplace` membaca data produk dari core, lalu menambah data marketplace seperti seller, rating, sold count, dan premium

#### Related Products

Logika dasar "produk terkait" sekarang sudah punya helper di `vd-store`.

Artinya:
- core menentukan logika dasar related products
- addon marketplace tinggal memakai hasil dari core lalu boleh menambah ranking marketplace jika perlu

#### Recently Viewed

Riwayat "baru dilihat" juga sudah dipindah dasar logikanya ke `vd-store`.

Artinya:
- core mengurus tracking dan daftar ID produk yang pernah dilihat
- addon marketplace tinggal memetakan tampilannya dengan data seller/review marketplace

#### Cart

Storage cart dasar sekarang sudah punya service resmi di `vd-store`.

Artinya:
- penyimpanan cart tidak lagi harus dimiliki addon marketplace
- `VD Marketplace` sekarang bisa memakai cart dasar dari core
- addon hanya menambah pengelompokan item per seller saat cart itu dipakai untuk flow marketplace

#### Wishlist

Wishlist dasar juga sudah dipindah ke service core.

Artinya:
- wishlist dasar sekarang dimiliki `vd-store`
- addon marketplace tidak perlu punya fondasi wishlist terpisah lagi

### 8.3 Apa yang Sudah Berubah di `VD Marketplace`

`VD Marketplace` sekarang mulai diposisikan ulang menjadi addon sungguhan.

Perubahan pentingnya:
- addon tidak lagi menjadi pemilik utama shortcode dasar `wp_store_*`
- addon mulai membaca data produk dasar dari `vd-store`
- addon mulai memakai cart dan wishlist foundation dari core
- addon masih menulis metadata `vmp_*`, tetapi itu sekarang sifatnya lapisan marketplace, bukan fondasi utama

Artinya:
- UI dan admin marketplace lama masih aman
- tapi mesin dasarnya perlahan dipindah ke core

### 8.4 Apa yang Sudah Terjadi di Checkout dan Order

Ini bagian paling sensitif, jadi perlu dibaca hati-hati.

Sekarang:
- `vd-store` sudah punya `OrderService`
- checkout dasar di core mulai memakai service itu
- checkout di addon marketplace juga mulai memakai service order dari core untuk membuat order dasar

Lalu setelah order dasar dibuat oleh core:
- addon marketplace menambahkan metadata `vmp_*`
- addon menyimpan `shipping groups`
- addon menyimpan split item per seller
- addon tetap mengurus notifikasi seller, email marketplace, dan status per toko

Jadi modelnya sekarang:
- core membuat order dasar
- addon memperkaya order itu menjadi order marketplace

Ini adalah perubahan yang paling penting dalam Sprint 3 dan awal Sprint 4.

### 8.4.a Apa yang Sudah Terjadi di Payment

Layer payment dasar sekarang juga mulai dipindah ke `vd-store`.

Artinya:
- `vd-store` sekarang punya registry metode pembayaran
- `vd-store` sekarang punya service payment dasar
- gateway Duitku dasar sekarang dipanggil dari core
- callback Duitku dasar sekarang diterima dan diproses oleh core

Setelah core menerima callback payment:
- core memperbarui data pembayaran order dasar
- core memicu event payment seperti:
  - `wp_store_payment_callback_received`
  - `wp_store_payment_completed`
  - `wp_store_payment_failed`
- addon marketplace tinggal subscribe ke event itu untuk:
  - sinkronisasi `vmp_gateway_*`
  - ubah `vmp_status`
  - update status grup pengiriman per seller
  - kirim notifikasi dan email marketplace

Jadi sekarang:
- core = owner payment dasar
- addon = owner efek marketplace setelah payment berubah

### 8.5 Kenapa Kita Masih Menyimpan `vmp_*`

Karena kita belum boleh memutus UI/admin marketplace yang sudah jalan.

Jadi untuk fase transisi:
- data dasar order sudah mulai ditulis ke meta `_store_*`
- data marketplace-specific tetap ditulis ke `vmp_*`

Tujuannya:
- admin/order page lama di marketplace tetap hidup
- core tetap mulai menjadi source of truth untuk order dasar

Ini bukan target akhir permanen, tapi langkah transisi yang aman.

### 8.4.b Apa yang Sudah Terjadi di Admin Order

Boundary admin order sekarang mulai ditegakkan lebih tegas.

Sekarang:
- `vd-store` tetap menjadi owner layar admin utama untuk `store_order`
- kolom list order utama tetap milik core
- ringkasan customer, payment, total, dan item order dasar tetap milik core
- `VD Marketplace` tidak lagi mengambil alih list table order
- `VD Marketplace` tidak lagi merender ulang ringkasan customer/payment/item order dasar

Addon sekarang hanya menambahkan metabox khusus marketplace, yaitu:
- shipping group per seller
- status seller per group
- resi aktual per seller
- catatan seller
- metadata fulfillment marketplace

Implikasinya:
- admin tidak lagi melihat dua layar order utama yang saling tumpang tindih
- core menjadi owner data dasar
- addon hanya owner operasi multi-seller

Sinkronisasi status:
- jika status core `_store_order_status` berubah dari admin core, addon akan ikut menyinkronkan `vmp_status`
- jika admin mengubah status seller/group dari metabox marketplace, addon akan menghitung ringkasan marketplace lalu menyinkronkannya kembali ke status core

### 8.6 Apa yang Belum Selesai

Walaupun arahnya sudah benar, pekerjaan belum selesai penuh.

Yang masih perlu dirapikan:
- admin order core vs admin order marketplace harus dibersihkan boundary-nya
- checkout/order flow masih perlu audit end-to-end di browser
- beberapa status order marketplace masih perlu sinkronisasi lebih ketat dengan status core
- dokumentasi boundary perlu terus dijaga agar tim tidak kembali menulis duplikasi

### 8.7 Kesimpulan Praktis untuk Tim

Kalau dijelaskan sangat sederhana:

- dulu `VD Marketplace` masih terasa seperti sistem sendiri
- sekarang `VD Marketplace` sedang diubah menjadi addon di atas `vd-store`
- produk, cart, wishlist, dan order dasar sudah mulai dipindah ke core
- seller split, shipping group, status per toko, dan fitur seller tetap ada di addon

Jadi hasil akhirnya yang kita kejar adalah:
- klien toko online cukup pakai `vd-store`
- klien marketplace tinggal aktifkan `VD Marketplace`
- data katalog, cart dasar, dan order dasar tidak perlu dibangun ulang dari nol
## 9. Risiko dan Mitigasi

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

## 10. Keputusan yang Perlu Disetujui Tim

Poin berikut perlu diputuskan tegas sebelum sprint implementasi berjalan:

1. Apakah `vd-store` resmi menjadi commerce core perusahaan?
2. Apakah `velocity-marketplace` resmi menjadi addon yang bergantung pada `vd-store`?
3. Apakah canonical CPT/taxonomy/meta produk mengikuti schema `store_*`?
4. Apakah shortcode `wp_store_*` ditetapkan sebagai public API resmi jangka panjang?
5. Apakah cart dan wishlist juga akan disamakan di level storage?
6. Apakah namespace internal `WpStore\...` tetap dipertahankan sementara pada fase awal?
7. Apakah order lama dianggap legacy domain yang tidak perlu dipaksa identik penuh?

## 11. Status Repo Saat Ini

Dokumen ini perlu dibaca bersama fakta implementasi saat ini:
- domain produk dasar sudah mulai dipusatkan ke `vd-store`
- query, related products, recently viewed, cart, dan wishlist dasar sudah mulai punya service/core owner
- checkout/order dasar sudah mulai memakai `OrderService` di `vd-store`
- `VD Marketplace` sudah mulai menjadi consumer untuk produk, cart, wishlist, dan order dasar
- metadata `vmp_*` masih dipakai sebagai layer marketplace agar UI/admin yang ada tidak rusak
- ownership shortcode dasar `wp_store_*` sudah mulai diarahkan ke core
- posisi saat ini masih transisi terkontrol, belum final

Artinya:
- repo saat ini belum final
- dokumen ini adalah acuan untuk menata ulang refactor berikutnya agar tidak menyimpang

## 12. Kesimpulan

Kesimpulan arsitektur yang diusulkan:
- `vd-store` menjadi fondasi commerce tunggal
- `velocity-marketplace` menjadi addon marketplace di atas fondasi itu
- contract bersama difokuskan pada domain produk dan public API dasar
- seller layer, multi-seller order, dan shipping groups tetap menjadi concern addon

Jika tim menyetujui dokumen ini, implementasi harus mengikuti sprint plan di atas dan menghindari refactor brutal di luar boundary yang sudah disepakati.
