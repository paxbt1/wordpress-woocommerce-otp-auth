<?php
namespace WCSA;

defined('ABSPATH') || exit;

final class Options {
    const KEY = 'wcsa_options';
    public static function defaults(): array {
        return [
            'enabled'=>'yes','login_mode'=>'both','login_slug'=>'sms-login','redirect_wp_login'=>'yes','redirect_guest_myaccount'=>'yes','myaccount_standalone_guest'=>'yes','replace_woocommerce_forms'=>'yes','replace_theme_forms'=>'yes','checkout_login_required'=>'no','checkout_login_modal'=>'no','logo_id'=>0,'logo_url'=>'','after_login_url'=>'','after_register_url'=>'','hide_wp_admin_bar'=>'yes',
            'require_first_last'=>'yes','require_national_code'=>'yes','identity_required'=>'yes',
            'sms_provider'=>'kavenegar','identity_provider'=>'jibit',
            'sms_message_template'=>'کد ورود شما: {code}\n{site}',
            // Legacy Kavenegar keys kept for old installations.
            'kavenegar_api_key'=>'','kavenegar_sender'=>'','kavenegar_pattern'=>'',
            // Universal provider keys: sms_{provider}_{field}
            'sms_kavenegar_api_key'=>'','sms_kavenegar_sender'=>'','sms_kavenegar_pattern_code'=>'','sms_kavenegar_token_field'=>'token','sms_kavenegar_custom_url'=>'','sms_kavenegar_custom_method'=>'POST','sms_kavenegar_custom_auth_header'=>'','sms_kavenegar_custom_auth_value'=>'','sms_kavenegar_custom_body'=>'','sms_kavenegar_success_path'=>'','sms_kavenegar_success_value'=>'',
            'jibit_base_url'=>'https://napi.jibit.ir/ide','jibit_api_key'=>'','jibit_secret_key'=>'',
            'otp_length'=>5,'otp_ttl'=>120,'otp_resend_delay'=>60,'otp_max_send_10min'=>3,'otp_max_verify'=>5,'ip_max_send_10min'=>10,'cache_identity_minutes'=>10,
            'test_mode'=>'no','test_show_otp_for_admin'=>'yes','ui_title'=>'ورود / عضویت','ui_subtitle'=>'برای ادامه، شماره موبایل خود را وارد کنید.','ui_primary_color'=>'#1b84ff','ui_bg_color'=>'#f7f3ff','ui_bg_gradient_start'=>'#eef6ff','ui_bg_gradient_end'=>'#fff1f7','ui_bg_accent_color'=>'#7c3aed','ui_card_bg_color'=>'rgba(255,255,255,.76)','ui_title_color'=>'#071437','ui_text_color'=>'#252f4a','ui_muted_color'=>'#6b7280','ui_input_text_color'=>'#071437','ui_input_border_color'=>'#cbd5e1','ui_button_text_color'=>'#ffffff','ui_card_radius'=>20,'ui_button_radius'=>10,'ui_form_width'=>460,'ui_bg_image_id'=>0,'ui_bg_image_url'=>'','logging_enabled'=>'yes','debug_sensitive_logging'=>'no','log_retention'=>100
        ];
    }
    public static function all(): array { $s = get_option(self::KEY, []); if (!is_array($s)) $s = []; return array_merge(self::defaults(), $s); }
    public static function get(string $k, $d=null) { $a = self::all(); return array_key_exists($k,$a) ? $a[$k] : $d; }
    public static function yes(string $k): bool { return self::get($k,'no') === 'yes'; }
}
