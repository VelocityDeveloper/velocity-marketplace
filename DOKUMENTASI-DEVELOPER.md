# Dokumentasi Developer VD Marketplace

Dokumen ini untuk developer. Isinya menjelaskan struktur addon `VD Marketplace`.

## Peran plugin

`VD Marketplace` adalah addon di atas `VD Store`.

Addon ini tidak boleh menjadi core kedua.

Yang ditambah oleh addon:
- seller dashboard
- store profile publik
- shipping per seller
- status fulfillment per seller
- pesan buyer dan seller
- notifikasi seller
- metrik seller

## Dependency

Plugin ini wajib membutuhkan `VD Store` aktif.

Bootstrap dependency ada di:
- `velocity-marketplace.php`

## Identitas seller

Akun marketplace memakai:
- role: `vd_member`
- flag seller aktif: `_store_is_seller`

Prinsipnya:
- `vd_member` berarti akun ada di ekosistem marketplace
- hak jual tidak otomatis aktif
- hak jual ditentukan oleh `_store_is_seller = 1`

## Data inti yang mengikuti VD Store

Addon membaca kontrak inti dari `VD Store`, terutama:
- CPT produk: `store_product`
- CPT order: `store_order`
- CPT kupon: `store_coupon`
- taxonomy kategori: `store_product_cat`
- meta inti produk
- meta inti kupon
- cart dasar
- wishlist dasar

Jangan buat source utama kedua untuk domain-domain itu.

## File penting

### Bootstrap dan core
- `velocity-marketplace.php`
- `src/Core/Plugin.php`
- `src/Frontend/Assets.php`
- `src/Support/Settings.php`

### Seller account dan profile
- `src/Modules/Account/Account.php`
- `src/Modules/Profile/ProfileService.php`
- `templates/seller/profile.php`
- `templates/seller/home.php`
- `templates/seller/products.php`

### Cart, shipping, checkout
- `src/Modules/Cart/CartRepository.php`
- `src/Modules/Shipping/ShippingController.php`
- `src/Modules/Checkout/CheckoutController.php`
- `templates/cart.php`
- `templates/checkout.php`

### Coupon, order, payment
- `src/Modules/Coupon/CouponService.php`
- `src/Modules/Order/OrderData.php`
- `src/Modules/Payment/DuitkuCallbackListener.php`

### Pesan dan notifikasi
- `src/Modules/Message/MessageController.php`
- `src/Modules/Notification/NotificationController.php`

## Frontend JS map

### `assets/js/frontend-shared.js`
Helper umum frontend:
- request REST
- format uang
- helper cart
- helper wilayah
- helper captcha

### `assets/js/frontend-cart.js`
Untuk halaman cart marketplace.

### `assets/js/frontend-checkout.js`
Untuk checkout marketplace.

Tanggung jawab utamanya:
- alamat tujuan
- shipping per toko
- kupon
- cart digital-only vs fisik
- submit order

### `assets/js/frontend-profile.js`
Untuk profil member dan profil toko.

### `assets/js/frontend-ui.js`
Untuk helper UI global:
- add to cart
- wishlist
- galeri produk
- lightbox

### `assets/js/media.js`
Untuk form seller:
- media library
- field produk kondisional fisik/digital
- popup validasi field wajib

## URL customer-facing order

URL order customer sekarang dipusatkan ke tracking publik.

Helper utama:
- `Settings::customer_order_url($invoice)`

Format default:
- `/tracking-order/?order=INVOICE`

Helper ini dipakai untuk:
- redirect selesai checkout
- notifikasi customer
- email order
- link order customer

## Aturan shipping penting

### Cart digital-only
- tidak meminta ongkir
- tidak membentuk shipping group

### Cart fisik-only
- wajib alamat tujuan
- wajib layanan pengiriman

### Cart campuran
- item digital tetap ikut order
- shipping hanya dihitung dari item fisik

## Form seller produk

Form seller produk mengikuti schema dari core `VD Store`.

Artinya:
- field tidak didefinisikan ulang manual di addon
- validasi server tetap mengikuti `ProductSchema` dan `ProductFields` dari core
- popup validasi di frontend hanya lapisan UX

## Area yang paling sensitif saat diubah

Kalau mengubah area ini, tes ulang end-to-end:
- aktivasi seller
- tambah/edit produk seller
- cart campuran fisik + digital
- checkout multi-seller
- shipping per toko
- kupon produk dan ongkir
- status order seller
- redirect ke tracking order
- pesan dan notifikasi seller

## Versi saat ini

- plugin version: `1.0.0`
- constant: `VMP_VERSION`
