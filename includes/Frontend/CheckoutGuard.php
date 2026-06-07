<?php
namespace WCSA\Frontend;

defined('ABSPATH') || exit;

use WCSA\Options;

final class CheckoutGuard {
    private static $i;

    public static function instance(): self {
        return self::$i ?: self::$i = new self();
    }

    public function init(): void {
        add_action('template_redirect', [$this, 'maybeRedirectCheckout'], 1);
        add_action('wp_footer', [$this, 'renderModal'], 90);
        add_action('wp_head', [$this, 'checkoutPageGuardCss'], 40);
    }

    private function enabled(): bool {
        return Options::yes('enabled') && Options::yes('checkout_login_required') && class_exists('WooCommerce') && function_exists('wc_get_checkout_url');
    }

    private function modalEnabled(): bool {
        return $this->enabled() && Options::yes('checkout_login_modal');
    }

    public function maybeRedirectCheckout(): void {
        if (!$this->enabled() || $this->modalEnabled() || is_user_logged_in() || wp_doing_ajax()) {
            return;
        }

        if (!function_exists('is_checkout') || !is_checkout()) {
            return;
        }

        if (function_exists('is_order_received_page') && is_order_received_page()) {
            return;
        }

        $checkout = wc_get_checkout_url();
        $login = (new LoginPage())->url($checkout);
        wp_safe_redirect($login);
        exit;
    }

    public function checkoutPageGuardCss(): void {
        if (!$this->modalEnabled() || is_user_logged_in() || !function_exists('is_checkout') || !is_checkout()) {
            return;
        }
        if (function_exists('is_order_received_page') && is_order_received_page()) {
            return;
        }
        echo '<style id="wcsa-checkout-guard-css">body.woocommerce-checkout form.checkout,body.woocommerce-checkout .woocommerce-checkout,body.woocommerce-checkout #customer_details,body.woocommerce-checkout #order_review{filter:blur(2px);pointer-events:none;user-select:none;opacity:.45}</style>';
    }

    public function renderModal(): void {
        if (!$this->modalEnabled() || is_user_logged_in()) {
            return;
        }
        ?>
        <div class="wcsa-checkout-modal" data-wcsa-checkout-modal aria-hidden="true">
            <div class="wcsa-checkout-modal__backdrop" data-wcsa-checkout-close></div>
            <div class="wcsa-checkout-modal__dialog" role="dialog" aria-modal="true" aria-label="ورود برای ادامه خرید">
                <button type="button" class="wcsa-checkout-modal__close" data-wcsa-checkout-close aria-label="بستن">×</button>
                <div class="wcsa-checkout-modal__head">
                    <span class="wcsa-checkout-modal__badge">ادامه خرید</span>
                    <h3>برای تسویه حساب وارد شوید</h3>
                    <p>پس از ورود یا عضویت، مسیر خرید شما ادامه پیدا می‌کند.</p>
                </div>
                <?php echo (new FormRenderer())->render(); ?>
            </div>
        </div>
        <?php
    }
}
