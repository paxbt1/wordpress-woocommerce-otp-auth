<?php
namespace WCSA\Admin;

defined('ABSPATH') || exit;

use WCSA\Options;
use WCSA\Support\Logger;
use WCSA\Frontend\LoginPage;
use WCSA\Providers\Sms\IranSmsProvider;
use WCSA\Providers\ProviderRegistry;
use WCSA\Support\Helpers;

final class SettingsPage {
    private static $i;
    public static function instance(): self { return self::$i ?: self::$i = new self(); }

    public function init(): void {
        add_action('admin_menu', [$this, 'menu'], 58);
        add_action('admin_init', [$this, 'register']);
        add_action('admin_post_wcsa_clear_logs', [$this, 'clear']);
        add_action('wp_ajax_wcsa_admin_test_sms', [$this, 'testSms']);
        add_filter('plugin_action_links_' . WCSA_BASENAME, [$this, 'links']);
        add_action('admin_footer', [$this, 'adminJs']);
        add_action('admin_enqueue_scripts', [$this, 'adminAssets']);
        add_action('admin_head', [$this, 'hideWpAdminNotices'], 1);
    }

    public function adminAssets($hook = ''): void {
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        $id = $screen ? (string) $screen->id : (string) $hook;
        if (strpos($id, 'wcsa-settings') === false && strpos((string)$hook, 'wcsa-settings') === false) return;
        wp_enqueue_media();
    }


    public function hideWpAdminNotices(): void {
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        $id = $screen ? (string) $screen->id : '';
        $page = isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : '';
        if (strpos($id, 'wcsa-settings') === false && strpos($page, 'wcsa-settings') === false) {
            return;
        }
        echo '<style id="wcsa-hide-wp-notices">.notice:not(.wcsa-keep-notice),.updated:not(.wcsa-keep-notice),.error:not(.wcsa-keep-notice),.update-nag,.fs-notice,.woocommerce-message,.woocommerce-error,.woocommerce-info{display:none!important;}</style>';
    }

    public function links(array $links): array {
        $links[] = '<a href="' . esc_url(admin_url('admin.php?page=wcsa-settings')) . '">تنظیمات</a>';
        return $links;
    }

    public function menu(): void {
        add_menu_page('ورود پیامکی', 'ورود پیامکی', 'manage_options', 'wcsa-settings', [$this, 'render'], 'dashicons-smartphone', 58);
        if (class_exists('WooCommerce')) {
            add_submenu_page('woocommerce', 'ورود پیامکی', 'ورود پیامکی', 'manage_options', 'wcsa-settings-woo', [$this, 'render']);
        }
    }

    public function register(): void {
        register_setting('wcsa_group', Options::KEY, [
            'type' => 'array',
            'sanitize_callback' => [$this, 'sanitize'],
            'default' => Options::defaults(),
        ]);
    }

    public function sanitize($in): array {
        $d = Options::defaults();
        $current = Options::all();
        $out = $current;

        foreach (['enabled','redirect_wp_login','redirect_guest_myaccount','myaccount_standalone_guest','replace_woocommerce_forms','replace_theme_forms','checkout_login_required','checkout_login_modal','hide_wp_admin_bar','require_first_last','require_national_code','identity_required','test_mode','test_show_otp_for_admin','logging_enabled','debug_sensitive_logging'] as $key) {
            if (array_key_exists($key, $in)) {
                $out[$key] = ($in[$key] === 'yes') ? 'yes' : 'no';
            }
        }

        if (array_key_exists('login_mode', $in)) { $out['login_mode'] = in_array(($in['login_mode'] ?? 'both'), ['standalone','woocommerce','both'], true) ? $in['login_mode'] : 'both'; }
        if (array_key_exists('login_slug', $in)) { $out['login_slug'] = sanitize_title($in['login_slug'] ?? 'sms-login'); }
        if (array_key_exists('logo_id', $in)) { $out['logo_id'] = absint($in['logo_id'] ?? 0); }
        if (array_key_exists('logo_url', $in)) { $out['logo_url'] = esc_url_raw($in['logo_url'] ?? ''); }
        if (array_key_exists('after_login_url', $in)) { $out['after_login_url'] = esc_url_raw($in['after_login_url'] ?? ''); }
        if (array_key_exists('after_register_url', $in)) { $out['after_register_url'] = esc_url_raw($in['after_register_url'] ?? ''); }

        $providerIds = array_keys(IranSmsProvider::catalog());
        if (array_key_exists('sms_provider', $in)) { $out['sms_provider'] = in_array(($in['sms_provider'] ?? 'kavenegar'), $providerIds, true) ? sanitize_key($in['sms_provider']) : 'kavenegar'; }
        if (array_key_exists('identity_provider', $in)) { $out['identity_provider'] = sanitize_key($in['identity_provider'] ?? 'jibit'); }
        if (array_key_exists('sms_message_template', $in)) { $out['sms_message_template'] = sanitize_textarea_field($in['sms_message_template'] ?? $d['sms_message_template']); }
        if (array_key_exists('jibit_base_url', $in)) { $out['jibit_base_url'] = esc_url_raw($in['jibit_base_url'] ?? $d['jibit_base_url']); }
        foreach (['jibit_api_key','jibit_secret_key','ui_title','ui_subtitle'] as $key) {
            if (array_key_exists($key, $in)) {
                $out[$key] = sanitize_text_field($in[$key] ?? '');
            }
        }

        foreach (IranSmsProvider::catalog() as $id => $meta) {
            foreach (['api_key','username','password','sender','pattern_code','token_field','custom_url','custom_method','custom_auth_header','custom_auth_value','custom_body','success_path','success_value','pattern_params'] as $field) {
                $name = 'sms_' . $id . '_' . $field;
                $val = array_key_exists($name, $in) ? $in[$name] : ($out[$name] ?? ($meta['defaults'][$field] ?? ''));
                if (!array_key_exists($name, $in)) { continue; }
                if ($field === 'custom_url') $out[$name] = esc_url_raw($val);
                elseif ($field === 'custom_body') $out[$name] = wp_kses_post($val);
                elseif ($id === 'payamito' && $field === 'pattern_code') {
                    $val = trim((string) $val);
                    $out[$name] = ($val === '' || preg_match('/^\d+$/', $val)) ? $val : '';
                }
                else $out[$name] = sanitize_text_field($val);
            }
        }

        $out['kavenegar_api_key'] = $out['sms_kavenegar_api_key'] ?? '';
        $out['kavenegar_sender'] = $out['sms_kavenegar_sender'] ?? '';
        $out['kavenegar_pattern'] = $out['sms_kavenegar_pattern_code'] ?? '';

        if (array_key_exists('otp_length', $in)) { $out['otp_length'] = max(4, min(6, absint($in['otp_length'] ?? 5))); }
        if (array_key_exists('otp_ttl', $in)) { $out['otp_ttl'] = max(30, min(900, absint($in['otp_ttl'] ?? 120))); }
        if (array_key_exists('otp_resend_delay', $in)) { $out['otp_resend_delay'] = max(15, min(300, absint($in['otp_resend_delay'] ?? 60))); }
        if (array_key_exists('otp_max_send_10min', $in)) { $out['otp_max_send_10min'] = max(1, min(20, absint($in['otp_max_send_10min'] ?? 3))); }
        if (array_key_exists('otp_max_verify', $in)) { $out['otp_max_verify'] = max(2, min(20, absint($in['otp_max_verify'] ?? 5))); }
        if (array_key_exists('ip_max_send_10min', $in)) { $out['ip_max_send_10min'] = max(1, min(100, absint($in['ip_max_send_10min'] ?? 10))); }
        if (array_key_exists('cache_identity_minutes', $in)) { $out['cache_identity_minutes'] = max(0, min(1440, absint($in['cache_identity_minutes'] ?? 10))); }
        if (array_key_exists('log_retention', $in)) { $out['log_retention'] = max(20, min(1000, absint($in['log_retention'] ?? 100))); }
        if (array_key_exists('ui_primary_color', $in)) { $out['ui_primary_color'] = sanitize_hex_color($in['ui_primary_color'] ?? $d['ui_primary_color']) ?: $d['ui_primary_color']; }
        if (array_key_exists('ui_bg_color', $in)) { $out['ui_bg_color'] = sanitize_hex_color($in['ui_bg_color'] ?? $d['ui_bg_color']) ?: $d['ui_bg_color']; }
        foreach (['ui_bg_gradient_start','ui_bg_gradient_end','ui_bg_accent_color','ui_title_color','ui_text_color','ui_muted_color','ui_input_text_color','ui_input_border_color','ui_button_text_color'] as $colorKey) {
            if (array_key_exists($colorKey, $in)) {
                $out[$colorKey] = sanitize_hex_color($in[$colorKey] ?? ($d[$colorKey] ?? '#000000')) ?: ($d[$colorKey] ?? '#000000');
            }
        }
        if (array_key_exists('ui_card_bg_color', $in)) {
            $val = trim((string) ($in['ui_card_bg_color'] ?? $d['ui_card_bg_color']));
            $out['ui_card_bg_color'] = preg_match('/^(#[0-9a-fA-F]{3,8}|rgba?\([0-9.,\s]+\))$/', $val) ? $val : $d['ui_card_bg_color'];
        }
        if (array_key_exists('ui_card_radius', $in)) { $out['ui_card_radius'] = max(0, min(40, absint($in['ui_card_radius'] ?? $d['ui_card_radius']))); }
        if (array_key_exists('ui_button_radius', $in)) { $out['ui_button_radius'] = max(0, min(24, absint($in['ui_button_radius'] ?? $d['ui_button_radius']))); }
        if (array_key_exists('ui_form_width', $in)) { $out['ui_form_width'] = max(360, min(720, absint($in['ui_form_width'] ?? $d['ui_form_width']))); }
        if (array_key_exists('ui_bg_image_id', $in)) { $out['ui_bg_image_id'] = absint($in['ui_bg_image_id'] ?? 0); }
        if (array_key_exists('ui_bg_image_url', $in)) { $out['ui_bg_image_url'] = esc_url_raw($in['ui_bg_image_url'] ?? ''); }

        return $out;
    }

