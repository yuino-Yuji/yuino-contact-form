<?php
/**
 * Yuino Contact Form: サニタイズ
 */

if (!defined('ABSPATH')) {
  exit;
}

function ycf_sanitize_input($form_key, $raw) {
  $fields = ycf_get_form_fields($form_key);
  $clean  = [];

  foreach ($fields as $key => $field) {
    $value = isset($raw[$key]) ? wp_unslash($raw[$key]) : '';
    $type  = isset($field['type']) ? $field['type'] : 'text';

    switch ($type) {
      case 'textarea':
        $clean[$key] = sanitize_textarea_field($value);
        break;

      case 'checkbox':
        $clean[$key] = !empty($value) ? '1' : '';
        break;

      case 'radio':
      case 'select':
        $allowed = isset($field['options']) ? array_keys($field['options']) : [];
        $clean[$key] = in_array($value, $allowed, true) ? $value : '';
        break;

      case 'email':
        $clean[$key] = sanitize_email($value);
        break;

      case 'tel':
        $clean[$key] = ycf_normalize_tel(sanitize_text_field($value));
        break;

      case 'contact_value':
        // connection_method が tel のときだけ電話番号として正規化する
        $method = isset($raw['connection_method']) ? wp_unslash($raw['connection_method']) : '';
        $clean[$key] = ($method === 'tel')
          ? ycf_normalize_tel(sanitize_text_field($value))
          : sanitize_text_field($value);
        break;

      case 'text':
      default:
        $clean[$key] = sanitize_text_field($value);
        break;
    }
  }

  return $clean;
}

/**
 * 電話番号を半角へ正規化する。
 *
 * 日本語環境では IME が全角のまま電話番号を入力されることが多く、
 * 検証（半角数字とハイフンのみ）で弾かれて確認画面へ進めない事故が起きる。
 * 検証の手前で表記ゆれを吸収し、利用者に入力し直しをさせない。
 *
 * 変換内容：
 *   - 全角英数字・全角スペース → 半角
 *   - ハイフンに見える各種文字（－ − ― ‐ – — ー 等）→ 半角ハイフン
 *   - 空白（半角・全角）と丸括弧を除去
 *
 * 例: '０９０－１２３４－５６７８' → '090-1234-5678'
 *     '090 1234 5678'             → '09012345678'
 *     '03(1234)5678'              → '0312345678'
 *
 * 桁数や使用可能文字の判定は行わない（検証は ycf_validate() の責務）。
 *
 * @param string $value 入力値
 * @return string 正規化後の文字列
 */
function ycf_normalize_tel($value) {
  $value = (string) $value;

  // 全角英数字（'a'）と全角スペース（'s'）を半角へ。'－'（全角ハイフン）もここで '-' になる
  if (function_exists('mb_convert_kana')) {
    $value = mb_convert_kana($value, 'as', 'UTF-8');
  }

  // 全角化されない別種のダッシュ類を半角ハイフンへ統一
  $value = strtr($value, [
    '−' => '-', // U+2212 MINUS SIGN
    '―' => '-', // U+2015 HORIZONTAL BAR
    '‐' => '-', // U+2010 HYPHEN
    '‑' => '-', // U+2011 NON-BREAKING HYPHEN
    '–' => '-', // U+2013 EN DASH
    '—' => '-', // U+2014 EM DASH
    'ー' => '-', // U+30FC 長音記号（テンキー入力で紛れ込みやすい）
    '─' => '-', // U+2500 BOX DRAWINGS LIGHT HORIZONTAL
  ]);

  // 空白（半角・全角）と丸括弧を除去
  $value = preg_replace('/[\s\x{3000}()（）]/u', '', $value);

  return $value === null ? '' : $value;
}
