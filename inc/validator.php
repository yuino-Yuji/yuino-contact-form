<?php
/**
 * Yuino Contact Form: バリデーション
 */

if (!defined('ABSPATH')) {
  exit;
}

function ycf_validate($form_key, $data) {
  $errors = [];
  $fields = ycf_get_form_fields($form_key);

  foreach ($fields as $key => $field) {
    $type     = isset($field['type']) ? $field['type'] : 'text';
    $required = !empty($field['required']);
    $value    = isset($data[$key]) ? $data[$key] : '';

    if ($required) {
      if ($type === 'checkbox') {
        if ($value !== '1') {
          $errors[$key] = ($field['label'] ?? $key) . 'にチェックしてください。';
          continue;
        }
      } else {
        if (trim((string) $value) === '') {
          $errors[$key] = ($field['label'] ?? $key) . 'を入力してください。';
          continue;
        }
      }
    }

    if (trim((string) $value) === '') {
      continue;
    }

    if ($type === 'email' && !is_email($value)) {
      $errors[$key] = 'メールアドレスの形式が正しくありません。';
      continue;
    }

    if ($type === 'contact_value') {
      $method = isset($data['connection_method']) ? $data['connection_method'] : '';
      switch ($method) {
        case 'mail':
          if (!is_email($value)) {
            $errors[$key] = 'メールアドレスの形式が正しくありません。';
          }
          break;
        case 'tel':
          if (!preg_match('/\A[0-9]{10,13}\z/', $value)) {
            $errors[$key] = '電話番号は半角数字のみ10〜13桁でご入力ください（ハイフン不要）。';
          }
          break;
      }
      if (isset($errors[$key])) {
        continue;
      }
    }

    $maxlength = isset($field['maxlength']) ? (int) $field['maxlength'] : 0;
    if ($maxlength > 0 && mb_strlen((string) $value) > $maxlength) {
      $errors[$key] = ($field['label'] ?? $key) . 'は' . $maxlength . '文字以内でご入力ください。';
    }
  }

  /**
   * カスタムバリデーション用フィルター
   * add_filter('ycf_validate', function($errors, $form_key, $data) { ... }, 10, 3);
   */
  $errors = apply_filters('ycf_validate', $errors, $form_key, $data);

  return is_array($errors) ? $errors : [];
}
