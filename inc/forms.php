<?php
/**
 * Yuino Contact Form: フォーム登録 API
 *
 * 各テーマは `ycf_register_forms` フィルターでフォームを登録する：
 *
 *   add_filter('ycf_register_forms', function ($forms) {
 *     $forms['contact'] = [
 *       'label'        => 'お問い合わせ',
 *       'page_slug'    => 'contact',
 *       'thanks_slug'  => 'contact-thanks',
 *       'privacy_url'  => home_url('/privacy/'),
 *       'fields'       => [ ... ],
 *     ];
 *     return $forms;
 *   });
 */

if (!defined('ABSPATH')) {
  exit;
}

/**
 * 登録済みフォーム一覧（フィルター結果をキャッシュ）
 */
function ycf_get_registered_forms() {
  static $cache = null;
  if ($cache !== null) {
    return $cache;
  }
  $forms = apply_filters('ycf_register_forms', []);
  if (!is_array($forms)) {
    $forms = [];
  }
  $cache = [];
  foreach ($forms as $key => $form) {
    $key = sanitize_key($key);
    if ($key === '' || !is_array($form)) {
      continue;
    }
    $cache[$key] = wp_parse_args($form, [
      'label'       => $key,
      'page_slug'   => $key,
      'thanks_slug' => $key . '-thanks',
      'privacy_url' => '',
      'anchor'      => '',
      'fields'      => [],
    ]);
  }
  return $cache;
}

function ycf_get_form_config($form_key) {
  $forms = ycf_get_registered_forms();
  return isset($forms[$form_key]) ? $forms[$form_key] : null;
}

function ycf_form_exists($form_key) {
  return ycf_get_form_config($form_key) !== null;
}

function ycf_get_form_fields($form_key) {
  $config = ycf_get_form_config($form_key);
  if (!$config) {
    return [];
  }
  return is_array($config['fields']) ? $config['fields'] : [];
}

function ycf_get_field_label($form_key, $field_key) {
  $fields = ycf_get_form_fields($form_key);
  return isset($fields[$field_key]['label']) ? $fields[$field_key]['label'] : $field_key;
}

function ycf_get_thanks_url($form_key) {
  $config = ycf_get_form_config($form_key);
  if (!$config) {
    return home_url('/');
  }
  return home_url('/' . trim($config['thanks_slug'], '/') . '/');
}

/**
 * フォームが設置されたページの URL。
 *
 * 入力 → 確認 → 入力（修正）の遷移は admin-post.php を経由した PRG リダイレクトで行うため、
 * 何もしないとリダイレクト先がページ最上部になり、確認画面が画面外になる。
 * フォーム定義に `anchor`（例: 'form'）を指定すると、その id へのフラグメントを付けて戻す。
 *
 * 固定ヘッダーがあるテーマでは、アンカー先が隠れないよう対象要素に
 * `scroll-margin-top: <ヘッダー高>` を指定すること（CSS 側の責務）。
 */
function ycf_get_form_url($form_key) {
  $config = ycf_get_form_config($form_key);
  if (!$config) {
    return home_url('/');
  }
  $slug = trim((string) $config['page_slug'], '/');

  // フロントページに設置された場合、home_url('/<slug>/') は正規化リダイレクト（301 → '/'）を挟む。
  // フラグメント付きで戻す際に余計な往復が生まれるため、実際のパーマリンクを引いて直接その URL を使う。
  // 該当ページが見つからない場合は従来どおりスラッグから組み立てる。
  $url  = '';
  $page = $slug !== '' ? get_page_by_path($slug) : null;
  if ($page) {
    $permalink = get_permalink($page);
    if (is_string($permalink) && $permalink !== '') {
      $url = $permalink;
    }
  }
  if ($url === '') {
    $url = home_url('/' . $slug . '/');
  }

  // フラグメントに使えない文字を落とす（id 属性に使える範囲だけ通す）
  $anchor = preg_replace('/[^A-Za-z0-9_\-]/', '', ltrim((string) ($config['anchor'] ?? ''), '#'));
  if ($anchor !== '') {
    $url .= '#' . $anchor;
  }

  return $url;
}

function ycf_get_privacy_url($form_key) {
  $config = ycf_get_form_config($form_key);
  if (!$config) {
    return '';
  }
  return $config['privacy_url'];
}

/**
 * 連絡方法の選択肢（フィールド定義の `options` で上書き可）
 */
function ycf_get_default_connection_method_options() {
  return [
    'mail' => 'メールでの連絡を希望する',
    'tel'  => '電話での連絡を希望する',
    'line' => 'LINEでの連絡を希望する',
  ];
}

function ycf_get_connection_method_options($form_key) {
  $fields = ycf_get_form_fields($form_key);
  if (isset($fields['connection_method']['options']) && is_array($fields['connection_method']['options'])) {
    return $fields['connection_method']['options'];
  }
  return ycf_get_default_connection_method_options();
}

function ycf_get_connection_method_label($form_key, $value) {
  $options = ycf_get_connection_method_options($form_key);
  return isset($options[$value]) ? $options[$value] : $value;
}

/**
 * `contact_value` フィールド（連絡先入力欄）の表示メタ。
 * `connection_method` の選択値に応じて placeholder / inputmode / pattern を切替える。
 */
function ycf_get_contact_value_meta($connection_method) {
  $defaults = [
    'mail' => [
      'placeholder' => '例）sample@xxx.com',
      'inputmode'   => 'email',
      'pattern'     => '',
      'autocomplete'=> 'email',
      'description' => 'メールアドレスをご入力ください',
    ],
    'tel' => [
      'placeholder' => '例）09012345678',
      'inputmode'   => 'numeric',
      'pattern'     => '[0-9]{10,13}',
      'autocomplete'=> 'tel',
      'description' => '半角数字のみ（ハイフンなし）でご入力ください',
    ],
    'line' => [
      'placeholder' => '例）@yourid',
      'inputmode'   => 'text',
      'pattern'     => '',
      'autocomplete'=> 'off',
      'description' => 'LINE ID をご入力ください',
    ],
  ];
  $meta = apply_filters('ycf_contact_value_meta', $defaults);
  if (isset($meta[$connection_method])) {
    return $meta[$connection_method];
  }
  return $meta['mail'];
}

/**
 * aria-describedby 属性を組み立てる
 *
 * 指示（hint）・エラー・動的なヘルプなど、入力欄に結び付けたい要素の id を
 * 受け取り、空要素を除いて 1 つの属性にまとめる。近くに置いただけのテキストは
 * 支援技術に読まれないため、必ずこの属性で参照する
 * （WCAG 2.2 達成基準 3.3.2 ラベル又は指示 / 3.3.1 エラーの特定）。
 *
 * @param array $ids 参照したい要素の id（空文字・重複は無視する）
 * @return string 例: ' aria-describedby="ycf-hint-contact-tel ycf-error-contact-tel"'
 */
function ycf_describedby_attr($ids) {
  if (!is_array($ids)) {
    $ids = [$ids];
  }
  $ids = array_values(array_unique(array_filter(array_map('strval', $ids), static function ($id) {
    return $id !== '';
  })));
  if (empty($ids)) {
    return '';
  }
  return ' aria-describedby="' . esc_attr(implode(' ', $ids)) . '"';
}
