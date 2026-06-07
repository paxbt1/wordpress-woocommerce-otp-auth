<?php
namespace WCSA\Frontend;

defined('ABSPATH') || exit;

use WCSA\Options;

final class LoginPage {
    private static $i;

    public static function instance(): self {
        return self::$i ?: self::$i = new self();
    }

    public function init(): void {
        add_action('template_redirect', [$this, 'renderGuestMyAccountStandalone'], -1000);
        add_action('template_redirect', [$this, 'redirectGuestMyAccount'], 0);
        add_action('template_redirect', [$this, 'renderStandalone']);
        add_action('login_init', [$this, 'redirectWpLogin']);
        add_filter('show_admin_bar', [$this, 'hideBar']);
        add_filter('login_url', [$this, 'loginUrl'], 20, 3);
        add_filter('register_url', [$this, 'registerUrl']);
    }

    public function url(string $r = ''): string {
        $u = home_url('/' . trim(sanitize_title((string) Options::get('login_slug', 'sms-login')), '/') . '/');
        return $r ? add_query_arg('redirect_to', $r, $u) : $u;
    }



    public function renderGuestMyAccountStandalone(): void {
        if (!Options::yes('enabled') || !Options::yes('myaccount_standalone_guest') || !Options::yes('replace_woocommerce_forms') || is_user_logged_in()) {
            return;
        }

        if (is_admin() || wp_doing_ajax() || (defined('REST_REQUEST') && REST_REQUEST)) {
            return;
        }

        if (!$this->isGuestRootMyAccountRequest()) {
            return;
        }

        $GLOBALS['wcsa_forced_redirect_to'] = $this->currentUrl();

        nocache_headers();
        status_header(200);
        ?><!doctype html>
<html <?php language_attributes(); ?> dir="rtl">
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title><?php echo esc_html(get_bloginfo('name') . ' - ورود'); ?></title>
    <?php wp_head(); ?>
</head>
<body class="wcsa-standalone-login wcsa-myaccount-guest-login">
<main class="wcsa-standalone-main"><?php echo (new FormRenderer())->render(); ?></main>
<?php wp_footer(); ?>
</body>
</html><?php
        exit;
    }

    private function isGuestRootMyAccountRequest(): bool {
        // Never hijack Woo endpoints such as lost-password, orders, edit-account, logout, etc.
        if (function_exists('is_wc_endpoint_url') && is_wc_endpoint_url()) {
            return false;
        }

        $isAccount = false;

        if (function_exists('is_account_page') && is_account_page()) {
            $isAccount = true;
        }

        if (!$isAccount && function_exists('wc_get_page_id')) {
            $myAccountId = (int) wc_get_page_id('myaccount');
            if ($myAccountId > 0 && function_exists('is_page') && is_page($myAccountId)) {
                $isAccount = true;
            }
        }

        [$path, $home] = $this->requestPathParts();

        if (!$isAccount && function_exists('wc_get_page_permalink')) {
            $accountUrl = wc_get_page_permalink('myaccount');
            if ($accountUrl && $accountUrl !== '#') {
                $accountPath = trim((string) parse_url($accountUrl, PHP_URL_PATH), '/');
                $accountPath = rawurldecode($accountPath);
                if ($home && strpos($accountPath, $home) === 0) {
                    $accountPath = trim(substr($accountPath, strlen($home)), '/');
                }
                $isAccount = ($accountPath !== '' && $path === $accountPath);
            }
        }

        if (!$isAccount) {
            $isAccount = in_array($path, ['my-account', 'myaccount'], true);
        }

        return $isAccount;
    }

