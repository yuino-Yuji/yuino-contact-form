<?php
/**
 * Yuino Contact Form: SMTP / メール送信
 */

if (!defined('ABSPATH')) {
  exit;
}

/**
 * PHPMailer 設定をフォーム送信時のみ流し込む。
 * - smtp_from_email が入っていれば From を上書き（SMTP接続有無を問わず）
 * - SMTP接続情報＋パスワードが揃っていれば SMTP モードに切替
 */
function ycf_configure_phpmailer($phpmailer) {
  $settings = ycf_get_settings();

  if (!empty($settings['smtp_from_email'])) {
    $phpmailer->setFrom(
      $settings['smtp_from_email'],
      !empty($settings['smtp_from_name']) ? $settings['smtp_from_name'] : '',
      false
    );
  }

  $phpmailer->CharSet  = 'UTF-8';
  $phpmailer->Encoding = '8bit';

  $password = ycf_get_smtp_password();
  $required = ['smtp_host', 'smtp_port', 'smtp_username', 'smtp_from_email'];
  foreach ($required as $key) {
    if (empty($settings[$key])) {
      return;
    }
  }
  if (empty($password)) {
    return;
  }

  $phpmailer->isSMTP();
  $phpmailer->Host     = $settings['smtp_host'];
  $phpmailer->Port     = (int) $settings['smtp_port'];
  $phpmailer->SMTPAuth = true;
  $phpmailer->Username = $settings['smtp_username'];
  $phpmailer->Password = $password;

  $encryption = $settings['smtp_encryption'];
  if ($encryption === 'starttls') {
    $phpmailer->SMTPSecure = 'tls';
  } elseif ($encryption === 'ssl' || $encryption === 'tls') {
    $phpmailer->SMTPSecure = $encryption;
  } else {
    $phpmailer->SMTPSecure   = '';
    $phpmailer->SMTPAutoTLS  = false;
  }
}
add_action('phpmailer_init', 'ycf_configure_phpmailer');

function ycf_filter_mail_from($email) {
  $configured = ycf_get_setting('smtp_from_email');
  return $configured ? $configured : $email;
}
add_filter('wp_mail_from', 'ycf_filter_mail_from');

function ycf_filter_mail_from_name($name) {
  $configured = ycf_get_setting('smtp_from_name');
  return $configured ? $configured : $name;
}
add_filter('wp_mail_from_name', 'ycf_filter_mail_from_name');

/**
 * テンプレートのプレースホルダ置換。
 * 各フィールド値は `{field_key}` で参照可能。
 * radio / select は `{field_key_label}` でラベル文字列も参照できる。
 */
function ycf_render_template($template, $data, $form_key) {
  $config = ycf_get_form_config($form_key);
  $fields = ycf_get_form_fields($form_key);

  $replacements = [
    '{form_key}'   => $form_key,
    '{form_label}' => $config ? ($config['label'] ?? $form_key) : $form_key,
    '{site_name}'  => get_bloginfo('name'),
    '{sent_at}'    => wp_date('Y-m-d H:i:s'),
    '{remote_ip}'  => ycf_get_remote_ip(),
  ];

  foreach ($data as $key => $value) {
    $replacements['{' . $key . '}'] = (string) $value;

    $type = isset($fields[$key]['type']) ? $fields[$key]['type'] : '';
    if ($type === 'radio' || $type === 'select') {
      $options = isset($fields[$key]['options']) ? $fields[$key]['options'] : [];
      $replacements['{' . $key . '_label}'] = isset($options[$value]) ? $options[$value] : (string) $value;
    } elseif ($type === 'checkbox') {
      $replacements['{' . $key . '_label}'] = ($value === '1') ? '同意済み' : '未同意';
    }
  }

  // 後方互換：connection_method_label を明示的に保証
  if (isset($data['connection_method']) && !isset($replacements['{connection_method_label}'])) {
    $replacements['{connection_method_label}'] = ycf_get_connection_method_label($form_key, $data['connection_method']);
  }

  /**
   * テンプレート置換のフィルター
   */
  $replacements = apply_filters('ycf_template_replacements', $replacements, $data, $form_key);

  return strtr($template, $replacements);
}

/**
 * 管理者通知メール送信
 */
function ycf_send_admin_notification($form_key, $data) {
  $settings = ycf_get_settings();
  $prefix   = $form_key . '_';

  $to = ycf_parse_email_list($settings[$prefix . 'admin_to'] ?? '');
  if (empty($to)) {
    return false;
  }

  $subject_tmpl = $settings[$prefix . 'admin_subject'] ?? '';
  $body_tmpl    = $settings[$prefix . 'admin_body']    ?? '';

  $subject = ycf_render_template($subject_tmpl, $data, $form_key);
  $body    = ycf_render_template($body_tmpl, $data, $form_key);

  $headers = ['Content-Type: text/plain; charset=UTF-8'];

  foreach (ycf_parse_email_list($settings[$prefix . 'admin_cc'] ?? '') as $addr) {
    $headers[] = 'Cc: ' . $addr;
  }
  foreach (ycf_parse_email_list($settings[$prefix . 'admin_bcc'] ?? '') as $addr) {
    $headers[] = 'Bcc: ' . $addr;
  }

  if (!empty($data['contact_value']) && !empty($data['connection_method']) && $data['connection_method'] === 'mail') {
    if (is_email($data['contact_value'])) {
      $headers[] = 'Reply-To: ' . $data['contact_value'];
    }
  } elseif (!empty($data['email']) && is_email($data['email'])) {
    $headers[] = 'Reply-To: ' . $data['email'];
  }

  return wp_mail($to, $subject, $body, $headers);
}

/**
 * ユーザー宛 自動返信メール送信
 */
function ycf_send_autoreply($form_key, $data) {
  $reply_to = ycf_resolve_user_email($data);
  if (!$reply_to) {
    return null;
  }

  $settings     = ycf_get_settings();
  $prefix       = $form_key . '_';
  $subject_tmpl = $settings[$prefix . 'autoreply_subject'] ?? '';
  $body_tmpl    = $settings[$prefix . 'autoreply_body']    ?? '';

  if ($subject_tmpl === '' && $body_tmpl === '') {
    return null;
  }

  $subject = ycf_render_template($subject_tmpl, $data, $form_key);
  $body    = ycf_render_template($body_tmpl, $data, $form_key);

  $headers = ['Content-Type: text/plain; charset=UTF-8'];

  return wp_mail($reply_to, $subject, $body, $headers);
}

/**
 * ユーザーへ返信できるメールアドレスを抽出。
 * - `email` フィールドがあればそれを優先
 * - `connection_method` が `mail` のときの `contact_value`
 */
function ycf_resolve_user_email($data) {
  if (!empty($data['email']) && is_email($data['email'])) {
    return $data['email'];
  }
  $method = $data['connection_method'] ?? '';
  if ($method === 'mail' && !empty($data['contact_value']) && is_email($data['contact_value'])) {
    return $data['contact_value'];
  }
  return null;
}

function ycf_parse_email_list($raw) {
  if (empty($raw)) {
    return [];
  }
  $list = preg_split('/[,\s]+/', $raw, -1, PREG_SPLIT_NO_EMPTY);
  $valid = [];
  foreach ($list as $email) {
    if (is_email($email)) {
      $valid[] = $email;
    }
  }
  return $valid;
}