    public function clear(): void {
        if (!current_user_can('manage_options') || !check_admin_referer('wcsa_clear_logs')) wp_die('Forbidden');
        Logger::clear();
        wp_safe_redirect(admin_url('admin.php?page=wcsa-settings&tab=logs&cleared=1'));
        exit;
    }

    public function testSms(): void {
        $baseLevel = ob_get_level();
        ob_start();

        try {
            if (!current_user_can('manage_options')) {
                $this->jsonErrorClean(['message' => 'دسترسی غیرمجاز است.'], 403, $baseLevel);
            }
            $nonce = sanitize_text_field(wp_unslash($_POST['nonce'] ?? ''));
            if (!wp_verify_nonce($nonce, 'wcsa_admin_test_sms')) {
                $this->jsonErrorClean(['message' => 'درخواست نامعتبر است. صفحه را رفرش کنید.'], 403, $baseLevel);
            }

            $mobile = Helpers::normalizeMobile((string) wp_unslash($_POST['mobile'] ?? ''));
            if (!Helpers::isValidMobile($mobile)) {
                $this->jsonErrorClean(['message' => 'شماره موبایل تست معتبر نیست. نمونه: 09121234567'], 422, $baseLevel);
            }

            $code = preg_replace('/\D/', '', Helpers::faToEn((string) wp_unslash($_POST['code'] ?? '')));
            if (!$code) $code = Helpers::randomDigits((int) Options::get('otp_length', 5));
            if (strlen($code) < 4 || strlen($code) > 6) {
                $this->jsonErrorClean(['message' => 'کد تست باید بین ۴ تا ۶ رقم باشد.'], 422, $baseLevel);
            }

            $postedProvider = sanitize_key((string) wp_unslash($_POST['provider'] ?? ''));
            $providerIds = array_keys(IranSmsProvider::catalog());
            $savedProvider = (string) Options::get('sms_provider', 'kavenegar');
            $providerId = in_array($postedProvider, $providerIds, true) ? $postedProvider : $savedProvider;
            $provider = ProviderRegistry::instance()->sms($providerId);
            if (!$provider) {
                $this->jsonErrorClean(['message' => 'سرویس‌دهنده پیامک فعال پیدا نشد.'], 500, $baseLevel);
            }

            $requestId = 'test_' . wp_generate_password(10, false, false);
            $debugSensitive = !empty($_POST['debug_sensitive']) && (string) wp_unslash($_POST['debug_sensitive']) === '1';
            Logger::add('info', 'Manual SMS test started', ['provider' => $providerId, 'saved_provider' => $savedProvider, 'posted_provider' => $postedProvider, 'mobile' => $mobile, 'code' => $code, 'request_id' => $requestId, 'debug_sensitive' => $debugSensitive], true, $debugSensitive);
            $ok = $provider->sendOtp($mobile, $code, ['manual_test' => true, 'source' => 'settings_page', 'request_id' => $requestId, 'debug_sensitive' => $debugSensitive]);
            Logger::add($ok ? 'info' : 'error', 'Manual SMS test finished', ['provider' => $providerId, 'saved_provider' => $savedProvider, 'posted_provider' => $postedProvider, 'mobile' => $mobile, 'code' => $code, 'ok' => $ok, 'request_id' => $requestId, 'debug_sensitive' => $debugSensitive], true, $debugSensitive);

            $logs = Logger::simplifyRows(Logger::byRequestId($requestId, 20));
            if ($ok) {
                $this->jsonSuccessClean([
                    'message' => 'پیامک تست با موفقیت ارسال شد.',
                    'provider' => $providerId,
                    'saved_provider' => $savedProvider,
                    'posted_provider' => $postedProvider,
                    'request_id' => $requestId,
                    'logs' => $logs,
                ], $baseLevel);
            }

            $this->jsonErrorClean([
                'message' => 'ارسال پیامک تست ناموفق بود. پاسخ دقیق وب‌سرویس در بخش لاگ ثبت شد.',
                'provider' => $providerId,
                'saved_provider' => $savedProvider,
                'posted_provider' => $postedProvider,
                'request_id' => $requestId,
                'logs' => $logs,
            ], 500, $baseLevel);
        } catch (\Throwable $e) {
            Logger::add('error', 'Manual SMS test fatal error', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            $this->jsonErrorClean([
                'message' => 'خطای داخلی هنگام ارسال تست پیامک: ' . $e->getMessage(),
                'logs' => Logger::latest(8),
            ], 500, $baseLevel);
        }
    }

    private function collectUnexpectedOutput(int $baseLevel): string {
        $noise = '';
        while (ob_get_level() > $baseLevel) {
            $chunk = (string) ob_get_clean();
            if ($chunk !== '') $noise .= $chunk;
        }
        $noise = trim($noise);
        if ($noise !== '') {
            Logger::add('error', 'Unexpected output during admin AJAX', ['output' => $noise]);
        }
        return $noise;
    }

    private function jsonSuccessClean(array $data, int $baseLevel): void {
        $noise = $this->collectUnexpectedOutput($baseLevel);
        if ($noise !== '') {
            $data['unexpected_output'] = wp_strip_all_tags(substr($noise, 0, 2000));
        }
        wp_send_json_success($data);
    }

    private function jsonErrorClean(array $data, int $httpCode, int $baseLevel): void {
        // Some Iranian hosting/proxy layers replace non-2xx admin-ajax responses with an HTML error page.
        // For admin test tools, always return JSON with HTTP 200 and include the intended status in payload.
        $noise = $this->collectUnexpectedOutput($baseLevel);
        if ($noise !== '') {
            $data['unexpected_output'] = wp_strip_all_tags(substr($noise, 0, 2000));
        }
        $data['intended_http_status'] = $httpCode;
        wp_send_json_error($data, 200);
    }

    public function render(): void {
        if (!current_user_can('manage_options')) return;
        $options = Options::all();
        $key = Options::KEY;
        $tab = isset($_GET['tab']) ? sanitize_key(wp_unslash($_GET['tab'])) : 'general';
        $tabs = [
            'general' => ['label' => 'عمومی', 'icon' => '⚙'],
            'sms' => ['label' => 'پیامک', 'icon' => '✉'],
            'identity' => ['label' => 'تطبیق', 'icon' => '🛡'],
            'security' => ['label' => 'امنیت', 'icon' => '🔐'],
            'ui' => ['label' => 'رابط کاربری', 'icon' => '◐'],
            'logs' => ['label' => 'لاگ‌ها', 'icon' => '☰'],
        ];
        ?>
        <div class="wrap wcsa-kt-admin" dir="rtl">
            <div class="kt-page-head">
                <div>
                    <div class="kt-page-eyebrow">Woocom SMS Auth</div>
                    <h1>ورود و عضویت پیامکی</h1>
                    <p>نسخه <?php echo esc_html(WCSA_VERSION); ?> — سازگار با وردپرس و ووکامرس، OOP، چند سرویس‌دهنده و آماده تجاری‌سازی</p>
                </div>
                <a class="kt-btn kt-btn-light" target="_blank" href="<?php echo esc_url((new LoginPage())->url()); ?>">مشاهده صفحه ورود</a>
            </div>

            <div class="kt-layout">
                <aside class="kt-sidebar-card">
                    <div class="kt-sidebar-title">تنظیمات افزونه</div>
                    <nav class="kt-nav-tabs">
                        <?php foreach ($tabs as $id => $meta): ?>
                            <a class="kt-nav-item <?php echo $tab === $id ? 'is-active' : ''; ?>" href="<?php echo esc_url(admin_url('admin.php?page=wcsa-settings&tab=' . $id)); ?>">
                                <span><?php echo esc_html($meta['icon']); ?></span>
                                <strong><?php echo esc_html($meta['label']); ?></strong>
                            </a>
                        <?php endforeach; ?>
                    </nav>
                </aside>

                <main class="kt-content-card">
                    <?php if ($tab === 'logs'): ?>
                        <?php $this->logs(); ?>
                    <?php else: ?>
                        <form method="post" action="options.php">
                            <?php settings_fields('wcsa_group'); ?>
                            <input type="hidden" name="<?php echo esc_attr($key); ?>[_wcsa_tab]" value="<?php echo esc_attr($tab); ?>">
                            <?php $this->tab($tab, $options, $key); ?>
                            <div class="kt-form-footer">
                                <?php submit_button('ذخیره تنظیمات', 'primary kt-submit', 'submit', false); ?>
                            </div>
                        </form>
                    <?php endif; ?>
                </main>
            </div>
        </div>
        <?php
        $this->style();
    }

    private function checkbox(string $key, array $o, string $name, string $label, string $hint = ''): void {
        echo '<label class="kt-switch-row"><span><strong>' . esc_html($label) . '</strong>';
        if ($hint) echo '<small>' . esc_html($hint) . '</small>';
        echo '</span><input type="hidden" name="' . esc_attr($name) . '[' . esc_attr($key) . ']" value="no"><input type="checkbox" name="' . esc_attr($name) . '[' . esc_attr($key) . ']" value="yes" ' . checked($o[$key] ?? 'no', 'yes', false) . '><i></i></label>';
    }

    private function tab(string $tab, array $o, string $key): void {
        if ($tab === 'sms') $this->sms($o, $key);
        elseif ($tab === 'identity') $this->identity($o, $key);
        elseif ($tab === 'security') $this->security($o, $key);
        elseif ($tab === 'ui') $this->ui($o, $key);
        else $this->general($o, $key);
    }

    private function sectionHead(string $title, string $desc): void {
        echo '<div class="kt-section-head"><h2>' . esc_html($title) . '</h2><p>' . esc_html($desc) . '</p></div>';
    }

    private function general(array $o, string $key): void {
        $this->sectionHead('تنظیمات عمومی', 'نحوه فعال‌سازی، مسیر ورود و رفتار افزونه در وردپرس و ووکامرس را مدیریت کنید.');
        ?>
        <div class="kt-grid kt-grid-2">
            <div class="kt-card-mini">
                <?php $this->checkbox('enabled', $o, $key, 'افزونه فعال باشد', 'در صورت غیرفعال شدن، فرم و endpointها از دسترس خارج می‌شوند.'); ?>
                <?php $this->checkbox('redirect_wp_login', $o, $key, 'هدایت wp-login.php', 'برای جلوگیری از نمایش فرم پیش‌فرض وردپرس.'); ?>
                <?php $this->checkbox('redirect_guest_myaccount', $o, $key, 'هدایت مهمان از My Account به صفحه ورود افزونه', 'اگر کاربر وارد نشده باشد و صفحه حساب کاربری ووکامرس را باز کند، به صفحه ورود افزونه منتقل می‌شود و بعد از ورود به همان صفحه برمی‌گردد.'); ?>
                <?php $this->checkbox('myaccount_standalone_guest', $o, $key, 'نمایش My Account مهمان به‌صورت صفحه کامل ورود', 'برای قالب‌هایی مثل Woodmart، صفحه حساب کاربری مهمان را بدون هدر، فوتر و فرم عضویت کناری با فرم اختصاصی افزونه نمایش می‌دهد.'); ?>
                <?php $this->checkbox('replace_woocommerce_forms', $o, $key, 'جایگزینی فرم ورود/عضویت ووکامرس', 'فرم ورود و عضویت صفحه My Account، از جمله قالب‌هایی مثل Woodmart، با فرم پیامکی جایگزین می‌شود.'); ?>
                <?php $this->checkbox('replace_theme_forms', $o, $key, 'جایگزینی همه فرم‌های ورود/عضویت قالب و افزونه‌ها', 'فرم‌های رایج ورود و ثبت‌نام قالب، EDD و افزونه‌های مشابه را با فرم پیامکی جایگزین می‌کند. پیش‌فرض فعال است.'); ?>
                <?php $this->checkbox('checkout_login_required', $o, $key, 'الزام ورود قبل از تسویه حساب', 'فقط وقتی فعال باشد، مهمان قبل از Checkout باید وارد شود؛ در حالت غیرفعال فروش مهمان ووکامرس دست‌نخورده می‌ماند.'); ?>
                <?php $this->checkbox('checkout_login_modal', $o, $key, 'نمایش ورود به‌صورت مودال در مسیر تسویه حساب', 'زیرگزینه الزام ورود: به‌جای خروج از مسیر خرید، فرم ورود در مودال نمایش داده می‌شود و بعد از ورود ادامه به Checkout انجام می‌شود.'); ?>
                <?php $this->checkbox('hide_wp_admin_bar', $o, $key, 'مخفی کردن ادمین‌بار', 'برای کاربران غیرمدیر.'); ?>
            </div>
            <div class="kt-card-mini">
                <label class="kt-label">حالت نمایش فرم</label>
                <select class="kt-input" name="<?php echo esc_attr($key); ?>[login_mode]">
                    <option value="both" <?php selected($o['login_mode'], 'both'); ?>>صفحه اختصاصی + ووکامرس (پیش‌فرض)</option>
                    <option value="standalone" <?php selected($o['login_mode'], 'standalone'); ?>>فقط صفحه اختصاصی بدون هدر و فوتر</option>
                    <option value="woocommerce" <?php selected($o['login_mode'], 'woocommerce'); ?>>فقط ووکامرس</option>
                </select>
                <label class="kt-label">اسلاگ صفحه اختصاصی</label>
                <input class="kt-input" type="text" name="<?php echo esc_attr($key); ?>[login_slug]" value="<?php echo esc_attr($o['login_slug']); ?>">
                <div class="kt-hint"><a target="_blank" href="<?php echo esc_url((new LoginPage())->url()); ?>"><?php echo esc_html((new LoginPage())->url()); ?></a></div>
            </div>
        </div>
        <div class="kt-grid kt-grid-2">
            <div><label class="kt-label">آدرس بعد از ورود</label><input class="kt-input" type="url" name="<?php echo esc_attr($key); ?>[after_login_url]" value="<?php echo esc_attr($o['after_login_url']); ?>"></div>
            <div><label class="kt-label">آدرس بعد از ثبت‌نام</label><input class="kt-input" type="url" name="<?php echo esc_attr($key); ?>[after_register_url]" value="<?php echo esc_attr($o['after_register_url']); ?>"></div>
        </div>
        <?php
    }

    private function sms(array $o, string $key): void {
        $catalog = IranSmsProvider::catalog();
        $this->sectionHead('هسته چند سرویس‌دهنده پیامک', 'با انتخاب هر پنل، فقط فیلدهای همان پنل نمایش داده می‌شود؛ API Key، Token، Username/Password و REST سفارشی پشتیبانی می‌شود.');
        ?>
        <div class="kt-alert-info">این بخش بر اساس ایده Driver/Provider پکیج iran-sms-laravel طراحی شده، اما کاملاً مخصوص وردپرس و بدون وابستگی Laravel/Composer پیاده‌سازی شده است.</div>
        <label class="kt-label">سرویس‌دهنده فعال</label>
        <select class="kt-input kt-provider-select" id="wcsa_provider_select" name="<?php echo esc_attr($key); ?>[sms_provider]">
            <?php foreach ($catalog as $id => $meta): ?>
                <option value="<?php echo esc_attr($id); ?>" <?php selected($o['sms_provider'], $id); ?>><?php echo esc_html($meta['title'] . ' — ' . $id); ?></option>
            <?php endforeach; ?>
        </select>
        <div class="kt-card-mini kt-mt kt-sms-test-box">
            <div class="kt-provider-head">
                <div>
                    <h3>ارسال تست پیامک</h3>
                    <p>بعد از ذخیره تنظیمات سرویس‌دهنده، اینجا همان لحظه ارسال را تست کنید. پاسخ دقیق وب‌سرویس در خروجی تست و تب لاگ‌ها نمایش داده می‌شود.</p>
                </div>
                <span class="kt-badge">Live Test</span>
            </div>
            <div class="kt-grid kt-grid-3">
                <div>
                    <label class="kt-label">شماره موبایل تست</label>
                    <input class="kt-input" type="text" id="wcsa_test_mobile" placeholder="09121234567">
                </div>
                <div>
                    <label class="kt-label">کد تست / OTP</label>
                    <input class="kt-input" type="text" id="wcsa_test_code" placeholder="خالی = تولید خودکار">
                </div>
                <div class="kt-test-send-action">
                    <label class="kt-label">&nbsp;</label>
                    <button type="button" class="kt-btn kt-btn-primary-soft" id="wcsa_test_sms_btn" data-nonce="<?php echo esc_attr(wp_create_nonce('wcsa_admin_test_sms')); ?>">ارسال تست</button>
                </div>
            </div>
            <label class="kt-checkline" style="margin-top:12px;display:flex;gap:8px;align-items:center;">
                <input type="checkbox" id="wcsa_test_sensitive" value="1">
                <span>نمایش کامل اطلاعات ارسالی همین تست در لاگ/خروجی (موبایل، فرستنده، نام‌کاربری، رمز، API Key). فقط برای عیب‌یابی فعال کنید.</span>
            </label>
            <div class="kt-test-result" id="wcsa_test_sms_result" style="display:none"></div>
        </div>

        <div class="kt-grid kt-grid-2 kt-mt">
            <div>
                <label class="kt-label">متن پیامک عمومی</label>
                <textarea class="kt-input" rows="4" name="<?php echo esc_attr($key); ?>[sms_message_template]"><?php echo esc_textarea($o['sms_message_template']); ?></textarea>
                <div class="kt-hint">متغیرها: <code>{code}</code> و <code>{site}</code></div>
            </div>
            <div class="kt-card-mini kt-muted-box">
                <strong>راهنما</strong>
                <p>اگر پنل شما در لیست نیست، گزینه «سرویس‌دهنده سفارشی REST» را انتخاب کنید و URL، Header و Body را با متغیرهای {mobile}، {mobile_09}، {code} و {message} تنظیم کنید.</p>
            </div>
        </div>
        <?php foreach ($catalog as $id => $meta): ?>
            <section class="kt-provider-panel" data-provider="<?php echo esc_attr($id); ?>">
                <div class="kt-provider-head">
                    <div><h3><?php echo esc_html($meta['title']); ?></h3><p><?php echo esc_html($meta['note'] ?? ''); ?></p></div>
                    <span class="kt-badge"><?php echo esc_html($meta['auth'] ?? '-'); ?></span>
                </div>
                <div class="kt-grid kt-grid-2">
                    <?php $this->providerFields($id, $meta, $o, $key); ?>
                </div>
            </section>
        <?php endforeach;
    }

    private function providerFields(string $id, array $meta, array $o, string $key): void {
        $fields = $meta['fields'] ?? [];
        $labels = [
            'api_key' => 'API Key / Token', 'username' => 'نام کاربری', 'password' => 'رمز عبور', 'sender' => 'شماره فرستنده', 'pattern_code' => 'کد پترن / قالب / BodyId', 'token_field' => 'نام متغیر کد در پترن',
            'custom_url' => 'Custom URL', 'custom_method' => 'متد', 'custom_auth_header' => 'نام هدر احراز هویت', 'custom_auth_value' => 'مقدار هدر احراز هویت', 'custom_body' => 'Body سفارشی', 'success_path' => 'مسیر فیلد موفقیت در پاسخ', 'success_value' => 'مقدار موفقیت', 'pattern_params' => 'متن/پارامترهای پترن'
        ];
        foreach ($fields as $field) {
            $name = 'sms_' . $id . '_' . $field;
            $val = $o[$name] ?? ($meta['defaults'][$field] ?? '');
            echo '<div><label class="kt-label">' . esc_html($labels[$field] ?? $field) . '</label>';
            if ($field === 'custom_body') {
                echo '<textarea class="kt-input kt-code" rows="5" name="' . esc_attr($key) . '[' . esc_attr($name) . ']" placeholder="{&quot;mobile&quot;:&quot;{mobile_09}&quot;,&quot;message&quot;:&quot;{message}&quot;}">' . esc_textarea((string) $val) . '</textarea>';
            } elseif ($field === 'custom_method') {
                echo '<select class="kt-input" name="' . esc_attr($key) . '[' . esc_attr($name) . ']"><option value="POST" ' . selected($val, 'POST', false) . '>POST</option><option value="GET" ' . selected($val, 'GET', false) . '>GET</option></select>';
            } else {
                $type = in_array($field, ['password','api_key','custom_auth_value'], true) ? 'password' : 'text';
                if ($id === 'payamito' && $field === 'pattern_code') $type = 'number';
                echo '<input class="kt-input" type="' . esc_attr($type) . '" name="' . esc_attr($key) . '[' . esc_attr($name) . ']" value="' . esc_attr((string) $val) . '">';
            }
            if ($id === 'payamito' && $field === 'pattern_code') {
                echo '<div class="kt-hint">برای ارسال پیامیتو بر اساس الگو، شناسه عددی BodyId را وارد کنید؛ مقدار متنی مثل نام الگو معتبر نیست. اگر خالی باشد، SendOtp معمولی استفاده می‌شود.</div>';
            }
            if ($id === 'payamito' && $field === 'pattern_params') {
                echo '<div class="kt-hint">پارامترهای الگو را دقیقاً با ; جدا کنید. مثال: <code>{code}</code> یا <code>{code};{site}</code>. اگر {code} ننویسید، افزونه کد را ابتدای متن قرار می‌دهد.</div>';
            }
            echo '</div>';
        }
    }

    private function identity(array $o, string $key): void {
        $this->sectionHead('تطبیق کد ملی و شماره موبایل', 'تنظیمات سرویس جیبیت برای استعلام مالکیت شماره همراه.');
        $this->checkbox('require_first_last', $o, $key, 'دریافت نام و نام خانوادگی');
        $this->checkbox('require_national_code', $o, $key, 'دریافت کد ملی');
        $this->checkbox('identity_required', $o, $key, 'تطبیق اجباری کد ملی و موبایل');
        ?>
        <div class="kt-grid kt-grid-3 kt-mt">
            <div><label class="kt-label">Base URL جیبیت</label><input class="kt-input" type="url" name="<?php echo esc_attr($key); ?>[jibit_base_url]" value="<?php echo esc_attr($o['jibit_base_url']); ?>"></div>
            <div><label class="kt-label">API Key</label><input class="kt-input" type="text" name="<?php echo esc_attr($key); ?>[jibit_api_key]" value="<?php echo esc_attr($o['jibit_api_key']); ?>"></div>
            <div><label class="kt-label">Secret Key</label><input class="kt-input" type="password" name="<?php echo esc_attr($key); ?>[jibit_secret_key]" value="<?php echo esc_attr($o['jibit_secret_key']); ?>"></div>
        </div>
        <div class="kt-hint">توکن جیبیت به‌صورت خودکار از API Key و Secret Key دریافت و کش می‌شود.</div>
        <?php
    }

    private function security(array $o, string $key): void {
        $this->sectionHead('امنیت و محدودیت‌ها', 'کنترل OTP، محدودیت نرخ ارسال، کش تطبیق و لاگ‌گیری.');
        $this->checkbox('test_mode', $o, $key, 'حالت تست');
        $this->checkbox('test_show_otp_for_admin', $o, $key, 'نمایش OTP فقط برای مدیر');
        $this->checkbox('logging_enabled', $o, $key, 'ثبت لاگ داخلی افزونه');
        $this->checkbox('debug_sensitive_logging', $o, $key, 'نمایش دائمی اطلاعات حساس در لاگ', 'خطرناک است؛ فقط هنگام عیب‌یابی روشن کنید و سپس خاموش کنید.');
        $items = ['otp_length'=>'طول OTP','otp_ttl'=>'اعتبار OTP ثانیه','otp_resend_delay'=>'تاخیر ارسال مجدد','otp_max_send_10min'=>'حد ارسال موبایل/۱۰دقیقه','ip_max_send_10min'=>'حد ارسال IP/۱۰دقیقه','otp_max_verify'=>'حد تلاش تایید','cache_identity_minutes'=>'کش تطبیق دقیقه','log_retention'=>'تعداد لاگ'];
        echo '<div class="kt-grid kt-grid-4 kt-mt">';
        foreach ($items as $id => $label) {
            echo '<div><label class="kt-label">' . esc_html($label) . '</label><input class="kt-input" type="number" name="' . esc_attr($key) . '[' . esc_attr($id) . ']" value="' . esc_attr($o[$id]) . '"></div>';
        }
        echo '</div>';
    }

    private function ui(array $o, string $key): void {
        $this->sectionHead('رابط کاربری', 'ظاهر صفحه ورود اختصاصی، رنگ‌ها، پس‌زمینه، فونت‌ها، لوگو و اندازه‌ها را مدیریت کنید.');
        ?>
        <div class="kt-grid kt-grid-2">
            <div><label class="kt-label">عنوان</label><input class="kt-input" type="text" name="<?php echo esc_attr($key); ?>[ui_title]" value="<?php echo esc_attr($o['ui_title']); ?>"></div>
            <div><label class="kt-label">زیرعنوان</label><input class="kt-input" type="text" name="<?php echo esc_attr($key); ?>[ui_subtitle]" value="<?php echo esc_attr($o['ui_subtitle']); ?>"></div>

            <div class="kt-logo-picker-wrap">
                <label class="kt-label">لوگوی صفحه ورود</label>
                <?php
                $logo_id = absint($o['logo_id'] ?? 0);
                $logo_url = $logo_id ? wp_get_attachment_image_url($logo_id, 'medium') : esc_url($o['logo_url'] ?? '');
                ?>
                <div class="kt-logo-picker" data-wcsa-logo-picker>
                    <div class="kt-logo-preview <?php echo $logo_url ? '' : 'is-empty'; ?>" data-wcsa-logo-preview>
                        <?php if ($logo_url): ?>
                            <img src="<?php echo esc_url($logo_url); ?>" alt="<?php echo esc_attr__('لوگو', 'woocom-sms-auth'); ?>">
                        <?php else: ?>
                            <span>لوگویی انتخاب نشده</span>
                        <?php endif; ?>
                    </div>
                    <div class="kt-logo-actions">
                        <button type="button" class="kt-btn kt-btn-primary-soft" data-wcsa-logo-select>انتخاب از رسانه وردپرس</button>
                        <button type="button" class="kt-btn kt-btn-light" data-wcsa-logo-remove <?php echo $logo_url ? '' : 'style="display:none"'; ?>>حذف لوگو</button>
                    </div>
                    <input type="hidden" name="<?php echo esc_attr($key); ?>[logo_id]" value="<?php echo esc_attr($logo_id); ?>" data-wcsa-logo-id>
                    <input type="hidden" name="<?php echo esc_attr($key); ?>[logo_url]" value="<?php echo esc_attr($o['logo_url'] ?? ''); ?>" data-wcsa-logo-url>
                </div>
            </div>

            <div class="kt-logo-picker-wrap">
                <label class="kt-label">تصویر پس‌زمینه اختیاری</label>
                <?php
                $bg_id = absint($o['ui_bg_image_id'] ?? 0);
                $bg_url = $bg_id ? wp_get_attachment_image_url($bg_id, 'large') : esc_url($o['ui_bg_image_url'] ?? '');
                ?>
                <div class="kt-logo-picker" data-wcsa-bg-picker>
                    <div class="kt-logo-preview <?php echo $bg_url ? '' : 'is-empty'; ?>" data-wcsa-bg-preview>
                        <?php if ($bg_url): ?>
                            <img src="<?php echo esc_url($bg_url); ?>" alt="<?php echo esc_attr__('پس‌زمینه', 'woocom-sms-auth'); ?>">
                        <?php else: ?>
                            <span>تصویر پس‌زمینه انتخاب نشده</span>
                        <?php endif; ?>
                    </div>
                    <div class="kt-logo-actions">
                        <button type="button" class="kt-btn kt-btn-primary-soft" data-wcsa-bg-select>انتخاب پس‌زمینه</button>
                        <button type="button" class="kt-btn kt-btn-light" data-wcsa-bg-remove <?php echo $bg_url ? '' : 'style="display:none"'; ?>>حذف پس‌زمینه</button>
                    </div>
                    <input type="hidden" name="<?php echo esc_attr($key); ?>[ui_bg_image_id]" value="<?php echo esc_attr($bg_id); ?>" data-wcsa-bg-id>
                    <input type="hidden" name="<?php echo esc_attr($key); ?>[ui_bg_image_url]" value="<?php echo esc_attr($o['ui_bg_image_url'] ?? ''); ?>" data-wcsa-bg-url>
                </div>
            </div>
        </div>

        <h3 class="kt-subtitle">رنگ‌ها</h3>
        <div class="kt-grid kt-grid-4">
            <?php
            $colors = [
                'ui_primary_color' => 'رنگ اصلی / دکمه',
                'ui_button_text_color' => 'رنگ متن دکمه',
                'ui_bg_color' => 'رنگ پایه پس‌زمینه',
                'ui_bg_gradient_start' => 'گرادینت شروع',
                'ui_bg_gradient_end' => 'گرادینت پایان',
                'ui_bg_accent_color' => 'رنگ افکت پس‌زمینه',
                'ui_title_color' => 'رنگ عنوان',
                'ui_text_color' => 'رنگ متن اصلی',
                'ui_muted_color' => 'رنگ متن کم‌رنگ',
                'ui_input_text_color' => 'رنگ متن فیلدها',
                'ui_input_border_color' => 'رنگ خط فیلدها',
            ];
            foreach ($colors as $field => $label): ?>
                <div><label class="kt-label"><?php echo esc_html($label); ?></label><input class="kt-input" type="color" name="<?php echo esc_attr($key); ?>[<?php echo esc_attr($field); ?>]" value="<?php echo esc_attr($o[$field] ?? '#000000'); ?>"></div>
            <?php endforeach; ?>
            <div><label class="kt-label">پس‌زمینه فرم</label><input class="kt-input" type="text" name="<?php echo esc_attr($key); ?>[ui_card_bg_color]" value="<?php echo esc_attr($o['ui_card_bg_color']); ?>" placeholder="rgba(255,255,255,.76)"></div>
        </div>

        <h3 class="kt-subtitle">اندازه‌ها و گوشه‌ها</h3>
        <div class="kt-grid kt-grid-3">
            <div><label class="kt-label">عرض فرم (px)</label><input class="kt-input" type="number" min="360" max="720" name="<?php echo esc_attr($key); ?>[ui_form_width]" value="<?php echo esc_attr($o['ui_form_width']); ?>"></div>
            <div><label class="kt-label">Radius فرم (px)</label><input class="kt-input" type="number" min="0" max="40" name="<?php echo esc_attr($key); ?>[ui_card_radius]" value="<?php echo esc_attr($o['ui_card_radius']); ?>"></div>
            <div><label class="kt-label">Radius دکمه‌ها (px)</label><input class="kt-input" type="number" min="0" max="24" name="<?php echo esc_attr($key); ?>[ui_button_radius]" value="<?php echo esc_attr($o['ui_button_radius']); ?>"></div>
        </div>
        <?php
    }

    private function logs(): void {
        $rawLogs = Logger::all();
        $logs = Logger::simplifyRows($rawLogs);
        ?>
        <div class="kt-section-head"><h2>لاگ‌ها</h2><p>نمای خلاصه برای عیب‌یابی سریع نمایش داده می‌شود؛ اطلاعات حساس در حالت عادی ماسک می‌شوند.</p></div>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="kt-log-actions">
            <?php wp_nonce_field('wcsa_clear_logs'); ?>
            <input type="hidden" name="action" value="wcsa_clear_logs">
            <?php submit_button('پاک کردن لاگ‌ها', 'delete kt-delete', 'submit', false); ?>
        </form>
        <div class="kt-table-wrap"><table class="kt-table"><thead><tr><th>زمان</th><th>سطح</th><th>Provider</th><th>پیام</th><th>HTTP</th><th>نتیجه</th></tr></thead><tbody>
        <?php if (!$logs): ?>
            <tr><td colspan="6">لاگی ثبت نشده است.</td></tr>
        <?php else: foreach ($logs as $log): ?>
            <tr>
                <td><?php echo esc_html($log['time'] ?? ''); ?></td>
                <td><span class="kt-badge"><?php echo esc_html($log['level'] ?? ''); ?></span></td>
                <td><?php echo esc_html($log['provider'] ?? ''); ?></td>
                <td><?php echo esc_html($log['message'] ?? ''); ?></td>
                <td><?php echo esc_html((string)($log['http'] ?? '')); ?></td>
                <td><?php echo esc_html(is_scalar($log['result'] ?? '') ? (string)($log['result'] ?? '') : wp_json_encode($log['result'] ?? '', JSON_UNESCAPED_UNICODE)); ?></td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody></table></div>
        <?php
    }

    private function style(): void {
        ?>
        <style>
        .wcsa-kt-admin{--kt-primary:#2563eb;--kt-text:#071437;--kt-muted:#78829d;--kt-border:#f1f1f4;--kt-bg:#f9f9f9;--kt-card:#fff;--kt-radius:12px;max-width:1240px}.wcsa-kt-admin *{box-sizing:border-box}.kt-page-head{display:flex;justify-content:space-between;gap:16px;align-items:center;background:var(--kt-card);border:1px solid var(--kt-border);border-radius:16px;padding:20px 22px;margin:14px 0 16px;box-shadow:0 7px 18px rgba(15,23,42,.03)}.kt-page-eyebrow{font-weight:800;font-size:12px;color:var(--kt-primary);margin-bottom:6px}.kt-page-head h1{margin:0;color:var(--kt-text);font-size:24px;font-weight:850}.kt-page-head p{margin:7px 0 0;color:var(--kt-muted)}.kt-layout{display:grid;grid-template-columns:260px minmax(0,1fr);gap:16px}.kt-sidebar-card,.kt-content-card{background:var(--kt-card);border:1px solid var(--kt-border);border-radius:16px;padding:16px;box-shadow:0 7px 18px rgba(15,23,42,.03)}.kt-sidebar-card{height:max-content;position:sticky;top:44px}.kt-sidebar-title{font-weight:850;margin:4px 4px 14px;color:var(--kt-text)}.kt-nav-tabs{display:flex;flex-direction:column;gap:6px}.kt-nav-item{display:flex;align-items:center;gap:10px;text-decoration:none;padding:10px 12px;border-radius:10px;color:#4b5675;border:1px solid transparent}.kt-nav-item strong{font-weight:750}.kt-nav-item:hover{background:#f9f9f9;color:var(--kt-text)}.kt-nav-item.is-active{background:#f1f6ff;border-color:#d8e6ff;color:var(--kt-primary)}.kt-section-head{border-bottom:1px solid var(--kt-border);padding-bottom:14px;margin-bottom:16px}.kt-section-head h2{margin:0;font-size:19px;font-weight:850;color:var(--kt-text)}.kt-section-head p{margin:7px 0 0;color:var(--kt-muted)}.kt-grid{display:grid;gap:14px}.kt-subtitle{margin:22px 0 8px;font-size:15px;font-weight:850;color:#252f4a;border-top:1px solid var(--kt-border);padding-top:16px}.kt-grid-2{grid-template-columns:repeat(2,minmax(0,1fr))}.kt-grid-3{grid-template-columns:repeat(3,minmax(0,1fr))}.kt-grid-4{grid-template-columns:repeat(4,minmax(0,1fr))}.kt-mt{margin-top:16px}.kt-card-mini{background:#fcfcfc;border:1px solid var(--kt-border);border-radius:14px;padding:14px}.kt-muted-box p{margin:8px 0 0;color:var(--kt-muted);line-height:1.8}.kt-label{display:block;font-weight:750;color:#252f4a;margin:10px 0 7px}.kt-input{width:100%;max-width:100%;border:1px solid #dbdfe9!important;border-radius:10px!important;padding:9px 12px!important;background:#fff;color:#071437;box-shadow:none!important}.kt-input[type=color]{height:42px;padding:4px!important}.kt-input:focus{border-color:var(--kt-primary)!important;box-shadow:0 0 0 4px rgba(37,99,235,.12)!important;outline:none}.kt-code{direction:ltr;text-align:left;font-family:monospace}.kt-hint,.kt-alert-info{background:#f8fafc;border:1px dashed #d8e0ec;border-radius:12px;padding:10px 12px;margin-top:10px;color:#4b5675;line-height:1.8}.kt-alert-info{background:#f1f6ff;border-color:#cfe0ff;color:#1b4a8f}.kt-switch-row{display:flex!important;justify-content:space-between;align-items:center;gap:14px;padding:12px 0;border-bottom:1px dashed #eef0f5;margin:0!important}.kt-switch-row:last-child{border-bottom:0}.kt-switch-row strong{display:block;color:#252f4a}.kt-switch-row small{display:block;color:var(--kt-muted);font-weight:400;margin-top:4px}.kt-switch-row input{display:none}.kt-switch-row i{width:42px;height:24px;border-radius:999px;background:#e1e3ea;position:relative;flex:0 0 auto;transition:.15s}.kt-switch-row i:before{content:"";position:absolute;width:18px;height:18px;border-radius:50%;background:#fff;top:3px;right:3px;box-shadow:0 2px 5px rgba(0,0,0,.15);transition:.15s}.kt-switch-row input:checked+i{background:var(--kt-primary)}.kt-switch-row input:checked+i:before{right:21px}.kt-btn{display:inline-flex;align-items:center;justify-content:center;text-decoration:none;border-radius:6px;padding:9px 14px;font-weight:750;border:1px solid var(--kt-border);cursor:pointer}.kt-btn-light{background:#fff;color:#4b5675}.kt-btn-light:hover{background:#f9f9f9;color:#071437}.kt-submit{background:var(--kt-primary)!important;border-color:var(--kt-primary)!important;border-radius:6px!important;padding:5px 18px!important;font-weight:800!important}.kt-form-footer{border-top:1px solid var(--kt-border);padding-top:16px;margin-top:20px}.kt-provider-select{max-width:460px}.kt-provider-panel{display:none;margin-top:18px;border:1px solid var(--kt-border);border-radius:16px;padding:16px;background:#fff}.kt-provider-panel.active{display:block}.kt-provider-head{display:flex;justify-content:space-between;gap:12px;align-items:flex-start;border-bottom:1px solid var(--kt-border);padding-bottom:12px;margin-bottom:12px}.kt-provider-head h3{margin:0;color:#071437;font-size:17px}.kt-provider-head p{margin:7px 0 0;color:var(--kt-muted);line-height:1.8}.kt-badge{display:inline-flex;align-items:center;border-radius:999px;background:#f1f6ff;color:var(--kt-primary);font-size:12px;font-weight:800;padding:5px 9px}.kt-table-wrap{overflow:auto;border:1px solid var(--kt-border);border-radius:14px}.kt-table{width:100%;border-collapse:collapse;background:#fff}.kt-table th,.kt-table td{padding:12px;border-bottom:1px solid var(--kt-border);text-align:right}.kt-table th{background:#fcfcfc;color:#252f4a;font-weight:850}.kt-log-actions{margin-bottom:12px}.kt-delete{border-radius:10px!important}.kt-test-result{margin-top:14px;border-radius:12px;padding:12px;white-space:pre-wrap;direction:ltr;text-align:left;font-family:monospace;font-size:12px;line-height:1.7;max-height:360px;overflow:auto}.kt-test-result.is-ok{background:#ecfdf3;border:1px solid #bbf7d0;color:#14532d}.kt-test-result.is-error{background:#fff1f2;border:1px solid #fecdd3;color:#7f1d1d}.kt-test-send-action .kt-btn{width:100%;height:40px}.kt-logo-picker-wrap{grid-column:1/-1}.kt-logo-picker{border:1px solid var(--kt-border);border-radius:14px;padding:14px;background:#fcfcfc}.kt-logo-preview{min-height:104px;border:1px dashed #d8e0ec;border-radius:12px;background:#fff;display:flex;align-items:center;justify-content:center;margin-bottom:12px;overflow:hidden}.kt-logo-preview img{max-width:180px;max-height:86px;object-fit:contain}.kt-logo-preview.is-empty span{color:var(--kt-muted);font-weight:750}.kt-logo-actions{display:flex;gap:10px;flex-wrap:wrap}.kt-btn-primary-soft{background:#f1f6ff;color:var(--kt-primary);border-color:#d8e6ff}.kt-btn-primary-soft:hover{background:#e8f1ff}@media(max-width:1100px){.kt-layout{grid-template-columns:1fr}.kt-sidebar-card{position:static}.kt-nav-tabs{flex-direction:row;flex-wrap:wrap}.kt-grid-4,.kt-grid-3,.kt-grid-2{grid-template-columns:1fr}}@media(prefers-color-scheme:dark){.admin-color-modern .wcsa-kt-admin,.admin-color-midnight .wcsa-kt-admin{--kt-card:#111827;--kt-text:#f9fafb;--kt-muted:#9ca3af;--kt-border:#243044}.admin-color-modern .kt-input,.admin-color-midnight .kt-input{background:#0b1120;color:#fff}}
        </style>
        <?php
    }

    public function adminJs(): void {
        $screen = get_current_screen();
        if (!$screen || strpos($screen->id, 'wcsa-settings') === false) return;
        ?>
        <script>
        (function(){
            function switchProvider(){
                var select=document.getElementById('wcsa_provider_select');
                if(!select) return;
                document.querySelectorAll('.kt-provider-panel').forEach(function(panel){
                    panel.classList.toggle('active', panel.getAttribute('data-provider')===select.value);
                });
            }
            function initSmsTester(){
                var btn = document.getElementById('wcsa_test_sms_btn');
                var out = document.getElementById('wcsa_test_sms_result');
                if(!btn || !out) return;
                btn.addEventListener('click', function(e){
                    e.preventDefault();
                    var mobile = document.getElementById('wcsa_test_mobile') ? document.getElementById('wcsa_test_mobile').value : '';
                    var code = document.getElementById('wcsa_test_code') ? document.getElementById('wcsa_test_code').value : '';
                    out.style.display = 'block';
                    out.className = 'kt-test-result';
                    out.textContent = 'در حال ارسال تست...';
                    btn.disabled = true;
                    var form = new FormData();
                    form.append('action', 'wcsa_admin_test_sms');
                    form.append('nonce', btn.getAttribute('data-nonce') || '');
                    form.append('mobile', mobile);
                    form.append('code', code);
                    var sensitive = document.getElementById('wcsa_test_sensitive') && document.getElementById('wcsa_test_sensitive').checked ? '1' : '0';
                    form.append('debug_sensitive', sensitive);
                    var providerSelect = document.getElementById('wcsa_provider_select');
                    if(providerSelect) form.append('provider', providerSelect.value || '');
                    fetch(ajaxurl, {method:'POST', credentials:'same-origin', body:form})
                        .then(function(r){
                            return r.text().then(function(text){
                                var j = null;
                                try { j = JSON.parse(text); }
                                catch(e) {
                                    return {success:false, data:{message:'پاسخ وردپرس JSON نبود. احتمالاً خطای PHP/HTML قبل از پاسخ Ajax چاپ شده است.', http_status:r.status, raw_response:text.substring(0, 4000)}};
                                }
                                j._http = r.status;
                                return j;
                            });
                        })
                        .then(function(j){
                            var ok = !!j.success;
                            out.className = 'kt-test-result ' + (ok ? 'is-ok' : 'is-error');
                            out.textContent = (j.data && j.data.message ? j.data.message : (ok ? 'موفق' : 'ناموفق')) + "\n\n" + JSON.stringify(j.data || {}, null, 2);
                        })
                        .catch(function(err){
                            out.className = 'kt-test-result is-error';
                            out.textContent = 'خطای ارتباط با وردپرس: ' + (err && err.message ? err.message : err);
                        })
                        .finally(function(){ btn.disabled = false; });
                });
            }
            function initLogoPicker(){
                var wrap = document.querySelector('[data-wcsa-logo-picker]');
                if(!wrap || typeof wp === 'undefined' || !wp.media) return;
                var preview = wrap.querySelector('[data-wcsa-logo-preview]');
                var idInput = wrap.querySelector('[data-wcsa-logo-id]');
                var urlInput = wrap.querySelector('[data-wcsa-logo-url]');
                var selectBtn = wrap.querySelector('[data-wcsa-logo-select]');
                var removeBtn = wrap.querySelector('[data-wcsa-logo-remove]');
                var frame;

                function setLogo(id, url){
                    idInput.value = id || '';
                    urlInput.value = '';
                    if(url){
                        preview.classList.remove('is-empty');
                        preview.innerHTML = '<img src="'+url.replace(/"/g,'&quot;')+'" alt="لوگو">';
                        if(removeBtn) removeBtn.style.display = '';
                    } else {
                        preview.classList.add('is-empty');
                        preview.innerHTML = '<span>لوگویی انتخاب نشده</span>';
                        if(removeBtn) removeBtn.style.display = 'none';
                    }
                }

                if(selectBtn){
                    selectBtn.addEventListener('click', function(e){
                        e.preventDefault();
                        if(frame){ frame.open(); return; }
                        frame = wp.media({
                            title: 'انتخاب لوگوی صفحه ورود',
                            button: { text: 'استفاده از این لوگو' },
                            library: { type: 'image' },
                            multiple: false
                        });
                        frame.on('select', function(){
                            var attachment = frame.state().get('selection').first().toJSON();
                            var url = attachment.sizes && attachment.sizes.medium ? attachment.sizes.medium.url : attachment.url;
                            setLogo(attachment.id, url);
                        });
                        frame.open();
                    });
                }
                if(removeBtn){
                    removeBtn.addEventListener('click', function(e){
                        e.preventDefault();
                        setLogo('', '');
                    });
                }
            }

            function initBgPicker(){
                var wrap = document.querySelector('[data-wcsa-bg-picker]');
                if(!wrap || typeof wp === 'undefined' || !wp.media) return;
                var preview = wrap.querySelector('[data-wcsa-bg-preview]');
                var idInput = wrap.querySelector('[data-wcsa-bg-id]');
                var urlInput = wrap.querySelector('[data-wcsa-bg-url]');
                var selectBtn = wrap.querySelector('[data-wcsa-bg-select]');
                var removeBtn = wrap.querySelector('[data-wcsa-bg-remove]');
                var frame;
                function setBg(id, url){
                    idInput.value = id || '';
                    urlInput.value = '';
                    if(url){
                        preview.classList.remove('is-empty');
                        preview.innerHTML = '<img src="'+url.replace(/"/g,'&quot;')+'" alt="پس‌زمینه">';
                        if(removeBtn) removeBtn.style.display = '';
                    } else {
                        preview.classList.add('is-empty');
                        preview.innerHTML = '<span>تصویر پس‌زمینه انتخاب نشده</span>';
                        if(removeBtn) removeBtn.style.display = 'none';
                    }
                }
                if(selectBtn){
                    selectBtn.addEventListener('click', function(e){
                        e.preventDefault();
                        if(frame){ frame.open(); return; }
                        frame = wp.media({title:'انتخاب تصویر پس‌زمینه',button:{text:'استفاده از این تصویر'},library:{type:'image'},multiple:false});
                        frame.on('select', function(){
                            var attachment = frame.state().get('selection').first().toJSON();
                            var url = attachment.sizes && attachment.sizes.large ? attachment.sizes.large.url : attachment.url;
                            setBg(attachment.id, url);
                        });
                        frame.open();
                    });
                }
                if(removeBtn){
                    removeBtn.addEventListener('click', function(e){ e.preventDefault(); setBg('', ''); });
                }
            }

            document.addEventListener('change',function(e){ if(e.target && e.target.id==='wcsa_provider_select') switchProvider(); });
            document.addEventListener('DOMContentLoaded',function(){ switchProvider(); initLogoPicker(); initBgPicker(); initSmsTester(); });
            switchProvider();
            initLogoPicker();
            initBgPicker();
            initSmsTester();
        })();
        </script>
        <?php
    }
}