    private function requestPathParts(): array {
        $path = trim((string) parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH), '/');
        $path = rawurldecode($path);
        $home = trim((string) parse_url(home_url('/'), PHP_URL_PATH), '/');
        $home = rawurldecode($home);
        if ($home && strpos($path, $home) === 0) {
            $path = trim(substr($path, strlen($home)), '/');
        }
        return [$path, $home];
    }

    private function currentUrl(): string {
        $scheme = is_ssl() ? 'https://' : 'http://';
        $host = sanitize_text_field(wp_unslash($_SERVER['HTTP_HOST'] ?? ''));
        $requestUri = esc_url_raw(wp_unslash($_SERVER['REQUEST_URI'] ?? '/'));
        return $host ? ($scheme . $host . $requestUri) : home_url('/');
    }

    public function redirectGuestMyAccount(): void {
        if (!Options::yes('enabled') || !Options::yes('redirect_guest_myaccount') || !Options::yes('replace_woocommerce_forms') || is_user_logged_in()) {
            return;
        }

        if (is_admin() || wp_doing_ajax() || (defined('REST_REQUEST') && REST_REQUEST)) {
            return;
        }

        if (!$this->isGuestRootMyAccountRequest()) {
            return;
        }

        wp_safe_redirect($this->url($this->currentUrl()));
        exit;
    }

    public function renderStandalone(): void {
        if (!Options::yes('enabled')) {
            return;
        }

        $mode = (string) Options::get('login_mode', 'both');

        $path = trim((string) parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH), '/');
        $path = rawurldecode($path);
        $home = trim((string) parse_url(home_url('/'), PHP_URL_PATH), '/');
        $home = rawurldecode($home);
        if ($home && strpos($path, $home) === 0) {
            $path = trim(substr($path, strlen($home)), '/');
        }

        $configured = trim(sanitize_title((string) Options::get('login_slug', 'sms-login')), '/');
        // Important: never hijack WooCommerce account pages such as /my-account/ or /حساب-کاربری/.
        // Older versions included those aliases and could replace the logged-in My Account dashboard
        // with the generic "you are logged in" auth message.
        if (function_exists('is_account_page') && is_account_page() && is_user_logged_in()) {
            return;
        }

        $aliases = array_unique(array_filter([
            $configured,
            'sms-login',
            'login',
            'register',
            'ورود',
            'عضویت',
            'ورود-عضویت',
            'ورود عضویت',
            'ورود-و-عضویت',
            'ورود و عضویت',
        ]));

        $match = false;
        foreach ($aliases as $alias) {
            $alias = trim(rawurldecode((string) $alias), '/');
            if ($alias !== '' && $path === $alias) {
                $match = true;
                break;
            }
        }

        if (!$match || (!in_array($mode, ['standalone', 'both'], true) && !Options::yes('replace_theme_forms'))) {
            return;
        }

        // If an already logged-in user opens the login page directly, send them to the
        // configured post-login URL or WooCommerce My Account instead of rendering auth UI.
        if (is_user_logged_in()) {
            $target = (string) Options::get('after_login_url', '');
            if (!$target && function_exists('wc_get_page_permalink')) {
                $target = wc_get_page_permalink('myaccount');
            }
            wp_safe_redirect(\WCSA\Support\Helpers::safeRedirect($target ?: home_url('/')));
            exit;
        }

        nocache_headers();
        status_header(200);
        ?><!doctype html>
<html <?php language_attributes(); ?> dir="rtl">
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title><?php echo esc_html(get_bloginfo('name') . ' - ورود'); ?></title>
    <?php wp_head(); ?>
</head>
<body class="wcsa-standalone-login">
<main class="wcsa-standalone-main"><?php echo (new FormRenderer())->render(); ?></main>
<?php wp_footer(); ?>
</body>
</html><?php
        exit;
    }

    public function redirectWpLogin(): void {
        if (!Options::yes('enabled') || !Options::yes('redirect_wp_login') || ($GLOBALS['pagenow'] ?? '') !== 'wp-login.php') {
            return;
        }
        $a = isset($_REQUEST['action']) ? sanitize_key(wp_unslash($_REQUEST['action'])) : 'login';
        if (in_array($a, ['logout', 'lostpassword', 'rp', 'resetpass'], true)) {
            return;
        }
        wp_safe_redirect($this->url(isset($_GET['redirect_to']) ? esc_url_raw(wp_unslash($_GET['redirect_to'])) : ''));
        exit;
    }

    public function loginUrl($u, $r, $f) {
        if (!Options::yes('enabled') || !in_array((string) Options::get('login_mode', 'both'), ['standalone', 'both'], true)) {
            return $u;
        }
        return $this->url((string) $r);
    }

    public function registerUrl($u): string {
        return Options::yes('enabled') ? $this->url() : $u;
    }

    public function hideBar(bool $s): bool {
        return Options::yes('hide_wp_admin_bar') && !current_user_can('manage_options') ? false : $s;
    }
}
