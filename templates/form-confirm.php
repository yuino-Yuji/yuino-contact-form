<?php
/**
 * Yuino Contact Form: 確認画面パーシャル
 *
 * @var string $form    登録済みフォームキー
 * @var array  $data    入力済みデータ
 * @var array  $errors  送信時のエラーメッセージ（Turnstile 失敗・メール送信失敗など）
 */

if (!defined('ABSPATH')) {
  exit;
}

$args   = $args ?? [];
$form   = $args['form']   ?? '';
$data   = $args['data']   ?? [];
$errors = $args['errors'] ?? [];

if (!ycf_form_exists($form)) {
  return;
}

$fields = ycf_get_form_fields($form);

$submit_action_url = admin_url('admin-post.php');
$back_action_url   = admin_url('admin-post.php');
?>

<div class="ContactForm ContactForm--confirm" data-ycf-form="<?php echo esc_attr($form); ?>" data-ycf-step="confirm-view">
  <?php if (!empty($errors['_global'])): ?>
    <div class="ContactForm__GlobalError" role="alert">
      <?php echo esc_html($errors['_global']); ?>
    </div>
  <?php endif; ?>

  <p class="ContactForm__ConfirmLead">下記の内容で送信します。よろしければ「送信する」を押してください。</p>

  <dl class="ContactForm__ConfirmList">
    <?php foreach ($fields as $key => $field):
      $type  = $field['type'] ?? 'text';
      $value = isset($data[$key]) ? $data[$key] : '';
    ?>
      <div class="ContactForm__ConfirmItem">
        <dt class="ContactForm__ConfirmLabel"><?php echo esc_html($field['label'] ?? $key); ?></dt>
        <dd class="ContactForm__ConfirmValue">
          <?php
          if ($type === 'checkbox') {
            echo $value === '1' ? '同意済み' : '未同意';
          } elseif ($type === 'radio' || $type === 'select') {
            $options = $field['options'] ?? [];
            echo esc_html(isset($options[$value]) ? $options[$value] : $value);
          } elseif ($type === 'textarea') {
            echo nl2br(esc_html($value));
          } else {
            echo esc_html($value);
          }
          ?>
        </dd>
      </div>
    <?php endforeach; ?>
  </dl>

  <form method="post" action="<?php echo esc_url($submit_action_url); ?>" class="ContactForm__SubmitForm" data-ycf-submit-form>
    <?php foreach ($data as $key => $value): ?>
      <input type="hidden" name="<?php echo esc_attr($key); ?>" value="<?php echo esc_attr($value); ?>" />
    <?php endforeach; ?>
    <input type="hidden" name="action" value="<?php echo esc_attr(YCF_ACTION_SUBMIT); ?>" />
    <input type="hidden" name="ycf_form" value="<?php echo esc_attr($form); ?>" />
    <?php
    // wp_nonce_field() は name と同じ値を id にも出力する。この画面には送信・修正の 2 フォームがあり、
    // 両方で使うと id="_ycf_nonce" が重複する（WCAG 2.0 / JIS X 8341-3:2016 の 4.1.1 構文解析）。
    // handler は $_POST['_ycf_nonce'] を読むだけなので、id なしの hidden で出す。
    ?>
    <input type="hidden" name="_ycf_nonce" value="<?php echo esc_attr(wp_create_nonce(YCF_NONCE_ACTION_SUBMIT . '_' . $form)); ?>" />
    <?php wp_referer_field(); ?>

    <?php if (ycf_is_turnstile_configured()): ?>
      <div class="ContactForm__TurnstileWrap">
        <div
          class="cf-turnstile"
          data-sitekey="<?php echo esc_attr(ycf_get_setting('turnstile_site_key')); ?>"
          data-theme="light"
          data-language="ja"
        ></div>
      </div>
    <?php endif; ?>

    <div class="ContactForm__ConfirmActions">
      <button type="submit" class="ContactForm__SubmitButton">送信する</button>
    </div>
  </form>

  <form method="post" action="<?php echo esc_url($back_action_url); ?>" class="ContactForm__BackForm">
    <?php foreach ($data as $key => $value): ?>
      <input type="hidden" name="<?php echo esc_attr($key); ?>" value="<?php echo esc_attr($value); ?>" />
    <?php endforeach; ?>
    <input type="hidden" name="action" value="<?php echo esc_attr(YCF_ACTION_INPUT); ?>" />
    <input type="hidden" name="ycf_form" value="<?php echo esc_attr($form); ?>" />
    <input type="hidden" name="ycf_step" value="back" />
    <input type="hidden" name="_ycf_nonce" value="<?php echo esc_attr(wp_create_nonce(YCF_NONCE_ACTION_INPUT . '_' . $form)); ?>" />
    <?php wp_referer_field(); ?>
    <div class="ContactForm__BackActions">
      <button type="submit" class="ContactForm__BackButton">修正する</button>
    </div>
  </form>
</div>
