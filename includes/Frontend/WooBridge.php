<?php
namespace WCSA\Frontend;

defined('ABSPATH') || exit;

use WCSA\Options;

final class WooBridge {
    private static $i;

    /** @var callable|null */
    private $originalMyAccountShortcode = null;

    /** @var bool */
    private $contentAlreadyReplaced = false;

    /** @var bool */
    private $authRendered = false;

    public static function instance(): self {
        return self::$i ?: self::$i = new self();
    }

    public function init(): void {
        // Reliable layer for WooCommerce account pages: take over the shortcode itself.
        // This is the most stable approach for themes like Woodmart where /my-account/ is a normal page
        // containing [woocommerce_my_account].
        add_action('init', [$this, 'takeoverMyAccountShortcode'], 100000);
        add_action('wp_loaded', [$this, 'takeoverMyAccountShortcode'], 100000);

        // Additional layers kept as fallbacks.
        add_filter('pre_do_shortcode_tag', [$this, 'preDoShortcodeTag'], -100000, 4);
        add_filter('do_shortcode_tag', [$this, 'doShortcodeTag'], 100000, 4);
        add_filter('the_content', [$this, 'replaceMyAccountContent'], -100000);

        add_filter('woocommerce_locate_template', [$this, 'locateTemplate'], 9999, 3);
        add_filter('wc_get_template', [$this, 'filterWcGetTemplate'], 9999, 5);

        add_action('woocommerce_before_customer_login_form', [$this, 'renderFallback'], -100000);
        add_action('wp_head', [$this, 'hideFallback'], 9999);
        add_action('wp_footer', [$this, 'renderFooterTemplate'], 5);
    }

    public function takeoverMyAccountShortcode(): void {
        if (!Options::yes('enabled') || !Options::yes('replace_woocommerce_forms')) {
            return;
        }

        global $shortcode_tags;
        if (!is_array($shortcode_tags)) {
            return;
        }

        $current = $shortcode_tags['woocommerce_my_account'] ?? null;

        // Do not overwrite our own callback repeatedly.
        if (is_array($current) && isset($current[0], $current[1]) && $current[0] === $this && $current[1] === 'myAccountShortcode') {
            return;
        }

        // Store the real WooCommerce callback so logged-in users and account endpoints keep working.
        if ($current && $current !== [$this, 'myAccountShortcode']) {
            $this->originalMyAccountShortcode = $current;
        } elseif (!$this->originalMyAccountShortcode && class_exists('WC_Shortcodes') && is_callable(['WC_Shortcodes', 'my_account'])) {
            $this->originalMyAccountShortcode = ['WC_Shortcodes', 'my_account'];
        }

        remove_shortcode('woocommerce_my_account');
        add_shortcode('woocommerce_my_account', [$this, 'myAccountShortcode']);
    }

    public function myAccountShortcode($atts = [], $content = null, $tag = 'woocommerce_my_account'): string {
        if ($this->shouldReplaceOnMyAccount()) {
            return $this->authMarkup('wcsa-woocommerce-shortcode-auth');
        }

        return $this->renderOriginalMyAccountShortcode($atts, $content, $tag);
    }

    private function renderOriginalMyAccountShortcode($atts = [], $content = null, $tag = 'woocommerce_my_account'): string {
        $callback = $this->originalMyAccountShortcode;

        if (!$callback && class_exists('WC_Shortcodes') && is_callable(['WC_Shortcodes', 'my_account'])) {
            $callback = ['WC_Shortcodes', 'my_account'];
        }

        if (is_callable($callback)) {
            return (string) call_user_func($callback, $atts, $content, $tag);
        }

        if (function_exists('wc_print_notices')) {
            ob_start();
            wc_print_notices();
            return (string) ob_get_clean();
        }

        return '';
    }

    private function shouldReplace(): bool {
        return Options::yes('enabled') && Options::yes('replace_woocommerce_forms') && !is_user_logged_in();
    }

    private function shouldReplaceOnMyAccount(): bool {
        if (!$this->shouldReplace()) {
            return false;
        }

        if ($this->isExcludedAccountEndpoint()) {
            return false;
        }

        return true;
    }

