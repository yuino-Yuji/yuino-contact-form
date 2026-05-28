<?php
/**
 * Yuino Contact Form: テンプレートローダー & 公開API
 *
 * テンプレート探索順：
 *   1. アクティブなテーマ／子テーマの `yuino-contact-form/<name>` （`locate_template`）
 *   2. プラグイン本体の `templates/<name>`
 *
 * 各テーマで HTML を上書きしたい場合は、
 *   `wp-content/themes/<theme>/yuino-contact-form/form-input.php`
 * のように同名のファイルを置けば自動的にそちらが採用される。
 */

if (!defined('ABSPATH')) {
  exit;
}

/**
 * テンプレート探索（パスを返すだけ。include はしない）
 */
function ycf_locate_template($name) {
  $name = ltrim($name, '/');
  $theme_paths = [
    YCF_TEMPLATE_DIR_NAME . '/' . $name,
  ];
  $found = locate_template($theme_paths, false, false);
  if ($found) {
    return $found;
  }
  $fallback = YCF_PLUGIN_DIR . 'templates/' . $name;
  return file_exists($fallback) ? $fallback : '';
}

/**
 * テンプレートを描画する。`$args` はテンプレート内で `$args` として参照可能。
 */
function ycf_get_template($name, $args = []) {
  $located = ycf_locate_template($name);
  if (!$located) {
    return;
  }
  if (!is_array($args)) {
    $args = [];
  }
  load_template($located, false, $args);
}

/**
 * フォームを表示する公開関数。
 * テーマやテンプレートから直接呼び出して使う：
 *
 *   ycf_render_form('contact');
 */
function ycf_render_form($form_key) {
  if (!ycf_form_exists($form_key)) {
    if (current_user_can('manage_options')) {
      printf(
        '<p style="color:#c00"><strong>[Yuino Contact Form]</strong> フォーム「%s」は登録されていません。</p>',
        esc_html($form_key)
      );
    }
    return;
  }
  ycf_get_template('form-section.php', ['form' => $form_key]);
}

/**
 * ショートコード `[ycf_form id="contact"]` でも使えるようにする。
 */
add_shortcode('ycf_form', 'ycf_shortcode_form');
function ycf_shortcode_form($atts) {
  $atts = shortcode_atts(['id' => ''], $atts, 'ycf_form');
  $form_key = sanitize_key($atts['id']);
  if ($form_key === '' || !ycf_form_exists($form_key)) {
    return '';
  }
  ob_start();
  ycf_render_form($form_key);
  return ob_get_clean();
}
