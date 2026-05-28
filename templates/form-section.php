<?php
/**
 * Yuino Contact Form: フォームセクション全体（入力 or 確認画面の出し分け）
 *
 * @var string $form  登録済みフォームキー
 */

if (!defined('ABSPATH')) {
  exit;
}

$args = $args ?? [];
$form = $args['form'] ?? '';
if (!ycf_form_exists($form)) {
  return;
}

$data   = [];
$errors = [];
$step   = 'input';

// admin-post.php からのリダイレクト時に状態を復元
$state = ycf_load_state($form);
if (is_array($state)) {
  $data   = $state['data']   ?? [];
  $errors = $state['errors'] ?? [];
  $step   = $state['step']   ?? 'input';
  ycf_clear_state($form);
}

// Turnstile スクリプトを必要時のみ読み込む
if (ycf_is_turnstile_configured()) {
  add_action('wp_footer', 'ycf_enqueue_turnstile_script', 5);
}

if ($step === 'confirm') {
  ycf_get_template('form-confirm.php', [
    'form' => $form,
    'data' => $data,
  ]);
} else {
  ycf_get_template('form-input.php', [
    'form'   => $form,
    'data'   => $data,
    'errors' => $errors,
  ]);
}
