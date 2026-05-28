<?php
/**
 * Yuino Contact Form: アセット読み込み
 *
 * フォームページに到達したときのみ JS をエンキューする。
 * 判定は登録済みフォームの page_slug を `is_page()` で照合。
 */

if (!defined('ABSPATH')) {
  exit;
}

add_action('wp_enqueue_scripts', 'ycf_enqueue_frontend_assets');
function ycf_enqueue_frontend_assets() {
  if (!ycf_is_any_form_page()) {
    return;
  }
  $rel  = 'assets/js/contact-form.js';
  $path = YCF_PLUGIN_DIR . $rel;
  $ver  = file_exists($path) ? filemtime($path) : YCF_VERSION;

  wp_enqueue_script(
    'yuino-contact-form',
    YCF_PLUGIN_URL . $rel,
    [],
    $ver,
    true
  );
}

function ycf_is_any_form_page() {
  $forms = ycf_get_registered_forms();
  foreach ($forms as $config) {
    $slug = $config['page_slug'] ?? '';
    if ($slug !== '' && is_page($slug)) {
      return true;
    }
  }
  return false;
}
