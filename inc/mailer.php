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

  // 管理者が受信メールから直接返信できるよう、問い合わせ者のアドレスを Reply-To に付ける。
  // 解決は ycf_resolve_user_email() に一本化する（自動返信の宛先と必ず同じ結果になる）。
  $reply_to = ycf_resolve_user_email($data, $form_key);
  if ($reply_to) {
    $headers[] = 'Reply-To: ' . $reply_to;
  }

  return wp_mail($to, $subject, $body, $headers);
}

/**
 * ユーザー宛 自動返信メール送信
 *
 * 到達性向上のため、以下のヘッダを付与する：
 * - Auto-Submitted / Precedence / X-Auto-Response-Suppress:
 *   RFC 3834 および各メールサーバ慣習に基づき「自動応答メール」と明示。
 *   受信側スパムフィルタが汎用判定から除外しやすくなる。
 * - Reply-To:
 *   ユーザーが自動返信に直接返信した際、noreply@ で行き止まりにならず
 *   管理者通知宛先に届くようにする。「返信不能アドレスからの一方通行」
 *   と判定されるリスクを低減。
 */
function ycf_send_autoreply($form_key, $data) {
  $recipient = ycf_resolve_user_email($data, $form_key);
  if (!$recipient) {
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

  $headers = [
    'Content-Type: text/plain; charset=UTF-8',
    'Auto-Submitted: auto-replied',
    'Precedence: auto_reply',
    'X-Auto-Response-Suppress: All',
  ];

  $admin_to_list = ycf_parse_email_list($settings[$prefix . 'admin_to'] ?? '');
  if (!empty($admin_to_list)) {
    $headers[] = 'Reply-To: ' . $admin_to_list[0];
  }

  return wp_mail($recipient, $subject, $body, $headers);
}

/**
 * ユーザーへ返信できるメールアドレスを抽出。
 *
 * 解決順：
 * 1. `email` という名前のフィールド（最も一般的な命名）
 * 2. `connection_method` が `mail` のときの `contact_value`
 * 3. **フォーム定義で type=email のフィールドを順に走査し、値が入っている最初のもの**
 *
 * 3 が無いと、メール欄の名前が `email` 以外のフォーム
 * （タブ切替で `email_corp` / `email_personal` に分かれている等）で
 * 自動返信が一通も送られず、管理者通知に Reply-To も付かない。
 * フィールド名を `email` に固定していたのは設計上の穴だったため、
 * 定義済みフィールドの型から自動解決するフォールバックを既定に加える。
 *
 * @param array  $data     サニタイズ済みの送信値
 * @param string $form_key フォームキー（省略時は 3 のフォールバックが働かない）
 * @return string|null
 */
function ycf_resolve_user_email($data, $form_key = '') {
  $resolved = null;

  if (!empty($data['email']) && is_email($data['email'])) {
    $resolved = $data['email'];
  }

  if ($resolved === null) {
    $method = $data['connection_method'] ?? '';
    if ($method === 'mail' && !empty($data['contact_value']) && is_email($data['contact_value'])) {
      $resolved = $data['contact_value'];
    }
  }

  if ($resolved === null && $form_key !== '') {
    foreach (ycf_get_form_fields($form_key) as $key => $field) {
      if (($field['type'] ?? '') !== 'email') {
        continue;
      }
      if (!empty($data[$key]) && is_email($data[$key])) {
        $resolved = $data[$key];
        break;
      }
    }
  }

  /**
   * 返信先メールアドレスの上書き。
   * 複数のメール欄があり、どれを返信先にするかをフォーム側で決めたい場合に使う。
   */
  return apply_filters('ycf_user_email', $resolved, $data, $form_key);
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
