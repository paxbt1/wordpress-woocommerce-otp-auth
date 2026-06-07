<?php
namespace WCSA\Frontend;

defined('ABSPATH') || exit;

use WCSA\Options;

final class ThemeBridge {
    private static $i;

    public static function instance(): self {
        return self::$i ?: self::$i = new self();
    }

    public function init(): void {
        add_action('wp_footer', [$this, 'printTemplate'], 100);
    }

    public function printTemplate(): void {
        if (!Options::yes('enabled') || !Options::yes('replace_theme_forms') || is_user_logged_in()) {
            return;
        }

        echo "<template id=\"wcsa-theme-form-template\">" . (new FormRenderer())->render() . "</template>";
    }
}