    private function authMarkup(string $class = ''): string {
        $this->authRendered = true;
        return '<div class="' . esc_attr(trim($class)) . '" data-wcsa-auth-source="php">' . (new FormRenderer())->render() . '</div>';
    }

    public function preDoShortcodeTag($return, string $tag, array $attr, array $m) {
        if ($tag !== 'woocommerce_my_account') {
            return $return;
        }

        if ($this->shouldReplaceOnMyAccount()) {
            return $this->authMarkup('wcsa-woocommerce-pre-shortcode-auth');
        }

        return $return;
    }

    public function doShortcodeTag($output, string $tag, array $attr, array $m) {
        if ($tag !== 'woocommerce_my_account') {
            return $output;
        }

        if ($this->shouldReplaceOnMyAccount()) {
            return $this->authMarkup('wcsa-woocommerce-do-shortcode-auth');
        }

        return $output;
    }

    public function replaceMyAccountContent(string $content): string {
        if (!$this->shouldReplaceOnMyAccount()) {
            return $content;
        }

        if ($this->contentAlreadyReplaced) {
            return $content;
        }

        // Only replace actual WooCommerce account content. This keeps normal pages safe.
        $isAccount = false;
        if (function_exists('is_account_page') && is_account_page()) {
            $isAccount = true;
        }
        if (!$isAccount && has_shortcode($content, 'woocommerce_my_account')) {
            $isAccount = true;
        }
        if (!$isAccount && function_exists('wc_get_page_id') && is_page((int) wc_get_page_id('myaccount'))) {
            $isAccount = true;
        }

        if (!$isAccount) {
            return $content;
        }

        $this->contentAlreadyReplaced = true;
        return $this->authMarkup('wcsa-woocommerce-content-auth');
    }

    private function isExcludedAccountEndpoint(): bool {
        if (!function_exists('is_wc_endpoint_url')) {
            return false;
        }

        foreach (['lost-password', 'order-received', 'view-order', 'edit-account', 'edit-address', 'payment-methods', 'downloads', 'orders', 'customer-logout', 'add-payment-method', 'delete-payment-method', 'set-default-payment-method'] as $endpoint) {
            if (is_wc_endpoint_url($endpoint)) {
                return true;
            }
        }

        return false;
    }

    public function locateTemplate($template, $template_name, $template_path) {
        if (!$this->shouldReplace()) {
            return $template;
        }

        if ($template_name === 'myaccount/form-login.php') {
            $custom = WCSA_DIR . 'templates/woocommerce/myaccount/form-login.php';
            if (is_readable($custom)) {
                return $custom;
            }
        }

        return $template;
    }

    public function filterWcGetTemplate($located, $template_name, $args, $template_path, $default_path) {
        if (!$this->shouldReplace()) {
            return $located;
        }

        if ($template_name === 'myaccount/form-login.php') {
            $custom = WCSA_DIR . 'templates/woocommerce/myaccount/form-login.php';
            if (is_readable($custom)) {
                return $custom;
            }
        }

        return $located;
    }

    public function renderFallback(): void {
        if (!$this->shouldReplace() || $this->authRendered) {
            return;
        }

        echo $this->authMarkup('wcsa-wc-fallback-auth');
    }

    public function renderFooterTemplate(): void {
        if (!$this->shouldReplace()) {
            return;
        }

        echo '<template id="wcsa-woocommerce-form-template">' . (new FormRenderer())->render() . '</template>';
    }

    public function hideFallback(): void {
        if (!$this->shouldReplace()) {
            return;
        }

        echo '<style id="wcsa-hide-woocommerce-auth">body:not(.logged-in) .woocommerce form.woocommerce-form-login,body:not(.logged-in) .woocommerce form.woocommerce-form-register,body:not(.logged-in) .woocommerce form.login,body:not(.logged-in) .woocommerce form.register,body:not(.logged-in) .woocommerce #customer_login,body:not(.logged-in) .woocommerce .u-columns.col2-set{display:none!important}.wcsa-wc-fallback-auth,.wcsa-woocommerce-shortcode-auth,.wcsa-woocommerce-pre-shortcode-auth,.wcsa-woocommerce-do-shortcode-auth,.wcsa-woocommerce-content-auth{display:flex;justify-content:center;margin:24px auto}</style>';
    }
}
