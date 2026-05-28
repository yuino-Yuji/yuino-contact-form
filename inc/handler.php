<?php
/**
 * Yuino Contact Form: 送信処理（admin-post.php フック）
 */

if (!defined('ABSPATH')) {
  exit;
}

const YCF_ACTION_SUBMIT = 'ycf_form_submit';
const YCF_ACTION_INPUT  = 'ycf_form_input';

add_action('admin_post_nopriv_' . YCF_ACTION_SUBMIT, 'ycf_handle_submit');
add_action('admin_post_'        . YCF_ACTION_SUBMIT, 'ycf_handle_submit');
add_action('admin_post_nopriv_' . YCF_ACTION_INPUT,  'ycf_handle_input');
add_action('admin_post_'        . YCF_ACTION_INPUT,  'ycf_handle_input');

/**
 * 入力フォームの送信を受けて、確認画面 or 入力画面（エラー再表示）にリダイレクト。
 * 固定ページに直接 POST すると環境によって 404 になるため、必ずこのハンドラを経由する。
 */
function ycf_handle_input() {
  $form_key = isset($_POST['ycf_form']) ? sanitize_key($_POST['ycf_form']) : '';
  if (!ycf_form_exists($form_key)) {
    wp_die('不正なリクエストです。', '送信エラー', ['response' => 400]);
  }

  $back_url = ycf_get_form_url($form_key);

  $nonce = isset($_POST['_ycf_nonce']) ? sanitize_text_field(wp_unslash($_POST['_ycf_nonce'])) : '';
  if (!wp_verify_nonce($nonce, YCF_NONCE_ACTION_INPUT . '_' . $form_key)) {
    ycf_save_state($form_key, [], ['_global' => 'セキュリティチェックに失敗しました。再度ご入力ください。'], 'input');
    wp_safe_redirect($back_url);
    exit;
  }

  $data        = ycf_sanitize_input($form_key, $_POST);
  $posted_step = isset($_POST['ycf_step']) ? sanitize_key(wp_unslash($_POST['ycf_step'])) : 'confirm';

  if ($posted_step === 'back') {
    ycf_save_state($form_key, $data, [], 'input');
    wp_safe_redirect($back_url);
    exit;
  }

  $errors = ycf_validate($form_key, $data);
  $step   = empty($errors) ? 'confirm' : 'input';

  ycf_save_state($form_key, $data, $errors, $step);
  wp_safe_redirect($back_url);
  exit;
}

function ycf_handle_submit() {
  $form_key = isset($_POST['ycf_form']) ? sanitize_key($_POST['ycf_form']) : '';
  if (!ycf_form_exists($form_key)) {
    wp_die('不正なリクエストです。', '送信エラー', ['response' => 400]);
  }

  $back_url = ycf_get_form_url($form_key);

  $nonce = isset($_POST['_ycf_nonce']) ? sanitize_text_field(wp_unslash($_POST['_ycf_nonce'])) : '';
  if (!wp_verify_nonce($nonce, YCF_NONCE_ACTION_SUBMIT . '_' . $form_key)) {
    ycf_save_state($form_key, [], ['_global' => 'セキュリティチェックに失敗しました。再度ご入力ください。'], 'input');
    wp_safe_redirect($back_url);
    exit;
  }

  $data   = ycf_sanitize_input($form_key, $_POST);
  $errors = ycf_validate($form_key, $data);

  if (!empty($errors)) {
    ycf_save_state($form_key, $data, $errors, 'input');
    wp_safe_redirect($back_url);
    exit;
  }

  if (ycf_is_turnstile_configured()) {
    $token = isset($_POST['cf-turnstile-response']) ? sanitize_text_field(wp_unslash($_POST['cf-turnstile-response'])) : '';
    if (!ycf_verify_turnstile($token, ycf_get_remote_ip())) {
      ycf_save_state($form_key, $data, ['_global' => 'スパム対策チェックに失敗しました。お手数ですが再度ご入力ください。'], 'confirm');
      wp_safe_redirect($back_url);
      exit;
    }
  }

  do_action('ycf_before_send', $form_key, $data);

  $admin_sent = ycf_send_admin_notification($form_key, $data);

  if (!$admin_sent) {
    error_log('[YuinoContactForm] Admin notification failed for form: ' . $form_key);
    ycf_save_state($form_key, $data, ['_global' => 'メール送信に失敗しました。時間をおいて再度お試しください。'], 'confirm');
    wp_safe_redirect($back_url);
    exit;
  }

  ycf_send_autoreply($form_key, $data);

  do_action('ycf_after_send', $form_key, $data);

  ycf_clear_state($form_key);
  wp_safe_redirect(ycf_get_thanks_url($form_key));
  exit;
}

/**
 * バリデーションエラー時に入力値とエラーをトランジェント保存。
 * クッキーで識別子を引き回すことで、リダイレクト後に取り出せる。
 */
function ycf_save_state($form_key, $data, $errors, $step = 'input') {
  $token = wp_generate_password(32, false);
  set_transient(YCF_TRANSIENT_PREFIX . $token, [
    'form'   => $form_key,
    'data'   => $data,
    'errors' => $errors,
    'step'   => $step,
  ], 5 * MINUTE_IN_SECONDS);

  $cookie_name = 'ycf_state_' . $form_key;
  setcookie($cookie_name, $token, [
    'expires'  => time() + (5 * MINUTE_IN_SECONDS),
    'path'     => COOKIEPATH ? COOKIEPATH : '/',
    'domain'   => COOKIE_DOMAIN,
    'secure'   => is_ssl(),
    'httponly' => true,
    'samesite' => 'Lax',
  ]);
  $_COOKIE[$cookie_name] = $token;
}

function ycf_load_state($form_key) {
  $cookie_name = 'ycf_state_' . $form_key;
  if (empty($_COOKIE[$cookie_name])) {
    return null;
  }
  $token = sanitize_text_field(wp_unslash($_COOKIE[$cookie_name]));
  $state = get_transient(YCF_TRANSIENT_PREFIX . $token);
  if (!is_array($state) || ($state['form'] ?? '') !== $form_key) {
    return null;
  }
  return $state;
}

function ycf_clear_state($form_key) {
  $cookie_name = 'ycf_state_' . $form_key;
  if (!empty($_COOKIE[$cookie_name])) {
    $token = sanitize_text_field(wp_unslash($_COOKIE[$cookie_name]));
    delete_transient(YCF_TRANSIENT_PREFIX . $token);
    setcookie($cookie_name, '', [
      'expires'  => time() - 3600,
      'path'     => COOKIEPATH ? COOKIEPATH : '/',
      'domain'   => COOKIE_DOMAIN,
      'secure'   => is_ssl(),
      'httponly' => true,
      'samesite' => 'Lax',
    ]);
    unset($_COOKIE[$cookie_name]);
  }
}
