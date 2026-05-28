<?php
/**
 * Yuino Contact Form: 設定ヘルパー
 *
 * - サイト固有値（ブランド名・宛先・SMTP・Turnstileキー）はDB保存（管理画面）
 * - 高機密情報（SMTPパスワード）は wp-config.php の定数から
 *   ※Turnstile シークレットは v0.2 以降 DB 保存（管理画面入力）に変更。
 *     既存案件互換のため YCF_TURNSTILE_SECRET 定数定義があれば優先する。
 */

if (!defined('ABSPATH')) {
  exit;
}

const YCF_OPTION_KEY = 'ycf_settings';
const YCF_NONCE_ACTION_INPUT  = 'ycf_form_input';
const YCF_NONCE_ACTION_SUBMIT = 'ycf_form_submit';
const YCF_TRANSIENT_PREFIX    = 'ycf_form_state_';

/**
 * グローバル設定（フォーム横断）
 */
function ycf_get_default_global_settings() {
  return [
    'smtp_host'       => '',
    'smtp_port'       => '465',
    'smtp_encryption' => 'ssl',
    'smtp_username'   => '',
    'smtp_from_email' => '',
    'smtp_from_name'  => '',

    'turnstile_site_key'   => '',
    'turnstile_secret_key' => '',
  ];
}

/**
 * フォーム単位の管理画面設定（宛先・件名・本文）
 *
 * 本文（admin_body / autoreply_body）は `examples/register-forms.php` の
 * サンプルフォーム（contact）のフィールド構成に沿ったデモ初期値。
 * プラグイン有効化直後でも「設定 > お問い合わせ設定」に内容が入った状態に
 * なり、利用者は実フォームの完成後に書き換えるだけで運用に入れる。
 */
function ycf_get_default_form_mail_settings() {
  $admin_body = <<<'TEXT'
{site_name} 管理者様

あなたのサイト「{site_name}」のお問い合わせフォームへ下記の内容にてお問い合わせがありました。
内容をご確認のうえご対応をお願いいたします。

────────────────────────────────────
■ ご相談目的
{purpose_label}

■ お名前
{name}

■ フリガナ
{kana}

■ ご連絡方法のご希望
{connection_method_label}

■ ご連絡先
{contact_value}

■ ご住所
{address}

■ お問い合わせ内容
{message}
────────────────────────────────────

■ 送信日時：{sent_at}
■ 送信元IP：{remote_ip}

──────────────────────────
このメールは「{site_name}」の {form_label} フォームから
自動送信されています。
お客様へ返信される場合は、送信者メールアドレス宛に
直接ご返信ください。
──────────────────────────

※ 本文は Yuino Contact Form のデモ初期値です。実際のフォーム構成に
　合わせて「設定 > お問い合わせ設定」から編集してください。
TEXT;

  $autoreply_body = <<<'TEXT'
{name} 様

このたびは、「{site_name}」 へお問い合わせをいただき
誠にありがとうございます。

下記の内容にて受け付けいたしました。
担当者より追ってご連絡を差し上げますので、今しばらくお待ちください。

※ 本メールは自動送信です。本メールへのご返信にはお答えできかねます。

────────────────────────────────────
■ ご相談目的
{purpose_label}

■ お名前
{name}

■ フリガナ
{kana}

■ ご連絡方法のご希望
{connection_method_label}

■ ご連絡先
{contact_value}

■ ご住所
{address}

■ お問い合わせ内容
{message}
────────────────────────────────────

■ 送信日時：{sent_at}

──────────────────────────
{site_name}
──────────────────────────

※ 本文は Yuino Contact Form のデモ初期値です。実際のフォーム構成に
　合わせて「設定 > お問い合わせ設定」から編集してください。
TEXT;

  return [
    'admin_to'           => '',
    'admin_cc'           => '',
    'admin_bcc'          => '',
    'admin_subject'      => '【自動送信】あなたのサイト「{site_name}」のお問い合わせフォームへお問い合わせがありました',
    'admin_body'         => $admin_body,
    'autoreply_subject'  => '【自動送信】お問い合わせありがとうございます。当サイト「{site_name}」へのお問い合わせを承りました',
    'autoreply_body'     => $autoreply_body,
  ];
}

function ycf_get_settings() {
  $option = get_option(YCF_OPTION_KEY, []);
  if (!is_array($option)) {
    $option = [];
  }
  $defaults = ycf_get_default_global_settings();
  $merged   = array_merge($defaults, $option);

  // フォーム単位のメール設定をデフォルトで埋める
  $defaults_mail = ycf_get_default_form_mail_settings();
  foreach (array_keys(ycf_get_registered_forms()) as $form_key) {
    foreach ($defaults_mail as $sub_key => $default_value) {
      $field_key = $form_key . '_' . $sub_key;
      if (!isset($merged[$field_key])) {
        $merged[$field_key] = $default_value;
      }
    }
  }
  return $merged;
}

function ycf_get_setting($key) {
  $settings = ycf_get_settings();
  return isset($settings[$key]) ? $settings[$key] : '';
}

function ycf_get_smtp_password() {
  return defined('YCF_SMTP_PASSWORD') ? YCF_SMTP_PASSWORD : '';
}

function ycf_get_turnstile_secret() {
  // 既存案件互換: wp-config.php に定数定義があれば優先
  if (defined('YCF_TURNSTILE_SECRET') && YCF_TURNSTILE_SECRET !== '') {
    return YCF_TURNSTILE_SECRET;
  }
  // 通常は管理画面入力（DB 保存）
  return (string) ycf_get_setting('turnstile_secret_key');
}

function ycf_is_turnstile_configured() {
  return ycf_get_setting('turnstile_site_key') !== '' && ycf_get_turnstile_secret() !== '';
}

function ycf_get_remote_ip() {
  if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
    return sanitize_text_field(wp_unslash($_SERVER['HTTP_CF_CONNECTING_IP']));
  }
  if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
    $list = explode(',', sanitize_text_field(wp_unslash($_SERVER['HTTP_X_FORWARDED_FOR'])));
    return trim($list[0]);
  }
  return isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : '';
}
