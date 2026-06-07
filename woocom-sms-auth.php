<?php
/**
 * Plugin Name: Woocom SMS Auth
 * Plugin URI: https://woocom.ir/
 * Description: افزونه ورود و عضویت پیامکی وردپرس و ووکامرس با پشتیبانی از چندین سرویس‌دهنده پیامک ایرانی، ورود با OTP، ثبت‌نام کاربر، تطبیق کد ملی و شماره همراه از طریق سرویس جیبیت، جایگزینی فرم‌های ورود/عضویت وردپرس، ووکامرس و قالب‌ها، صفحه ورود اختصاصی، و رابط کاربری مدرن و واکنش‌گرا.
 * Version: 1.6.2
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Author: Woocom - Saeed Ghourbanian
 * Author URI: https://woocom.ir/
 * Text Domain: woocom-sms-auth
 * Domain Path: /languages
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 *
 * Developed by: Engineer Saeed Ghourbanian
 * Brand: Woocom
 * Website: https://woocom.ir/
 * Support Phone: 09113678868
 */
defined('ABSPATH') || exit;
define('WCSA_VERSION','1.6.2');
define('WCSA_FILE',__FILE__);
define('WCSA_DIR',plugin_dir_path(__FILE__));
define('WCSA_URL',plugin_dir_url(__FILE__));
define('WCSA_BASENAME',plugin_basename(__FILE__));
require_once WCSA_DIR.'includes/Autoloader.php';
\WCSA\Autoloader::register();
register_activation_hook(__FILE__, ['\\WCSA\\Installer','activate']);
add_action('before_woocommerce_init', function(){ if(class_exists('\\Automattic\\WooCommerce\\Utilities\\FeaturesUtil')){ \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', WCSA_FILE, true); }});
add_action('plugins_loaded', function(){ \WCSA\Plugin::instance()->init(); }, 5);
