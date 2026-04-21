<div class="vmp-cart-drawer-host" x-data="vmpCart({ drawer: true })" x-init="init()" x-cloak>
    <div
        id="vmp-cart-drawer-panel"
        class="offcanvas offcanvas-end vmp-cart-drawer"
        tabindex="-1"
        aria-labelledby="vmp-cart-drawer-title"
    >
        <div class="offcanvas-header vmp-cart-drawer__header">
            <div>
                <h3 id="vmp-cart-drawer-title" class="vmp-cart-drawer__title"><?php echo esc_html__('Keranjang', 'velocity-marketplace'); ?></h3>
                <div class="vmp-cart-drawer__meta" x-text="count > 0 ? count + ' <?php echo esc_attr__('products', 'velocity-marketplace'); ?>' : '<?php echo esc_attr__('No products yet', 'velocity-marketplace'); ?>'"></div>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="<?php echo esc_attr__('Close', 'velocity-marketplace'); ?>"></button>
        </div>

        <div class="offcanvas-body d-flex flex-column p-0">
            <div class="alert alert-primary mx-3 mt-3 mb-0 py-2 small" x-show="message" x-text="message"></div>

            <div class="vmp-cart-drawer__body">
                <div class="vmp-cart-drawer__loading" x-show="loading"><?php echo esc_html__('Loading cart...', 'velocity-marketplace'); ?></div>

                <div class="vmp-cart-drawer__empty" x-show="!loading && items.length === 0">
                    <div class="vmp-cart-drawer__empty-title"><?php echo esc_html__('Your cart is empty', 'velocity-marketplace'); ?></div>
                    <div class="vmp-cart-drawer__empty-text"><?php echo esc_html__('Add products to continue your checkout.', 'velocity-marketplace'); ?></div>
                    <a class="vmp-cart-drawer__browse" :href="catalogUrl"><?php echo esc_html__('Lihat Katalog', 'velocity-marketplace'); ?></a>
                </div>

                <div class="vmp-cart-drawer__items" x-show="items.length > 0">
                    <template x-for="(item, index) in items" :key="item.id + '-' + optionKey(item) + '-' + index">
                        <div>
                            <div class="vmp-cart-drawer__seller" x-show="index === 0 || items[index - 1].seller_id !== item.seller_id">
                                <div class="vmp-cart-drawer__seller-row">
                                    <a
                                        class="vmp-cart-drawer__seller-label"
                                        :href="item.seller_url || '#'"
                                        x-text="item.seller_name || '<?php echo esc_attr__('Toko', 'velocity-marketplace'); ?>'"
                                    ></a>
                                    <span class="vmp-cart-drawer__seller-subtotal" x-text="formatPrice(sellerSubtotal(item.seller_id))"></span>
                                </div>
                            </div>
                            <article class="vmp-cart-drawer__item">
                                <a class="vmp-cart-drawer__thumb-wrap" :href="item.link">
                                    <img class="vmp-cart-drawer__thumb" :src="item.image || placeholder" alt="">
                                </a>
                                <div class="vmp-cart-drawer__item-content">
                                    <a class="vmp-cart-drawer__item-title" :href="item.link" x-text="item.title"></a>
                                    <div class="vmp-cart-drawer__item-option" x-show="optionText(item.options)" x-text="optionText(item.options)"></div>
                                    <div class="vmp-cart-drawer__item-price" x-text="formatPrice(item.price) + ' x ' + item.qty + ' = ' + formatPrice(item.subtotal)"></div>
                                    <div class="vmp-cart-drawer__qty">
                                        <button type="button" class="vmp-cart-drawer__qty-btn" @click="changeQty(item, item.qty - 1)" :disabled="!canDecrease(item)" :class="{ 'opacity-50': !canDecrease(item) }">-</button>
                                        <span class="vmp-cart-drawer__qty-value" x-text="item.qty"></span>
                                        <button type="button" class="vmp-cart-drawer__qty-btn" @click="changeQty(item, item.qty + 1)">+</button>
                                    </div>
                                </div>
                <button type="button" class="vmp-cart-drawer__remove" @click="remove(item)" aria-label="<?php echo esc_attr__('Remove product', 'velocity-marketplace'); ?>">
                                    <span class="vmp-cart-drawer__remove-icon" aria-hidden="true">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round">
                                            <path d="M18 6L6 18"></path>
                                            <path d="M6 6l12 12"></path>
                                        </svg>
                                    </span>
                                </button>
                            </article>
                        </div>
                    </template>
                </div>
            </div>

            <div class="vmp-cart-drawer__footer mt-auto" x-show="items.length > 0">
                <div class="vmp-cart-drawer__actions">
                    <a class="vmp-cart-drawer__cart-link" :href="cartUrl"><?php echo esc_html__('Lihat Keranjang', 'velocity-marketplace'); ?></a>
                    <button class="vmp-cart-drawer__clear" type="button" @click="clearCart()" :disabled="loading || items.length === 0"><?php echo esc_html__('Clear', 'velocity-marketplace'); ?></button>
                </div>
                <div class="vmp-cart-drawer__summary">
                    <div class="vmp-cart-drawer__summary-label"><?php echo esc_html__('Total', 'velocity-marketplace'); ?></div>
                    <div class="vmp-cart-drawer__summary-value" x-text="formatPrice(total)"></div>
                </div>
                <a class="vmp-cart-drawer__checkout" :href="checkoutUrl"><?php echo esc_html__('Checkout', 'velocity-marketplace'); ?></a>
            </div>
        </div>
    </div>
</div>
