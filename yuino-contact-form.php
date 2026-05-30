<?php
/**
 * Plugin Name: Yuino Contact Form
 * Plugin URI:  https://github.com/yuino-Yuji/yuino-contact-form
 * Description: 確認画面付きのお問い合わせ／資料請求フォームを、コードベースで定義できる軽量プラグイン。フィールド定義はテーマ側でフィルター登録、テンプレートはテーマ側で上書き可能。
 * Version:     0.2.4
 * Author:      Yuino
 * License:     GPL-2.0-or-later
 * Text Domain: yuino-contact-form
 * Requires at least: 6.0
 * Requires PHP: 8.0
 */

if (!defined('ABSPATH')) {
  exit;
}

define('YCF_VERSION', '0.2.4');
define('YCF_PLUGIN_FILE', __FILE__);
define('YCF_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('YCF_PLUGIN_URL', plugin_dir_url(__FILE__));
define('YCF_TEMPLATE_DIR_NAME', 'yuino-contact-form');

require_once YCF_PLUGIN_DIR . 'inc/config.php';
require_once YCF_PLUGIN_DIR . 'inc/forms.php';
require_once YCF_PLUGIN_DIR . 'inc/sanitizer.php';
require_once YCF_PLUGIN_DIR . 'inc/validator.php';
require_once YCF_PLUGIN_DIR . 'inc/turnstile.php';
require_once YCF_PLUGIN_DIR . 'inc/mailer.php';
require_once YCF_PLUGIN_DIR . 'inc/handler.php';
require_once YCF_PLUGIN_DIR . 'inc/template-loader.php';
require_once YCF_PLUGIN_DIR . 'inc/assets.php';

if (is_admin()) {
  require_once YCF_PLUGIN_DIR . 'inc/mx-preflight.php';
  require_once YCF_PLUGIN_DIR . 'inc/admin-settings.php';
}

// GitHub Release を介した自動更新（Plugin Update Checker）
$ycf_puc_loader = YCF_PLUGIN_DIR . 'vendor/plugin-update-checker/plugin-update-checker.php';
if (file_exists($ycf_puc_loader)) {
  require_once $ycf_puc_loader;
  $ycf_update_checker = \YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
    'https://github.com/yuino-Yuji/yuino-contact-form/',
    __FILE__,
    'yuino-contact-form'
  );
  $ycf_update_checker->setBranch('main');
}
