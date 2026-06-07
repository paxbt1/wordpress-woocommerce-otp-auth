<?php
namespace WCSA;

defined('ABSPATH') || exit;

final class Installer {
    public static function activate(): void {
        self::ensureDefaults();
    }

    public static function ensureDefaults(): void {
        $stored = get_option(Options::KEY, []);
        if (!is_array($stored)) {
            $stored = [];
        }

        $defaults = Options::defaults();
        $changed = false;
        foreach ($defaults as $key => $value) {
            if (!array_key_exists($key, $stored)) {
                $stored[$key] = $value;
                $changed = true;
            }
        }

        // v1.5.6: this is a new independent switch, enabled by default for existing installs too.
        if (!array_key_exists('replace_woocommerce_forms', $stored)) {
            $stored['replace_woocommerce_forms'] = 'yes';
            $changed = true;
        }

        $stored['wcsa_db_version'] = WCSA_VERSION;
        update_option(Options::KEY, $stored, false);
    }
}
