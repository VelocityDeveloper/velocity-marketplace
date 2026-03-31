<?php

namespace VelocityMarketplace\Modules\Account;

use VelocityMarketplace\Modules\Account\Handlers\MessageActionHandler;
use VelocityMarketplace\Modules\Account\Handlers\NotificationActionHandler;
use VelocityMarketplace\Modules\Account\Handlers\OrderActionHandler;
use VelocityMarketplace\Modules\Account\Handlers\ProductActionHandler;
use VelocityMarketplace\Modules\Account\Handlers\ProfileActionHandler;
use VelocityMarketplace\Modules\Account\Handlers\ReviewActionHandler;
use VelocityMarketplace\Modules\Account\Handlers\WishlistActionHandler;

class Actions
{
    public function register()
    {
        add_action('init', [$this, 'handle_actions']);
    }

    public function handle_actions()
    {
        if (!is_user_logged_in()) {
            return;
        }

        if (isset($_GET['vmp_delete_product']) && Account::can_sell()) {
            (new ProductActionHandler())->delete_product();
            return;
        }

        if (!isset($_POST['vmp_action'])) {
            return;
        }

        $action = sanitize_key((string) wp_unslash($_POST['vmp_action']));

        switch ($action) {
            case 'seller_save_product':
                (new ProductActionHandler())->save_product();
                return;
            case 'seller_update_order':
                (new OrderActionHandler())->seller_update_order();
                return;
            case 'buyer_upload_transfer':
                (new OrderActionHandler())->buyer_upload_transfer();
                return;
            case 'buyer_confirm_received':
                (new OrderActionHandler())->buyer_confirm_received();
                return;
            case 'save_store_profile':
                (new ProfileActionHandler())->save_store_profile();
                return;
            case 'save_customer_profile':
                (new ProfileActionHandler())->save_customer_profile();
                return;
            case 'wishlist_remove':
                (new WishlistActionHandler())->remove();
                return;
            case 'notification_mark_read':
                (new NotificationActionHandler())->mark_read();
                return;
            case 'notification_mark_all':
                (new NotificationActionHandler())->mark_all();
                return;
            case 'notification_delete':
                (new NotificationActionHandler())->delete();
                return;
            case 'message_send':
                (new MessageActionHandler())->send();
                return;
            case 'review_submit':
                (new ReviewActionHandler())->submit();
                return;
        }
    }
}
