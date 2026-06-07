<?php
namespace WCSA\Frontend;
defined('ABSPATH') || exit;

use WCSA\Options;

final class Assets {
    private static $i;

    public static function instance(): self {
        return self::$i ?: self::$i = new self();
    }

    public function init(): void {
        add_action('wp_enqueue_scripts', [$this, 'enqueue']);
        add_action('login_enqueue_scripts', [$this, 'enqueue']);
    }

    public function enqueue(): void {
        if (!Options::yes('enabled')) return;

        wp_register_style('wcsa-front', WCSA_URL . 'assets/css/frontend.css', [], WCSA_VERSION);
        wp_register_script('wcsa-front', WCSA_URL . 'assets/js/frontend.js', ['jquery'], WCSA_VERSION, true);

        $p = sanitize_hex_color((string) Options::get('ui_primary_color', '#1b84ff')) ?: '#1b84ff';
        $b = sanitize_hex_color((string) Options::get('ui_bg_color', '#f7f3ff')) ?: '#f7f3ff';
        $bgStart = sanitize_hex_color((string) Options::get('ui_bg_gradient_start', '#eef6ff')) ?: '#eef6ff';
        $bgEnd = sanitize_hex_color((string) Options::get('ui_bg_gradient_end', '#fff1f7')) ?: '#fff1f7';
        $accent = sanitize_hex_color((string) Options::get('ui_bg_accent_color', '#7c3aed')) ?: '#7c3aed';
        $title = sanitize_hex_color((string) Options::get('ui_title_color', '#071437')) ?: '#071437';
        $text = sanitize_hex_color((string) Options::get('ui_text_color', '#252f4a')) ?: '#252f4a';
        $muted = sanitize_hex_color((string) Options::get('ui_muted_color', '#6b7280')) ?: '#6b7280';
        $input = sanitize_hex_color((string) Options::get('ui_input_text_color', '#071437')) ?: '#071437';
        $inputBorder = sanitize_hex_color((string) Options::get('ui_input_border_color', '#cbd5e1')) ?: '#cbd5e1';
        $btnText = sanitize_hex_color((string) Options::get('ui_button_text_color', '#ffffff')) ?: '#ffffff';
        $cardBg = (string) Options::get('ui_card_bg_color', 'rgba(255,255,255,.76)');
        if (!preg_match('/^(#[0-9a-fA-F]{3,8}|rgba?\([0-9.,\s]+\))$/', $cardBg)) $cardBg = 'rgba(255,255,255,.76)';
        $cardRadius = max(0, min(40, (int) Options::get('ui_card_radius', 20)));
        $buttonRadius = max(0, min(24, (int) Options::get('ui_button_radius', 10)));
        $formWidth = max(360, min(720, (int) Options::get('ui_form_width', 460)));
        $bgId = absint(Options::get('ui_bg_image_id', 0));
        $bgUrl = $bgId ? (wp_get_attachment_image_url($bgId, 'full') ?: '') : (string) Options::get('ui_bg_image_url', '');
        $bgUrlCss = $bgUrl ? 'url(' . esc_url_raw($bgUrl) . ')' : 'none';

        wp_add_inline_style('wcsa-front', ':root{'
            . '--wcsa-primary:' . $p . ';'
            . '--wcsa-bg:' . $b . ';'
            . '--wcsa-bg-start:' . $bgStart . ';'
            . '--wcsa-bg-end:' . $bgEnd . ';'
            . '--wcsa-bg-accent:' . $accent . ';'
            . '--wcsa-card-bg:' . $cardBg . ';'
            . '--wcsa-title-color:' . $title . ';'
            . '--wcsa-text-color:' . $text . ';'
            . '--wcsa-muted-color:' . $muted . ';'
            . '--wcsa-input-color:' . $input . ';'
            . '--wcsa-input-border:' . $inputBorder . ';'
            . '--wcsa-button-text:' . $btnText . ';'
            . '--wcsa-card-radius:' . $cardRadius . 'px;'
            . '--wcsa-button-radius:' . $buttonRadius . 'px;'
            . '--wcsa-form-width:' . $formWidth . 'px;'
            . '--wcsa-bg-image:' . $bgUrlCss . ';'
            . '}');

        $otpLength = max(4, min(6, (int) Options::get('otp_length', 5)));
        $otpTtl = max(30, min(900, (int) Options::get('otp_ttl', 120)));

        $checkoutUrl = (class_exists('WooCommerce') && function_exists('wc_get_checkout_url')) ? wc_get_checkout_url() : '';
        $redirectTo = isset($_GET['redirect_to']) ? esc_url_raw(wp_unslash($_GET['redirect_to'])) : '';
        if (!$redirectTo && !empty($GLOBALS['wcsa_forced_redirect_to'])) {
            $redirectTo = esc_url_raw((string) $GLOBALS['wcsa_forced_redirect_to']);
        }
        if (!$redirectTo && !is_user_logged_in() && Options::yes('replace_woocommerce_forms') && function_exists('is_account_page') && is_account_page()) {
            $redirectTo = (is_ssl() ? 'https://' : 'http://') . ($_SERVER['HTTP_HOST'] ?? '') . ($_SERVER['REQUEST_URI'] ?? '');
            $redirectTo = esc_url_raw($redirectTo);
        }
        wp_localize_script('wcsa-front', 'WCSA_AUTH', [
            'ajax' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wcsa_nonce'),
            'redirect_to' => $redirectTo,
            'otp_length' => $otpLength,
            'otp_ttl' => $otpTtl,
            'replace_woocommerce_forms' => Options::yes('replace_woocommerce_forms'),
            'replace_theme_forms' => Options::yes('replace_theme_forms'),
            'checkout_login_required' => Options::yes('checkout_login_required'),
            'checkout_login_modal' => Options::yes('checkout_login_modal'),
            'checkout_url' => $checkoutUrl,
            'is_logged_in' => is_user_logged_in(),
            'i18n' => [
                'sending' => 'در حال ارسال...',
                'verifying' => 'در حال بررسی...',
                'registering' => 'در حال ثبت‌نام...',
                'resend' => 'ارسال مجدد کد',
                'change_mobile' => 'تغییر شماره',
            ],
        ]);

        wp_enqueue_style('wcsa-front');
        wp_enqueue_script('wcsa-front');
    }
}
